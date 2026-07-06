<?php
/**
 * Settings page + accessor.
 *
 * Single source of truth for every customizable knob: per-page count,
 * message limit, brand colors, typography (desktop + mobile font sizes),
 * form requirements, content overrides, notifications, data retention,
 * and GitHub auto-update repository.
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
        add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
    }

    /**
     * The list of font-size knobs we expose. Each one becomes a CSS variable
     * `--zcrb-fs-{key}` plus `--zcrb-fs-{key}-m` for the mobile breakpoint.
     *
     * @return array<string,array{label:string,desktop:int,mobile:int}>
     */
    public static function font_size_fields(): array {
        return array(
            'h1'           => array( 'label' => __( 'H1 / Page title',         'zymarg-community-board' ), 'desktop' => 48, 'mobile' => 28 ),
            'subtitle'     => array( 'label' => __( 'Hero subtitle',           'zymarg-community-board' ), 'desktop' => 18, 'mobile' => 16 ),
            'section-h'    => array( 'label' => __( 'Section heading (Recent Requests)', 'zymarg-community-board' ), 'desktop' => 24, 'mobile' => 20 ),
            'form-h'       => array( 'label' => __( 'Form heading (Submit a Request)', 'zymarg-community-board' ),   'desktop' => 20, 'mobile' => 18 ),
            'label'        => array( 'label' => __( 'Form labels',             'zymarg-community-board' ), 'desktop' => 14, 'mobile' => 14 ),
            'input'        => array( 'label' => __( 'Form input text',         'zymarg-community-board' ), 'desktop' => 16, 'mobile' => 16 ),
            'button'       => array( 'label' => __( 'Buttons',                 'zymarg-community-board' ), 'desktop' => 14, 'mobile' => 14 ),
            'card-author'  => array( 'label' => __( 'Card author name',        'zymarg-community-board' ), 'desktop' => 14, 'mobile' => 14 ),
            'card-date'    => array( 'label' => __( 'Card date badge',         'zymarg-community-board' ), 'desktop' => 10, 'mobile' => 10 ),
            'card-message' => array( 'label' => __( 'Card message',            'zymarg-community-board' ), 'desktop' => 16, 'mobile' => 14 ),
            'card-ref'     => array( 'label' => __( 'Card reference (Ref:)',   'zymarg-community-board' ), 'desktop' => 12, 'mobile' => 12 ),
            'card-link'    => array( 'label' => __( 'Card "View Details" link','zymarg-community-board' ), 'desktop' => 12, 'mobile' => 12 ),
            'pagination'   => array( 'label' => __( 'Pagination buttons',      'zymarg-community-board' ), 'desktop' => 14, 'mobile' => 14 ),
            'privacy'      => array( 'label' => __( 'Privacy notice',          'zymarg-community-board' ), 'desktop' => 14, 'mobile' => 13 ),
            'meta'         => array( 'label' => __( '"Showing X of Y" meta',   'zymarg-community-board' ), 'desktop' => 12, 'mobile' => 12 ),
        );
    }

    /**
     * Default values for every customizable option.
     */
    public static function defaults(): array {
        $defaults = array(
            // -------- General --------
            'per_page'             => 30,
            'message_limit'        => 200,
            'default_language'     => 'en',
            'rate_limit_per_hour'  => 5,

            // -------- Image upload --------
            'image_enabled'        => 1,
            'image_max_mb'         => 2,
            'image_max_count'      => 1,
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

            // -------- Branding (ZYMARG brand palette — single source of truth) --------
            'color_primary'        => '#9500a5',
            'color_primary_hover'  => '#bd00d1',
            'color_primary_50'     => '#fceaff',
            'color_primary_100'    => '#fea9ff',
            'color_purple_fixed'   => '#f8e8fa',
            'color_orb_1'          => '#9500a5',
            'color_orb_2'          => '#bd00d1',
            'color_orb_3'          => '#fea9ff',
            'color_text'           => '#131b2e',
            'color_muted'          => '#534152',
            'color_bg'             => '#ffffff',
            'color_surface'        => '#fcfaff',

            // -------- Typography --------
            'font_heading'         => 'Cabinet Grotesk',
            'font_body'            => 'Inter',
            'load_google_fonts'    => 0,

            // -------- Notifications --------
            'notify_email'                  => '',
            'notify_subject'                => '',
            'notify_submitter_on_approve'   => 1,
            'notify_submitter_on_response'  => 1,

            // -------- Data Retention (auto-delete) --------
            'data_retention_days'  => 0,

            // -------- GitHub Updates --------
            'enable_auto_updates'  => 1,
            'github_owner'         => 'Aspeyash',
            'github_repo'          => 'Community-board',
            'github_branch'        => 'main',
            'github_token'         => '',
        );

        // Inject every font-size default (desktop + mobile).
        foreach ( self::font_size_fields() as $key => $cfg ) {
            $defaults[ 'fs_' . $key . '_d' ] = (int) $cfg['desktop'];
            $defaults[ 'fs_' . $key . '_m' ] = (int) $cfg['mobile'];
        }

        return $defaults;
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

    public function get( string $key, $default = null ) {
        $all = $this->all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public function add_menu(): void {
        add_submenu_page(
            'zcrb-hub',
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

    public function sanitize( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = array();
        }
        $defaults = self::defaults();
        $clean    = array();

        $clean['per_page']            = max( 1, min( 200, (int) ( $input['per_page'] ?? $defaults['per_page'] ) ) );
        $clean['message_limit']       = max( 50, min( 2000, (int) ( $input['message_limit'] ?? $defaults['message_limit'] ) ) );
        $clean['image_max_mb']        = max( 1, min( 20, (int) ( $input['image_max_mb'] ?? $defaults['image_max_mb'] ) ) );
        $clean['image_max_count']     = max( 1, min( 4, (int) ( $input['image_max_count'] ?? $defaults['image_max_count'] ) ) );
        $clean['rate_limit_per_hour'] = max( 1, min( 50, (int) ( $input['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour'] ) ) );

        foreach ( array( 'image_enabled', 'phone_required', 'email_required', 'image_required', 'enable_auto_updates', 'load_google_fonts', 'notify_submitter_on_approve', 'notify_submitter_on_response' ) as $k ) {
            $clean[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
        }

        $lang = $input['default_language'] ?? 'en';
        $clean['default_language'] = in_array( $lang, array( 'en', 'bn' ), true ) ? $lang : 'en';

        foreach ( array(
            'page_title_en', 'page_subtitle_en', 'meta_description_en',
            'page_title_bn', 'page_subtitle_bn', 'meta_description_bn',
            'notify_subject', 'font_heading', 'font_body',
        ) as $k ) {
            $clean[ $k ] = sanitize_text_field( (string) ( $input[ $k ] ?? '' ) );
        }
        $clean['image_allowed_types'] = sanitize_text_field( (string) ( $input['image_allowed_types'] ?? $defaults['image_allowed_types'] ) );
        $clean['notify_email']        = sanitize_email( (string) ( $input['notify_email'] ?? '' ) );

        $retention = (int) ( $input['data_retention_days'] ?? 0 );
        $clean['data_retention_days'] = in_array( $retention, array( 0, 30, 60, 90 ), true ) ? $retention : 0;

        $clean['github_owner']  = sanitize_text_field( (string) ( $input['github_owner'] ?? $defaults['github_owner'] ) );
        $clean['github_repo']   = sanitize_text_field( (string) ( $input['github_repo'] ?? $defaults['github_repo'] ) );
        $clean['github_branch'] = sanitize_text_field( (string) ( $input['github_branch'] ?? $defaults['github_branch'] ) );
        $clean['github_token']  = sanitize_text_field( (string) ( $input['github_token'] ?? '' ) );

        // Colors.
        foreach ( array(
            'color_primary', 'color_primary_hover', 'color_primary_50', 'color_primary_100',
            'color_purple_fixed', 'color_orb_1', 'color_orb_2', 'color_orb_3',
            'color_text', 'color_muted', 'color_bg', 'color_surface',
        ) as $k ) {
            $val = sanitize_hex_color( (string) ( $input[ $k ] ?? '' ) );
            $clean[ $k ] = $val ? $val : $defaults[ $k ];
        }

        // Font sizes — clamp 8..120.
        foreach ( self::font_size_fields() as $key => $cfg ) {
            foreach ( array( 'd', 'm' ) as $bp ) {
                $opt = 'fs_' . $key . '_' . $bp;
                $val = isset( $input[ $opt ] ) ? (int) $input[ $opt ] : (int) $defaults[ $opt ];
                $clean[ $opt ] = max( 8, min( 120, $val ) );
            }
        }

        // Fall back the empty font-family to defaults.
        if ( '' === $clean['font_heading'] ) {
            $clean['font_heading'] = $defaults['font_heading'];
        }
        if ( '' === $clean['font_body'] ) {
            $clean['font_body'] = $defaults['font_body'];
        }

        $this->cache = null;
        return $clean;
    }

    public function enqueue_admin( string $hook ): void {
        if ( false === strpos( $hook, self::SETTINGS_SLUG ) ) {
            return;
        }
        wp_enqueue_style(
            'zcrb-admin',
            ZCRB_PLUGIN_URL . 'assets/css/zcrb-admin.css',
            array(),
            ZCRB_VERSION
        );
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
        $opt = self::OPTION_KEY;
        ?>
        <div class="wrap zcrb-settings">
            <?php ZCRB_Admin_Hub::render_branded_header(); ?>

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
                        <td><input id="zcrb_per_page" type="number" min="1" max="200" name="<?php echo esc_attr( $opt ); ?>[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_message_limit"><?php esc_html_e( 'Message character limit', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_message_limit" type="number" min="50" max="2000" name="<?php echo esc_attr( $opt ); ?>[message_limit]" value="<?php echo esc_attr( (string) $s['message_limit'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_default_language"><?php esc_html_e( 'Default language', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <select id="zcrb_default_language" name="<?php echo esc_attr( $opt ); ?>[default_language]">
                                <option value="en" <?php selected( $s['default_language'], 'en' ); ?>>English</option>
                                <option value="bn" <?php selected( $s['default_language'], 'bn' ); ?>>বাংলা (Bengali)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_rate_limit_per_hour"><?php esc_html_e( 'Submissions per user per hour', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_rate_limit_per_hour" type="number" min="1" max="50" name="<?php echo esc_attr( $opt ); ?>[rate_limit_per_hour]" value="<?php echo esc_attr( (string) $s['rate_limit_per_hour'] ); ?>" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Form & Image Upload', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Required fields', 'zymarg-community-board' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[phone_required]" value="1" <?php checked( $s['phone_required'], 1 ); ?> /> <?php esc_html_e( 'Phone number required', 'zymarg-community-board' ); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[email_required]" value="1" <?php checked( $s['email_required'], 1 ); ?> /> <?php esc_html_e( 'Email required', 'zymarg-community-board' ); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[image_required]" value="1" <?php checked( $s['image_required'], 1 ); ?> /> <?php esc_html_e( 'Image required', 'zymarg-community-board' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Image upload', 'zymarg-community-board' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[image_enabled]" value="1" <?php checked( $s['image_enabled'], 1 ); ?> /> <?php esc_html_e( 'Allow image uploads', 'zymarg-community-board' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_image_max_mb"><?php esc_html_e( 'Max image size (MB)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_image_max_mb" type="number" min="1" max="20" name="<?php echo esc_attr( $opt ); ?>[image_max_mb]" value="<?php echo esc_attr( (string) $s['image_max_mb'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_image_max_count"><?php esc_html_e( 'Max images per submission', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <select id="zcrb_image_max_count" name="<?php echo esc_attr( $opt ); ?>[image_max_count]">
                                <option value="1" <?php selected( (int) $s['image_max_count'], 1 ); ?>>1</option>
                                <option value="2" <?php selected( (int) $s['image_max_count'], 2 ); ?>>2</option>
                                <option value="3" <?php selected( (int) $s['image_max_count'], 3 ); ?>>3</option>
                                <option value="4" <?php selected( (int) $s['image_max_count'], 4 ); ?>>4</option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How many images a user can upload per request.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_image_allowed_types"><?php esc_html_e( 'Allowed image MIME types', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <input id="zcrb_image_allowed_types" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[image_allowed_types]" value="<?php echo esc_attr( $s['image_allowed_types'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Comma-separated MIME types. Defaults to JPG, PNG, and WEBP.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Page Content (overrides)', 'zymarg-community-board' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Leave any field blank to use the default text from the bilingual string table. Tip: include " — " (space-dash-space) in the H1 to split it onto two lines visually.', 'zymarg-community-board' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="zcrb_page_title_en"><?php esc_html_e( 'H1 / Page title (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_page_title_en" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[page_title_en]" value="<?php echo esc_attr( $s['page_title_en'] ); ?>" placeholder="Community Request Board — Tell Us What You Need" /></td></tr>
                    <tr><th scope="row"><label for="zcrb_page_subtitle_en"><?php esc_html_e( 'Subtitle (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_page_subtitle_en" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[page_subtitle_en]" value="<?php echo esc_attr( $s['page_subtitle_en'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="zcrb_meta_description_en"><?php esc_html_e( 'Meta description (EN)', 'zymarg-community-board' ); ?></label></th>
                        <td><textarea id="zcrb_meta_description_en" class="large-text" rows="2" name="<?php echo esc_attr( $opt ); ?>[meta_description_en]"><?php echo esc_textarea( $s['meta_description_en'] ); ?></textarea></td></tr>
                    <tr><th scope="row"><label for="zcrb_page_title_bn"><?php esc_html_e( 'H1 / Page title (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_page_title_bn" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[page_title_bn]" value="<?php echo esc_attr( $s['page_title_bn'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="zcrb_page_subtitle_bn"><?php esc_html_e( 'Subtitle (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_page_subtitle_bn" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[page_subtitle_bn]" value="<?php echo esc_attr( $s['page_subtitle_bn'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="zcrb_meta_description_bn"><?php esc_html_e( 'Meta description (BN)', 'zymarg-community-board' ); ?></label></th>
                        <td><textarea id="zcrb_meta_description_bn" class="large-text" rows="2" name="<?php echo esc_attr( $opt ); ?>[meta_description_bn]"><?php echo esc_textarea( $s['meta_description_bn'] ); ?></textarea></td></tr>
                </table>

                <h2><?php esc_html_e( 'Branding & Colors', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $color_fields = array(
                        'color_primary'        => __( 'Primary purple (Submit, links, hover states)', 'zymarg-community-board' ),
                        'color_primary_hover'  => __( 'Primary purple — button background', 'zymarg-community-board' ),
                        'color_primary_50'     => __( 'Soft purple tint (button hover bg)',  'zymarg-community-board' ),
                        'color_primary_100'    => __( 'Light purple (borders, accents)',     'zymarg-community-board' ),
                        'color_purple_fixed'   => __( 'Privacy notice background',           'zymarg-community-board' ),
                        'color_orb_1'          => __( 'Gradient orb 1 (top-left)',           'zymarg-community-board' ),
                        'color_orb_2'          => __( 'Gradient orb 2 (right)',              'zymarg-community-board' ),
                        'color_orb_3'          => __( 'Gradient orb 3 (bottom)',             'zymarg-community-board' ),
                        'color_text'           => __( 'Body text color',                     'zymarg-community-board' ),
                        'color_muted'          => __( 'Muted / secondary text',              'zymarg-community-board' ),
                        'color_bg'             => __( 'Page background',                     'zymarg-community-board' ),
                        'color_surface'        => __( 'Surface / wrapper background',        'zymarg-community-board' ),
                    );
                    foreach ( $color_fields as $key => $label ) :
                        ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr( "zcrb_$key" ); ?>"><?php echo esc_html( $label ); ?></label></th>
                            <td>
                                <input class="zcrb-color" id="<?php echo esc_attr( "zcrb_$key" ); ?>" type="text" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ $key ] ); ?>" data-default-color="<?php echo esc_attr( self::defaults()[ $key ] ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e( 'Typography (font sizes)', 'zymarg-community-board' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Set the font size (in pixels) for every text element on the public page. The "Mobile" column kicks in below 768px wide. Values are clamped between 8 and 120.', 'zymarg-community-board' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_font_heading"><?php esc_html_e( 'Heading font family', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_font_heading" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[font_heading]" value="<?php echo esc_attr( $s['font_heading'] ); ?>" placeholder="Sora" />
                            <p class="description"><?php esc_html_e( 'A Google Font name (Sora, Inter, Poppins, Manrope, ...) or any system font. Used for H1 + section headings.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_font_body"><?php esc_html_e( 'Body font family', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_font_body" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[font_body]" value="<?php echo esc_attr( $s['font_body'] ); ?>" placeholder="Inter" />
                            <p class="description"><?php esc_html_e( 'Used for paragraphs, labels, buttons, and card text.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Load Google Fonts', 'zymarg-community-board' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[load_google_fonts]" value="1" <?php checked( $s['load_google_fonts'], 1 ); ?> /> <?php esc_html_e( 'Auto-load the configured fonts from Google Fonts CDN', 'zymarg-community-board' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Disable if you self-host the fonts or prefer a system font.', 'zymarg-community-board' ); ?></p>
                        </td>
                    </tr>
                </table>

                <table class="form-table" role="presentation">
                    <thead>
                        <tr>
                            <th scope="col" style="width:38%;"><?php esc_html_e( 'Element', 'zymarg-community-board' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Desktop (px)', 'zymarg-community-board' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Mobile (px)', 'zymarg-community-board' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( self::font_size_fields() as $key => $cfg ) :
                            $d_name = 'fs_' . $key . '_d';
                            $m_name = 'fs_' . $key . '_m';
                            ?>
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr( "zcrb_$d_name" ); ?>"><?php echo esc_html( $cfg['label'] ); ?></label></th>
                                <td><input id="<?php echo esc_attr( "zcrb_$d_name" ); ?>" type="number" min="8" max="120" step="1" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $d_name ); ?>]" value="<?php echo esc_attr( (string) $s[ $d_name ] ); ?>" style="width:90px;" /> px</td>
                                <td><input id="<?php echo esc_attr( "zcrb_$m_name" ); ?>" type="number" min="8" max="120" step="1" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $m_name ); ?>]" value="<?php echo esc_attr( (string) $s[ $m_name ] ); ?>" style="width:90px;" /> px</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Notifications', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_notify_email"><?php esc_html_e( 'Notification email', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_notify_email" type="email" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_notify_subject"><?php esc_html_e( 'Notification subject', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_notify_subject" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[notify_subject]" value="<?php echo esc_attr( $s['notify_subject'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Submitter notifications', 'zymarg-community-board' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notify_submitter_on_approve]" value="1" <?php checked( $s['notify_submitter_on_approve'], 1 ); ?> /> <?php esc_html_e( 'Email submitter when their request is approved', 'zymarg-community-board' ); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notify_submitter_on_response]" value="1" <?php checked( $s['notify_submitter_on_response'], 1 ); ?> /> <?php esc_html_e( 'Email submitter when a vendor responds', 'zymarg-community-board' ); ?></label>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Privacy & Data Retention', 'zymarg-community-board' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Automatically delete every community request once it gets older than the chosen age. Deletion removes the post, all submitter info (Name, Phone, Email), and any uploaded image. The selected period is also displayed to visitors on the submission form so they know how long their data will be kept.', 'zymarg-community-board' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="zcrb_data_retention_days"><?php esc_html_e( 'Auto-delete requests after', 'zymarg-community-board' ); ?></label></th>
                        <td>
                            <select id="zcrb_data_retention_days" name="<?php echo esc_attr( $opt ); ?>[data_retention_days]">
                                <option value="0"  <?php selected( (int) $s['data_retention_days'], 0 ); ?>><?php esc_html_e( 'Disabled (never delete)', 'zymarg-community-board' ); ?></option>
                                <option value="30" <?php selected( (int) $s['data_retention_days'], 30 ); ?>><?php esc_html_e( '30 days', 'zymarg-community-board' ); ?></option>
                                <option value="60" <?php selected( (int) $s['data_retention_days'], 60 ); ?>><?php esc_html_e( '60 days', 'zymarg-community-board' ); ?></option>
                                <option value="90" <?php selected( (int) $s['data_retention_days'], 90 ); ?>><?php esc_html_e( '90 days', 'zymarg-community-board' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Run cleanup now', 'zymarg-community-board' ); ?></th>
                        <td><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'zcrb_action', 'run_cleanup' ), 'zcrb_run_cleanup' ) ); ?>"><?php esc_html_e( 'Delete expired requests now', 'zymarg-community-board' ); ?></a></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'GitHub Auto-Updates', 'zymarg-community-board' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable auto-updates', 'zymarg-community-board' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_auto_updates]" value="1" <?php checked( $s['enable_auto_updates'], 1 ); ?> /> <?php esc_html_e( 'Check GitHub for new releases', 'zymarg-community-board' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_owner"><?php esc_html_e( 'GitHub owner', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_github_owner" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[github_owner]" value="<?php echo esc_attr( $s['github_owner'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_repo"><?php esc_html_e( 'GitHub repository', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_github_repo" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[github_repo]" value="<?php echo esc_attr( $s['github_repo'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_branch"><?php esc_html_e( 'Branch (informational)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_github_branch" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[github_branch]" value="<?php echo esc_attr( $s['github_branch'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="zcrb_github_token"><?php esc_html_e( 'Personal access token (private repos only)', 'zymarg-community-board' ); ?></label></th>
                        <td><input id="zcrb_github_token" type="password" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[github_token]" value="<?php echo esc_attr( $s['github_token'] ); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Force re-check now', 'zymarg-community-board' ); ?></th>
                        <td><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'zcrb_action', 'check_updates' ), 'zcrb_check_updates' ) ); ?>"><?php esc_html_e( 'Check for updates', 'zymarg-community-board' ); ?></a></td>
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
     * Build the inline CSS that applies the configured colors AND every
     * configured font size (desktop + mobile breakpoint) to the front-end.
     */
    public function render_dynamic_css(): string {
        $s = $this->all();

        $orb1_a = self::hex_to_rgba( (string) $s['color_orb_1'], 0.10 );
        $orb1_b = self::hex_to_rgba( (string) $s['color_orb_1'], 0 );
        $orb2_a = self::hex_to_rgba( (string) $s['color_orb_2'], 0.10 );
        $orb2_b = self::hex_to_rgba( (string) $s['color_orb_2'], 0 );
        $orb3_a = self::hex_to_rgba( (string) $s['color_orb_3'], 0.10 );
        $orb3_b = self::hex_to_rgba( (string) $s['color_orb_3'], 0 );

        $heading = trim( (string) $s['font_heading'] ) ?: 'Sora';
        $body    = trim( (string) $s['font_body'] ) ?: 'Inter';

        // Build :root colors + font families + desktop sizes.
        $root  = ':root{';
        $root .= '--zcrb-purple:' . $s['color_primary'] . ';';
        $root .= '--zcrb-purple-600:' . $s['color_primary_hover'] . ';';
        $root .= '--zcrb-purple-50:' . $s['color_primary_50'] . ';';
        $root .= '--zcrb-purple-100:' . $s['color_primary_100'] . ';';
        $root .= '--zcrb-purple-fixed:' . $s['color_purple_fixed'] . ';';
        $root .= '--zcrb-text:' . $s['color_text'] . ';';
        $root .= '--zcrb-muted:' . $s['color_muted'] . ';';
        $root .= '--zcrb-bg:' . $s['color_bg'] . ';';
        $root .= '--zcrb-surface:' . $s['color_surface'] . ';';
        $root .= '--zcrb-font-heading:"' . str_replace( '"', '', $heading ) . '",-apple-system,BlinkMacSystemFont,"Segoe UI","Hind Siliguri","Noto Sans Bengali",Roboto,sans-serif;';
        $root .= '--zcrb-font-body:"' . str_replace( '"', '', $body ) . '",-apple-system,BlinkMacSystemFont,"Segoe UI","Hind Siliguri","Noto Sans Bengali",Roboto,sans-serif;';

        foreach ( self::font_size_fields() as $key => $cfg ) {
            $root .= '--zcrb-fs-' . $key . ':' . (int) $s[ 'fs_' . $key . '_d' ] . 'px;';
        }
        $root .= '}';

        // Mobile breakpoint overrides.
        $mobile = '@media (max-width:767px){:root{';
        foreach ( self::font_size_fields() as $key => $cfg ) {
            $mobile .= '--zcrb-fs-' . $key . ':' . (int) $s[ 'fs_' . $key . '_m' ] . 'px;';
        }
        $mobile .= '}}';

        // Orb gradients.
        $orbs  = '.zcrb-orb--1{background:radial-gradient(circle,' . $orb1_a . ' 0%,' . $orb1_b . ' 70%);}';
        $orbs .= '.zcrb-orb--2{background:radial-gradient(circle,' . $orb2_a . ' 0%,' . $orb2_b . ' 70%);}';
        $orbs .= '.zcrb-orb--3{background:radial-gradient(circle,' . $orb3_a . ' 0%,' . $orb3_b . ' 70%);}';

        return $root . $mobile . $orbs;
    }

    /**
     * URL of the Google Fonts stylesheet for the configured heading + body
     * fonts. Returns empty string if the user disabled Google Fonts loading.
     */
    public function google_fonts_url(): string {
        $s = $this->all();
        if ( empty( $s['load_google_fonts'] ) ) {
            return '';
        }
        $heading = trim( (string) $s['font_heading'] );
        $body    = trim( (string) $s['font_body'] );

        $families = array();
        if ( '' !== $heading ) {
            $families[] = str_replace( ' ', '+', $heading ) . ':wght@400;600;700';
        }
        if ( '' !== $body && strcasecmp( $body, $heading ) !== 0 ) {
            $families[] = str_replace( ' ', '+', $body ) . ':wght@400;600';
        }

        if ( empty( $families ) ) {
            return '';
        }

        $query = 'family=' . implode( '&family=', $families ) . '&display=swap';
        return 'https://fonts.googleapis.com/css2?' . $query;
    }
}
