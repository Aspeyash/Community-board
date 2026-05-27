<?php
/**
 * Settings page + accessor.
 *
 * Single source of truth for every customizable knob: per-page count,
 * message limit, brand colors, form requirements, content overrides,
 * notifications, and GitHub auto-update repository.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Settings {

    const OPTION_KEY    = 'zcrb_settings';
    const SETTINGS_SLUG = 'zcrb-settings';

    /** @var ZCRB_Settings|null */
    private static $instance = null;

    /** @var array<string,mixed>|null */
    private $cache = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
    }

    /**
     * Default values for every customizable option.
     */
    public static function defaults(): array {
        return array(
            // -------- General --------
            'per_page'             => 30,
            'message_limit'        => 200,
            'default_language'     => 'en',
            'rate_limit_per_hour'  => 5,

            // -------- Image upload --------
            'image_enabled'        => 1,
            'image_max_mb'         => 2,
            'image_allowed_types'  => 'image/jpeg,image/png,image/webp',

            // -------- Form requirements --------
            'phone_required'       => 1,
            'email_required'       => 1,
            'image_required'       => 0,

            // -------- Content overrides (blank => use built-in i18n strings) --------
            'page_title_en'        => '',
            'page_subtitle_en'     => '',
            'meta_description_en'  => '',
            'page_title_bn'        => '',
            'page_subtitle_bn'     => '',
            'meta_description_bn'  => '',

            // -------- Branding --------
            'color_primary'        => '#6b3fa0',
            'color_primary_hover'  => '#5a2f8e',
            'color_primary_50'     => '#f7f2ff',
            'color_primary_100'    => '#efe7fb',
            'color_orb_1'          => '#8d5cd9',
            'color_orb_2'          => '#ba98f0',
            'color_orb_3'          => '#d8c4f7',
            'color_text'           => '#1f1b2d',
            'color_muted'          => '#6b6577',
            'color_bg'             => '#ffffff',

            // -------- Notifications --------
            'notify_email'         => '', // blank = admin_email
            'notify_subject'       => '', // blank = default

            // -------- GitHub Updates --------
            'enable_auto_updates'  => 1,
            'github_owner'         => 'Aspeyash',
            'github_repo'          => 'Community-page',
            'github_branch'        => 'main',
            'github_token'         => '', // optional, only needed for private repos
        );
    }

    public function all(): array {
        if ( null !== $this->cache ) {
            return $this->cache;
        }
        $stored = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        $this->cache = array_merge( self::defaults(), $stored );
        return $this->cache;
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $all = $this->all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public function add_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . ZCRB_POST_TYPE,
            __( 'Community Board Settings', 'zymarg-community-board' ),
            __( 'Settings', 'zymarg-community-board' ),
            'manage_options',
            self::SETTINGS_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function register(): void {
        register_setting(
            'zcrb_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => self::defaults(),
            )
        );
    }

    /**
     * Sanitize the entire option array.
     *
     * @param mixed $input
     * @return array
     */
    public function sanitize( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = array();
        }
        $defaults = self::defaults();
        $clean    = array();

        // Numeric (with min/max bounds).
        $clean['per_page']            = max( 1, min( 200, (int) ( $input['per_page'] ?? $defaults['per_page'] ) ) );
        $clean['message_limit']       = max( 50, min( 2000, (int) ( $input['message_limit'] ?? $defaults['message_limit'] ) ) );
        $clean['image_max_mb']        = max( 1, min( 20, (int) ( $input['image_max_mb'] ?? $defaults['image_max_mb'] ) ) );
        $clean['rate_limit_per_hour'] = max( 1, min( 50, (int) ( $input['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour'] ) ) );

        // Booleans.
        foreach ( array( 'image_enabled', 'phone_required', 'email_required', 'image_required', 'enable_auto_updates' ) as $k ) {
            $clean[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
        }

        // Default language.
        $lang = $input['default_language'] ?? 'en';
        $clean['default_language'] = in_array( $lang, array( 'en', 'bn' ), true ) ? $lang : 'en';

        // Free-text fields.
        foreach ( array(
            'page_title_en', 'page_subtitle_en', 'meta_description_en',
            'page_title_bn', 'page_subtitle_bn', 'meta_description_bn',
            'notify_subject',
        ) as $k ) {
            $clean[ $k ] = sanitize_text_field( (string) ( $input[ $k ] ?? '' ) );
        }
        $clean['image_allowed_types'] = sanitize_text_field( (string) ( $input['image_allowed_types'] ?? $defaults['image_allowed_types'] ) );
        $clean['notify_email']        = sanitize_email( (string) ( $input['notify_email'] ?? '' ) );

        // GitHub.
        $clean['github_owner']  = sanitize_text_field( (string) ( $input['github_owner'] ?? $defaults['github_owner'] ) );
        $clean['github_repo']   = sanitize_text_field( (string) ( $input['github_repo'] ?? $defaults['github_repo'] ) );
        $clean['github_branch'] = sanitize_text_field( (string) ( $input['github_branch'] ?? $defaults['github_branch'] ) );
        $clean['github_token']  = sanitize_text_field( (string) ( $input['github_token'] ?? '' ) );

        // Colors.
        foreach ( array(
            'color_primary', 'color_primary_hover', 'color_primary_50', 'color_primary_100',
            'color_orb_1', 'color_orb_2', 'color_orb_3',
            'color_text', 'color_muted', 'color_bg',
        ) as $k ) {
            $val = sanitize_hex_color( (string) ( $input[ $k ] ?? '' ) );
            $clean[ $k ] = $val ? $val : $defaults[ $k ];
        }

        // Bust local cache.
        $this->cache = null;

        return $clean;
    }

    public function enqueue_admin( string $hook ): void {
        if ( false === strpos( $hook, self::SETTINGS_SLUG ) ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(function($){ $(".zcrb-color").wpColorPicker(); });'
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'zymarg-community-board' ) );
        }
        $s = $this->all();
        ?>
        <div class="wrap zcrb-settings">
            <h1><?php esc_html_e( 'ZYMARG Community Board — Settings', 'zymarg-community-board' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Configure every aspect of the Community Request Board. Defaults are sensible — leave a field blank to keep the built-in value.', 'zymarg-community-board' ); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( 'zcrb_settings_group' ); ?>

                <h2><?php esc_html_e( 'General', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_per_page"><?php esc_html_e( 'Requests per page', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_per_page" type="number" min="1" max="200" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Number of approved requests shown per page on the public board.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_message_limit"><?php esc_html_e( 'Message character limit', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_message_limit" type="number" min="50" max="2000" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[message_limit]" value="<?php echo esc_attr( (string) $s['message_limit'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_default_language"><?php esc_html_e( 'Default language', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <select id="zcrb_default_language" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_language]">
                                <option value="en" <?php selected( $s['default_language'], 'en' ); ?>>English</option>
                                <option value="bn" <?php selected( $s['default_language'], 'bn' ); ?>>বাংলা (Bengali)</option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Used when no ?lang= query is set, no cookie exists, and the WP locale is neither English nor Bengali.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_rate_limit_per_hour"><?php esc_html_e( 'Submissions per user per hour', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_rate_limit_per_hour" type="number" min="1" max="50" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_hour]" value="<?php echo esc_attr( (string) $s['rate_limit_per_hour'] ); ?>" />
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Form & Image Upload', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Required fields', 'zymarg-community-board' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[phone_required]" value="1" <?php checked( $s['phone_required'], 1 ); ?> /> <?php esc_html_e( 'Phone number required', 'zymarg-community-board' ); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_required]" value="1" <?php checked( $s['email_required'], 1 ); ?> /> <?php esc_html_e( 'Email required', 'zymarg-community-board' ); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_required]" value="1" <?php checked( $s['image_required'], 1 ); ?> /> <?php esc_html_e( 'Image required', 'zymarg-community-board' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Full Name and Request Message are always required.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Image upload', 'zymarg-community-board' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_enabled]" value="1" <?php checked( $s['image_enabled'], 1 ); ?> /> <?php esc_html_e( 'Allow image uploads', 'zymarg-community-board' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_image_max_mb"><?php esc_html_e( 'Max image size (MB)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_image_max_mb" type="number" min="1" max="20" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_max_mb]" value="<?php echo esc_attr( (string) $s['image_max_mb'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_image_allowed_types"><?php esc_html_e( 'Allowed image MIME types', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_image_allowed_types" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_allowed_types]" value="<?php echo esc_attr( $s['image_allowed_types'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Comma-separated MIME types. Defaults to JPG, PNG, and WEBP.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Page Content (overrides)', 'zymarg-community-board' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Leave any field blank to use the default text from the bilingual string table.', 'zymarg-community-board' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_page_title_en"><?php esc_html_e( 'H1 / Page title (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_page_title_en" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[page_title_en]" value="<?php echo esc_attr( $s['page_title_en'] ); ?>" placeholder="Community Request Board — Tell Us What You Need" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_page_subtitle_en"><?php esc_html_e( 'Subtitle (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_page_subtitle_en" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[page_subtitle_en]" value="<?php echo esc_attr( $s['page_subtitle_en'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_meta_description_en"><?php esc_html_e( 'Meta description (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <textarea id="zcrb_meta_description_en" class="large-text" rows="2" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[meta_description_en]"><?php echo esc_textarea( $s['meta_description_en'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_page_title_bn"><?php esc_html_e( 'H1 / Page title (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_page_title_bn" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[page_title_bn]" value="<?php echo esc_attr( $s['page_title_bn'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_page_subtitle_bn"><?php esc_html_e( 'Subtitle (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_page_subtitle_bn" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[page_subtitle_bn]" value="<?php echo esc_attr( $s['page_subtitle_bn'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_meta_description_bn"><?php esc_html_e( 'Meta description (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <textarea id="zcrb_meta_description_bn" class="large-text" rows="2" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[meta_description_bn]"><?php echo esc_textarea( $s['meta_description_bn'] ); ?></textarea>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Branding & Colors', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $color_fields = array(
                        'color_primary'       => __( 'Primary purple (Submit, active page, links)', 'zymarg-community-board' ),
                        'color_primary_hover' => __( 'Primary purple (hover/active state)', 'zymarg-community-board' ),
                        'color_primary_50'    => __( 'Soft purple tint (button hover backgrounds)', 'zymarg-community-board' ),
                        'color_primary_100'   => __( 'Light purple (borders, accents)', 'zymarg-community-board' ),
                        'color_orb_1'         => __( 'Gradient orb 1 (top-left)', 'zymarg-community-board' ),
                        'color_orb_2'         => __( 'Gradient orb 2 (right)', 'zymarg-community-board' ),
                        'color_orb_3'         => __( 'Gradient orb 3 (bottom)', 'zymarg-community-board' ),
                        'color_text'          => __( 'Body text color', 'zymarg-community-board' ),
                        'color_muted'         => __( 'Muted / secondary text', 'zymarg-community-board' ),
                        'color_bg'            => __( 'Page base background', 'zymarg-community-board' ),
                    );
                    foreach ( $color_fields as $key => $label ) :
                        ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr( "zcrb_$key" ); ?>"><?php echo esc_html( $label ); ?></label></th>
                            <td>
                                <input class="zcrb-color"
                                       id="<?php echo esc_attr( "zcrb_$key" ); ?>"
                                       type="text"
                                       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( $s[ $key ] ); ?>"
                                       data-default-color="<?php echo esc_attr( self::defaults()[ $key ] ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e( 'Notifications', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_notify_email"><?php esc_html_e( 'Notification email', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_notify_email" type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
                            <p class="description"><?php esc_html_e( 'Where new submissions are sent for moderation. Defaults to the WordPress admin email.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_notify_subject"><?php esc_html_e( 'Notification subject', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_notify_subject" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notify_subject]" value="<?php echo esc_attr( $s['notify_subject'] ); ?>" placeholder="<?php esc_attr_e( '[Site] New community request awaiting approval', 'zymarg-community-board' ); ?>" />
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'GitHub Auto-Updates', 'zymarg-community-board' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'When a new release is published on GitHub (e.g. v1.3.0), this plugin will offer it as an update on the WordPress Plugins screen — just like a wordpress.org plugin.', 'zymarg-community-board' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable auto-updates', 'zymarg-community-board' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_auto_updates]" value="1" <?php checked( $s['enable_auto_updates'], 1 ); ?> /> <?php esc_html_e( 'Check GitHub for new releases', 'zymarg-community-board' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_owner"><?php esc_html_e( 'GitHub owner', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_github_owner" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[github_owner]" value="<?php echo esc_attr( $s['github_owner'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_repo"><?php esc_html_e( 'GitHub repository', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_github_repo" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[github_repo]" value="<?php echo esc_attr( $s['github_repo'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_branch"><?php esc_html_e( 'Branch (informational)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_github_branch" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[github_branch]" value="<?php echo esc_attr( $s['github_branch'] ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_token"><?php esc_html_e( 'Personal access token (private repos only)', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_github_token" type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[github_token]" value="<?php echo esc_attr( $s['github_token'] ); ?>" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'Leave blank for public repositories.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Force re-check now', 'zymarg-community-board' ); ?></th>
                        <td>
                            <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'zcrb_action', 'check_updates' ), 'zcrb_check_updates' ) ); ?>"><?php esc_html_e( 'Check for updates', 'zymarg-community-board' ); ?></a>
                            <p class="description"><?php esc_html_e( 'Clears the GitHub cache and re-queries the latest release.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Hex → rgba helper used to color the gradient orbs from settings.
     */
    public static function hex_to_rgba( string $hex, float $alpha ): string {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $r = hexdec( $hex[0] . $hex[0] );
            $g = hexdec( $hex[1] . $hex[1] );
            $b = hexdec( $hex[2] . $hex[2] );
        } elseif ( 6 === strlen( $hex ) ) {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        } else {
            $r = $g = $b = 0;
        }
        $alpha = max( 0, min( 1, $alpha ) );
        return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0' );
    }

    /**
     * Build the inline CSS that applies the configured colors to the front-end.
     */
    public function render_dynamic_css(): string {
        $s = $this->all();

        $orb1_a = self::hex_to_rgba( (string) $s['color_orb_1'], 0.55 );
        $orb1_b = self::hex_to_rgba( (string) $s['color_orb_1'], 0 );
        $orb2_a = self::hex_to_rgba( (string) $s['color_orb_2'], 0.55 );
        $orb2_b = self::hex_to_rgba( (string) $s['color_orb_2'], 0 );
        $orb3_a = self::hex_to_rgba( (string) $s['color_orb_3'], 0.5 );
        $orb3_b = self::hex_to_rgba( (string) $s['color_orb_3'], 0 );

        return sprintf(
            ':root{--zcrb-purple:%s;--zcrb-purple-600:%s;--zcrb-purple-50:%s;--zcrb-purple-100:%s;--zcrb-text:%s;--zcrb-muted:%s;--zcrb-bg:%s;}'
            . '.zcrb-orb--1{background:radial-gradient(circle,%s 0%%,%s 70%%);}'
            . '.zcrb-orb--2{background:radial-gradient(circle,%s 0%%,%s 70%%);}'
            . '.zcrb-orb--3{background:radial-gradient(circle,%s 0%%,%s 70%%);}',
            $s['color_primary'],
            $s['color_primary_hover'],
            $s['color_primary_50'],
            $s['color_primary_100'],
            $s['color_text'],
            $s['color_muted'],
            $s['color_bg'],
            $orb1_a, $orb1_b,
            $orb2_a, $orb2_b,
            $orb3_a, $orb3_b
        );
    }
}
