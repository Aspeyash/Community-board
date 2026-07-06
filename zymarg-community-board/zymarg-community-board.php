<?php
/**
 * Plugin Name:       ZYMARG Community Request Board
 * Plugin URI:        https://zymarg.com/community/
 * Description:       SEO-optimized Community Request Board for ZYMARG. Logged-in users submit requests (Name, Phone, Email, Message, Image). Admin approves/rejects from the WP dashboard. Public feed shows only Name, Message, Date, and Image. Bilingual (English/Bengali), schema-marked, mobile responsive, with a Material 3 inspired glass-card design, fully customizable typography (per-element font sizes for desktop + mobile), numbered-pagination crawlable feed, and configurable data retention. Updates ship via GitHub Releases. Compatible with Astra, Elementor Pro, WooCommerce, and Dokan.
 * Version:           2.3.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ZYMARG
 * Author URI:        https://zymarg.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zymarg-community-board
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/Aspeyash/Community-board
 * GitHub Branch:     main
 * Update URI:        https://github.com/Aspeyash/Community-board
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------

// Read the version straight from the "Version:" header above, so there is a
// SINGLE source of truth. Bumping the header is enough — every consumer of
// ZCRB_VERSION (asset cache-buster, updater, etc.) stays in sync automatically.
if ( ! defined( 'ZCRB_VERSION' ) ) {
    $zcrb_plugin_data = function_exists( 'get_file_data' )
        ? get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' )
        : array( 'Version' => '' );
    define( 'ZCRB_VERSION', ! empty( $zcrb_plugin_data['Version'] ) ? $zcrb_plugin_data['Version'] : '0.0.0' );
}
define( 'ZCRB_PLUGIN_FILE', __FILE__ );
define( 'ZCRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZCRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZCRB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

define( 'ZCRB_POST_TYPE', 'zcrb_request' );
define( 'ZCRB_ARCHIVE_SLUG', 'community' );
// Runtime fallbacks. The Settings page is the source of truth.
define( 'ZCRB_PER_PAGE', 30 );
define( 'ZCRB_MESSAGE_LIMIT', 200 );

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-settings.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-updater.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-retention.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-i18n.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-cpt.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-form.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-ajax.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-admin.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-admin-hub.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-shortcode.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-seo.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-template.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-status.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-vendor-response.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-upvote.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-notify.php';

/**
 * Convenience accessor — read any setting from anywhere.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
if ( ! function_exists( 'zcrb_get_setting' ) ) {
    function zcrb_get_setting( string $key, $default = null ) {
        return ZCRB_Settings::instance()->get( $key, $default );
    }
}

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain(
        'zymarg-community-board',
        false,
        dirname( ZCRB_PLUGIN_BASENAME ) . '/languages'
    );

    ZCRB_Settings::instance();
    ZCRB_I18n::instance();
    ZCRB_CPT::instance();
    ZCRB_Form::instance();
    ZCRB_Ajax::instance();
    ZCRB_Admin::instance();
    ZCRB_Admin_Hub::instance();
    ZCRB_Shortcode::instance();
    ZCRB_SEO::instance();
    ZCRB_Template::instance();
    ZCRB_Retention::instance();
    ZCRB_Status::instance();
    ZCRB_Vendor_Response::instance();
    ZCRB_Upvote::instance();
    ZCRB_Notify::instance();

    if ( is_admin() && zcrb_get_setting( 'enable_auto_updates', 1 ) ) {
        new ZCRB_Updater( array(
            'owner'       => (string) zcrb_get_setting( 'github_owner', 'Aspeyash' ),
            'repo'        => (string) zcrb_get_setting( 'github_repo', 'Community-board' ),
            'plugin_file' => ZCRB_PLUGIN_BASENAME,
            'version'     => ZCRB_VERSION,
            'token'       => (string) zcrb_get_setting( 'github_token', '' ),
        ) );
    }
} );

add_action( 'pre_get_posts', static function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->is_post_type_archive( ZCRB_POST_TYPE ) ) {
        $query->set( 'posts_per_page', (int) zcrb_get_setting( 'per_page', ZCRB_PER_PAGE ) );
        $query->set( 'post_status', array( 'publish', 'zcrb_in_progress', 'zcrb_fulfilled' ) );
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
} );

// -----------------------------------------------------------------------------
// Activation / Deactivation
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, static function () {
    require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-cpt.php';
    require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-retention.php';
    ZCRB_CPT::register_post_type();
    ZCRB_Retention::activate();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-retention.php';
    ZCRB_Retention::deactivate();
    flush_rewrite_rules();
} );

// -----------------------------------------------------------------------------
// Front-end asset enqueue
// -----------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', static function () {
    $load = is_post_type_archive( ZCRB_POST_TYPE )
        || is_singular( ZCRB_POST_TYPE )
        || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'zymarg_community_board' ) )
        || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'zymarg_community_form' ) );

    /**
     * Allow other code (Elementor templates, theme builder) to force-load assets.
     *
     * @param bool $load
     */
    $load = (bool) apply_filters( 'zcrb_enqueue_assets', $load );

    if ( ! $load ) {
        return;
    }

    // Optionally enqueue Google Fonts (Sora + Inter by default).
    $gfonts_url = ZCRB_Settings::instance()->google_fonts_url();
    if ( $gfonts_url ) {
        wp_enqueue_style( 'zcrb-fonts', $gfonts_url, array(), null );
    }

    wp_enqueue_style(
        'zcrb-frontend',
        ZCRB_PLUGIN_URL . 'assets/css/zcrb.css',
        array(),
        ZCRB_VERSION
    );

    // Inject the Settings-driven brand colors + font sizes as CSS variables.
    $inline = ZCRB_Settings::instance()->render_dynamic_css();
    if ( $inline ) {
        wp_add_inline_style( 'zcrb-frontend', $inline );
    }

    wp_enqueue_script(
        'zcrb-frontend',
        ZCRB_PLUGIN_URL . 'assets/js/zcrb.js',
        array(),
        ZCRB_VERSION,
        true
    );

    wp_localize_script( 'zcrb-frontend', 'ZCRB', array(
        'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'zcrb_nonce' ),
        'upvoteNonce'      => wp_create_nonce( 'zcrb_upvote_nonce' ),
        'vendorNonce'      => wp_create_nonce( 'zcrb_vendor_respond_nonce' ),
        'archiveUrl'       => get_post_type_archive_link( ZCRB_POST_TYPE ),
        'isLoggedIn'       => is_user_logged_in(),
        'canVendorRespond' => current_user_can( 'edit_others_posts' ),
        'loginUrl'         => wp_login_url( get_permalink() ),
        'messageLimit'     => (int) zcrb_get_setting( 'message_limit', ZCRB_MESSAGE_LIMIT ),
        'imageMaxMb'       => (int) zcrb_get_setting( 'image_max_mb', 2 ),
        'imageMaxCount'    => (int) zcrb_get_setting( 'image_max_count', 1 ),
        'imageTypes'       => array_filter( array_map( 'trim', explode( ',', (string) zcrb_get_setting( 'image_allowed_types', 'image/jpeg,image/png,image/webp' ) ) ) ),
        'lang'             => ZCRB_I18n::current_lang(),
        'i18n'             => array(
            'submitting'        => ZCRB_I18n::t( 'submitting' ),
            'submitSuccess'     => ZCRB_I18n::t( 'submit_success' ),
            'submitError'       => ZCRB_I18n::t( 'submit_error' ),
            'mustLogin'         => ZCRB_I18n::t( 'must_login' ),
            'charsRemaining'    => ZCRB_I18n::t( 'chars_remaining' ),
            'fileTooLarge'      => ZCRB_I18n::t( 'file_too_large' ),
            'invalidImage'      => ZCRB_I18n::t( 'invalid_image' ),
            'tooManyImages'     => ZCRB_I18n::t( 'too_many_images' ),
            'responseSubmitted' => ZCRB_I18n::t( 'response_submitted' ),
            'upvote'            => ZCRB_I18n::t( 'upvote' ),
            'upvoted'           => ZCRB_I18n::t( 'upvoted' ),
        ),
    ) );
}, 20 );

