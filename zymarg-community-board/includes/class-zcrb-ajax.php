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
        add_action( 'wp_ajax_zcrb_duplicate_search', array( $this, 'duplicate_search' ) );
        add_action( 'wp_ajax_nopriv_zcrb_duplicate_search', array( $this, 'duplicate_search' ) );
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

    /**
     * AJAX endpoint for duplicate detection (search-before-submit).
     */
    public function duplicate_search(): void {
        if ( ! check_ajax_referer( 'zcrb_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'zymarg-community-board' ) ), 403 );
        }

        $query_text = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( strlen( $query_text ) < 10 ) {
            wp_send_json_error( array( 'message' => __( 'Query must be at least 10 characters.', 'zymarg-community-board' ) ), 400 );
        }

        $results = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => array( 'publish', 'zcrb_in_progress', 'zcrb_fulfilled' ),
            'posts_per_page' => 3,
            's'              => $query_text,
            'orderby'        => 'relevance',
            'order'          => 'DESC',
        ) );

        $matches = array();
        if ( $results->have_posts() ) {
            while ( $results->have_posts() ) {
                $results->the_post();
                $post = get_post();
                $matches[] = array(
                    'title'     => wp_trim_words( $post->post_content, 12, '...' ),
                    'ref'       => ZCRB_Template::ref_number( (int) $post->ID ),
                    'permalink' => get_permalink( $post ),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( array( 'matches' => $matches ) );
    }
}
