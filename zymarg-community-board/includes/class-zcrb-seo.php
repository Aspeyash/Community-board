<?php
/**
 * SEO: meta description, canonical, Open Graph, Twitter Card, JSON-LD schema.
 * Each paginated archive page (`/community/page/N/`) gets its own unique
 * title, canonical, and OG URL so Google can index every page distinctly.
 *
 * Plays nice with Yoast / Rank Math: only emits the meta tags they don't
 * already provide. JSON-LD is always emitted.
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

    private function current_paged(): int {
        return max( 1, (int) get_query_var( 'paged' ) );
    }

    public function title_parts( array $parts ): array {
        if ( is_post_type_archive( ZCRB_POST_TYPE ) ) {
            $title = ZCRB_I18n::t( 'page_title' );
            $paged = $this->current_paged();
            if ( $paged > 1 ) {
                /* translators: %d: page number */
                $title .= ' — ' . sprintf( ZCRB_I18n::t( 'page_n' ), $paged );
            }
            $parts['title'] = $title;
        }
        return $parts;
    }

    public function meta_tags(): void {
        if ( ! $this->is_relevant() ) {
            return;
        }

        // Skip duplicate meta-description / OG output if a dedicated SEO plugin is active.
        if ( $this->seo_plugin_active() ) {
            return;
        }

        $description = ZCRB_I18n::t( 'meta_description' );
        $title       = ZCRB_I18n::t( 'page_title' );
        $url         = '';
        $image       = '';
        $type        = 'website';

        if ( is_singular( ZCRB_POST_TYPE ) ) {
            $type = 'article';
            $post = get_post();
            if ( $post ) {
                $msg         = wp_strip_all_tags( $post->post_content );
                $description = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 155, 'UTF-8' ) : substr( $msg, 0, 155 );
                $title       = function_exists( 'mb_substr' ) ? mb_substr( $msg, 0, 70, 'UTF-8' ) : substr( $msg, 0, 70 );
                $url         = get_permalink( $post );
                if ( has_post_thumbnail( $post ) ) {
                    $img_arr = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post ), 'large' );
                    if ( $img_arr ) {
                        $image = (string) $img_arr[0];
                    }
                }
            }
        } else {
            // Paginated archive — every page has its own canonical URL & title.
            $paged = $this->current_paged();
            $url   = $paged > 1 ? get_pagenum_link( $paged ) : (string) get_post_type_archive_link( ZCRB_POST_TYPE );
            if ( $paged > 1 ) {
                /* translators: %d: page number */
                $title       .= ' — ' . sprintf( ZCRB_I18n::t( 'page_n' ), $paged );
                $description .= ' ' . sprintf( ZCRB_I18n::t( 'page_n' ), $paged ) . '.';
            }
        }

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $locale    = ZCRB_I18n::current_lang() === 'bn' ? 'bn_BD' : 'en_US';
        ?>
<meta name="description" content="<?php echo esc_attr( $description ); ?>" />
<meta name="robots" content="index,follow,max-image-preview:large" />
<link rel="canonical" href="<?php echo esc_url( (string) $url ); ?>" />
<meta property="og:type" content="<?php echo esc_attr( $type ); ?>" />
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
        $paged    = $this->current_paged();
        $per_page = function_exists( 'zcrb_get_setting' ) ? (int) zcrb_get_setting( 'per_page', ZCRB_PER_PAGE ) : ZCRB_PER_PAGE;

        $query = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ) );

        $faq_items  = array();
        $list_items = array();
        $position   = ( $paged - 1 ) * $per_page;

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

                // Cap FAQ schema at 20 entries per page (Google ignores long lists).
                if ( count( $faq_items ) < 20 ) {
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
                }

                $list_items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position,
                    'url'      => $permalink,
                    'name'     => $msg,
                );
            }
        }

        $page_url = $paged > 1 ? get_pagenum_link( $paged ) : (string) get_post_type_archive_link( ZCRB_POST_TYPE );
        $page_title = ZCRB_I18n::t( 'page_title' );
        if ( $paged > 1 ) {
            /* translators: %d: page number */
            $page_title .= ' — ' . sprintf( ZCRB_I18n::t( 'page_n' ), $paged );
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
                'name'            => $page_title,
                'url'             => $page_url,
                'itemListElement' => $list_items,
            );
        }

        $schemas[] = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $page_title,
            'url'         => $page_url,
            'description' => ZCRB_I18n::t( 'meta_description' ),
            'inLanguage'  => ZCRB_I18n::current_lang() === 'bn' ? 'bn-BD' : 'en',
            'isPartOf'    => array(
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
            '@context'         => 'https://schema.org',
            '@type'            => 'Question',
            'name'             => $msg,
            'text'             => $msg,
            'dateCreated'      => get_the_date( 'c', $post ),
            'inLanguage'       => ( get_post_meta( $post->ID, '_zcrb_lang', true ) === 'bn' ) ? 'bn-BD' : 'en',
            'author'           => array(
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
