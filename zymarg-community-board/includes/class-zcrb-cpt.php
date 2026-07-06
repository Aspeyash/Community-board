<?php
/**
 * Custom Post Type: zcrb_request
 * Public archive at /community/, single posts at /community/{slug}.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_CPT {

    /** @var ZCRB_CPT|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_filter( 'wp_unique_post_slug', array( $this, 'enforce_slug_format' ), 10, 6 );
    }

    public static function register_post_type(): void {
        $labels = array(
            'name'               => __( 'Community Requests', 'zymarg-community-board' ),
            'singular_name'      => __( 'Community Request', 'zymarg-community-board' ),
            'menu_name'          => __( 'Community Board', 'zymarg-community-board' ),
            'add_new'            => __( 'Add New', 'zymarg-community-board' ),
            'add_new_item'       => __( 'Add New Request', 'zymarg-community-board' ),
            'edit_item'          => __( 'Edit Request', 'zymarg-community-board' ),
            'new_item'           => __( 'New Request', 'zymarg-community-board' ),
            'view_item'          => __( 'View Request', 'zymarg-community-board' ),
            'all_items'          => __( 'All Requests', 'zymarg-community-board' ),
            'search_items'       => __( 'Search Requests', 'zymarg-community-board' ),
            'not_found'          => __( 'No requests found.', 'zymarg-community-board' ),
            'not_found_in_trash' => __( 'No requests found in Trash.', 'zymarg-community-board' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'zcrb-hub',
            'show_in_rest'        => false, // We expose only sanitized public data ourselves.
            'has_archive'         => ZCRB_ARCHIVE_SLUG,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => array( 'title', 'editor', 'thumbnail', 'author' ),
            'rewrite'             => array(
                'slug'       => ZCRB_ARCHIVE_SLUG,
                'with_front' => false,
                'feeds'      => false,
                'pages'      => true,
            ),
            'exclude_from_search' => false,
        );

        register_post_type( ZCRB_POST_TYPE, $args );

        // Private post meta — never exposed to REST or frontend.
        register_post_meta( ZCRB_POST_TYPE, '_zcrb_full_name', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_phone', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => static function ( $v ) {
                return preg_replace( '/[^0-9+\- ]/', '', (string) $v );
            },
            'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_email', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_email',
            'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_lang', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_key',
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_submitter_ip', array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_upvote_count', array(
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => false,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_upvoted_users', array(
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'default'           => array(),
            'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
        ) );

        register_post_meta( ZCRB_POST_TYPE, '_zcrb_vendor_responses', array(
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'default'           => array(),
            'auth_callback'     => static fn() => current_user_can( 'edit_others_posts' ),
        ) );
    }

    /**
     * Make sure the slug stays human-readable. WP already does this; we only
     * lowercase and strip extra punctuation for safety.
     *
     * @param string $slug
     * @param int    $post_ID
     * @param string $post_status
     * @param string $post_type
     * @param int    $post_parent
     * @param string $original_slug
     */
    public function enforce_slug_format( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ) {
        if ( ZCRB_POST_TYPE !== $post_type ) {
            return $slug;
        }
        $slug = strtolower( $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( (string) $slug, '-' );
        return $slug;
    }
}
