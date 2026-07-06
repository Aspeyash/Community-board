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
        add_action( 'admin_head', array( $this, 'sidebar_branding_css' ) );
        add_action( 'all_admin_notices', array( $this, 'inject_cpt_header' ) );
    }

    /**
     * Output inline CSS to brand the sidebar menu item purple.
     */
    public function sidebar_branding_css(): void {
        ?>
        <style>
            #adminmenu .toplevel_page_zcrb-hub .wp-menu-name {
                color: #9500A5;
                font-weight: 600;
            }
            #adminmenu .toplevel_page_zcrb-hub:hover .wp-menu-name,
            #adminmenu .toplevel_page_zcrb-hub.wp-has-current-submenu .wp-menu-name,
            #adminmenu .toplevel_page_zcrb-hub.current .wp-menu-name {
                color: #fff;
            }
        </style>
        <?php
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
     * Enqueue admin CSS on hub pages AND CPT screens.
     */
    public function enqueue_assets( string $hook ): void {
        $is_hub = ( false !== strpos( $hook, self::MENU_SLUG ) )
                || ( false !== strpos( $hook, 'zcrb' ) )
                || ( 'toplevel_page_' . self::MENU_SLUG === $hook );

        $is_cpt = $this->is_cpt_screen();

        if ( ! $is_hub && ! $is_cpt ) {
            return;
        }

        wp_enqueue_style(
            'zcrb-admin',
            ZCRB_PLUGIN_URL . 'assets/css/zcrb-admin.css',
            array(),
            ZCRB_VERSION
        );
    }

    /**
     * Inject the branded header above the CPT list table and edit screens.
     */
    public function inject_cpt_header(): void {
        if ( ! $this->is_cpt_screen() ) {
            return;
        }
        self::render_branded_header();
    }

    /**
     * Check if we are on a CPT list/edit screen for zcrb_request.
     */
    private function is_cpt_screen(): bool {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( null === $screen ) {
            return false;
        }
        // edit.php?post_type=zcrb_request (list table)
        if ( 'edit-zcrb_request' === $screen->id ) {
            return true;
        }
        // post.php editing a single zcrb_request
        if ( 'zcrb_request' === $screen->id && 'post' === $screen->base ) {
            return true;
        }
        return false;
    }

    /**
     * Render the unified branded gradient header with integrated Discovery Spark.
     *
     * Layout: [Spark] ZYMARG Community Board [v2.0.x badge]
     */
    public static function render_branded_header(): void {
        $version = defined( 'ZCRB_VERSION' ) ? ZCRB_VERSION : '0.0.0';
        ?>
        <div class="zcrb-hub-header">
            <span class="zymarg-spark zymarg-spark--xl" role="img" aria-label="ZYMARG Discovery Spark">
              <svg class="zymarg-spark__svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <g class="zymarg-spark-group--accent">
                  <path class="zymarg-spark-item--purple" d="M10.4 5.4c0 1.32-0.24 2.4-1.44 2.4 1.2 0 1.44 1.08 1.44 2.4 0-1.32 0.24-2.4 1.44-2.4-1.2 0-1.44-1.08-1.44-2.4z" fill="#6833ea"/>
                  <path class="zymarg-spark-item--gold" d="M10.4 6.0c0 0.96-0.18 1.8-1.08 1.8 0.9 0 1.08 0.84 1.08 1.8 0-0.9 0.18-1.8 1.08-1.8-0.9 0-1.08-0.84-1.08-1.8z" fill="#ffd166"/>
                </g>
                <g class="zymarg-spark-group--companion">
                  <path class="zymarg-spark-item--purple" d="M9.5 10.92c0 2.25-0.45 4.12-2.4 4.12 1.95 0 2.4 1.87 2.4 4.12 0-2.25 0.45-4.12 2.4-4.12-1.95 0-2.4-1.87-2.4-4.12z" fill="#6833ea"/>
                  <path class="zymarg-spark-item--gold" d="M9.5 11.5c0 1.9-0.38 3.54-2.0 3.54 1.62 0 2.0 1.64 2.0 3.54 0-1.9 0.38-3.54 2.0-3.54-1.62 0-2.0-1.64-2.0-3.54z" fill="#ffd166"/>
                </g>
                <g class="zymarg-spark-group--hero">
                  <path class="zymarg-spark-item--purple" d="M15.2 5.6c0 3.45-0.69 6.3-4.08 6.3 3.39 0 4.08 2.85 4.08 6.3 0-3.45 0.69-6.3 4.08-6.3-3.39 0-4.08-2.85-4.08-6.3z" fill="#6833ea"/>
                  <path class="zymarg-spark-item--gold" d="M15.2 6.5c0 2.9-0.58 5.4-3.39 5.4 2.81 0 3.39 2.5 3.39 5.4 0-2.9 0.58-5.4 3.39-5.4-2.81 0-3.39-2.5-3.39-5.4z" fill="#ffd166"/>
                </g>
              </svg>
            </span>
            <span class="zcrb-hub-header__title">ZYMARG Community Board</span>
            <span class="zcrb-hub-header__version">v<?php echo esc_html( $version ); ?></span>
        </div>
        <?php
    }

    /**
     * Render the hub dashboard page.
     */
    public function render_page(): void {
        // Gather stats.
        $total       = $this->count_posts( array( 'publish', 'pending', 'draft', 'zcrb_in_progress', 'zcrb_fulfilled' ) );
        $pending     = $this->count_posts( array( 'pending', 'draft' ) );
        $in_progress = $this->count_posts( array( 'zcrb_in_progress' ) );
        $fulfilled   = $this->count_posts( array( 'zcrb_fulfilled' ) );

        $settings_url = admin_url( 'admin.php?page=' . ZCRB_Settings::SETTINGS_SLUG );
        $requests_url = admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE );
        ?>
        <div class="wrap zcrb-hub-wrap">

            <!-- Branded Header with integrated Discovery Spark -->
            <?php self::render_branded_header(); ?>

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
