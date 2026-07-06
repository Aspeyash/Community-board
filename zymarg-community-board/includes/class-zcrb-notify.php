<?php
/**
 * Notification system for community requests.
 *
 * Sends email to the submitter when:
 * - Their request is approved (status transitions to 'publish')
 * - A vendor responds to their request
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Notify {

    /** @var ZCRB_Notify|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'transition_post_status', array( $this, 'on_status_transition' ), 10, 3 );
        add_action( 'zcrb_vendor_responded', array( $this, 'on_vendor_response' ), 10, 3 );
    }

    /**
     * Handle post status transitions. Notify submitter when request is approved.
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public function on_status_transition( string $new_status, string $old_status, WP_Post $post ): void {
        if ( ZCRB_POST_TYPE !== $post->post_type ) {
            return;
        }

        // Notify on approval: any status -> publish.
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        if ( ! (int) zcrb_get_setting( 'notify_submitter_on_approve', 1 ) ) {
            return;
        }

        $email = (string) get_post_meta( $post->ID, '_zcrb_email', true );
        if ( '' === $email || ! is_email( $email ) ) {
            return;
        }

        $subject = ZCRB_I18n::t( 'notification_approved_subject' );
        $body    = ZCRB_I18n::t( 'notification_approved_body' );

        // Replace placeholders in body.
        $permalink = get_permalink( $post->ID );
        $name      = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
        $body      = str_replace( '{name}', $name, $body );
        $body      = str_replace( '{link}', (string) $permalink, $body );

        wp_mail( $email, $subject, $body );
    }

    /**
     * Handle vendor response. Notify submitter that a vendor has responded.
     *
     * @param int    $post_id
     * @param int    $vendor_id
     * @param string $message
     */
    public function on_vendor_response( int $post_id, int $vendor_id, string $message ): void {
        if ( ! (int) zcrb_get_setting( 'notify_submitter_on_response', 1 ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || ZCRB_POST_TYPE !== $post->post_type ) {
            return;
        }

        $email = (string) get_post_meta( $post_id, '_zcrb_email', true );
        if ( '' === $email || ! is_email( $email ) ) {
            return;
        }

        $subject = ZCRB_I18n::t( 'notification_response_subject' );
        $body    = ZCRB_I18n::t( 'notification_response_body' );

        $permalink   = get_permalink( $post_id );
        $name        = (string) get_post_meta( $post_id, '_zcrb_full_name', true );
        $vendor_user = get_userdata( $vendor_id );
        $vendor_name = $vendor_user ? $vendor_user->display_name : __( 'A vendor', 'zymarg-community-board' );

        $body = str_replace( '{name}', $name, $body );
        $body = str_replace( '{vendor_name}', $vendor_name, $body );
        $body = str_replace( '{link}', (string) $permalink, $body );

        wp_mail( $email, $subject, $body );
    }
}
