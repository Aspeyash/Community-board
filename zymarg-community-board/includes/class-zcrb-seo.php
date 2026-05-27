<?php
/**
 * SEO: meta description, Open Graph, Twitter Card, JSON-LD schema.
 * Plays nice with Yoast/RankMath: only emits tags they don't already provide.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_SEO {

    /** @var ZCRB_SEO|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'meta_tags' ), 2 );
        add_action( 'wp_head', array( $this, 'json_ld' ), 30 );
        add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
    }

    private function is_relevant(): bool {
        return is_post_type_archive( ZCRB_POST_TYPE ) || is_singular( ZCRB_POST_TYPE );
    }

    private function seo_plugin_active(): bool {
        return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\Common\\Main' );
    }

    public function title_parts( array $parts ): array {
        if ( is_post_type_archive( ZCRB_POST_TYPE ) ) {
            $parts['title'] = ZCRB_I18n::t( 'page_title' );
        }
        return $parts;
    }

    public function meta_tags(): void {
        if ( ! $this->is_relevant() ) {
            return;
        }

        // If a dedicated SEO plugin is active, let it own the meta description / OG tags
        // to avoid duplicates. We still output our schema below.
        if ( $this->seo_plugin_active() ) {
            return;
        }

        $description = ZCRB_I18n::t( 'meta_description' );
        $title       = ZCRB_I18n::t( 'page_title' );
        $url         = is_singular( ZCRB_POST_TYPE ) ? get_permalink() : get_post_type_archive_link( ZCRB_POST_TYPE );
        $image       = '';

        if ( is_singular( ZCRB_POST_TYPE ) ) {
            $post = get_post();
            if ( $post ) {
                $msg         = wp_strip_all_tags( $post->post_content );
                $description = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 155, 'UTF-8' ) : substr( $msg, 0, 155 );
                $title       = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 70, 'UTF-8' ) : substr( $msg, 0, 70 );
                if ( has_post_thumbnail( $post ) ) {
                    $img_arr = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post ), 'large' );
                    if ( $img_arr ) {
                        $image = (string) $img_arr[0];
                    }
                }
            }
        }

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $locale    = ZCRB_I18n::current_lang() === 'bn' ? 'bn_BD' : 'en_US';
        ?>
<meta name="description" content="<?php echo esc_attr( $description ); ?>" />
<meta name="robots" content="index,follow,max-image-preview:large" />
<meta property="og:type" content="<?php echo is_singular( ZCRB_POST_TYPE ) ? 'article' : 'website'; ?>" />
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>" />
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
<meta property="og:url" content="<?php echo esc_url( (string) $url ); ?>" />
<meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>" />
<meta property="og:locale" content="<?php echo esc_attr( $locale ); ?>" />
<?php if ( $image ) : ?>
<meta property="og:image" content="<?php echo esc_url( $image ); ?>" />
<meta property="og:image:alt" content="<?php echo esc_attr( $title ); ?>" />
<?php endif; ?>
<meta name="twitter:card" content="<?php echo $image ? 'summary_large_image' : 'summary'; ?>" />
<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
<?php if ( $image ) : ?>
<meta name="twitter:image" content="<?php echo esc_url( $image ); ?>" />
<?php endif; ?>
        <?php
    }

    public function json_ld(): void {
        if ( ! $this->is_relevant() ) {
            return;
        }

        if ( is_post_type_archive( ZCRB_POST_TYPE ) ) {
            $this->json_ld_archive();
        } elseif ( is_singular( ZCRB_POST_TYPE ) ) {
            $this->json_ld_single();
        }
    }

    private function json_ld_archive(): void {
        $query = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ) );

        $faq_items   = array();
        $list_items  = array();
        $position    = 0;

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $position++;
                $msg = wp_strip_all_tags( $post->post_content );
                if ( '' === $msg ) {
                    continue;
                }
                $permalink = get_permalink( $post );
                $name      = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
                if ( '' === $name ) {
                    $author = get_userdata( (int) $post->post_author );
                    $name   = $author ? $author->display_name : __( 'Community Member', 'zymarg-community-board' );
                }

                // FAQPage entries — community Q&A style.
                $faq_items[] = array(
                    '@type'          => 'Question',
                    'name'           => $msg,
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => sprintf(
                            /* translators: %s: poster name */
                            __( 'Asked by %s on the ZYMARG Community Request Board. ZYMARG vendors can respond directly through the marketplace.', 'zymarg-community-board' ),
                            $name
                        ),
                    ),
                );

                $list_items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position,
                    'url'      => $permalink,
                    'name'     => $msg,
                );
            }
        }

        $schemas = array();

        if ( ! empty( $faq_items ) ) {
            $schemas[] = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faq_items,
            );
        }

        if ( ! empty( $list_items ) ) {
            $schemas[] = array(
                '@context'        => 'https://schema.org',
                '@type'           => 'ItemList',
                'name'            => ZCRB_I18n::t( 'page_title' ),
                'itemListElement' => $list_items,
            );
        }

        $schemas[] = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'CollectionPage',
            'name'            => ZCRB_I18n::t( 'page_title' ),
            'description'     => ZCRB_I18n::t( 'meta_description' ),
            'inLanguage'      => ZCRB_I18n::current_lang() === 'bn' ? 'bn-BD' : 'en',
            'isPartOf'        => array(
                '@type' => 'WebSite',
                'name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                'url'   => home_url( '/' ),
            ),
        );

        foreach ( $schemas as $schema ) {
            echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
        }
    }

    private function json_ld_single(): void {
        $post = get_post();
        if ( ! $post ) {
            return;
        }

        $msg  = wp_strip_all_tags( $post->post_content );
        $name = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
        if ( '' === $name ) {
            $author = get_userdata( (int) $post->post_author );
            $name   = $author ? $author->display_name : __( 'Community Member', 'zymarg-community-board' );
        }

        $image = '';
        if ( has_post_thumbnail( $post ) ) {
            $img_arr = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post ), 'large' );
            if ( $img_arr ) {
                $image = (string) $img_arr[0];
            }
        }

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Question',
            'name'          => $msg,
            'text'          => $msg,
            'dateCreated'   => get_the_date( 'c', $post ),
            'inLanguage'    => ( get_post_meta( $post->ID, '_zcrb_lang', true ) === 'bn' ) ? 'bn-BD' : 'en',
            'author'        => array(
                '@type' => 'Person',
                'name'  => $name,
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post ),
            ),
        );

        if ( $image ) {
            $schema['image'] = $image;
        }

        echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
    }
}
