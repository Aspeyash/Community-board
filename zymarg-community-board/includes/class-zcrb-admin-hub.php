<?php
/**
 * Admin Hub — branded dashboard landing page.
 *
 * Registers the top-level admin menu "Community Board" and its submenus,
 * renders the branded hub with gradient header, Discovery Spark SVG,
 * tab navigation, and quick stats.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Admin_Hub {

    const MENU_SLUG = 'zcrb-hub';

    /** @var ZCRB_Admin_Hub|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the top-level menu and submenu pages.
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'ZYMARG Community Board', 'zymarg-community-board' ),
            __( 'Community Board', 'zymarg-community-board' ),
            'edit_posts',
            self::MENU_SLUG,
            array( $this, 'render_page' ),
            'dashicons-megaphone',
            26
        );

        // Relabel the first submenu item to "Dashboard" (WP auto-creates one matching the parent).
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'zymarg-community-board' ),
            __( 'Dashboard', 'zymarg-community-board' ),
            'edit_posts',
            self::MENU_SLUG,
            array( $this, 'render_page' )
        );

        // "All Requests" links to the CPT list table.
        add_submenu_page(
            self::MENU_SLUG,
            __( 'All Requests', 'zymarg-community-board' ),
            __( 'All Requests', 'zymarg-community-board' ),
            'edit_posts',
            'edit.php?post_type=' . ZCRB_POST_TYPE
        );
    }

    /**
     * Enqueue admin CSS only on hub pages.
     */
    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, self::MENU_SLUG ) && false === strpos( $hook, 'zcrb' ) ) {
            // Also check the toplevel_page_ prefix.
            if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
                return;
            }
        }

        wp_enqueue_style(
            'zcrb-admin',
            ZCRB_PLUGIN_URL . 'assets/css/zcrb-admin.css',
            array(),
            ZCRB_VERSION
        );
    }

    /**
     * Render the hub dashboard page.
     */
    public function render_page(): void {
        $version = defined( 'ZCRB_VERSION' ) ? ZCRB_VERSION : '0.0.0';

        // Gather stats.
        $total       = $this->count_posts( array( 'publish', 'pending', 'draft', 'zcrb_in_progress', 'zcrb_fulfilled' ) );
        $pending     = $this->count_posts( array( 'pending', 'draft' ) );
        $in_progress = $this->count_posts( array( 'zcrb_in_progress' ) );
        $fulfilled   = $this->count_posts( array( 'zcrb_fulfilled' ) );

        $settings_url = admin_url( 'admin.php?page=' . ZCRB_Settings::SETTINGS_SLUG );
        $requests_url = admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE );
        ?>
        <div class="wrap zcrb-hub-wrap">

            <!-- Gradient Header -->
            <div class="zcrb-hub-header">
                <span class="zcrb-hub-header__title">ZYMARG Community Board</span>
                <span class="zcrb-hub-header__version">v<?php echo esc_html( $version ); ?></span>
            </div>

            <!-- Discovery Spark -->
            <div class="zcrb-hub-spark">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Discovery Spark">
                    <!-- Purple paths (animated) -->
                    <g class="zcrb-spark-purple">
                        <path d="M40 4 L44 36 L76 40 L44 44 L40 76 L36 44 L4 40 L36 36 Z" fill="#6833ea" />
                        <path d="M60 10 L62 28 L72 30 L62 32 L60 50 L58 32 L48 30 L58 28 Z" fill="#6833ea" opacity="0.8" />
                        <path d="M20 50 L22 58 L30 60 L22 62 L20 70 L18 62 L10 60 L18 58 Z" fill="#6833ea" opacity="0.7" />
                    </g>
                    <!-- Gold paths (static) -->
                    <g class="zcrb-spark-gold">
                        <circle cx="64" cy="58" r="3" fill="#FFD166" />
                        <circle cx="18" cy="22" r="2.5" fill="#FFD166" />
                    </g>
                </svg>
            </div>

            <!-- Tab Navigation -->
            <nav class="zcrb-hub-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" class="zcrb-hub-tab is-active"><?php esc_html_e( 'Dashboard', 'zymarg-community-board' ); ?></a>
                <a href="<?php echo esc_url( $requests_url ); ?>" class="zcrb-hub-tab"><?php esc_html_e( 'All Requests', 'zymarg-community-board' ); ?></a>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="zcrb-hub-tab"><?php esc_html_e( 'Settings', 'zymarg-community-board' ); ?></a>
            </nav>

            <!-- Stats Cards -->
            <div class="zcrb-hub-stats">
                <div class="zcrb-hub-stat">
                    <div class="zcrb-hub-stat__count"><?php echo esc_html( (string) $total ); ?></div>
                    <div class="zcrb-hub-stat__label"><?php esc_html_e( 'Total Requests', 'zymarg-community-board' ); ?></div>
                </div>
                <div class="zcrb-hub-stat">
                    <div class="zcrb-hub-stat__count"><?php echo esc_html( (string) $pending ); ?></div>
                    <div class="zcrb-hub-stat__label"><?php esc_html_e( 'Pending', 'zymarg-community-board' ); ?></div>
                </div>
                <div class="zcrb-hub-stat">
                    <div class="zcrb-hub-stat__count"><?php echo esc_html( (string) $in_progress ); ?></div>
                    <div class="zcrb-hub-stat__label"><?php esc_html_e( 'In Progress', 'zymarg-community-board' ); ?></div>
                </div>
                <div class="zcrb-hub-stat">
                    <div class="zcrb-hub-stat__count"><?php echo esc_html( (string) $fulfilled ); ?></div>
                    <div class="zcrb-hub-stat__label"><?php esc_html_e( 'Fulfilled', 'zymarg-community-board' ); ?></div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Count posts by status array.
     */
    private function count_posts( array $statuses ): int {
        $counts = wp_count_posts( ZCRB_POST_TYPE );
        $total  = 0;
        foreach ( $statuses as $status ) {
            if ( isset( $counts->$status ) ) {
                $total += (int) $counts->$status;
            }
        }
        return $total;
    }
}
