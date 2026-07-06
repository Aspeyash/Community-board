<?php
/**
 * Upvote system for community requests.
 *
 * Allows logged-in users to upvote a request once (toggle).
 * Stores count in '_zcrb_upvote_count' and user IDs in '_zcrb_upvoted_users'.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Upvote {

    /** @var ZCRB_Upvote|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_zcrb_upvote', array( $this, 'ajax_upvote' ) );
    }

    /**
     * AJAX handler for upvote toggle.
     */
    public function ajax_upvote(): void {
        check_ajax_referer( 'zcrb_upvote_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in.', 'zymarg-community-board' ) ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'zymarg-community-board' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ZCRB_POST_TYPE !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'zymarg-community-board' ) ) );
        }

        $user_id = get_current_user_id();
        $users   = self::get_upvoted_users( $post_id );
        $active  = false;

        if ( in_array( $user_id, $users, true ) ) {
            // Remove upvote.
            $users = array_values( array_diff( $users, array( $user_id ) ) );
            $active = false;
        } else {
            // Add upvote.
            $users[] = $user_id;
            $active  = true;
        }

        $count = count( $users );
        update_post_meta( $post_id, '_zcrb_upvoted_users', $users );
        update_post_meta( $post_id, '_zcrb_upvote_count', $count );

        wp_send_json_success( array(
            'count'  => $count,
            'active' => $active,
        ) );
    }

    /**
     * Get the upvote count for a post.
     */
    public static function get_count( int $post_id ): int {
        return (int) get_post_meta( $post_id, '_zcrb_upvote_count', true );
    }

    /**
     * Check if a user has upvoted a post.
     */
    public static function has_user_upvoted( int $post_id, int $user_id ): bool {
        $users = self::get_upvoted_users( $post_id );
        return in_array( $user_id, $users, true );
    }

    /**
     * Get the list of user IDs who have upvoted.
     *
     * @return int[]
     */
    private static function get_upvoted_users( int $post_id ): array {
        $users = get_post_meta( $post_id, '_zcrb_upvoted_users', true );
        if ( ! is_array( $users ) ) {
            return array();
        }
        return array_map( 'intval', $users );
    }

    /**
     * Render the upvote button HTML for a post.
     */
    public static function render_button( int $post_id ): void {
        $count      = self::get_count( $post_id );
        $is_active  = is_user_logged_in() && self::has_user_upvoted( $post_id, get_current_user_id() );
        $active_cls = $is_active ? ' is-active' : '';
        ?>
        <button type="button"
                class="zcrb-upvote<?php echo esc_attr( $active_cls ); ?>"
                data-zcrb-upvote
                data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
                aria-label="<?php echo esc_attr( $is_active ? ZCRB_I18n::t( 'upvoted' ) : ZCRB_I18n::t( 'upvote' ) ); ?>">
            <svg class="zcrb-upvote__icon" viewBox="0 0 24 24" fill="<?php echo $is_active ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <span class="zcrb-upvote__count" data-zcrb-upvote-count><?php echo esc_html( (string) $count ); ?></span>
        </button>
        <?php
    }
}
