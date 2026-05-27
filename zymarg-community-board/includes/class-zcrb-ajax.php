<?php
/**
 * AJAX handler for form submission.
 * (No load-more endpoint — pagination is server-rendered with crawlable URLs.)
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Ajax {

    /** @var ZCRB_Ajax|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_zcrb_submit_request', array( $this, 'submit_request' ) );
        add_action( 'wp_ajax_nopriv_zcrb_submit_request', array( $this, 'submit_request_nopriv' ) );
    }

    public function submit_request_nopriv(): void {
        wp_send_json_error( array( 'message' => ZCRB_I18n::t( 'must_login' ) ), 401 );
    }

    public function submit_request(): void {
        if ( ! check_ajax_referer( 'zcrb_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'zymarg-community-board' ) ), 403 );
        }

        $form   = ZCRB_Form::instance();
        $result = $form->process_submission( wp_unslash( $_POST ), $_FILES );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        wp_send_json_success( array(
            'message' => ZCRB_I18n::t( 'submit_success' ),
            'postId'  => $result,
        ) );
    }
}
