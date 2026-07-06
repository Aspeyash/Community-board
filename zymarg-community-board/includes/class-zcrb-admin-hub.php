<?php
/**
 * Admin Hub — branded SPA control panel for Community Board.
 *
 * Renders ONE admin page with all three sections (Dashboard, All Requests,
 * Settings) rendered server-side into distinct `.zcrb-hub-view` panels.
 * Client-side JS toggles which panel is visible when a nav item is clicked,
 * so there are NO page reloads and NO AJAX fetches when switching sections.
 *
 * Layout follows the canonical ZYMARG admin design (sidebar + main panel)
 * used by ZYMARG Backups and the Theme Builder admin.
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

    /** @var string The page hook returned by add_menu_page(). */
    private $hook = '';

    /** @var string The page hook for the "All Requests" submenu. */
    private $requests_hook = '';

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

        // Bulk-action endpoint — POST target for the "All Requests" bulk form.
        // Registered here (constructor) so it fires on every admin request,
        // not just when the SPA page is being rendered.
        add_action( 'admin_post_zcrb_bulk_action', array( $this, 'handle_bulk_action' ) );
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
        $this->hook = add_menu_page(
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

        // Explicit "All Requests" submenu pointing at the SPA hub (not the WP CPT list table).
        $this->requests_hook = add_submenu_page(
            self::MENU_SLUG,
            __( 'All Requests', 'zymarg-community-board' ),
            __( 'All Requests', 'zymarg-community-board' ),
            'edit_posts',
            'zcrb-hub-requests',
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
            'zcrb-hub-requests'                    => 20, // All Requests (explicit SPA submenu)
            'edit.php?post_type=' . ZCRB_POST_TYPE => 25, // All Requests (CPT auto-added, if present)
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
     * Enqueue admin CSS + JS on hub / settings pages AND CPT screens.
     *
     * The Hub and Settings pages both render the same SPA shell, so both
     * need the same CSS and JS. The CPT list/edit screens only need the CSS
     * for the branded gradient header injector.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        $is_hub_page      = ( $hook === $this->hook )
                            || ( $hook === $this->requests_hook )
                            || ( 'toplevel_page_' . self::MENU_SLUG === $hook );
        $is_settings_page = ( false !== strpos( $hook, ZCRB_Settings::SETTINGS_SLUG ) );
        $is_cpt_screen    = $this->is_cpt_screen();

        if ( ! $is_hub_page && ! $is_settings_page && ! $is_cpt_screen ) {
            return;
        }

        wp_enqueue_style(
            'zcrb-admin',
            ZCRB_PLUGIN_URL . 'assets/css/zcrb-admin.css',
            array(),
            ZCRB_VERSION
        );

        // Only the SPA screens need the JS + color picker; CPT screens do not.
        if ( ! $is_hub_page && ! $is_settings_page ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        wp_enqueue_script(
            'zcrb-admin',
            ZCRB_PLUGIN_URL . 'assets/js/zcrb-admin.js',
            array( 'jquery', 'wp-color-picker' ),
            ZCRB_VERSION,
            true
        );

        wp_localize_script( 'zcrb-admin', 'ZCRBHub', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'settingsNonce' => wp_create_nonce( 'zcrb_settings_save' ),
            'hubUrl'       => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
            'defaultView'  => $this->determine_initial_view(),
            'i18n'         => array(
                'saving'    => __( 'Saving…', 'zymarg-community-board' ),
                'saved'     => __( 'Settings saved', 'zymarg-community-board' ),
                'saveError' => __( 'Failed to save settings. Please try again.', 'zymarg-community-board' ),
            ),
        ) );
    }

    /**
     * Determine which view should be active on initial page load, based on
     * the current URL (page + optional `section` query var). Falls back to
     * "dashboard" for the hub and "settings" for the settings slug.
     */
    private function determine_initial_view(): string {
        $section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        if ( in_array( $section, array( 'dashboard', 'requests', 'settings' ), true ) ) {
            return $section;
        }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( $page === 'zcrb-hub-requests' ) {
            return 'requests';
        }
        if ( $page === ZCRB_Settings::SETTINGS_SLUG ) {
            return 'settings';
        }
        return 'dashboard';
    }

    /**
     * Inject the branded header above the CPT list table and edit screens.
     * The SPA hub renders its own topbar, so this only runs on `edit.php` and
     * `post.php` for the zcrb_request CPT.
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
     * Render the unified branded gradient header (LEGACY — used only on
     * the WordPress CPT list/edit screens now, since the SPA hub renders
     * its own sidebar + topbar instead).
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
                <?php echo self::spark_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
     */
    private static function detect_section_title(): string {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return __( 'Dashboard', 'zymarg-community-board' );
        }
        if ( 'edit-' . ZCRB_POST_TYPE === $screen->id ) {
            return __( 'All Requests', 'zymarg-community-board' );
        }
        if ( ZCRB_POST_TYPE === $screen->id ) {
            return __( 'Edit Request', 'zymarg-community-board' );
        }
        if ( false !== strpos( (string) $screen->id, ZCRB_Settings::SETTINGS_SLUG ) ) {
            return __( 'Settings', 'zymarg-community-board' );
        }
        return __( 'Dashboard', 'zymarg-community-board' );
    }

    /**
     * The Discovery Spark SVG (inline). Shared between the sidebar brand and
     * the legacy CPT-screen header. Kept as a helper so both call sites stay
     * pixel-identical when the canonical spark evolves.
     */
    public static function spark_svg(): string {
        return '<svg class="zymarg-spark__svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
            . '<g class="zymarg-spark-group--accent">'
            . '<path class="zymarg-spark-item--purple" d="M10.4 5.4c0 1.32-0.24 2.4-1.44 2.4 1.2 0 1.44 1.08 1.44 2.4 0-1.32 0.24-2.4 1.44-2.4-1.2 0-1.44-1.08-1.44-2.4z"/>'
            . '<path class="zymarg-spark-item--gold" d="M10.4 6.0c0 0.96-0.18 1.8-1.08 1.8 0.9 0 1.08 0.84 1.08 1.8 0-0.9 0.18-1.8 1.08-1.8-0.9 0-1.08-0.84-1.08-1.8z"/>'
            . '</g>'
            . '<g class="zymarg-spark-group--companion">'
            . '<path class="zymarg-spark-item--purple" d="M9.5 10.92c0 2.25-0.45 4.12-2.4 4.12 1.95 0 2.4 1.87 2.4 4.12 0-2.25 0.45-4.12 2.4-4.12-1.95 0-2.4-1.87-2.4-4.12z"/>'
            . '<path class="zymarg-spark-item--gold" d="M9.5 11.5c0 1.9-0.38 3.54-2.0 3.54 1.62 0 2.0 1.64 2.0 3.54 0-1.9 0.38-3.54 2.0-3.54-1.62 0-2.0-1.64-2.0-3.54z"/>'
            . '</g>'
            . '<g class="zymarg-spark-group--hero">'
            . '<path class="zymarg-spark-item--purple" d="M15.2 5.6c0 3.45-0.69 6.3-4.08 6.3 3.39 0 4.08 2.85 4.08 6.3 0-3.45 0.69-6.3 4.08-6.3-3.39 0-4.08-2.85-4.08-6.3z"/>'
            . '<path class="zymarg-spark-item--gold" d="M15.2 6.5c0 2.9-0.58 5.4-3.39 5.4 2.81 0 3.39 2.5 3.39 5.4 0-2.9 0.58-5.4 3.39-5.4-2.81 0-3.39-2.5-3.39-5.4z"/>'
            . '</g>'
            . '</svg>';
    }

    /**
     * Render the SPA hub page. All three views (Dashboard, All Requests,
     * Settings) are rendered server-side inside a single shell; JS just
     * toggles `.is-active` on the target view when a nav item is clicked.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $initial   = $this->determine_initial_view();
        $version   = defined( 'ZCRB_VERSION' ) ? ZCRB_VERSION : '0.0.0';
        $can_admin = current_user_can( 'manage_options' );

        $nav = array(
            'dashboard' => array(
                'label' => __( 'Dashboard', 'zymarg-community-board' ),
                'icon'  => 'dashicons-chart-area',
            ),
            'requests'  => array(
                'label' => __( 'All Requests', 'zymarg-community-board' ),
                'icon'  => 'dashicons-list-view',
            ),
            'settings'  => array(
                'label' => __( 'Settings', 'zymarg-community-board' ),
                'icon'  => 'dashicons-admin-generic',
            ),
        );
        ?>
        <div class="wrap zcrb-wrap">
            <div class="zcrb-app" id="zcrb-app" data-initial-view="<?php echo esc_attr( $initial ); ?>">

                <!-- Sidebar -->
                <aside class="zcrb-sidebar">
                    <div class="zcrb-brand">
                        <span class="zymarg-spark zymarg-spark--lg" role="img" aria-label="ZYMARG Discovery Spark">
                            <?php echo self::spark_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                        <div class="zcrb-brand-text">
                            <span class="zcrb-brand-name"><?php esc_html_e( 'ZYMARG', 'zymarg-community-board' ); ?></span>
                            <span class="zcrb-brand-sub"><?php esc_html_e( 'Community Board', 'zymarg-community-board' ); ?></span>
                        </div>
                    </div>

                    <nav class="zcrb-nav" role="tablist">
                        <?php foreach ( $nav as $key => $item ) : ?>
                            <?php
                            // Hide the Settings tab from users without manage_options,
                            // since the view body will render an access-denied stub anyway.
                            if ( 'settings' === $key && ! $can_admin ) {
                                continue;
                            }
                            $is_active = ( $key === $initial );
                            ?>
                            <button
                                type="button"
                                class="zcrb-nav-item<?php echo $is_active ? ' is-active' : ''; ?>"
                                data-view="<?php echo esc_attr( $key ); ?>"
                                role="tab"
                                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                                <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
                                <span class="zcrb-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </nav>

                    <div class="zcrb-sidebar-foot"></div>
                </aside>

                <!-- Main panel -->
                <main class="zcrb-main">

                    <header class="zcrb-topbar">
                        <div class="zcrb-topbar-title">
                            <h1 id="zcrb-view-title"><?php echo esc_html( $nav[ $initial ]['label'] ); ?></h1>
                            <p class="zcrb-topbar-sub"><?php esc_html_e( 'Community Request Board — moderate, respond, and configure everything from one place.', 'zymarg-community-board' ); ?></p>
                        </div>
                        <div class="zcrb-topbar-badge">
                            <span class="zcrb-ver-badge">v<?php echo esc_html( $version ); ?></span>
                        </div>
                    </header>

                    <div class="zcrb-views">

                        <!-- Dashboard view -->
                        <section class="zcrb-view<?php echo 'dashboard' === $initial ? ' is-active' : ''; ?>" data-view="dashboard">
                            <?php $this->render_dashboard_content(); ?>
                        </section>

                        <!-- All Requests view -->
                        <section class="zcrb-view<?php echo 'requests' === $initial ? ' is-active' : ''; ?>" data-view="requests">
                            <?php $this->render_requests_content(); ?>
                        </section>

                        <!-- Settings view -->
                        <section class="zcrb-view<?php echo 'settings' === $initial ? ' is-active' : ''; ?>" data-view="settings">
                            <?php if ( $can_admin ) : ?>
                                <?php ZCRB_Settings::instance()->render_settings_body(); ?>
                            <?php else : ?>
                                <div class="zcrb-notice zcrb-notice--warn">
                                    <strong><?php esc_html_e( 'Not enough permission.', 'zymarg-community-board' ); ?></strong>
                                    <?php esc_html_e( 'You need the Manage Options capability to edit the plugin settings.', 'zymarg-community-board' ); ?>
                                </div>
                            <?php endif; ?>
                        </section>

                    </div>
                </main>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Dashboard view body (stats cards + quick-action panel).
     * Called from `render_page()` and never exposed via AJAX.
     */
    public function render_dashboard_content(): void {
        $total       = $this->count_posts( array( 'publish', 'pending', 'draft', 'zcrb_in_progress', 'zcrb_fulfilled' ) );
        $pending     = $this->count_posts( array( 'pending', 'draft' ) );
        $in_progress = $this->count_posts( array( 'zcrb_in_progress' ) );
        $fulfilled   = $this->count_posts( array( 'zcrb_fulfilled' ) );

        $archive_url = get_post_type_archive_link( ZCRB_POST_TYPE );
        ?>
        <div class="zcrb-notice">
            <strong><?php esc_html_e( 'Welcome to the Community Board control panel.', 'zymarg-community-board' ); ?></strong>
            <?php esc_html_e( 'Approve, respond to, and moderate community requests. Switching sections is instant — no page reload.', 'zymarg-community-board' ); ?>
        </div>

        <div class="zcrb-cards">
            <div class="zcrb-card">
                <span class="dashicons dashicons-portfolio zcrb-card-icon"></span>
                <div class="zcrb-card-body">
                    <span class="zcrb-card-label"><?php esc_html_e( 'Total Requests', 'zymarg-community-board' ); ?></span>
                    <span class="zcrb-card-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
                </div>
            </div>
            <div class="zcrb-card">
                <span class="dashicons dashicons-clock zcrb-card-icon"></span>
                <div class="zcrb-card-body">
                    <span class="zcrb-card-label"><?php esc_html_e( 'Pending', 'zymarg-community-board' ); ?></span>
                    <span class="zcrb-card-value"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
                </div>
            </div>
            <div class="zcrb-card">
                <span class="dashicons dashicons-update zcrb-card-icon"></span>
                <div class="zcrb-card-body">
                    <span class="zcrb-card-label"><?php esc_html_e( 'In Progress', 'zymarg-community-board' ); ?></span>
                    <span class="zcrb-card-value"><?php echo esc_html( number_format_i18n( $in_progress ) ); ?></span>
                </div>
            </div>
            <div class="zcrb-card">
                <span class="dashicons dashicons-yes-alt zcrb-card-icon"></span>
                <div class="zcrb-card-body">
                    <span class="zcrb-card-label"><?php esc_html_e( 'Fulfilled', 'zymarg-community-board' ); ?></span>
                    <span class="zcrb-card-value"><?php echo esc_html( number_format_i18n( $fulfilled ) ); ?></span>
                </div>
            </div>
        </div>

        <div class="zcrb-panel">
            <h2><?php esc_html_e( 'What the Community Board does', 'zymarg-community-board' ); ?></h2>
            <ul class="zcrb-check-list">
                <li><?php esc_html_e( 'Logged-in shoppers submit requests (Name, Phone, Email, Message, optional Image) — private submitter data stays private.', 'zymarg-community-board' ); ?></li>
                <li><?php esc_html_e( 'Requests arrive as pending. Approve or reject them from the All Requests tab (or the WP list table).', 'zymarg-community-board' ); ?></li>
                <li><?php esc_html_e( 'Vendors can respond to approved requests; submitters get an email notification.', 'zymarg-community-board' ); ?></li>
                <li><?php esc_html_e( 'Fully bilingual (English / Bengali), SEO-optimised, and mobile responsive.', 'zymarg-community-board' ); ?></li>
            </ul>
            <?php if ( $archive_url ) : ?>
                <p style="margin-top:14px;">
                    <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e( 'View the public board', 'zymarg-community-board' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the "All Requests" view body — a full-featured, self-contained
     * management surface with status tabs, sortable table (desktop) / card
     * fallback (mobile), bulk actions, trash tab, and per-page selector.
     * Lives inside the SPA shell so switching to it is instant.
     *
     * Server-side filtering by ?zcrb_status={tab} handles pagination and
     * accurate empty states. Live keyword search + column sort run in JS.
     */
    public function render_requests_content(): void {
        // -----------------------------------------------------------------
        // 1) Parse query parameters (paging, per-page, active tab).
        // -----------------------------------------------------------------
        $paged        = isset( $_GET['zcrb_page'] ) ? max( 1, (int) $_GET['zcrb_page'] ) : 1;
        $per_page_req = isset( $_GET['per_page'] ) ? sanitize_key( wp_unslash( $_GET['per_page'] ) ) : '20';
        $active_tab   = isset( $_GET['zcrb_status'] ) ? sanitize_key( wp_unslash( $_GET['zcrb_status'] ) ) : 'all';

        $allowed_per_page = array( '20', '50', '100', 'all' );
        if ( ! in_array( $per_page_req, $allowed_per_page, true ) ) {
            $per_page_req = '20';
        }
        $per = ( 'all' === $per_page_req ) ? -1 : (int) $per_page_req;

        // -----------------------------------------------------------------
        // 2) Human-readable status labels (used both for badges and tabs).
        // -----------------------------------------------------------------
        $status_labels = array(
            'publish'          => __( 'Approved', 'zymarg-community-board' ),
            'pending'          => __( 'Pending', 'zymarg-community-board' ),
            'draft'            => __( 'Pending', 'zymarg-community-board' ),
            'zcrb_in_progress' => __( 'In Progress', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'Fulfilled', 'zymarg-community-board' ),
            'trash'            => __( 'Trashed', 'zymarg-community-board' ),
        );

        // -----------------------------------------------------------------
        // 3) Counts per tab from wp_count_posts() (single DB round-trip).
        // -----------------------------------------------------------------
        $counts_obj = wp_count_posts( ZCRB_POST_TYPE );
        $c_publish  = isset( $counts_obj->publish )          ? (int) $counts_obj->publish          : 0;
        $c_pending  = ( isset( $counts_obj->pending ) ? (int) $counts_obj->pending : 0 )
                    + ( isset( $counts_obj->draft )   ? (int) $counts_obj->draft   : 0 );
        $c_progress = isset( $counts_obj->zcrb_in_progress ) ? (int) $counts_obj->zcrb_in_progress : 0;
        $c_fulfil   = isset( $counts_obj->zcrb_fulfilled )   ? (int) $counts_obj->zcrb_fulfilled   : 0;
        $c_trash    = isset( $counts_obj->trash )            ? (int) $counts_obj->trash            : 0;
        $c_all      = $c_publish + $c_pending + $c_progress + $c_fulfil;

        $tab_counts = array(
            'all'              => $c_all,
            'publish'          => $c_publish,
            'pending'          => $c_pending,
            'zcrb_in_progress' => $c_progress,
            'zcrb_fulfilled'   => $c_fulfil,
            'trash'            => $c_trash,
        );

        // -----------------------------------------------------------------
        // 4) Resolve active tab → post_status[] for the query.
        // -----------------------------------------------------------------
        $status_map = array(
            'all'              => array( 'publish', 'pending', 'draft', 'zcrb_in_progress', 'zcrb_fulfilled' ),
            'publish'          => array( 'publish' ),
            'pending'          => array( 'pending', 'draft' ),
            'zcrb_in_progress' => array( 'zcrb_in_progress' ),
            'zcrb_fulfilled'   => array( 'zcrb_fulfilled' ),
            'trash'            => array( 'trash' ),
        );
        if ( ! isset( $status_map[ $active_tab ] ) ) {
            $active_tab = 'all';
        }
        $post_statuses = $status_map[ $active_tab ];
        $is_trash      = ( 'trash' === $active_tab );

        // -----------------------------------------------------------------
        // 5) Run the query.
        // -----------------------------------------------------------------
        $q_args = array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => $post_statuses,
            'posts_per_page' => $per,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( -1 === $per ) {
            $q_args['no_found_rows'] = true;
            $q_args['paged']         = 1;
        }
        $q = new WP_Query( $q_args );

        // -----------------------------------------------------------------
        // 6) Cache the rendering data once so we can iterate twice (table
        //    on desktop, card fallback on mobile) without a second query.
        // -----------------------------------------------------------------
        $rows = array();
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $post_id  = get_the_ID();
                $status   = get_post_status( $post_id );
                $lbl      = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( (string) $status );
                $full_nm  = (string) get_post_meta( $post_id, '_zcrb_full_name', true );
                $upvotes  = (int) get_post_meta( $post_id, '_zcrb_upvote_count', true );
                $ref      = 'RQ' . str_pad( (string) $post_id, 5, '0', STR_PAD_LEFT );
                $excerpt  = wp_strip_all_tags( (string) get_the_excerpt() );
                if ( '' === $excerpt ) {
                    $excerpt = wp_strip_all_tags( (string) get_the_content() );
                }
                $excerpt   = wp_trim_words( $excerpt, 18, '…' );
                $title     = get_the_title( $post_id );
                if ( '' === $title ) {
                    $title = __( '(no title)', 'zymarg-community-board' );
                }
                $date_ts   = (int) get_the_date( 'U', $post_id );
                $date_disp = get_the_date( '', $post_id );

                $rows[] = array(
                    'id'          => (int) $post_id,
                    'ref'         => $ref,
                    'title'       => (string) $title,
                    'excerpt'     => (string) $excerpt,
                    'submitter'   => (string) $full_nm,
                    'status'      => (string) $status,
                    'status_lbl'  => (string) $lbl,
                    'upvotes'     => (int) $upvotes,
                    'date_ts'     => $date_ts,
                    'date_disp'   => (string) $date_disp,
                    'view_url'    => (string) get_permalink( $post_id ),
                    'edit_url'    => (string) get_edit_post_link( $post_id ),
                );
            }
            wp_reset_postdata();
        }

        // -----------------------------------------------------------------
        // 7) Helpers: URL builders (tabs + row actions + per-page).
        // -----------------------------------------------------------------
        $base_url = add_query_arg(
            array(
                'page'    => self::MENU_SLUG,
                'section' => 'requests',
            ),
            admin_url( 'admin.php' )
        );

        // Tab links preserve the current per-page choice.
        $build_tab_url = function ( $tab_key ) use ( $base_url, $per_page_req ) {
            $args = array( 'zcrb_status' => $tab_key );
            if ( '20' !== $per_page_req ) {
                $args['per_page'] = $per_page_req;
            }
            return add_query_arg( $args, $base_url );
        };

        // Row-level action URLs. The redirect back to the hub is handled by
        // ZCRB_Admin::handle_quick_action() when it sees zcrb_return=hub.
        $build_row_action_url = function ( $post_id, $action ) use ( $active_tab ) {
            return wp_nonce_url(
                add_query_arg(
                    array(
                        'zcrb_action' => $action,
                        'post'        => $post_id,
                        'zcrb_return' => 'hub',
                        'zcrb_tab'    => $active_tab,
                    ),
                    admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                ),
                'zcrb_quick_action_' . $post_id
            );
        };

        $add_new_url  = admin_url( 'post-new.php?post_type=' . ZCRB_POST_TYPE );
        $bulk_form_id = 'zcrb-bulk-form';

        // Human-friendly empty state per tab.
        $empty_map = array(
            'all'              => __( 'No community requests yet. They will show up here as visitors submit them.', 'zymarg-community-board' ),
            'publish'          => __( 'No approved requests.', 'zymarg-community-board' ),
            'pending'          => __( 'No pending requests.', 'zymarg-community-board' ),
            'zcrb_in_progress' => __( 'No requests in progress.', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'No fulfilled requests.', 'zymarg-community-board' ),
            'trash'            => __( 'Trash is empty.', 'zymarg-community-board' ),
        );

        // Tabs, in display order.
        $tabs = array(
            'all'              => __( 'All', 'zymarg-community-board' ),
            'publish'          => __( 'Approved', 'zymarg-community-board' ),
            'pending'          => __( 'Pending', 'zymarg-community-board' ),
            'zcrb_in_progress' => __( 'In Progress', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'Fulfilled', 'zymarg-community-board' ),
            'trash'            => __( 'Trash', 'zymarg-community-board' ),
        );
        ?>
        <div class="zcrb-panel zcrb-requests-panel">

            <!-- Header: title + Add New button -->
            <div class="zcrb-requests-header">
                <h2 class="zcrb-requests-title">
                    <?php esc_html_e( 'All Community Requests', 'zymarg-community-board' ); ?>
                </h2>
                <a class="zcrb-btn zcrb-add-new-btn" href="<?php echo esc_url( $add_new_url ); ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Add New', 'zymarg-community-board' ); ?>
                </a>
            </div>

            <!-- Status tabs + live search -->
            <div class="zcrb-requests-tabbar">
                <nav class="zcrb-status-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter by status', 'zymarg-community-board' ); ?>">
                    <?php foreach ( $tabs as $tab_key => $tab_label ) :
                        $is_active = ( $tab_key === $active_tab );
                        $c = isset( $tab_counts[ $tab_key ] ) ? (int) $tab_counts[ $tab_key ] : 0;
                        ?>
                        <a
                            class="zcrb-status-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                            href="<?php echo esc_url( $build_tab_url( $tab_key ) ); ?>"
                            data-status-tab="<?php echo esc_attr( $tab_key ); ?>"
                            role="tab"
                            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                            <span class="zcrb-status-tab-label"><?php echo esc_html( $tab_label ); ?></span>
                            <span class="zcrb-status-tab-count"><?php echo esc_html( number_format_i18n( $c ) ); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="zcrb-requests-search-wrap">
                    <span class="dashicons dashicons-search zcrb-search-icon" aria-hidden="true"></span>
                    <input
                        type="search"
                        class="zcrb-requests-search"
                        placeholder="<?php esc_attr_e( 'Search requests…', 'zymarg-community-board' ); ?>"
                        data-zcrb-requests-search
                        aria-label="<?php esc_attr_e( 'Search requests', 'zymarg-community-board' ); ?>"
                    />
                </div>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <div class="zcrb-empty-inline">
                    <?php echo esc_html( $empty_map[ $active_tab ] ); ?>
                </div>
            <?php else : ?>
                <form
                    id="<?php echo esc_attr( $bulk_form_id ); ?>"
                    method="post"
                    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                    class="zcrb-bulk-form"
                    data-zcrb-bulk-form>
                    <input type="hidden" name="action" value="zcrb_bulk_action" />
                    <input type="hidden" name="zcrb_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
                    <input type="hidden" name="zcrb_per_page" value="<?php echo esc_attr( $per_page_req ); ?>" />
                    <?php wp_nonce_field( 'zcrb_bulk', 'zcrb_bulk_nonce' ); ?>

                    <!-- Bulk actions bar -->
                    <div class="zcrb-bulk-bar">
                        <div class="zcrb-bulk-controls">
                            <label for="zcrb_bulk_action_top" class="screen-reader-text">
                                <?php esc_html_e( 'Bulk actions', 'zymarg-community-board' ); ?>
                            </label>
                            <select id="zcrb_bulk_action_top" name="zcrb_bulk_action" class="zcrb-bulk-select">
                                <option value=""><?php esc_html_e( 'Bulk actions', 'zymarg-community-board' ); ?></option>
                                <?php if ( $is_trash ) : ?>
                                    <option value="restore"><?php esc_html_e( 'Restore', 'zymarg-community-board' ); ?></option>
                                    <option value="delete"><?php esc_html_e( 'Delete Permanently', 'zymarg-community-board' ); ?></option>
                                <?php else : ?>
                                    <option value="approve"><?php esc_html_e( 'Approve', 'zymarg-community-board' ); ?></option>
                                    <option value="reject"><?php esc_html_e( 'Reject', 'zymarg-community-board' ); ?></option>
                                    <option value="trash"><?php esc_html_e( 'Move to Trash', 'zymarg-community-board' ); ?></option>
                                    <option value="delete"><?php esc_html_e( 'Delete Permanently', 'zymarg-community-board' ); ?></option>
                                <?php endif; ?>
                            </select>
                            <button
                                type="submit"
                                class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm"
                                data-zcrb-bulk-apply>
                                <?php esc_html_e( 'Apply', 'zymarg-community-board' ); ?>
                            </button>
                        </div>
                        <div class="zcrb-bulk-perpage">
                            <label for="zcrb_per_page_select">
                                <?php esc_html_e( 'Show', 'zymarg-community-board' ); ?>
                            </label>
                            <select
                                id="zcrb_per_page_select"
                                class="zcrb-per-page-select"
                                data-zcrb-per-page
                                data-base-url="<?php echo esc_attr( $build_tab_url( $active_tab ) ); ?>">
                                <?php foreach ( array( '20', '50', '100', 'all' ) as $pp ) :
                                    $lbl = ( 'all' === $pp ) ? __( 'All', 'zymarg-community-board' ) : $pp;
                                    ?>
                                    <option value="<?php echo esc_attr( $pp ); ?>" <?php selected( $per_page_req, $pp ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="zcrb-per-page-suffix"><?php esc_html_e( 'per page', 'zymarg-community-board' ); ?></span>
                        </div>
                    </div>

                    <!-- Desktop: sortable table -->
                    <div class="zcrb-requests-table-wrap">
                        <table class="zcrb-requests-table" data-zcrb-requests-table>
                            <thead>
                                <tr>
                                    <th class="zcrb-col-check" scope="col">
                                        <input
                                            type="checkbox"
                                            data-zcrb-check-all="table"
                                            aria-label="<?php esc_attr_e( 'Select all requests', 'zymarg-community-board' ); ?>" />
                                    </th>
                                    <th class="zcrb-col-ref zcrb-sortable" data-sort-key="ref" scope="col">
                                        <button type="button" class="zcrb-sort-btn">
                                            <?php esc_html_e( 'Ref', 'zymarg-community-board' ); ?>
                                            <span class="zcrb-sort-icon" aria-hidden="true">&#x21C5;</span>
                                        </button>
                                    </th>
                                    <th class="zcrb-col-title zcrb-sortable" data-sort-key="title" scope="col">
                                        <button type="button" class="zcrb-sort-btn">
                                            <?php esc_html_e( 'Title', 'zymarg-community-board' ); ?>
                                            <span class="zcrb-sort-icon" aria-hidden="true">&#x21C5;</span>
                                        </button>
                                    </th>
                                    <th class="zcrb-col-submitter zcrb-sortable" data-sort-key="submitter" scope="col">
                                        <button type="button" class="zcrb-sort-btn">
                                            <?php esc_html_e( 'Submitter', 'zymarg-community-board' ); ?>
                                            <span class="zcrb-sort-icon" aria-hidden="true">&#x21C5;</span>
                                        </button>
                                    </th>
                                    <th class="zcrb-col-status" scope="col">
                                        <?php esc_html_e( 'Status', 'zymarg-community-board' ); ?>
                                    </th>
                                    <th class="zcrb-col-upvotes zcrb-sortable" data-sort-key="upvotes" scope="col">
                                        <button type="button" class="zcrb-sort-btn">
                                            <?php esc_html_e( 'Upvotes', 'zymarg-community-board' ); ?>
                                            <span class="zcrb-sort-icon" aria-hidden="true">&#x21C5;</span>
                                        </button>
                                    </th>
                                    <th class="zcrb-col-date zcrb-sortable" data-sort-key="date" scope="col">
                                        <button type="button" class="zcrb-sort-btn">
                                            <?php esc_html_e( 'Date', 'zymarg-community-board' ); ?>
                                            <span class="zcrb-sort-icon" aria-hidden="true">&#x21C5;</span>
                                        </button>
                                    </th>
                                    <th class="zcrb-col-actions" scope="col">
                                        <?php esc_html_e( 'Actions', 'zymarg-community-board' ); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody data-zcrb-tbody>
                                <?php foreach ( $rows as $row ) :
                                    $this->render_request_table_row( $row, $is_trash, $build_row_action_url );
                                endforeach; ?>
                            </tbody>
                        </table>
                        <div class="zcrb-empty-inline zcrb-empty-tab" data-zcrb-empty-message style="display:none;">
                            <?php esc_html_e( 'No matching requests.', 'zymarg-community-board' ); ?>
                        </div>
                    </div>

                    <!-- Mobile: card fallback (same data, different markup) -->
                    <div class="zcrb-requests-cards" data-zcrb-cards>
                        <?php foreach ( $rows as $row ) :
                            $this->render_request_card( $row, $is_trash, $build_row_action_url );
                        endforeach; ?>
                        <div class="zcrb-empty-inline zcrb-empty-tab" data-zcrb-empty-message-cards style="display:none;">
                            <?php esc_html_e( 'No matching requests.', 'zymarg-community-board' ); ?>
                        </div>
                    </div>
                </form>

                <?php
                $total_pages = (int) $q->max_num_pages;
                if ( $total_pages > 1 ) :
                    $base = $build_tab_url( $active_tab );
                    ?>
                    <div class="zcrb-requests-pagination">
                        <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
                            $url = add_query_arg( 'zcrb_page', $i, $base );
                            $cls = ( $i === $paged ) ? 'zcrb-page is-active' : 'zcrb-page';
                            ?>
                            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( (string) $i ); ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a single request as a desktop table row.
     *
     * @param array    $row                  Row data (see render_requests_content()).
     * @param bool     $is_trash             Whether the Trash tab is currently active.
     * @param callable $build_row_action_url URL builder for quick actions.
     */
    private function render_request_table_row( array $row, bool $is_trash, callable $build_row_action_url ): void {
        $post_id       = (int) $row['id'];
        $status        = (string) $row['status'];
        $search_hay    = strtolower( trim( $row['title'] . ' ' . $row['excerpt'] . ' ' . $row['submitter'] . ' ' . $row['ref'] ) );
        $sort_date_iso = $row['date_ts'] > 0 ? gmdate( 'c', $row['date_ts'] ) : '';
        ?>
        <tr
            class="zcrb-row"
            data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
            data-status="<?php echo esc_attr( $status ); ?>"
            data-search="<?php echo esc_attr( $search_hay ); ?>"
            data-sort-ref="<?php echo esc_attr( (string) $post_id ); ?>"
            data-sort-title="<?php echo esc_attr( strtolower( $row['title'] ) ); ?>"
            data-sort-submitter="<?php echo esc_attr( strtolower( $row['submitter'] ) ); ?>"
            data-sort-upvotes="<?php echo esc_attr( (string) $row['upvotes'] ); ?>"
            data-sort-date="<?php echo esc_attr( (string) $row['date_ts'] ); ?>"
            data-sort-date-iso="<?php echo esc_attr( $sort_date_iso ); ?>">
            <td class="zcrb-col-check" data-label="<?php esc_attr_e( 'Select', 'zymarg-community-board' ); ?>">
                <input
                    type="checkbox"
                    class="zcrb-row-check"
                    name="zcrb_bulk_ids[]"
                    value="<?php echo esc_attr( (string) $post_id ); ?>"
                    data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
                    aria-label="<?php echo esc_attr( sprintf( /* translators: %s: ref number */ __( 'Select %s', 'zymarg-community-board' ), $row['ref'] ) ); ?>" />
            </td>
            <td class="zcrb-col-ref" data-label="<?php esc_attr_e( 'Ref', 'zymarg-community-board' ); ?>">
                <span class="zcrb-request-ref"><?php echo esc_html( $row['ref'] ); ?></span>
            </td>
            <td class="zcrb-col-title" data-label="<?php esc_attr_e( 'Title', 'zymarg-community-board' ); ?>">
                <div class="zcrb-cell-title"><?php echo esc_html( $row['title'] ); ?></div>
                <?php if ( $row['excerpt'] ) : ?>
                    <div class="zcrb-cell-excerpt"><?php echo esc_html( $row['excerpt'] ); ?></div>
                <?php endif; ?>
            </td>
            <td class="zcrb-col-submitter" data-label="<?php esc_attr_e( 'Submitter', 'zymarg-community-board' ); ?>">
                <?php echo $row['submitter'] ? esc_html( $row['submitter'] ) : '<span class="zcrb-muted">&mdash;</span>'; ?>
            </td>
            <td class="zcrb-col-status" data-label="<?php esc_attr_e( 'Status', 'zymarg-community-board' ); ?>">
                <span class="zcrb-request-status zcrb-request-status--<?php echo esc_attr( $status ); ?>">
                    <?php echo esc_html( $row['status_lbl'] ); ?>
                </span>
            </td>
            <td class="zcrb-col-upvotes" data-label="<?php esc_attr_e( 'Upvotes', 'zymarg-community-board' ); ?>">
                <?php if ( $row['upvotes'] > 0 ) : ?>
                    <span class="zcrb-request-upvotes">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        <?php echo esc_html( (string) $row['upvotes'] ); ?>
                    </span>
                <?php else : ?>
                    <span class="zcrb-muted">0</span>
                <?php endif; ?>
            </td>
            <td class="zcrb-col-date" data-label="<?php esc_attr_e( 'Date', 'zymarg-community-board' ); ?>">
                <?php echo esc_html( $row['date_disp'] ); ?>
            </td>
            <td class="zcrb-col-actions" data-label="<?php esc_attr_e( 'Actions', 'zymarg-community-board' ); ?>">
                <?php $this->render_row_action_buttons( $row, $is_trash, $build_row_action_url ); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render a single request as a mobile card (visible below 768px).
     *
     * @param array    $row                  Row data (see render_requests_content()).
     * @param bool     $is_trash             Whether the Trash tab is currently active.
     * @param callable $build_row_action_url URL builder for quick actions.
     */
    private function render_request_card( array $row, bool $is_trash, callable $build_row_action_url ): void {
        $post_id       = (int) $row['id'];
        $status        = (string) $row['status'];
        $search_hay    = strtolower( trim( $row['title'] . ' ' . $row['excerpt'] . ' ' . $row['submitter'] . ' ' . $row['ref'] ) );
        ?>
        <div
            class="zcrb-request zcrb-request-card"
            data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
            data-status="<?php echo esc_attr( $status ); ?>"
            data-search="<?php echo esc_attr( $search_hay ); ?>"
            data-sort-ref="<?php echo esc_attr( (string) $post_id ); ?>"
            data-sort-title="<?php echo esc_attr( strtolower( $row['title'] ) ); ?>"
            data-sort-submitter="<?php echo esc_attr( strtolower( $row['submitter'] ) ); ?>"
            data-sort-upvotes="<?php echo esc_attr( (string) $row['upvotes'] ); ?>"
            data-sort-date="<?php echo esc_attr( (string) $row['date_ts'] ); ?>">
            <div class="zcrb-request-head">
                <label class="zcrb-request-check-wrap">
                    <input
                        type="checkbox"
                        class="zcrb-row-check zcrb-row-check--card"
                        name="zcrb_bulk_ids[]"
                        value="<?php echo esc_attr( (string) $post_id ); ?>"
                        data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
                        aria-label="<?php echo esc_attr( sprintf( /* translators: %s: ref number */ __( 'Select %s', 'zymarg-community-board' ), $row['ref'] ) ); ?>" />
                </label>
                <span class="zcrb-request-ref"><?php echo esc_html( $row['ref'] ); ?></span>
                <span class="zcrb-request-status zcrb-request-status--<?php echo esc_attr( $status ); ?>">
                    <?php echo esc_html( $row['status_lbl'] ); ?>
                </span>
                <span class="zcrb-request-date"><?php echo esc_html( $row['date_disp'] ); ?></span>
                <?php if ( $row['upvotes'] > 0 ) : ?>
                    <span class="zcrb-request-upvotes" title="<?php esc_attr_e( 'Upvotes', 'zymarg-community-board' ); ?>">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        <?php echo esc_html( (string) $row['upvotes'] ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="zcrb-request-title"><?php echo esc_html( $row['title'] ); ?></div>
            <?php if ( $row['excerpt'] ) : ?>
                <div class="zcrb-request-body"><?php echo esc_html( $row['excerpt'] ); ?></div>
            <?php endif; ?>
            <?php if ( $row['submitter'] ) : ?>
                <div class="zcrb-request-meta">
                    <span class="zcrb-request-submitter">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php echo esc_html( $row['submitter'] ); ?>
                    </span>
                </div>
            <?php endif; ?>
            <div class="zcrb-request-actions">
                <?php $this->render_row_action_buttons( $row, $is_trash, $build_row_action_url ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the per-row action buttons (View / Edit / Approve / Reject /
     * Trash / Restore / Delete Permanently). Buttons vary by current status
     * and by whether the Trash tab is active.
     *
     * @param array    $row                  Row data.
     * @param bool     $is_trash             Whether the Trash tab is currently active.
     * @param callable $build_row_action_url URL builder for quick actions.
     */
    private function render_row_action_buttons( array $row, bool $is_trash, callable $build_row_action_url ): void {
        $post_id  = (int) $row['id'];
        $status   = (string) $row['status'];
        $view_url = (string) $row['view_url'];
        $edit_url = (string) $row['edit_url'];

        if ( $is_trash ) {
            $restore_url = $build_row_action_url( $post_id, 'restore' );
            $delete_url  = $build_row_action_url( $post_id, 'delete' );
            ?>
            <a class="zcrb-btn zcrb-btn--sm" href="<?php echo esc_url( $restore_url ); ?>">
                <span class="dashicons dashicons-undo"></span><?php esc_html_e( 'Restore', 'zymarg-community-board' ); ?>
            </a>
            <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm zcrb-btn--danger"
               href="<?php echo esc_url( $delete_url ); ?>"
               onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this request? This cannot be undone.', 'zymarg-community-board' ) ); ?>');">
                <span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Delete Permanently', 'zymarg-community-board' ); ?>
            </a>
            <?php
            return;
        }

        $approve_url = ( 'pending' === $status || 'draft' === $status ) ? $build_row_action_url( $post_id, 'approve' ) : '';
        $reject_url  = ( 'publish' !== $status ) ? $build_row_action_url( $post_id, 'reject' ) : '';
        $delete_url  = $build_row_action_url( $post_id, 'delete' );

        if ( $view_url ) : ?>
            <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">
                <span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'View', 'zymarg-community-board' ); ?>
            </a>
        <?php endif;
        if ( $edit_url ) : ?>
            <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm" href="<?php echo esc_url( $edit_url ); ?>">
                <span class="dashicons dashicons-edit"></span><?php esc_html_e( 'Edit', 'zymarg-community-board' ); ?>
            </a>
        <?php endif;
        if ( $approve_url ) : ?>
            <a class="zcrb-btn zcrb-btn--sm" href="<?php echo esc_url( $approve_url ); ?>">
                <span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Approve', 'zymarg-community-board' ); ?>
            </a>
        <?php endif;
        if ( $reject_url ) : ?>
            <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm zcrb-btn--danger" href="<?php echo esc_url( $reject_url ); ?>">
                <span class="dashicons dashicons-dismiss"></span><?php esc_html_e( 'Reject', 'zymarg-community-board' ); ?>
            </a>
        <?php endif; ?>
        <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm zcrb-btn--danger" href="<?php echo esc_url( $delete_url ); ?>"
           onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this request? This cannot be undone.', 'zymarg-community-board' ) ); ?>');">
            <span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Delete', 'zymarg-community-board' ); ?>
        </a>
        <?php
    }

    /**
     * Handle the bulk-action form POST from the SPA All Requests view.
     *
     * Hooked on `admin_post_zcrb_bulk_action`. Verifies nonce + capability,
     * applies the chosen action (approve / reject / trash / delete /
     * restore) to each selected post, then redirects back to the SPA with
     * a `zcrb_msg=bulk_done` notice.
     */
    public function handle_bulk_action(): void {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform bulk actions on community requests.', 'zymarg-community-board' ) );
        }
        check_admin_referer( 'zcrb_bulk', 'zcrb_bulk_nonce' );

        $action     = isset( $_POST['zcrb_bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['zcrb_bulk_action'] ) ) : '';
        $raw_ids    = isset( $_POST['zcrb_bulk_ids'] ) ? (array) $_POST['zcrb_bulk_ids'] : array();
        $return_tab = isset( $_POST['zcrb_tab'] ) ? sanitize_key( wp_unslash( $_POST['zcrb_tab'] ) ) : 'all';
        $per_page   = isset( $_POST['zcrb_per_page'] ) ? sanitize_key( wp_unslash( $_POST['zcrb_per_page'] ) ) : '20';

        // Dedupe (mobile-card + desktop-table checkboxes may both submit).
        $ids  = array_values( array_unique( array_map( 'intval', $raw_ids ) ) );
        $done = 0;

        $valid_actions = array( 'approve', 'reject', 'trash', 'delete', 'restore' );
        if ( in_array( $action, $valid_actions, true ) && ! empty( $ids ) ) {
            foreach ( $ids as $id ) {
                $id = (int) $id;
                if ( $id <= 0 ) {
                    continue;
                }
                if ( ! current_user_can( 'edit_post', $id ) ) {
                    continue;
                }
                if ( ZCRB_POST_TYPE !== get_post_type( $id ) ) {
                    continue;
                }

                switch ( $action ) {
                    case 'approve':
                        $res = wp_update_post( array(
                            'ID'          => $id,
                            'post_status' => 'publish',
                        ), true );
                        if ( ! is_wp_error( $res ) ) {
                            $done++;
                        }
                        break;

                    case 'reject':
                    case 'trash':
                        if ( wp_trash_post( $id ) ) {
                            $done++;
                        }
                        break;

                    case 'delete':
                        if ( class_exists( 'ZCRB_Retention' ) && method_exists( 'ZCRB_Retention', 'delete_request' ) ) {
                            if ( ZCRB_Retention::delete_request( $id ) ) {
                                $done++;
                            }
                        } elseif ( false !== wp_delete_post( $id, true ) ) {
                            $done++;
                        }
                        break;

                    case 'restore':
                        if ( wp_untrash_post( $id ) ) {
                            $done++;
                        }
                        break;
                }
            }
        }

        $redirect_args = array(
            'page'        => self::MENU_SLUG,
            'section'     => 'requests',
            'zcrb_status' => $return_tab,
            'zcrb_msg'    => 'bulk_done',
            'zcrb_done'   => $done,
            'zcrb_op'     => $action,
        );
        if ( '20' !== $per_page && in_array( $per_page, array( '50', '100', 'all' ), true ) ) {
            $redirect_args['per_page'] = $per_page;
        }
        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Delegate to the Settings module (kept in `class-zcrb-settings.php`).
     * Present here so the router in `render_page()` can call all three views
     * with a consistent signature.
     */
    public function render_settings_content(): void {
        ZCRB_Settings::instance()->render_settings_body();
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
