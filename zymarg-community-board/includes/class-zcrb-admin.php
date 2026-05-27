<?php
/**
 * Admin meta box, list table columns, and quick-approve actions.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Admin {

    /** @var ZCRB_Admin|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post_' . ZCRB_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );

        add_filter( 'manage_' . ZCRB_POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . ZCRB_POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-' . ZCRB_POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );

        add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
        add_action( 'admin_init', array( $this, 'handle_quick_action' ) );
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );
    }

    public function register_meta_boxes(): void {
        add_meta_box(
            'zcrb_submitter_details',
            __( 'Submitter Details (Private — never shown publicly)', 'zymarg-community-board' ),
            array( $this, 'render_meta_box' ),
            ZCRB_POST_TYPE,
            'side',
            'high'
        );
    }

    public function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'zcrb_save_meta', 'zcrb_meta_nonce' );

        $full_name = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
        $phone     = (string) get_post_meta( $post->ID, '_zcrb_phone', true );
        $email     = (string) get_post_meta( $post->ID, '_zcrb_email', true );
        $lang      = (string) get_post_meta( $post->ID, '_zcrb_lang', true );
        $ip        = (string) get_post_meta( $post->ID, '_zcrb_submitter_ip', true );
        ?>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Full Name', 'zymarg-community-board' ); ?></strong><br>
            <input type="text" class="widefat" name="zcrb_full_name" value="<?php echo esc_attr( $full_name ); ?>" />
        </p>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Phone', 'zymarg-community-board' ); ?></strong><br>
            <input type="text" class="widefat" name="zcrb_phone" value="<?php echo esc_attr( $phone ); ?>" />
            <small><?php esc_html_e( 'Visible only to admins.', 'zymarg-community-board' ); ?></small>
        </p>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Email', 'zymarg-community-board' ); ?></strong><br>
            <input type="email" class="widefat" name="zcrb_email" value="<?php echo esc_attr( $email ); ?>" />
            <small><?php esc_html_e( 'Visible only to admins.', 'zymarg-community-board' ); ?></small>
        </p>
        <p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Language', 'zymarg-community-board' ); ?></strong><br>
            <select name="zcrb_lang" class="widefat">
                <option value="en" <?php selected( $lang, 'en' ); ?>>English</option>
                <option value="bn" <?php selected( $lang, 'bn' ); ?>>বাংলা</option>
            </select>
        </p>
        <?php if ( $ip ) : ?>
            <p style="margin:0;"><small><?php echo esc_html( sprintf( /* translators: %s: ip */ __( 'Submitted from IP: %s', 'zymarg-community-board' ), $ip ) ); ?></small></p>
        <?php endif; ?>
        <hr>
        <p>
            <strong><?php esc_html_e( 'Approval', 'zymarg-community-board' ); ?>:</strong>
            <?php esc_html_e( 'Set the post to "Published" to make this request visible on the public board. Only Name, Message, Date, and Image are shown publicly.', 'zymarg-community-board' ); ?>
        </p>
        <?php
    }

    public function save_meta( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['zcrb_meta_nonce'] ) || ! wp_verify_nonce( $_POST['zcrb_meta_nonce'], 'zcrb_save_meta' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['zcrb_full_name'] ) ) {
            update_post_meta( $post_id, '_zcrb_full_name', sanitize_text_field( wp_unslash( $_POST['zcrb_full_name'] ) ) );
        }
        if ( isset( $_POST['zcrb_phone'] ) ) {
            update_post_meta( $post_id, '_zcrb_phone', sanitize_text_field( wp_unslash( $_POST['zcrb_phone'] ) ) );
        }
        if ( isset( $_POST['zcrb_email'] ) ) {
            update_post_meta( $post_id, '_zcrb_email', sanitize_email( wp_unslash( $_POST['zcrb_email'] ) ) );
        }
        if ( isset( $_POST['zcrb_lang'] ) ) {
            $lang = sanitize_key( wp_unslash( $_POST['zcrb_lang'] ) );
            update_post_meta( $post_id, '_zcrb_lang', in_array( $lang, array( 'en', 'bn' ), true ) ? $lang : 'en' );
        }
    }

    public function columns( array $columns ): array {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['zcrb_full_name'] = __( 'Full Name', 'zymarg-community-board' );
                $new['zcrb_phone']     = __( 'Phone', 'zymarg-community-board' );
                $new['zcrb_email']     = __( 'Email', 'zymarg-community-board' );
                $new['zcrb_lang']      = __( 'Lang', 'zymarg-community-board' );
            }
        }
        return $new;
    }

    public function sortable_columns( array $columns ): array {
        $columns['zcrb_full_name'] = 'zcrb_full_name';
        return $columns;
    }

    public function render_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'zcrb_full_name':
                echo esc_html( (string) get_post_meta( $post_id, '_zcrb_full_name', true ) );
                break;
            case 'zcrb_phone':
                echo esc_html( (string) get_post_meta( $post_id, '_zcrb_phone', true ) );
                break;
            case 'zcrb_email':
                $email = (string) get_post_meta( $post_id, '_zcrb_email', true );
                if ( $email ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                }
                break;
            case 'zcrb_lang':
                $lang = (string) get_post_meta( $post_id, '_zcrb_lang', true );
                echo esc_html( strtoupper( $lang ?: 'en' ) );
                break;
        }
    }

    public function row_actions( array $actions, WP_Post $post ): array {
        if ( ZCRB_POST_TYPE !== $post->post_type ) {
            return $actions;
        }
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return $actions;
        }

        if ( 'pending' === $post->post_status || 'draft' === $post->post_status ) {
            $url = wp_nonce_url(
                add_query_arg(
                    array(
                        'zcrb_action' => 'approve',
                        'post'        => $post->ID,
                    ),
                    admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                ),
                'zcrb_quick_action_' . $post->ID
            );
            $actions['zcrb_approve'] = '<a href="' . esc_url( $url ) . '" style="color:#6b3fa0;font-weight:600;">' . esc_html__( 'Approve', 'zymarg-community-board' ) . '</a>';
        }
        if ( 'publish' !== $post->post_status ) {
            $url = wp_nonce_url(
                add_query_arg(
                    array(
                        'zcrb_action' => 'reject',
                        'post'        => $post->ID,
                    ),
                    admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                ),
                'zcrb_quick_action_' . $post->ID
            );
            $actions['zcrb_reject'] = '<a href="' . esc_url( $url ) . '" style="color:#a00;">' . esc_html__( 'Reject', 'zymarg-community-board' ) . '</a>';
        }
        return $actions;
    }

    public function handle_quick_action(): void {
        if ( ! isset( $_GET['zcrb_action'], $_GET['post'] ) ) {
            return;
        }
        $post_id = absint( $_GET['post'] );
        $action  = sanitize_key( wp_unslash( $_GET['zcrb_action'] ) );
        if ( ! $post_id || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
            return;
        }
        check_admin_referer( 'zcrb_quick_action_' . $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'zymarg-community-board' ) );
        }

        if ( 'approve' === $action ) {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => 'publish',
            ) );
            $msg = 'approved';
        } else {
            wp_update_post( array(
                'ID'          => $post_id,
                'post_status' => 'trash',
            ) );
            $msg = 'rejected';
        }

        wp_safe_redirect( add_query_arg( array(
            'post_type'   => ZCRB_POST_TYPE,
            'zcrb_notice' => $msg,
        ), admin_url( 'edit.php' ) ) );
        exit;
    }

    public function admin_notice(): void {
        if ( ! isset( $_GET['zcrb_notice'] ) ) {
            return;
        }
        $notice = sanitize_key( wp_unslash( $_GET['zcrb_notice'] ) );
        if ( 'approved' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Request approved and published.', 'zymarg-community-board' ) . '</p></div>';
        } elseif ( 'rejected' === $notice ) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Request rejected and moved to Trash.', 'zymarg-community-board' ) . '</p></div>';
        }
    }
}
