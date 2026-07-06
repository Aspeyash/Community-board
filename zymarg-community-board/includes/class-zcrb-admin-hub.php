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
        // Priority 999 runs AFTER WordPress' _add_post_type_submenus() (priority 9)
        // and any other module's admin_menu callbacks, so we can safely reshuffle
        // the fully-populated $submenu['zcrb-hub'] array into the desired order.
        add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 999 );
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
    }

    /**
     * Reorder the "Community Board" submenu items so they always render as:
     *   1. Dashboard        (this hub — parent slug points at itself)
     *   2. All Requests     (auto-added by WP for the zcrb_request CPT)
     *   3. Settings         (ZCRB_Settings::SETTINGS_SLUG)
     *
     * Without this, WordPress' _add_post_type_submenus() runs on admin_menu
     * priority 9 (BEFORE our register_menu() at priority 10), which pushes
     * "All Requests" to the top of the list.
     */
    public function reorder_submenu(): void {
        global $submenu;
        if ( ! isset( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
            return;
        }

        // Desired priority by slug fragment. Lower = earlier.
        $priority_map = array(
            self::MENU_SLUG                        => 10, // Dashboard (parent slug = self)
            'edit.php?post_type=' . ZCRB_POST_TYPE => 20, // All Requests (CPT auto-added)
            ZCRB_Settings::SETTINGS_SLUG           => 30, // Settings ("zcrb-settings")
        );

        $items = $submenu[ self::MENU_SLUG ];

        usort( $items, static function ( $a, $b ) use ( $priority_map ) {
            $a_slug = isset( $a[2] ) ? (string) $a[2] : '';
            $b_slug = isset( $b[2] ) ? (string) $b[2] : '';
            $a_prio = 999;
            $b_prio = 999;
            foreach ( $priority_map as $needle => $prio ) {
                if ( $a_slug === $needle || false !== strpos( $a_slug, $needle ) ) {
                    $a_prio = $prio;
                }
                if ( $b_slug === $needle || false !== strpos( $b_slug, $needle ) ) {
                    $b_prio = $prio;
                }
            }
            return $a_prio <=> $b_prio;
        } );

        // WP submenu arrays are numerically keyed — re-index after sorting.
        $submenu[ self::MENU_SLUG ] = array_values( $items );
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
     * Layout: [Spark(white chip)]  ZYMARG COMMUNITY BOARD (kicker)   [v2.1.x badge]
     *                              {Section title}          (big)
     *
     * The big title is the SECTION name (Dashboard / All Requests / Settings),
     * so every admin screen gets a header that reflects the current context.
     * "ZYMARG Community Board" becomes a small uppercase eyebrow above it.
     *
     * @param string $section_title The current section name. Leave blank to
     *                              auto-detect from the current admin screen.
     */
    public static function render_branded_header( string $section_title = '' ): void {
        $version = defined( 'ZCRB_VERSION' ) ? ZCRB_VERSION : '0.0.0';

        if ( '' === $section_title ) {
            $section_title = self::detect_section_title();
        }
        ?>
        <div class="zcrb-hub-header">
            <span class="zymarg-spark zymarg-spark--xl" role="img" aria-label="ZYMARG Discovery Spark">
              <svg class="zymarg-spark__svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <g class="zymarg-spark-group--accent">
                  <path class="zymarg-spark-item--purple" d="M10.4 5.4c0 1.32-0.24 2.4-1.44 2.4 1.2 0 1.44 1.08 1.44 2.4 0-1.32 0.24-2.4 1.44-2.4-1.2 0-1.44-1.08-1.44-2.4z"/>
                  <path class="zymarg-spark-item--gold" d="M10.4 6.0c0 0.96-0.18 1.8-1.08 1.8 0.9 0 1.08 0.84 1.08 1.8 0-0.9 0.18-1.8 1.08-1.8-0.9 0-1.08-0.84-1.08-1.8z"/>
                </g>
                <g class="zymarg-spark-group--companion">
                  <path class="zymarg-spark-item--purple" d="M9.5 10.92c0 2.25-0.45 4.12-2.4 4.12 1.95 0 2.4 1.87 2.4 4.12 0-2.25 0.45-4.12 2.4-4.12-1.95 0-2.4-1.87-2.4-4.12z"/>
                  <path class="zymarg-spark-item--gold" d="M9.5 11.5c0 1.9-0.38 3.54-2.0 3.54 1.62 0 2.0 1.64 2.0 3.54 0-1.9 0.38-3.54 2.0-3.54-1.62 0-2.0-1.64-2.0-3.54z"/>
                </g>
                <g class="zymarg-spark-group--hero">
                  <path class="zymarg-spark-item--purple" d="M15.2 5.6c0 3.45-0.69 6.3-4.08 6.3 3.39 0 4.08 2.85 4.08 6.3 0-3.45 0.69-6.3 4.08-6.3-3.39 0-4.08-2.85-4.08-6.3z"/>
                  <path class="zymarg-spark-item--gold" d="M15.2 6.5c0 2.9-0.58 5.4-3.39 5.4 2.81 0 3.39 2.5 3.39 5.4 0-2.9 0.58-5.4 3.39-5.4-2.81 0-3.39-2.5-3.39-5.4z"/>
                </g>
              </svg>
            </span>
            <div class="zcrb-hub-header__text">
                <span class="zcrb-hub-header__kicker"><?php esc_html_e( 'ZYMARG Community Board', 'zymarg-community-board' ); ?></span>
                <span class="zcrb-hub-header__title"><?php echo esc_html( $section_title ); ?></span>
            </div>
            <span class="zcrb-hub-header__version">v<?php echo esc_html( $version ); ?></span>
        </div>
        <?php
    }

    /**
     * Auto-detect the current section title from the WP admin screen.
     * Used when render_branded_header() is called without an explicit title
     * (e.g., inject_cpt_header() on the CPT list and edit screens).
     */
    private static function detect_section_title(): string {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return __( 'Dashboard', 'zymarg-community-board' );
        }
        // CPT list table: edit.php?post_type=zcrb_request
        if ( 'edit-' . ZCRB_POST_TYPE === $screen->id ) {
            return __( 'All Requests', 'zymarg-community-board' );
        }
        // Single request edit screen: post.php?post=…
        if ( ZCRB_POST_TYPE === $screen->id ) {
            return __( 'Edit Request', 'zymarg-community-board' );
        }
        // Settings page: toplevel_page_zcrb-hub_page_zcrb-settings etc.
        if ( false !== strpos( (string) $screen->id, ZCRB_Settings::SETTINGS_SLUG ) ) {
            return __( 'Settings', 'zymarg-community-board' );
        }
        // Hub landing page fallback.
        return __( 'Dashboard', 'zymarg-community-board' );
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
            <?php self::render_branded_header( __( 'Dashboard', 'zymarg-community-board' ) ); ?>

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
