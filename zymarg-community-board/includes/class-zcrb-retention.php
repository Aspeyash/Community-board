<?php
/**
 * Data retention.
 *
 * When an admin chooses 30 / 60 / 90 days on the Settings page, a daily
 * WP-Cron job permanently deletes every community request older than that
 * cutoff — including its post meta (Full Name, Phone, Email) and the
 * uploaded image attachment, if any.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Retention {

    const CRON_HOOK = 'zcrb_daily_cleanup';

    /** @var ZCRB_Retention|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( self::CRON_HOOK, array( $this, 'run_cleanup' ) );
        add_action( 'init', array( $this, 'ensure_scheduled' ), 20 );
        add_action( 'admin_init', array( $this, 'maybe_run_manually' ) );
    }

    /**
     * Allowed retention values, in days. 0 means "never delete".
     *
     * @return int[]
     */
    public static function allowed_days(): array {
        return array( 0, 30, 60, 90 );
    }

    /**
     * Resolve the configured retention period (in days). Returns 0 if
     * automatic deletion is disabled.
     */
    public static function configured_days(): int {
        $days = (int) ( function_exists( 'zcrb_get_setting' )
            ? zcrb_get_setting( 'data_retention_days', 0 )
            : 0 );
        return in_array( $days, self::allowed_days(), true ) ? $days : 0;
    }

    /**
     * Schedule the daily cleanup event. Called from the activation hook
     * AND every page load (via `init`) as a safety net in case the cron
     * was cleared.
     */
    public static function activate(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Fire the first run an hour from now to avoid blocking activation.
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Remove the cron event. Called from the deactivation hook.
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public function ensure_scheduled(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * The actual cleanup. Hard-deletes every request older than the cutoff.
     * Runs in batches of 200 to avoid blowing up memory on large sites.
     *
     * @return int Number of requests deleted.
     */
    public function run_cleanup(): int {
        $days = self::configured_days();
        if ( $days <= 0 ) {
            return 0;
        }

        $cutoff_gmt = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        $deleted = 0;
        $batch   = 200;

        do {
            $ids = get_posts( array(
                'post_type'      => ZCRB_POST_TYPE,
                'post_status'    => array( 'publish', 'pending', 'draft', 'trash', 'private', 'future' ),
                'posts_per_page' => $batch,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'date_query'     => array(
                    array(
                        'before'    => $cutoff_gmt,
                        'inclusive' => true,
                        'column'    => 'post_date_gmt',
                    ),
                ),
            ) );

            if ( empty( $ids ) ) {
                break;
            }

            foreach ( $ids as $post_id ) {
                if ( self::delete_request( (int) $post_id ) ) {
                    $deleted++;
                }
            }
        } while ( count( $ids ) === $batch );

        /**
         * Fires after a retention sweep completes.
         *
         * @param int $deleted Count of requests removed.
         * @param int $days    Configured retention days.
         */
        do_action( 'zcrb_retention_cleanup_complete', $deleted, $days );

        return $deleted;
    }

    /**
     * Permanently delete a request and every byte of personal data attached
     * to it: post, all post_meta, and the uploaded image (if any).
     */
    public static function delete_request( int $post_id ): bool {
        if ( ! $post_id || ZCRB_POST_TYPE !== get_post_type( $post_id ) ) {
            return false;
        }

        $thumb_id = (int) get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            wp_delete_attachment( $thumb_id, true );
        }

        return (bool) wp_delete_post( $post_id, true );
    }

    /**
     * Admin button: "Run cleanup now". Triggered from Community Board → Settings.
     */
    public function maybe_run_manually(): void {
        if ( ! is_admin() || empty( $_GET['zcrb_action'] ) || 'run_cleanup' !== $_GET['zcrb_action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'zcrb_run_cleanup' );

        $deleted = $this->run_cleanup();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'         => ZCRB_Settings::SETTINGS_SLUG,
                    'zcrb_msg'     => 'cleanup_done',
                    'zcrb_deleted' => (int) $deleted,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
