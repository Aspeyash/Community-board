<?php
/**
 * Bilingual (English / Bengali) string registry and language switcher.
 *
 * Settings page values (`page_title_en`, `page_subtitle_en`,
 * `meta_description_en`, `page_title_bn`, `page_subtitle_bn`,
 * `meta_description_bn`) override the corresponding default strings when
 * non-empty.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_I18n {

    /** @var ZCRB_I18n|null */
    private static $instance = null;

    /** @var string */
    private static $lang = '';

    /** @var array<string,array<string,string>> */
    private static $strings = array();

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        self::$strings = self::default_strings();

        add_action( 'init', array( $this, 'detect_language' ), 1 );
        add_action( 'wp_head', array( $this, 'output_html_lang' ), 1 );
    }

    public static function default_strings(): array {
        return array(
            'en' => array(
                'page_title'        => 'Community Request Board — Tell Us What You Need',
                'page_subtitle'     => 'Tell the ZYMARG community what you are looking for. Sellers across Bangladesh will see your request and respond.',
                'meta_description'  => 'ZYMARG Community Request Board — share what you want to buy and let trusted Bangladeshi vendors respond. Bengali and English supported.',
                'submit_request'    => 'Submit a Request',
                'full_name'         => 'Full Name',
                'phone_number'      => 'Phone Number',
                'email_address'     => 'Email Address',
                'request_message'   => 'Request Message',
                'image_optional'    => 'Image (optional)',
                'image_required'    => 'Image',
                'submit'            => 'Submit Request',
                'must_login'        => 'Please log in to submit a request.',
                'login_now'         => 'Log in',
                'register'          => 'Register',
                'submitting'        => 'Submitting…',
                'submit_success'    => 'Thanks! Your request has been received and will appear after admin approval.',
                'submit_error'      => 'Something went wrong. Please try again.',
                'chars_remaining'   => 'characters remaining',
                'file_too_large'    => 'Image is too large.',
                'invalid_image'     => 'Image format is not allowed.',
                'posted_on'         => 'Posted on',
                'no_requests'       => 'No requests have been published yet. Be the first to submit one!',
                'view_request'      => 'View request',
                'language_switch'   => 'বাংলা',
                'phone_help'        => 'Your phone is private. It is never shown publicly.',
                'email_help'        => 'Your email is private. It is never shown publicly.',
                'message_help'      => 'Up to %d characters.',
                'required'          => 'required',
                'page_meta'         => 'Page %1$d of %2$d &middot; %3$d total requests',
                'pagination_label'  => 'Pagination',
                'prev_page'         => 'Previous',
                'next_page'         => 'Next',
                /* translators: %d page number */
                'page_n'            => 'Page %d',
            ),
            'bn' => array(
                'page_title'        => 'কমিউনিটি রিকোয়েস্ট বোর্ড — আপনার যা প্রয়োজন আমাদের জানান',
                'page_subtitle'     => 'আপনি কী খুঁজছেন তা ZYMARG কমিউনিটিকে জানান। বাংলাদেশের বিক্রেতারা আপনার অনুরোধ দেখবেন এবং সাড়া দেবেন।',
                'meta_description'  => 'ZYMARG কমিউনিটি রিকোয়েস্ট বোর্ড — আপনি কী কিনতে চান তা শেয়ার করুন এবং বিশ্বস্ত বাংলাদেশি বিক্রেতাদের কাছ থেকে সাড়া পান। বাংলা ও ইংরেজি সমর্থিত।',
                'submit_request'    => 'রিকোয়েস্ট জমা দিন',
                'full_name'         => 'পূর্ণ নাম',
                'phone_number'      => 'ফোন নম্বর',
                'email_address'     => 'ইমেইল ঠিকানা',
                'request_message'   => 'রিকোয়েস্ট মেসেজ',
                'image_optional'    => 'ছবি (ঐচ্ছিক)',
                'image_required'    => 'ছবি',
                'submit'            => 'জমা দিন',
                'must_login'        => 'রিকোয়েস্ট জমা দিতে লগইন করুন।',
                'login_now'         => 'লগইন',
                'register'          => 'রেজিস্টার',
                'submitting'        => 'জমা হচ্ছে…',
                'submit_success'    => 'ধন্যবাদ! আপনার রিকোয়েস্ট গ্রহণ করা হয়েছে এবং অ্যাডমিন অনুমোদনের পরে প্রকাশিত হবে।',
                'submit_error'      => 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
                'chars_remaining'   => 'অক্ষর বাকি',
                'file_too_large'    => 'ছবিটি অনেক বড়।',
                'invalid_image'     => 'এই ছবির ফরম্যাট অনুমোদিত নয়।',
                'posted_on'         => 'পোস্ট করা হয়েছে',
                'no_requests'       => 'এখনও কোনো রিকোয়েস্ট প্রকাশিত হয়নি। প্রথম জনে হোন!',
                'view_request'      => 'রিকোয়েস্ট দেখুন',
                'language_switch'   => 'English',
                'phone_help'        => 'আপনার ফোন নম্বর গোপনীয়। এটি কখনোই প্রকাশ্যে দেখানো হয় না।',
                'email_help'        => 'আপনার ইমেইল গোপনীয়। এটি কখনোই প্রকাশ্যে দেখানো হয় না।',
                'message_help'      => 'সর্বোচ্চ %d অক্ষর।',
                'required'          => 'আবশ্যিক',
                'page_meta'         => 'পৃষ্ঠা %1$d / %2$d &middot; মোট %3$d টি রিকোয়েস্ট',
                'pagination_label'  => 'পেজিনেশন',
                'prev_page'         => 'পূর্ববর্তী',
                'next_page'         => 'পরবর্তী',
                /* translators: %d page number */
                'page_n'            => 'পৃষ্ঠা %d',
            ),
        );
    }

    /**
     * Detect language from query string, cookie, the configured default,
     * or the WP locale.
     */
    public function detect_language(): void {
        $lang = '';

        if ( isset( $_GET['lang'] ) ) {
            $lang = sanitize_key( wp_unslash( $_GET['lang'] ) );
            if ( in_array( $lang, array( 'en', 'bn' ), true ) ) {
                setcookie( 'zcrb_lang', $lang, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
                $_COOKIE['zcrb_lang'] = $lang;
            } else {
                $lang = '';
            }
        }

        if ( ! $lang && isset( $_COOKIE['zcrb_lang'] ) ) {
            $lang = sanitize_key( wp_unslash( $_COOKIE['zcrb_lang'] ) );
        }

        if ( ! $lang ) {
            $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
            if ( strpos( (string) $locale, 'bn' ) === 0 ) {
                $lang = 'bn';
            }
        }

        if ( ! $lang ) {
            // Fall back to the admin-configured default.
            $configured = function_exists( 'zcrb_get_setting' ) ? (string) zcrb_get_setting( 'default_language', 'en' ) : 'en';
            $lang       = in_array( $configured, array( 'en', 'bn' ), true ) ? $configured : 'en';
        }

        if ( ! in_array( $lang, array( 'en', 'bn' ), true ) ) {
            $lang = 'en';
        }

        self::$lang = $lang;
    }

    public static function current_lang(): string {
        if ( ! self::$lang ) {
            self::$lang = 'en';
        }
        return self::$lang;
    }

    /**
     * Translate a key. Settings overrides take priority for the three
     * publicly-visible strings (page_title, page_subtitle, meta_description).
     */
    public static function t( string $key ): string {
        $lang = self::current_lang();

        // Allow Settings-page overrides for these three keys.
        if ( in_array( $key, array( 'page_title', 'page_subtitle', 'meta_description' ), true ) && function_exists( 'zcrb_get_setting' ) ) {
            $override_key = $key . '_' . $lang;
            $override     = (string) zcrb_get_setting( $override_key, '' );
            if ( '' !== $override ) {
                return $override;
            }
        }

        $strings = self::$strings[ $lang ] ?? self::$strings['en'];
        if ( isset( $strings[ $key ] ) ) {
            return $strings[ $key ];
        }
        return self::$strings['en'][ $key ] ?? $key;
    }

    /**
     * Build a URL switching the language while preserving the current path.
     */
    public static function switch_url(): string {
        $current = self::current_lang();
        $target  = ( 'bn' === $current ) ? 'en' : 'bn';

        $base = ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' );
        return esc_url( add_query_arg( 'lang', $target, $base ) );
    }

    public function output_html_lang(): void {
        if ( ! is_post_type_archive( ZCRB_POST_TYPE ) && ! is_singular( ZCRB_POST_TYPE ) ) {
            return;
        }
        echo '<meta name="zcrb-lang" content="' . esc_attr( self::current_lang() ) . '" />' . "\n";
    }
}
