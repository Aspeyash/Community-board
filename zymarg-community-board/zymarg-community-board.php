<?php
/**
 * Plugin Name:       ZYMARG Community Request Board
 * Plugin URI:        https://zymarg.com/community/
 * Description:       SEO-optimized Community Request Board for ZYMARG. Logged-in users submit requests (Name, Phone, Email, Message, Image). Admin approves/rejects from the WP dashboard. Public feed shows only Name, Message, Date, and Image. Bilingual (English/Bengali), schema-marked, mobile responsive, infinite scroll with crawlable paginated URLs. Compatible with Astra, Elementor Pro, WooCommerce, and Dokan.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ZYMARG
 * Author URI:        https://zymarg.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zymarg-community-board
 * Domain Path:       /languages
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
define( 'ZCRB_VERSION', '1.0.0' );
define( 'ZCRB_PLUGIN_FILE', __FILE__ );
define( 'ZCRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZCRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZCRB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

define( 'ZCRB_POST_TYPE', 'zcrb_request' );
define( 'ZCRB_ARCHIVE_SLUG', 'community' );
define( 'ZCRB_PER_PAGE', 12 );           // cards rendered per page
define( 'ZCRB_INFINITE_THRESHOLD', 50 );  // switch to infinite scroll above this count
define( 'ZCRB_MESSAGE_LIMIT', 200 );      // request message hard cap

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-i18n.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-cpt.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-form.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-ajax.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-admin.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-shortcode.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-seo.php';
require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-template.php';

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain(
        'zymarg-community-board',
        false,
        dirname( ZCRB_PLUGIN_BASENAME ) . '/languages'
    );

    ZCRB_I18n::instance();
    ZCRB_CPT::instance();
    ZCRB_Form::instance();
    ZCRB_Ajax::instance();
    ZCRB_Admin::instance();
    ZCRB_Shortcode::instance();
    ZCRB_SEO::instance();
    ZCRB_Template::instance();
} );

// -----------------------------------------------------------------------------
// Activation / Deactivation
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, static function () {
    require_once ZCRB_PLUGIN_DIR . 'includes/class-zcrb-cpt.php';
    ZCRB_CPT::register_post_type();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    flush_rewrite_rules();
} );

// -----------------------------------------------------------------------------
// Front-end asset enqueue
// -----------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', static function () {
    // Only load on the board archive, single, or any page that contains the shortcode.
    $load = is_post_type_archive( ZCRB_POST_TYPE )
        || is_singular( ZCRB_POST_TYPE )
        || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_the_ID() ), 'zymarg_community_board' ) );

    /**
     * Allow other code (Elementor templates, theme builder) to force-load assets.
     *
     * @param bool $load
     */
    $load = (bool) apply_filters( 'zcrb_enqueue_assets', $load );

    if ( ! $load ) {
        return;
    }

    wp_enqueue_style(
        'zcrb-frontend',
        ZCRB_PLUGIN_URL . 'assets/css/zcrb.css',
        array(),
        ZCRB_VERSION
    );

    wp_enqueue_script(
        'zcrb-frontend',
        ZCRB_PLUGIN_URL . 'assets/js/zcrb.js',
        array(),
        ZCRB_VERSION,
        true
    );

    wp_localize_script( 'zcrb-frontend', 'ZCRB', array(
        'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
        'nonce'             => wp_create_nonce( 'zcrb_nonce' ),
        'archiveUrl'        => get_post_type_archive_link( ZCRB_POST_TYPE ),
        'isLoggedIn'        => is_user_logged_in(),
        'loginUrl'          => wp_login_url( get_permalink() ),
        'messageLimit'      => ZCRB_MESSAGE_LIMIT,
        'infiniteThreshold' => ZCRB_INFINITE_THRESHOLD,
        'lang'              => ZCRB_I18n::current_lang(),
        'i18n'              => array(
            'submitting'      => ZCRB_I18n::t( 'submitting' ),
            'submitSuccess'   => ZCRB_I18n::t( 'submit_success' ),
            'submitError'     => ZCRB_I18n::t( 'submit_error' ),
            'loadingMore'     => ZCRB_I18n::t( 'loading_more' ),
            'noMore'          => ZCRB_I18n::t( 'no_more' ),
            'mustLogin'       => ZCRB_I18n::t( 'must_login' ),
            'charsRemaining'  => ZCRB_I18n::t( 'chars_remaining' ),
            'fileTooLarge'    => ZCRB_I18n::t( 'file_too_large' ),
            'invalidImage'    => ZCRB_I18n::t( 'invalid_image' ),
        ),
    ) );
}, 20 );
