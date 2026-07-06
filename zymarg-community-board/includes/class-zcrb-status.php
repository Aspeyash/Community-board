<?php
/**
 * Custom post statuses for community requests.
 *
 * Registers 'zcrb_in_progress' and 'zcrb_fulfilled' statuses,
 * adds them to the admin dropdown, and provides helper methods.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Status {

    /** @var ZCRB_Status|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_statuses' ) );
        add_action( 'admin_footer-post.php', array( $this, 'append_status_to_dropdown' ) );
        add_action( 'admin_footer-post-new.php', array( $this, 'append_status_to_dropdown' ) );
    }

    /**
     * Register custom post statuses.
     */
    public function register_statuses(): void {
        register_post_status( 'zcrb_in_progress', array(
            'label'                     => __( 'In Progress', 'zymarg-community-board' ),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of posts */
            'label_count'               => _n_noop(
                'In Progress <span class="count">(%s)</span>',
                'In Progress <span class="count">(%s)</span>',
                'zymarg-community-board'
            ),
        ) );

        register_post_status( 'zcrb_fulfilled', array(
            'label'                     => __( 'Fulfilled', 'zymarg-community-board' ),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of posts */
            'label_count'               => _n_noop(
                'Fulfilled <span class="count">(%s)</span>',
                'Fulfilled <span class="count">(%s)</span>',
                'zymarg-community-board'
            ),
        ) );
    }

    /**
     * Inject custom statuses into the post status dropdown on the edit screen.
     * Standard WordPress pattern for custom post statuses.
     */
    public function append_status_to_dropdown(): void {
        global $post;

        if ( ! $post || ZCRB_POST_TYPE !== $post->post_type ) {
            return;
        }

        $statuses = array(
            'zcrb_in_progress' => __( 'In Progress', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'Fulfilled', 'zymarg-community-board' ),
        );

        echo '<script type="text/javascript">' . "\n";
        echo 'jQuery(document).ready(function($){' . "\n";

        foreach ( $statuses as $status => $label ) {
            $selected = ( $post->post_status === $status ) ? ' selected="selected"' : '';
            printf(
                '  $("select#post_status").append(\'<option value="%s"%s>%s</option>\');' . "\n",
                esc_js( $status ),
                $selected,
                esc_js( $label )
            );

            if ( $post->post_status === $status ) {
                printf(
                    '  $("#post-status-display").text("%s");' . "\n",
                    esc_js( $label )
                );
            }
        }

        echo '});' . "\n";
        echo '</script>' . "\n";
    }

    /**
     * Get human-readable label for a given status.
     */
    public static function get_status_label( string $status ): string {
        $labels = array(
            'publish'          => __( 'Approved', 'zymarg-community-board' ),
            'pending'          => __( 'Pending', 'zymarg-community-board' ),
            'zcrb_in_progress' => __( 'In Progress', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'Fulfilled', 'zymarg-community-board' ),
        );

        return $labels[ $status ] ?? $status;
    }

    /**
     * Get all statuses that are shown on the public board.
     *
     * @return string[]
     */
    public static function get_all_public_statuses(): array {
        return array( 'publish', 'zcrb_in_progress', 'zcrb_fulfilled' );
    }
}
