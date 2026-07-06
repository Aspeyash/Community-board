<?php
/**
 * Submission form handler. Logged-in users only. Creates a `pending`
 * zcrb_request post that requires admin approval before publishing.
 *
 * Reads runtime limits and required-field flags from ZCRB_Settings so an
 * admin can change them from the UI without editing code.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Form {

    /** @var ZCRB_Form|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Non-JS fallback: classic POST to admin-post.php.
        add_action( 'admin_post_zcrb_submit_request', array( $this, 'handle_classic_post' ) );
    }

    private function setting( string $key, $default = null ) {
        return function_exists( 'zcrb_get_setting' ) ? zcrb_get_setting( $key, $default ) : $default;
    }

    /**
     * Validate and persist a submission. Returns post ID on success or WP_Error.
     *
     * @param array $input  Already-unslashed (raw) input.
     * @param array $files  $_FILES superglobal.
     *
     * @return int|WP_Error
     */
    public function process_submission( array $input, array $files ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'zcrb_not_logged_in', ZCRB_I18n::t( 'must_login' ) );
        }

        $user_id = get_current_user_id();

        $message_limit  = (int) $this->setting( 'message_limit', ZCRB_MESSAGE_LIMIT );
        $phone_required = (bool) $this->setting( 'phone_required', 1 );
        $email_required = (bool) $this->setting( 'email_required', 1 );
        $image_required = (bool) $this->setting( 'image_required', 0 );
        $image_enabled  = (bool) $this->setting( 'image_enabled', 1 );
        $rate_limit     = (int) $this->setting( 'rate_limit_per_hour', 5 );

        $full_name = isset( $input['zcrb_full_name'] ) ? sanitize_text_field( $input['zcrb_full_name'] ) : '';
        $phone     = isset( $input['zcrb_phone'] ) ? sanitize_text_field( $input['zcrb_phone'] ) : '';
        $email     = isset( $input['zcrb_email'] ) ? sanitize_email( $input['zcrb_email'] ) : '';
        $message   = isset( $input['zcrb_message'] ) ? sanitize_textarea_field( $input['zcrb_message'] ) : '';
        $lang      = isset( $input['zcrb_lang'] ) ? sanitize_key( $input['zcrb_lang'] ) : ZCRB_I18n::current_lang();

        // Validation.
        if ( '' === $full_name ) {
            return new WP_Error( 'zcrb_missing_name', __( 'Please enter your full name.', 'zymarg-community-board' ) );
        }
        if ( $phone_required && ( '' === $phone || ! preg_match( '/[0-9]{6,}/', $phone ) ) ) {
            return new WP_Error( 'zcrb_missing_phone', __( 'Please enter a valid phone number.', 'zymarg-community-board' ) );
        }
        if ( $email_required && ( '' === $email || ! is_email( $email ) ) ) {
            return new WP_Error( 'zcrb_missing_email', __( 'Please enter a valid email address.', 'zymarg-community-board' ) );
        }
        // If email is provided but invalid, reject (even when not required).
        if ( '' !== $email && ! is_email( $email ) ) {
            return new WP_Error( 'zcrb_invalid_email', __( 'Please enter a valid email address.', 'zymarg-community-board' ) );
        }
        if ( '' === $message ) {
            return new WP_Error( 'zcrb_missing_message', __( 'Please write your request message.', 'zymarg-community-board' ) );
        }
        // Enforce character cap.
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $message, 'UTF-8' ) > $message_limit ) {
            $message = mb_substr( $message, 0, $message_limit, 'UTF-8' );
        } elseif ( strlen( $message ) > $message_limit ) {
            $message = substr( $message, 0, $message_limit );
        }

        // Image required check (only when uploads are also enabled).
        $image_max_count = (int) $this->setting( 'image_max_count', 1 );
        $has_images      = ! empty( $files['zcrb_images']['name'][0] );
        if ( $image_enabled && $image_required && ! $has_images ) {
            return new WP_Error( 'zcrb_missing_image', __( 'Please attach an image.', 'zymarg-community-board' ) );
        }
        // Also support the legacy single input name for backward compatibility.
        if ( ! $has_images && ! empty( $files['zcrb_image']['name'] ) ) {
            $has_images = true;
        }

        // Throttle: max N submissions per user per hour.
        $recent = get_posts( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => array( 'pending', 'publish', 'draft' ),
            'author'         => $user_id,
            'date_query'     => array(
                array(
                    'after'     => '1 hour ago',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => $rate_limit + 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        if ( count( $recent ) >= $rate_limit ) {
            return new WP_Error( 'zcrb_rate_limited', __( 'Too many submissions. Please try again later.', 'zymarg-community-board' ) );
        }

        // Build a readable slug from the message.
        $title_seed = $message;
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $title_seed, 'UTF-8' ) > 60 ) {
            $title_seed = mb_substr( $title_seed, 0, 60, 'UTF-8' );
        }

        $post_id = wp_insert_post( array(
            'post_type'    => ZCRB_POST_TYPE,
            'post_status'  => 'pending',
            'post_title'   => $title_seed,
            'post_content' => $message,
            'post_author'  => $user_id,
            'post_name'    => $this->build_slug( $message, $lang ),
            'meta_input'   => array(
                '_zcrb_full_name'    => $full_name,
                '_zcrb_phone'        => $phone,
                '_zcrb_email'        => $email,
                '_zcrb_lang'         => in_array( $lang, array( 'en', 'bn' ), true ) ? $lang : 'en',
                '_zcrb_submitter_ip' => $this->client_ip(),
            ),
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Optional / required image upload (supports multiple files via zcrb_images[]).
        if ( $image_enabled && $has_images ) {
            $uploaded_count = 0;
            $first_attachment_id = 0;

            // Handle new multi-file input (zcrb_images[]).
            if ( ! empty( $files['zcrb_images']['name'][0] ) ) {
                $file_count = count( $files['zcrb_images']['name'] );
                // Enforce the max image count.
                $file_count = min( $file_count, $image_max_count );

                for ( $i = 0; $i < $file_count; $i++ ) {
                    if ( empty( $files['zcrb_images']['name'][ $i ] ) ) {
                        continue;
                    }
                    $single_file = array(
                        'name'     => $files['zcrb_images']['name'][ $i ],
                        'type'     => $files['zcrb_images']['type'][ $i ],
                        'tmp_name' => $files['zcrb_images']['tmp_name'][ $i ],
                        'error'    => $files['zcrb_images']['error'][ $i ],
                        'size'     => $files['zcrb_images']['size'][ $i ],
                    );
                    $upload = $this->handle_image_upload( $single_file, (int) $post_id );
                    if ( is_wp_error( $upload ) ) {
                        update_post_meta( $post_id, '_zcrb_image_error', $upload->get_error_message() );
                        if ( $image_required && 0 === $uploaded_count ) {
                            wp_delete_post( (int) $post_id, true );
                            return $upload;
                        }
                    } else {
                        $uploaded_count++;
                        if ( 0 === $first_attachment_id ) {
                            $first_attachment_id = $upload;
                        }
                    }
                }
            } elseif ( ! empty( $files['zcrb_image']['name'] ) ) {
                // Legacy single-file input fallback.
                $upload = $this->handle_image_upload( $files['zcrb_image'], (int) $post_id );
                if ( is_wp_error( $upload ) ) {
                    update_post_meta( $post_id, '_zcrb_image_error', $upload->get_error_message() );
                    if ( $image_required ) {
                        wp_delete_post( (int) $post_id, true );
                        return $upload;
                    }
                } else {
                    $first_attachment_id = $upload;
                }
            }

            // Set the first uploaded image as the post thumbnail.
            if ( $first_attachment_id ) {
                set_post_thumbnail( $post_id, $first_attachment_id );
            }
        }

        /**
         * Fires after a community request has been submitted (still pending).
         *
         * @param int   $post_id
         * @param array $data
         */
        do_action( 'zcrb_request_submitted', $post_id, array(
            'full_name' => $full_name,
            'phone'     => $phone,
            'email'     => $email,
            'message'   => $message,
            'lang'      => $lang,
        ) );

        $this->notify_admin( (int) $post_id, $full_name );

        return (int) $post_id;
    }

    private function build_slug( string $message, string $lang ): string {
        $base = $message;
        if ( 'bn' === $lang ) {
            $base = function_exists( 'mb_substr' ) ? mb_substr( $base, 0, 60, 'UTF-8' ) : substr( $base, 0, 60 );
        } else {
            $base = function_exists( 'mb_substr' ) ? mb_substr( $base, 0, 80, 'UTF-8' ) : substr( $base, 0, 80 );
        }
        $slug = sanitize_title( $base );
        if ( '' === $slug ) {
            $slug = 'request-' . wp_generate_password( 6, false, false );
        }
        return $slug;
    }

    private function client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return preg_replace( '/[^0-9a-f\.\:]/i', '', (string) $ip );
    }

    /**
     * Handle a single image upload. Returns attachment ID or WP_Error.
     *
     * @param array $file
     * @param int   $post_id
     */
    private function handle_image_upload( array $file, int $post_id ) {
        $allowed_csv = (string) $this->setting( 'image_allowed_types', 'image/jpeg,image/png,image/webp' );
        $allowed     = array_filter( array_map( 'trim', explode( ',', $allowed_csv ) ) );
        $type        = isset( $file['type'] ) ? strtolower( (string) $file['type'] ) : '';
        if ( ! in_array( $type, $allowed, true ) ) {
            return new WP_Error( 'zcrb_bad_image_type', ZCRB_I18n::t( 'invalid_image' ) );
        }

        $max_mb = (int) $this->setting( 'image_max_mb', 2 );
        if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_mb * 1024 * 1024 ) {
            return new WP_Error( 'zcrb_image_too_large', ZCRB_I18n::t( 'file_too_large' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Build a mimes whitelist from the allowed-types setting.
        $mimes = array();
        foreach ( $allowed as $m ) {
            switch ( $m ) {
                case 'image/jpeg':
                    $mimes['jpg|jpeg'] = 'image/jpeg';
                    break;
                case 'image/png':
                    $mimes['png'] = 'image/png';
                    break;
                case 'image/webp':
                    $mimes['webp'] = 'image/webp';
                    break;
                case 'image/gif':
                    $mimes['gif'] = 'image/gif';
                    break;
            }
        }

        $_FILES['zcrb_image'] = $file;
        $attachment_id        = media_handle_upload( 'zcrb_image', $post_id, array(), array(
            'test_form' => false,
            'mimes'     => $mimes ?: array(
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
            ),
        ) );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }
        return (int) $attachment_id;
    }

    private function notify_admin( int $post_id, string $full_name ): void {
        $configured_email = (string) $this->setting( 'notify_email', '' );
        $admin_email      = $configured_email !== '' ? $configured_email : get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return;
        }

        $edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

        $configured_subject = (string) $this->setting( 'notify_subject', '' );
        $subject            = $configured_subject !== '' ? $configured_subject : sprintf(
            /* translators: %s: site name */
            __( '[%s] New community request awaiting approval', 'zymarg-community-board' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
        );

        $body = sprintf(
            "%s\n\n%s\n%s",
            sprintf( __( 'A new community request was submitted by %s and is awaiting moderation.', 'zymarg-community-board' ), $full_name ),
            __( 'Review here:', 'zymarg-community-board' ),
            $edit_link
        );
        wp_mail( $admin_email, $subject, $body );
    }

    /**
     * Non-JS classic POST fallback handler.
     */
    public function handle_classic_post(): void {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }

        check_admin_referer( 'zcrb_submit', 'zcrb_nonce_field' );

        $result = $this->process_submission( wp_unslash( $_POST ), $_FILES );
        $back   = wp_get_referer() ?: get_post_type_archive_link( ZCRB_POST_TYPE );

        if ( is_wp_error( $result ) ) {
            $back = add_query_arg( array(
                'zcrb_status' => 'error',
                'zcrb_msg'    => rawurlencode( $result->get_error_message() ),
            ), $back );
        } else {
            $back = add_query_arg( array( 'zcrb_status' => 'pending' ), $back );
        }

        wp_safe_redirect( $back );
        exit;
    }
}
