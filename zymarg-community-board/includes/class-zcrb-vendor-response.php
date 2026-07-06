<?php
/**
 * Vendor Response system for community requests.
 *
 * Stores vendor responses in post meta, provides admin meta box,
 * front-end rendering, and AJAX endpoint for submission.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Vendor_Response {

    /** @var ZCRB_Vendor_Response|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_' . ZCRB_POST_TYPE, array( $this, 'save_meta_box' ) );
        add_action( 'wp_ajax_zcrb_vendor_respond', array( $this, 'ajax_vendor_respond' ) );
    }

    /**
     * Add meta box on the request edit screen.
     */
    public function add_meta_box(): void {
        add_meta_box(
            'zcrb_vendor_responses',
            __( 'Vendor Responses', 'zymarg-community-board' ),
            array( $this, 'render_meta_box' ),
            ZCRB_POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Check if the current user can respond as a vendor.
     *
     * Allows users with edit_others_posts capability OR Dokan/MultiVendorX vendor roles.
     */
    private static function can_respond(): bool {
        if ( current_user_can( 'edit_others_posts' ) ) {
            return true;
        }
        $user = wp_get_current_user();
        return ! empty( array_intersect( array( 'seller', 'dc_vendor', 'vendor' ), (array) $user->roles ) );
    }

    /**
     * Render the admin meta box showing existing responses and a textarea to add a new one.
     */
    public function render_meta_box( WP_Post $post ): void {
        $responses = self::get_responses( $post->ID );

        if ( ! empty( $responses ) ) {
            echo '<div style="max-height:200px;overflow-y:auto;margin-bottom:10px;">';
            foreach ( $responses as $response ) {
                $time = isset( $response['timestamp'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $response['timestamp'] ) : '';
                echo '<div style="border-bottom:1px solid #ddd;padding:6px 0;margin-bottom:4px;">';
                echo '<strong>' . esc_html( $response['vendor_name'] ?? '' ) . '</strong>';
                echo ' <small style="color:#666;">(' . esc_html( $time ) . ')</small><br>';
                echo '<span>' . esc_html( $response['message'] ?? '' ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p style="color:#666;"><em>' . esc_html__( 'No responses yet.', 'zymarg-community-board' ) . '</em></p>';
        }

        if ( self::can_respond() ) {
            wp_nonce_field( 'zcrb_vendor_response_meta', 'zcrb_vendor_response_nonce' );
            echo '<textarea name="zcrb_new_vendor_response" rows="3" style="width:100%;" placeholder="' . esc_attr__( 'Add a response...', 'zymarg-community-board' ) . '"></textarea>';
            echo '<p class="description">' . esc_html__( 'Type a response and update the post to save.', 'zymarg-community-board' ) . '</p>';
        }
    }

    /**
     * Save meta box data when the post is saved.
     */
    public function save_meta_box( int $post_id ): void {
        if ( ! isset( $_POST['zcrb_vendor_response_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['zcrb_vendor_response_nonce'], 'zcrb_vendor_response_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! self::can_respond() ) {
            return;
        }

        $message = isset( $_POST['zcrb_new_vendor_response'] ) ? sanitize_textarea_field( wp_unslash( $_POST['zcrb_new_vendor_response'] ) ) : '';
        if ( '' === $message ) {
            return;
        }

        $user = wp_get_current_user();
        self::add_response( $post_id, $user->ID, $user->display_name, $message );
    }

    /**
     * AJAX endpoint for vendor response submission from the frontend.
     */
    public function ajax_vendor_respond(): void {
        check_ajax_referer( 'zcrb_vendor_respond_nonce', 'nonce' );

        if ( ! self::can_respond() ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'zymarg-community-board' ) ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( ! $post_id || '' === $message ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'zymarg-community-board' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ZCRB_POST_TYPE !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'zymarg-community-board' ) ) );
        }

        $user = wp_get_current_user();
        self::add_response( $post_id, $user->ID, $user->display_name, $message );

        wp_send_json_success( array(
            'message'     => ZCRB_I18n::t( 'response_submitted' ),
            'vendor_name' => $user->display_name,
            'timestamp'   => time(),
        ) );
    }

    /**
     * Add a vendor response to a post.
     */
    public static function add_response( int $post_id, int $vendor_id, string $vendor_name, string $message ): void {
        $responses   = self::get_responses( $post_id );
        $responses[] = array(
            'vendor_id'   => $vendor_id,
            'vendor_name' => $vendor_name,
            'message'     => $message,
            'timestamp'   => time(),
        );

        update_post_meta( $post_id, '_zcrb_vendor_responses', $responses );

        /**
         * Fires after a vendor response is saved.
         *
         * @param int    $post_id     The request post ID.
         * @param int    $vendor_id   The vendor user ID.
         * @param string $message     The response message.
         */
        do_action( 'zcrb_vendor_responded', $post_id, $vendor_id, $message );
    }

    /**
     * Get all vendor responses for a post.
     *
     * @return array[]
     */
    public static function get_responses( int $post_id ): array {
        $responses = get_post_meta( $post_id, '_zcrb_vendor_responses', true );
        if ( ! is_array( $responses ) ) {
            return array();
        }
        return $responses;
    }

    /**
     * Render the responses section on the public single view.
     */
    public function render_responses( int $post_id ): void {
        $responses = self::get_responses( $post_id );
        ?>
        <section class="zcrb-responses">
            <h3 class="zcrb-responses__title"><?php echo esc_html( ZCRB_I18n::t( 'vendor_responses' ) ); ?></h3>
            <?php if ( empty( $responses ) ) : ?>
                <p class="zcrb-responses__empty"><?php echo esc_html( ZCRB_I18n::t( 'no_responses_yet' ) ); ?></p>
            <?php else : ?>
                <div class="zcrb-responses__list">
                    <?php foreach ( $responses as $response ) :
                        $time = isset( $response['timestamp'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $response['timestamp'] ) : '';
                        ?>
                        <div class="zcrb-response-card">
                            <div class="zcrb-response-card__header">
                                <strong class="zcrb-response-card__vendor"><?php echo esc_html( $response['vendor_name'] ?? '' ); ?></strong>
                                <time class="zcrb-response-card__time"><?php echo esc_html( $time ); ?></time>
                            </div>
                            <p class="zcrb-response-card__message"><?php echo esc_html( $response['message'] ?? '' ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render the vendor response form for the frontend single view.
     */
    public function render_response_form( int $post_id ): void {
        if ( ! self::can_respond() ) {
            return;
        }
        ?>
        <section class="zcrb-response-form" data-zcrb-response-form data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
            <h4 class="zcrb-response-form__title"><?php echo esc_html( ZCRB_I18n::t( 'vendor_response' ) ); ?></h4>
            <textarea class="zcrb-response-form__textarea" name="zcrb_vendor_message" rows="3" placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'respond_placeholder' ) ); ?>" data-zcrb-vendor-message></textarea>
            <button type="button" class="zcrb-btn zcrb-btn--primary zcrb-response-form__submit" data-zcrb-vendor-submit>
                <?php echo esc_html( ZCRB_I18n::t( 'submit_response' ) ); ?>
            </button>
            <p class="zcrb-response-form__feedback" data-zcrb-vendor-feedback role="status" aria-live="polite"></p>
        </section>
        <?php
    }
}
