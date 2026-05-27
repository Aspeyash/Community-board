<?php
/**
 * Submission form handler. Logged-in users only. Creates a `pending`
 * zcrb_request post that requires admin approval before publishing.
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

        $full_name = isset( $input['zcrb_full_name'] ) ? sanitize_text_field( $input['zcrb_full_name'] ) : '';
        $phone     = isset( $input['zcrb_phone'] ) ? sanitize_text_field( $input['zcrb_phone'] ) : '';
        $email     = isset( $input['zcrb_email'] ) ? sanitize_email( $input['zcrb_email'] ) : '';
        $message   = isset( $input['zcrb_message'] ) ? sanitize_textarea_field( $input['zcrb_message'] ) : '';
        $lang      = isset( $input['zcrb_lang'] ) ? sanitize_key( $input['zcrb_lang'] ) : ZCRB_I18n::current_lang();

        // Validation.
        if ( '' === $full_name ) {
            return new WP_Error( 'zcrb_missing_name', __( 'Please enter your full name.', 'zymarg-community-board' ) );
        }
        if ( '' === $phone || ! preg_match( '/[0-9]{6,}/', $phone ) ) {
            return new WP_Error( 'zcrb_missing_phone', __( 'Please enter a valid phone number.', 'zymarg-community-board' ) );
        }
        if ( '' === $email || ! is_email( $email ) ) {
            return new WP_Error( 'zcrb_missing_email', __( 'Please enter a valid email address.', 'zymarg-community-board' ) );
        }
        if ( '' === $message ) {
            return new WP_Error( 'zcrb_missing_message', __( 'Please write your request message.', 'zymarg-community-board' ) );
        }
        // Enforce 200-character cap (count multibyte safely).
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $message, 'UTF-8' ) > ZCRB_MESSAGE_LIMIT ) {
            $message = mb_substr( $message, 0, ZCRB_MESSAGE_LIMIT, 'UTF-8' );
        } elseif ( strlen( $message ) > ZCRB_MESSAGE_LIMIT ) {
            $message = substr( $message, 0, ZCRB_MESSAGE_LIMIT );
        }

        // Throttle: max 5 submissions per user per hour.
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
            'posts_per_page' => 6,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        if ( count( $recent ) >= 5 ) {
            return new WP_Error( 'zcrb_rate_limited', __( 'Too many submissions. Please try again later.', 'zymarg-community-board' ) );
        }

        // Build a readable slug from the message.
        $title_seed = $message;
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $title_seed, 'UTF-8' ) > 60 ) {
            $title_seed = mb_substr( $title_seed, 0, 60, 'UTF-8' );
        }

        $post_id = wp_insert_post( array(
            'post_type'    => ZCRB_POST_TYPE,
            'post_status'  => 'pending', // Admin must approve before publish.
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

        // Optional image upload.
        if ( ! empty( $files['zcrb_image']['name'] ) ) {
            $upload = $this->handle_image_upload( $files['zcrb_image'], (int) $post_id );
            if ( is_wp_error( $upload ) ) {
                // Don't fail the whole submission — just record a warning meta and continue.
                update_post_meta( $post_id, '_zcrb_image_error', $upload->get_error_message() );
            } else {
                set_post_thumbnail( $post_id, $upload );
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

        // Notify admin.
        $this->notify_admin( (int) $post_id, $full_name );

        return (int) $post_id;
    }

    private function build_slug( string $message, string $lang ): string {
        $base = $message;
        if ( 'bn' === $lang ) {
            // Bengali: keep it short, sanitize_title will handle UTF-8.
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
        $allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
        $type    = isset( $file['type'] ) ? strtolower( (string) $file['type'] ) : '';
        if ( ! in_array( $type, $allowed, true ) ) {
            return new WP_Error( 'zcrb_bad_image_type', ZCRB_I18n::t( 'invalid_image' ) );
        }
        if ( ! empty( $file['size'] ) && (int) $file['size'] > 2 * 1024 * 1024 ) {
            return new WP_Error( 'zcrb_image_too_large', ZCRB_I18n::t( 'file_too_large' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Use media_handle_upload to attach to the new post.
        $_FILES['zcrb_image'] = $file;
        $attachment_id        = media_handle_upload( 'zcrb_image', $post_id, array(), array(
            'test_form' => false,
            'mimes'     => array(
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
        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return;
        }
        $edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        $subject   = sprintf(
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
