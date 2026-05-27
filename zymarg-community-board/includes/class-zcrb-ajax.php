<?php
/**
 * AJAX handlers for form submission and infinite-scroll pagination.
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

        add_action( 'wp_ajax_zcrb_load_more', array( $this, 'load_more' ) );
        add_action( 'wp_ajax_nopriv_zcrb_load_more', array( $this, 'load_more' ) );
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
     * Returns rendered HTML for the next page of cards, plus pagination info.
     */
    public function load_more(): void {
        if ( ! check_ajax_referer( 'zcrb_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'zymarg-community-board' ) ), 403 );
        }

        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 2;
        $per_page = ZCRB_PER_PAGE;

        $query = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ) );

        ob_start();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                ZCRB_Template::instance()->render_card( get_post() );
            }
            wp_reset_postdata();
        }
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html'        => $html,
            'page'        => $page,
            'maxPages'    => (int) $query->max_num_pages,
            'hasMore'     => $page < (int) $query->max_num_pages,
            'totalFound'  => (int) $query->found_posts,
        ) );
    }
}
