<?php
/**
 * Template helpers + theme-compatible single/archive overrides.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Template {

    /** @var ZCRB_Template|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setting( string $key, $default = null ) {
        return function_exists( 'zcrb_get_setting' ) ? zcrb_get_setting( $key, $default ) : $default;
    }

    private function __construct() {
        add_filter( 'archive_template', array( $this, 'maybe_archive_template' ) );
        add_filter( 'single_template', array( $this, 'maybe_single_template' ) );
        add_filter( 'the_content', array( $this, 'filter_single_content' ), 9 );
        add_action( 'wp_head', array( $this, 'rel_next_prev' ), 5 );
    }

    public function maybe_archive_template( $template ) {
        if ( is_post_type_archive( ZCRB_POST_TYPE ) ) {
            $theme = locate_template( array( 'archive-' . ZCRB_POST_TYPE . '.php' ) );
            if ( $theme ) {
                return $theme;
            }
            return ZCRB_PLUGIN_DIR . 'templates/archive-zcrb.php';
        }
        return $template;
    }

    public function maybe_single_template( $template ) {
        if ( is_singular( ZCRB_POST_TYPE ) ) {
            $theme = locate_template( array( 'single-' . ZCRB_POST_TYPE . '.php' ) );
            if ( $theme ) {
                return $theme;
            }
            return ZCRB_PLUGIN_DIR . 'templates/single-zcrb.php';
        }
        return $template;
    }

    public function filter_single_content( $content ) {
        if ( ! is_singular( ZCRB_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        $post = get_post();
        if ( ! $post ) {
            return $content;
        }
        ob_start();
        $this->render_single( $post );
        return (string) ob_get_clean();
    }

    public function rel_next_prev(): void {
        if ( ! is_post_type_archive( ZCRB_POST_TYPE ) ) {
            return;
        }
        global $wp_query;
        $paged = max( 1, (int) get_query_var( 'paged' ) );
        $max   = (int) ( $wp_query->max_num_pages ?? 1 );

        if ( $paged > 1 ) {
            echo '<link rel="prev" href="' . esc_url( get_pagenum_link( $paged - 1 ) ) . '" />' . "\n";
        }
        if ( $paged < $max ) {
            echo '<link rel="next" href="' . esc_url( get_pagenum_link( $paged + 1 ) ) . '" />' . "\n";
        }
    }

    /**
     * Render a single card. Public-safe: only Name, Message, Date, Image.
     */
    public function render_card( WP_Post $post ): void {
        $full_name = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
        if ( '' === $full_name ) {
            $author    = get_userdata( (int) $post->post_author );
            $full_name = $author ? $author->display_name : __( 'Community Member', 'zymarg-community-board' );
        }

        $message    = wp_strip_all_tags( $post->post_content );
        $date       = get_the_date( '', $post );
        $iso_date   = get_the_date( 'c', $post );
        $permalink  = get_permalink( $post );
        $thumb_html = '';
        if ( has_post_thumbnail( $post ) ) {
            $thumb_html = get_the_post_thumbnail( $post, 'medium_large', array(
                'class'   => 'zcrb-card__image',
                'loading' => 'lazy',
                'alt'     => esc_attr( wp_trim_words( $message, 12, '' ) ),
            ) );
        }
        ?>
        <article class="zcrb-card" itemscope itemtype="https://schema.org/Question">
            <?php if ( $thumb_html ) : ?>
                <a class="zcrb-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-hidden="true" tabindex="-1">
                    <?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            <?php endif; ?>
            <div class="zcrb-card__body">
                <header class="zcrb-card__header">
                    <span class="zcrb-card__name" itemprop="author" itemscope itemtype="https://schema.org/Person">
                        <span itemprop="name"><?php echo esc_html( $full_name ); ?></span>
                    </span>
                    <time class="zcrb-card__date" datetime="<?php echo esc_attr( $iso_date ); ?>" itemprop="dateCreated">
                        <?php echo esc_html( ZCRB_I18n::t( 'posted_on' ) ); ?> <?php echo esc_html( $date ); ?>
                    </time>
                </header>
                <p class="zcrb-card__message" itemprop="name"><?php echo esc_html( $message ); ?></p>
                <a class="zcrb-card__link" href="<?php echo esc_url( $permalink ); ?>">
                    <?php echo esc_html( ZCRB_I18n::t( 'view_request' ) ); ?> <span aria-hidden="true">→</span>
                </a>
            </div>
        </article>
        <?php
    }

    /**
     * Render the archive board (used by template + shortcode).
     *
     * @param array $args { 'show_form' => bool, 'show_header' => bool }
     */
    public function render_board( array $args = array() ): string {
        $args = wp_parse_args( $args, array(
            'show_form'   => true,
            'show_header' => true,
        ) );

        $per_page = (int) self::setting( 'per_page', ZCRB_PER_PAGE );

        $paged = max( 1, (int) get_query_var( 'paged' ) );
        if ( 1 === $paged ) {
            $paged = max( 1, (int) get_query_var( 'page' ) );
        }

        $query = new WP_Query( array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $total        = (int) $query->found_posts;
        $current_lang = ZCRB_I18n::current_lang();
        $archive_url  = get_post_type_archive_link( ZCRB_POST_TYPE );
        $max_pages    = (int) $query->max_num_pages;

        ob_start();
        ?>
        <div class="zcrb-wrap zcrb-lang-<?php echo esc_attr( $current_lang ); ?>"
             data-zcrb-board
             data-total="<?php echo esc_attr( (string) $total ); ?>"
             data-current-page="<?php echo esc_attr( (string) $paged ); ?>"
             data-archive-url="<?php echo esc_attr( (string) $archive_url ); ?>">

            <div class="zcrb-orbs" aria-hidden="true">
                <span class="zcrb-orb zcrb-orb--1"></span>
                <span class="zcrb-orb zcrb-orb--2"></span>
                <span class="zcrb-orb zcrb-orb--3"></span>
            </div>

            <?php if ( $args['show_header'] ) : ?>
                <header class="zcrb-hero">
                    <div class="zcrb-hero__row">
                        <h1 class="zcrb-hero__title"><?php echo esc_html( ZCRB_I18n::t( 'page_title' ) ); ?></h1>
                        <a class="zcrb-lang-switch" href="<?php echo ZCRB_I18n::switch_url(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( ZCRB_I18n::t( 'language_switch' ) ); ?></a>
                    </div>
                    <p class="zcrb-hero__sub"><?php echo esc_html( ZCRB_I18n::t( 'page_subtitle' ) ); ?></p>
                </header>
            <?php endif; ?>

            <?php if ( $args['show_form'] ) : ?>
                <?php $this->render_form(); ?>
            <?php endif; ?>

            <section class="zcrb-feed" aria-label="<?php echo esc_attr( ZCRB_I18n::t( 'page_title' ) ); ?>">
                <?php if ( $total > 0 ) : ?>
                    <p class="zcrb-feed__meta">
                        <?php
                        printf(
                            /* translators: 1: current page, 2: total pages, 3: total requests */
                            esc_html( ZCRB_I18n::t( 'page_meta' ) ),
                            (int) $paged,
                            max( 1, $max_pages ),
                            $total
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <div class="zcrb-grid" data-zcrb-grid>
                    <?php
                    if ( $query->have_posts() ) {
                        while ( $query->have_posts() ) {
                            $query->the_post();
                            $this->render_card( get_post() );
                        }
                        wp_reset_postdata();
                    } else {
                        echo '<p class="zcrb-empty">' . esc_html( ZCRB_I18n::t( 'no_requests' ) ) . '</p>';
                    }
                    ?>
                </div>

                <?php $this->render_pagination( $paged, $max_pages ); ?>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render numbered pagination. Each link is a real, crawlable URL
     * (e.g. /community/page/2/). Uses paginate_links() so the markup is
     * compatible with theme styles that already target .page-numbers.
     */
    public function render_pagination( int $current, int $max_pages ): void {
        if ( $max_pages <= 1 ) {
            return;
        }

        $base = get_pagenum_link( 1 );
        $base = remove_query_arg( 'paged', $base );
        if ( false === strpos( $base, '%_%' ) ) {
            $base = trailingslashit( $base ) . '%_%';
        }

        $format = get_option( 'permalink_structure' ) ? 'page/%#%/' : '?paged=%#%';

        $links = paginate_links( array(
            'base'      => $base,
            'format'    => $format,
            'total'     => $max_pages,
            'current'   => $current,
            'mid_size'  => 2,
            'end_size'  => 1,
            'prev_text' => '&laquo; ' . ZCRB_I18n::t( 'prev_page' ),
            'next_text' => ZCRB_I18n::t( 'next_page' ) . ' &raquo;',
            'type'      => 'array',
        ) );

        if ( empty( $links ) || ! is_array( $links ) ) {
            return;
        }
        ?>
        <nav class="zcrb-pagination" aria-label="<?php echo esc_attr( ZCRB_I18n::t( 'pagination_label' ) ); ?>">
            <ul class="zcrb-pagination__list">
                <?php foreach ( $links as $link ) : ?>
                    <li class="zcrb-pagination__item"><?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
    }

    public function render_form(): void {
        $is_logged_in = is_user_logged_in();
        $current_lang = ZCRB_I18n::current_lang();
        $action_url   = admin_url( 'admin-post.php' );
        $login_url    = wp_login_url( get_permalink() ?: home_url( '/' . ZCRB_ARCHIVE_SLUG . '/' ) );
        $register_url = wp_registration_url();

        $message_limit  = (int) self::setting( 'message_limit', ZCRB_MESSAGE_LIMIT );
        $phone_required = (bool) self::setting( 'phone_required', 1 );
        $email_required = (bool) self::setting( 'email_required', 1 );
        $image_required = (bool) self::setting( 'image_required', 0 );
        $image_enabled  = (bool) self::setting( 'image_enabled', 1 );
        $image_types    = (string) self::setting( 'image_allowed_types', 'image/jpeg,image/png,image/webp' );
        ?>
        <section class="zcrb-form-wrap" aria-labelledby="zcrb-form-title">
            <h2 id="zcrb-form-title" class="zcrb-form__title"><?php echo esc_html( ZCRB_I18n::t( 'submit_request' ) ); ?></h2>

            <?php if ( ! $is_logged_in ) : ?>
                <div class="zcrb-form__login">
                    <p><?php echo esc_html( ZCRB_I18n::t( 'must_login' ) ); ?></p>
                    <p>
                        <a class="zcrb-btn zcrb-btn--primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( ZCRB_I18n::t( 'login_now' ) ); ?></a>
                        <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html( ZCRB_I18n::t( 'register' ) ); ?></a>
                    </p>
                </div>
            <?php else : ?>
                <?php
                $user      = wp_get_current_user();
                $full_name = $user ? $user->display_name : '';
                $email     = $user ? $user->user_email : '';
                ?>
                <form class="zcrb-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $action_url ); ?>" data-zcrb-form>
                    <input type="hidden" name="action" value="zcrb_submit_request" />
                    <input type="hidden" name="zcrb_lang" value="<?php echo esc_attr( $current_lang ); ?>" />
                    <?php wp_nonce_field( 'zcrb_submit', 'zcrb_nonce_field' ); ?>

                    <div class="zcrb-field">
                        <label for="zcrb-full-name"><?php echo esc_html( ZCRB_I18n::t( 'full_name' ) ); ?> <span class="zcrb-req">*</span></label>
                        <input id="zcrb-full-name" type="text" name="zcrb_full_name" required maxlength="100" value="<?php echo esc_attr( $full_name ); ?>" autocomplete="name" />
                    </div>

                    <div class="zcrb-field-row">
                        <div class="zcrb-field">
                            <label for="zcrb-phone">
                                <?php echo esc_html( ZCRB_I18n::t( 'phone_number' ) ); ?>
                                <?php if ( $phone_required ) : ?><span class="zcrb-req">*</span><?php endif; ?>
                            </label>
                            <input id="zcrb-phone" type="tel" name="zcrb_phone" <?php echo $phone_required ? 'required' : ''; ?> maxlength="32" autocomplete="tel" inputmode="tel" />
                            <small class="zcrb-help"><?php echo esc_html( ZCRB_I18n::t( 'phone_help' ) ); ?></small>
                        </div>
                        <div class="zcrb-field">
                            <label for="zcrb-email">
                                <?php echo esc_html( ZCRB_I18n::t( 'email_address' ) ); ?>
                                <?php if ( $email_required ) : ?><span class="zcrb-req">*</span><?php endif; ?>
                            </label>
                            <input id="zcrb-email" type="email" name="zcrb_email" <?php echo $email_required ? 'required' : ''; ?> value="<?php echo esc_attr( $email ); ?>" autocomplete="email" />
                            <small class="zcrb-help"><?php echo esc_html( ZCRB_I18n::t( 'email_help' ) ); ?></small>
                        </div>
                    </div>

                    <div class="zcrb-field">
                        <label for="zcrb-message"><?php echo esc_html( ZCRB_I18n::t( 'request_message' ) ); ?> <span class="zcrb-req">*</span></label>
                        <textarea id="zcrb-message" name="zcrb_message" required rows="4" maxlength="<?php echo (int) $message_limit; ?>" data-zcrb-message></textarea>
                        <small class="zcrb-help">
                            <span data-zcrb-counter><?php echo (int) $message_limit; ?></span>
                            <?php echo esc_html( ZCRB_I18n::t( 'chars_remaining' ) ); ?>
                        </small>
                    </div>

                    <?php if ( $image_enabled ) : ?>
                        <div class="zcrb-field">
                            <label for="zcrb-image">
                                <?php echo esc_html( $image_required ? ZCRB_I18n::t( 'image_required' ) : ZCRB_I18n::t( 'image_optional' ) ); ?>
                                <?php if ( $image_required ) : ?><span class="zcrb-req">*</span><?php endif; ?>
                            </label>
                            <input id="zcrb-image" type="file" name="zcrb_image" <?php echo $image_required ? 'required' : ''; ?> accept="<?php echo esc_attr( $image_types ); ?>" />
                        </div>
                    <?php endif; ?>

                    <div class="zcrb-form__actions">
                        <button type="submit" class="zcrb-btn zcrb-btn--primary" data-zcrb-submit>
                            <?php echo esc_html( ZCRB_I18n::t( 'submit' ) ); ?>
                        </button>
                        <p class="zcrb-form__feedback" data-zcrb-feedback role="status" aria-live="polite"></p>
                    </div>
                </form>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Single-post render. Public-safe view of an approved request.
     */
    public function render_single( WP_Post $post ): void {
        $full_name = (string) get_post_meta( $post->ID, '_zcrb_full_name', true );
        if ( '' === $full_name ) {
            $author    = get_userdata( (int) $post->post_author );
            $full_name = $author ? $author->display_name : __( 'Community Member', 'zymarg-community-board' );
        }
        $message  = wp_strip_all_tags( $post->post_content );
        $date     = get_the_date( '', $post );
        $iso_date = get_the_date( 'c', $post );
        ?>
        <div class="zcrb-wrap zcrb-single">
            <div class="zcrb-orbs" aria-hidden="true">
                <span class="zcrb-orb zcrb-orb--1"></span>
                <span class="zcrb-orb zcrb-orb--2"></span>
            </div>

            <article class="zcrb-single__article" itemscope itemtype="https://schema.org/Question">
                <header class="zcrb-single__header">
                    <p class="zcrb-single__author">
                        <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <span itemprop="name"><?php echo esc_html( $full_name ); ?></span>
                        </span>
                    </p>
                    <time class="zcrb-single__date" datetime="<?php echo esc_attr( $iso_date ); ?>" itemprop="dateCreated">
                        <?php echo esc_html( ZCRB_I18n::t( 'posted_on' ) ); ?> <?php echo esc_html( $date ); ?>
                    </time>
                </header>

                <h1 class="zcrb-single__title" itemprop="name"><?php echo esc_html( $message ); ?></h1>

                <?php if ( has_post_thumbnail( $post ) ) : ?>
                    <figure class="zcrb-single__media">
                        <?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </figure>
                <?php endif; ?>

                <p class="zcrb-single__back">
                    <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( ZCRB_POST_TYPE ) ); ?>">
                        ← <?php echo esc_html( ZCRB_I18n::t( 'page_title' ) ); ?>
                    </a>
                </p>
            </article>
        </div>
        <?php
    }
}
