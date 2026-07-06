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
     * Render the "All Requests" view body — a custom, self-contained list of
     * every community request with quick admin actions. Lives inside the SPA
     * shell so switching to it is instant (no page reload).
     */
    public function render_requests_content(): void {
        $paged = isset( $_GET['zcrb_page'] ) ? max( 1, (int) $_GET['zcrb_page'] ) : 1;
        $per   = 20;

        $q = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => array( 'publish', 'pending', 'draft', 'zcrb_in_progress', 'zcrb_fulfilled' ),
            'posts_per_page' => $per,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ) );

        $status_labels = array(
            'publish'          => __( 'Approved', 'zymarg-community-board' ),
            'pending'          => __( 'Pending', 'zymarg-community-board' ),
            'draft'            => __( 'Draft', 'zymarg-community-board' ),
            'zcrb_in_progress' => __( 'In Progress', 'zymarg-community-board' ),
            'zcrb_fulfilled'   => __( 'Fulfilled', 'zymarg-community-board' ),
            'trash'            => __( 'Trashed', 'zymarg-community-board' ),
        );

        $cpt_list_url = admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE );
        ?>
        <div class="zcrb-panel">
            <div class="zcrb-requests-toolbar">
                <h2 style="margin:0;flex:1;"><?php esc_html_e( 'All Community Requests', 'zymarg-community-board' ); ?></h2>
                <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( $cpt_list_url ); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Advanced list view', 'zymarg-community-board' ); ?>
                </a>
            </div>

            <?php if ( ! $q->have_posts() ) : ?>
                <div class="zcrb-empty-inline">
                    <?php esc_html_e( 'No community requests yet. They will show up here as visitors submit them.', 'zymarg-community-board' ); ?>
                </div>
            <?php else : ?>
                <div class="zcrb-requests-filters">
                    <input
                        type="text"
                        class="zcrb-requests-search"
                        placeholder="<?php esc_attr_e( 'Filter by keyword…', 'zymarg-community-board' ); ?>"
                        data-zcrb-requests-search
                    />
                    <select data-zcrb-requests-status class="zcrb-requests-status-select">
                        <option value=""><?php esc_html_e( 'All statuses', 'zymarg-community-board' ); ?></option>
                        <?php foreach ( $status_labels as $s => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="zcrb-requests-list">
                    <?php while ( $q->have_posts() ) : $q->the_post();
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
                        $excerpt  = wp_trim_words( $excerpt, 18, '…' );
                        $view_url = get_permalink( $post_id );
                        $edit_url = get_edit_post_link( $post_id );

                        $approve_url = ( 'pending' === $status || 'draft' === $status )
                            ? wp_nonce_url(
                                add_query_arg(
                                    array( 'zcrb_action' => 'approve', 'post' => $post_id ),
                                    admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                                ),
                                'zcrb_quick_action_' . $post_id
                            )
                            : '';
                        $reject_url = ( 'publish' !== $status )
                            ? wp_nonce_url(
                                add_query_arg(
                                    array( 'zcrb_action' => 'reject', 'post' => $post_id ),
                                    admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                                ),
                                'zcrb_quick_action_' . $post_id
                            )
                            : '';
                        $delete_url = wp_nonce_url(
                            add_query_arg(
                                array( 'zcrb_action' => 'delete', 'post' => $post_id ),
                                admin_url( 'edit.php?post_type=' . ZCRB_POST_TYPE )
                            ),
                            'zcrb_quick_action_' . $post_id
                        );

                        $search_haystack = strtolower( trim(
                            get_the_title( $post_id ) . ' ' . $excerpt . ' ' . $full_nm . ' ' . $ref
                        ) );
                        ?>
                        <div class="zcrb-request" data-status="<?php echo esc_attr( $status ); ?>" data-search="<?php echo esc_attr( $search_haystack ); ?>">
                            <div class="zcrb-request-head">
                                <span class="zcrb-request-ref"><?php echo esc_html( $ref ); ?></span>
                                <span class="zcrb-request-status zcrb-request-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $lbl ); ?></span>
                                <span class="zcrb-request-date"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></span>
                                <?php if ( $upvotes > 0 ) : ?>
                                    <span class="zcrb-request-upvotes" title="<?php esc_attr_e( 'Upvotes', 'zymarg-community-board' ); ?>">
                                        <span class="dashicons dashicons-arrow-up-alt"></span>
                                        <?php echo esc_html( (string) $upvotes ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="zcrb-request-title"><?php echo esc_html( get_the_title( $post_id ) ?: __( '(no title)', 'zymarg-community-board' ) ); ?></div>
                            <div class="zcrb-request-body"><?php echo esc_html( $excerpt ); ?></div>
                            <div class="zcrb-request-meta">
                                <?php if ( $full_nm ) : ?>
                                    <span class="zcrb-request-submitter"><span class="dashicons dashicons-admin-users"></span><?php echo esc_html( $full_nm ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="zcrb-request-actions">
                                <?php if ( $view_url ) : ?>
                                    <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">
                                        <span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'View', 'zymarg-community-board' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $edit_url ) : ?>
                                    <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm" href="<?php echo esc_url( $edit_url ); ?>">
                                        <span class="dashicons dashicons-edit"></span><?php esc_html_e( 'Edit', 'zymarg-community-board' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $approve_url ) : ?>
                                    <a class="zcrb-btn zcrb-btn--sm" href="<?php echo esc_url( $approve_url ); ?>">
                                        <span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Approve', 'zymarg-community-board' ); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ( $reject_url ) : ?>
                                    <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm zcrb-btn--danger" href="<?php echo esc_url( $reject_url ); ?>">
                                        <span class="dashicons dashicons-dismiss"></span><?php esc_html_e( 'Reject', 'zymarg-community-board' ); ?>
                                    </a>
                                <?php endif; ?>
                                <a class="zcrb-btn zcrb-btn--ghost zcrb-btn--sm zcrb-btn--danger" href="<?php echo esc_url( $delete_url ); ?>"
                                   onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this request? This cannot be undone.', 'zymarg-community-board' ) ); ?>');">
                                    <span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Delete', 'zymarg-community-board' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

                <?php
                $total_pages = (int) $q->max_num_pages;
                if ( $total_pages > 1 ) :
                    $base = add_query_arg(
                        array(
                            'page'    => self::MENU_SLUG,
                            'section' => 'requests',
                        ),
                        admin_url( 'admin.php' )
                    );
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