// -----------------------------------------------------------------------------
// Admin notices
// -----------------------------------------------------------------------------
add_action( 'admin_notices', static function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( empty( $_GET['zcrb_msg'] ) ) {
        return;
    }
    $msg = sanitize_key( wp_unslash( $_GET['zcrb_msg'] ) );

    if ( 'updates_checked' === $msg ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Update cache cleared. Re-checking GitHub for new releases…', 'zymarg-community-board' ) . '</p></div>';
    } elseif ( 'cleanup_done' === $msg ) {
        $deleted = isset( $_GET['zcrb_deleted'] ) ? (int) $_GET['zcrb_deleted'] : 0;
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html( sprintf(
                /* translators: %d: number of requests removed */
                _n( 'Cleanup complete. %d expired request was deleted.', 'Cleanup complete. %d expired requests were deleted.', $deleted, 'zymarg-community-board' ),
                $deleted
            ) )
            . '</p></div>';
    } elseif ( 'bulk_done' === $msg ) {
        $done = isset( $_GET['zcrb_done'] ) ? (int) $_GET['zcrb_done'] : 0;
        $op   = isset( $_GET['zcrb_op'] ) ? sanitize_key( wp_unslash( $_GET['zcrb_op'] ) ) : 'processed';

        // Map internal action keys to a human-readable past-tense verb.
        $op_labels = array(
            'approve' => __( 'approved', 'zymarg-community-board' ),
            'reject'  => __( 'rejected', 'zymarg-community-board' ),
            'trash'   => __( 'moved to Trash', 'zymarg-community-board' ),
            'delete'  => __( 'permanently deleted', 'zymarg-community-board' ),
            'restore' => __( 'restored', 'zymarg-community-board' ),
        );
        $op_display = isset( $op_labels[ $op ] ) ? $op_labels[ $op ] : $op;

        if ( $done > 0 ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf(
                /* translators: 1: number of requests, 2: past-tense verb (approved, rejected, ...) */
                _n( '%1$d request %2$s.', '%1$d requests %2$s.', $done, 'zymarg-community-board' ),
                $done,
                $op_display
            ) ) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No requests were changed. Select at least one row and choose a bulk action.', 'zymarg-community-board' ) . '</p></div>';
        }
    }
} );
