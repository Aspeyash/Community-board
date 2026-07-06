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
                'page_title'               => 'Community Request Board — Tell Us What You Need',
                'page_subtitle'            => "Can't find a specific product? Post it here and our network of verified vendors will find it for you. Your community, your choice.",
                'meta_description'         => 'ZYMARG Community Request Board — share what you want to buy and let trusted Bangladeshi vendors respond. Bengali and English supported.',
                'submit_request'           => 'Submit a Request',
                'full_name'                => 'Full Name',
                'phone_number'             => 'Phone',
                'email_address'            => 'Email',
                'request_message'          => 'Message',
                'request_message_with_limit' => 'Message (%d character max)',
                'image_optional'           => 'Image Upload',
                'image_required'           => 'Image Upload',
                'submit'                   => 'Submit Request',
                'must_login'               => 'Please log in to submit a request.',
                'login_now'                => 'Log in',
                'register'                 => 'Register',
                'submitting'               => 'Submitting…',
                'submit_success'           => 'Thanks! Your request has been received and will appear after admin approval.',
                'submit_error'             => 'Something went wrong. Please try again.',
                'chars_remaining'          => 'characters remaining',
                'file_too_large'           => 'Image is too large.',
                'invalid_image'            => 'Image format is not allowed.',
                'upload_hint'              => 'PNG, JPG up to %d MB',
                'placeholder_name'         => 'John Doe',
                'placeholder_phone'        => '+880 1XXX-XXXXXX',
                'placeholder_email'        => 'example@zymarg.com',
                'placeholder_message'      => 'Describe what you are looking for…',
                'posted_on'                => 'Posted on',
                'no_requests'              => 'No requests have been published yet. Be the first to submit one!',
                'view_request'             => 'View request',
                'view_details'             => 'View Details',
                'recent_requests'          => 'Recent Requests',
                'ref_label'                => 'Ref:',
                'showing_results'          => 'Showing %1$d-%2$d of %3$d requests',
                'language_switch'          => 'বাংলা',
                'phone_help'               => 'Your phone is private. It is never shown publicly.',
                'email_help'               => 'Your email is private. It is never shown publicly.',
                'message_help'             => 'Up to %d characters.',
                'required'                 => 'required',
                'pagination_label'         => 'Pagination',
                'prev_page'                => 'Previous',
                'next_page'                => 'Next',
                /* translators: %d page number */
                'page_n'                   => 'Page %d',
                /* translators: %d retention days */
                'privacy_notice'           => 'Phone and Email are used for fulfillment only and are never shown publicly. Every submission is automatically deleted after %d days.',
                'privacy_no_delete'        => 'Phone and Email are used for fulfillment only and are never shown publicly.',
                'upvote'                   => 'Upvote',
                'upvoted'                  => 'Upvoted',
                'upvotes_count'            => '%d upvotes',
                'vendor_response'          => 'Vendor Response',
                'vendor_responses'         => 'Vendor Responses',
                'no_responses_yet'         => 'No vendor responses yet.',
                'respond_placeholder'      => 'Write your response to this request...',
                'submit_response'          => 'Submit Response',
                'response_submitted'       => 'Your response has been submitted successfully.',
                'status_pending'           => 'Pending',
                'status_approved'          => 'Approved',
                'status_in_progress'       => 'In Progress',
                'status_fulfilled'         => 'Fulfilled',
                'notification_approved_subject' => 'Your request has been approved!',
                'notification_approved_body'    => "Hi {name},\n\nGreat news! Your community request has been approved and is now live on the board.\n\nView it here: {link}\n\nThank you for contributing to our community!",
                'notification_response_subject' => 'A vendor responded to your request!',
                'notification_response_body'    => "Hi {name},\n\n{vendor_name} has responded to your community request.\n\nView the response here: {link}\n\nThank you for using the Community Request Board!",
                'vendor_responded_label'   => 'Vendor responded',
            ),
            'bn' => array(
                'page_title'               => 'কমিউনিটি রিকোয়েস্ট বোর্ড — আপনার যা প্রয়োজন আমাদের জানান',
                'page_subtitle'            => 'নির্দিষ্ট কোনো পণ্য খুঁজে পাচ্ছেন না? এখানে পোস্ট করুন এবং আমাদের যাচাইকৃত বিক্রেতাদের নেটওয়ার্ক আপনার জন্য তা খুঁজে দেবে। আপনার কমিউনিটি, আপনার পছন্দ।',
                'meta_description'         => 'ZYMARG কমিউনিটি রিকোয়েস্ট বোর্ড — আপনি কী কিনতে চান তা শেয়ার করুন এবং বিশ্বস্ত বাংলাদেশি বিক্রেতাদের কাছ থেকে সাড়া পান। বাংলা ও ইংরেজি সমর্থিত।',
                'submit_request'           => 'রিকোয়েস্ট জমা দিন',
                'full_name'                => 'পূর্ণ নাম',
                'phone_number'             => 'ফোন',
                'email_address'            => 'ইমেইল',
                'request_message'          => 'মেসেজ',
                'request_message_with_limit' => 'মেসেজ (সর্বোচ্চ %d অক্ষর)',
                'image_optional'           => 'ছবি আপলোড',
                'image_required'           => 'ছবি আপলোড',
                'submit'                   => 'জমা দিন',
                'must_login'               => 'রিকোয়েস্ট জমা দিতে লগইন করুন।',
                'login_now'                => 'লগইন',
                'register'                 => 'রেজিস্টার',
                'submitting'               => 'জমা হচ্ছে…',
                'submit_success'           => 'ধন্যবাদ! আপনার রিকোয়েস্ট গ্রহণ করা হয়েছে এবং অ্যাডমিন অনুমোদনের পরে প্রকাশিত হবে।',
                'submit_error'             => 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
                'chars_remaining'          => 'অক্ষর বাকি',
                'file_too_large'           => 'ছবিটি অনেক বড়।',
                'invalid_image'            => 'এই ছবির ফরম্যাট অনুমোদিত নয়।',
                'upload_hint'              => 'PNG, JPG সর্বোচ্চ %d এমবি',
                'placeholder_name'         => 'জন ডো',
                'placeholder_phone'        => '+৮৮০ ১XXX-XXXXXX',
                'placeholder_email'        => 'example@zymarg.com',
                'placeholder_message'      => 'আপনি কী খুঁজছেন তা বর্ণনা করুন…',
                'posted_on'                => 'পোস্ট করা হয়েছে',
                'no_requests'              => 'এখনও কোনো রিকোয়েস্ট প্রকাশিত হয়নি। প্রথম জনে হোন!',
                'view_request'             => 'রিকোয়েস্ট দেখুন',
                'view_details'             => 'বিস্তারিত দেখুন',
                'recent_requests'          => 'সাম্প্রতিক রিকোয়েস্ট',
                'ref_label'                => 'রেফ:',
                'showing_results'          => 'মোট %3$d টি রিকোয়েস্টের মধ্যে %1$d-%2$d দেখানো হচ্ছে',
                'language_switch'          => 'English',
                'phone_help'               => 'আপনার ফোন নম্বর গোপনীয়। এটি কখনোই প্রকাশ্যে দেখানো হয় না।',
                'email_help'               => 'আপনার ইমেইল গোপনীয়। এটি কখনোই প্রকাশ্যে দেখানো হয় না।',
                'message_help'             => 'সর্বোচ্চ %d অক্ষর।',
                'required'                 => 'আবশ্যিক',
                'pagination_label'         => 'পেজিনেশন',
                'prev_page'                => 'পূর্ববর্তী',
                'next_page'                => 'পরবর্তী',
                /* translators: %d page number */
                'page_n'                   => 'পৃষ্ঠা %d',
                /* translators: %d retention days */
                'privacy_notice'           => 'ফোন ও ইমেইল শুধুমাত্র অনুরোধ পূরণের জন্য ব্যবহৃত হয় এবং কখনোই প্রকাশ্যে দেখানো হয় না। প্রতিটি রিকোয়েস্ট %d দিন পর স্বয়ংক্রিয়ভাবে মুছে ফেলা হবে।',
                'privacy_no_delete'        => 'ফোন ও ইমেইল শুধুমাত্র অনুরোধ পূরণের জন্য ব্যবহৃত হয় এবং কখনোই প্রকাশ্যে দেখানো হয় না।',
                'upvote'                   => 'আপভোট',
                'upvoted'                  => 'আপভোট করা হয়েছে',
                'upvotes_count'            => '%d আপভোট',
                'vendor_response'          => 'বিক্রেতার প্রতিক্রিয়া',
                'vendor_responses'         => 'বিক্রেতাদের প্রতিক্রিয়া',
                'no_responses_yet'         => 'এখনও কোনো বিক্রেতার প্রতিক্রিয়া নেই।',
                'respond_placeholder'      => 'এই রিকোয়েস্টের জন্য আপনার প্রতিক্রিয়া লিখুন...',
                'submit_response'          => 'প্রতিক্রিয়া জমা দিন',
                'response_submitted'       => 'আপনার প্রতিক্রিয়া সফলভাবে জমা হয়েছে।',
                'status_pending'           => 'অপেক্ষমাণ',
                'status_approved'          => 'অনুমোদিত',
                'status_in_progress'       => 'চলমান',
                'status_fulfilled'         => 'পূরণ হয়েছে',
                'notification_approved_subject' => 'আপনার রিকোয়েস্ট অনুমোদিত হয়েছে!',
                'notification_approved_body'    => "প্রিয় {name},\n\nসুখবর! আপনার কমিউনিটি রিকোয়েস্ট অনুমোদিত হয়েছে এবং এখন বোর্ডে লাইভ।\n\nএখানে দেখুন: {link}\n\nকমিউনিটিতে অবদান রাখার জন্য ধন্যবাদ!",
                'notification_response_subject' => 'একজন বিক্রেতা আপনার রিকোয়েস্টে সাড়া দিয়েছেন!',
                'notification_response_body'    => "প্রিয় {name},\n\n{vendor_name} আপনার কমিউনিটি রিকোয়েস্টে প্রতিক্রিয়া জানিয়েছেন।\n\nপ্রতিক্রিয়া দেখুন: {link}\n\nকমিউনিটি রিকোয়েস্ট বোর্ড ব্যবহার করার জন্য ধন্যবাদ!",
                'vendor_responded_label'   => 'বিক্রেতা সাড়া দিয়েছেন',
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
