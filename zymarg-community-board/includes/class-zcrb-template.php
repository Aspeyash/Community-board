<?php
/**
 * Template helpers + theme-compatible single/archive overrides.
 *
 * v1.4.0 — redesigned to match the Material 3 inspired ZYMARG mockup:
 * sticky glass form on the left, card grid on the right, hero with
 * decorative orbs, footer pagination with "Showing X-Y of Z" meta.
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
     * Build a public-safe reference number for a request, e.g. "RQ0123".
     */
    public static function ref_number( int $post_id ): string {
        return 'RQ' . str_pad( (string) $post_id, 4, '0', STR_PAD_LEFT );
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
        $ref        = self::ref_number( (int) $post->ID );
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
                    <h3 class="zcrb-card__name" itemprop="author" itemscope itemtype="https://schema.org/Person">
                        <span itemprop="name"><?php echo esc_html( $full_name ); ?></span>
                    </h3>
                    <time class="zcrb-card__date-badge" datetime="<?php echo esc_attr( $iso_date ); ?>" itemprop="dateCreated">
                        <?php echo esc_html( $date ); ?>
                    </time>
                </header>
                <p class="zcrb-card__message" itemprop="name"><?php echo esc_html( $message ); ?></p>
                <footer class="zcrb-card__footer">
                    <span class="zcrb-card__ref"><?php echo esc_html( ZCRB_I18n::t( 'ref_label' ) ); ?> #<?php echo esc_html( $ref ); ?></span>
                    <?php
                    $post_status = get_post_status( $post );
                    if ( $post_status && 'publish' !== $post_status ) :
                        $status_label = ZCRB_Status::get_status_label( $post_status );
                        $status_class = 'zcrb-status-badge zcrb-status-badge--' . sanitize_html_class( str_replace( 'zcrb_', '', $post_status ) );
                        ?>
                        <span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                    <?php endif; ?>
                    <span class="zcrb-card__upvotes">
                        <svg class="zcrb-card__upvotes-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        <?php echo esc_html( (string) ZCRB_Upvote::get_count( (int) $post->ID ) ); ?>
                    </span>
                    <a class="zcrb-card__link" href="<?php echo esc_url( $permalink ); ?>">
                        <?php echo esc_html( ZCRB_I18n::t( 'view_details' ) ); ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </a>
                </footer>
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

        // Search and filter parameters.
        $zcrb_search = isset( $_GET['zcrb_search'] ) ? sanitize_text_field( wp_unslash( $_GET['zcrb_search'] ) ) : '';
        $zcrb_filter = isset( $_GET['zcrb_filter'] ) ? sanitize_key( wp_unslash( $_GET['zcrb_filter'] ) ) : '';

        // Determine post_status based on filter.
        $post_status = array( 'publish', 'zcrb_in_progress', 'zcrb_fulfilled' );
        if ( 'in_progress' === $zcrb_filter ) {
            $post_status = 'zcrb_in_progress';
        } elseif ( 'fulfilled' === $zcrb_filter ) {
            $post_status = 'zcrb_fulfilled';
        }

        $query_args = array(
            'post_type'      => ZCRB_POST_TYPE,
            'post_status'    => $post_status,
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( '' !== $zcrb_search ) {
            $query_args['s'] = $zcrb_search;
        }

        $query = new WP_Query( $query_args );

        $total        = (int) $query->found_posts;
        $current_lang = ZCRB_I18n::current_lang();
        $archive_url  = get_post_type_archive_link( ZCRB_POST_TYPE );
        $max_pages    = (int) $query->max_num_pages;

        // Title splits on " — " for the two-line hero look.
        $title       = ZCRB_I18n::t( 'page_title' );
        $title_parts = explode( ' — ', $title, 2 );

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
                        <h1 class="zcrb-hero__title">
                            <?php echo esc_html( $title_parts[0] ); ?>
                            <?php if ( isset( $title_parts[1] ) ) : ?>
                                <span class="zcrb-hero__title-line2"><?php echo esc_html( $title_parts[1] ); ?></span>
                            <?php endif; ?>
                        </h1>
                    </div>
                    <p class="zcrb-hero__sub"><?php echo esc_html( ZCRB_I18n::t( 'page_subtitle' ) ); ?></p>
                    <p style="margin-top:16px;">
                        <a class="zcrb-lang-switch" href="<?php echo ZCRB_I18n::switch_url(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( ZCRB_I18n::t( 'language_switch' ) ); ?></a>
                    </p>
                </header>
            <?php endif; ?>

            <div class="zcrb-layout">
                <?php if ( $args['show_form'] ) : ?>
                    <aside class="zcrb-sidebar">
                        <?php $this->render_form(); ?>
                        <?php $this->render_privacy_notice(); ?>
                    </aside>
                <?php endif; ?>

                <section class="zcrb-feed" aria-label="<?php echo esc_attr( ZCRB_I18n::t( 'page_title' ) ); ?>">
                    <header class="zcrb-feed__header">
                        <h2 class="zcrb-feed__title"><?php echo esc_html( ZCRB_I18n::t( 'recent_requests' ) ); ?></h2>
                    </header>

                    <form class="zcrb-search-bar" method="get" action="<?php echo esc_url( (string) $archive_url ); ?>">
                        <input type="text" name="zcrb_search" value="<?php echo esc_attr( $zcrb_search ); ?>" placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'search_placeholder' ) ); ?>" />
                        <select name="zcrb_filter">
                            <option value="" <?php selected( $zcrb_filter, '' ); ?>><?php echo esc_html( ZCRB_I18n::t( 'filter_all' ) ); ?></option>
                            <option value="in_progress" <?php selected( $zcrb_filter, 'in_progress' ); ?>><?php echo esc_html( ZCRB_I18n::t( 'filter_in_progress' ) ); ?></option>
                            <option value="fulfilled" <?php selected( $zcrb_filter, 'fulfilled' ); ?>><?php echo esc_html( ZCRB_I18n::t( 'filter_fulfilled' ) ); ?></option>
                        </select>
                        <button type="submit" class="zcrb-btn zcrb-btn--ghost"><?php echo esc_html( ZCRB_I18n::t( 'search_button' ) ); ?></button>
                    </form>

                    <div class="zcrb-grid" data-zcrb-grid>
                        <?php
                        if ( $query->have_posts() ) {
                            while ( $query->have_posts() ) {
                                $query->the_post();
                                $this->render_card( get_post() );
                            }
                            wp_reset_postdata();
                        } else {
                            ?>
                            <div class="zcrb-empty-state">
                                <svg class="zcrb-empty-state__spark" viewBox="0 0 24 24" width="64" height="64" aria-hidden="true">
                                    <g class="zcrb-spark-group--accent"><path d="M10.4 5.4c0 1.32-0.24 2.4-1.44 2.4 1.2 0 1.44 1.08 1.44 2.4 0-1.32 0.24-2.4 1.44-2.4-1.2 0-1.44-1.08-1.44-2.4z" fill="#6833ea"/><path d="M10.4 6.0c0 0.96-0.18 1.8-1.08 1.8 0.9 0 1.08 0.84 1.08 1.8 0-0.9 0.18-1.8 1.08-1.8-0.9 0-1.08-0.84-1.08-1.8z" fill="#ffd166"/></g>
                                    <g class="zcrb-spark-group--companion"><path d="M9.5 10.92c0 2.25-0.45 4.12-2.4 4.12 1.95 0 2.4 1.87 2.4 4.12 0-2.25 0.45-4.12 2.4-4.12-1.95 0-2.4-1.87-2.4-4.12z" fill="#6833ea"/><path d="M9.5 11.5c0 1.9-0.38 3.54-2.0 3.54 1.62 0 2.0 1.64 2.0 3.54 0-1.9 0.38-3.54 2.0-3.54-1.62 0-2.0-1.64-2.0-3.54z" fill="#ffd166"/></g>
                                    <g class="zcrb-spark-group--hero"><path d="M15.2 5.6c0 3.45-0.69 6.3-4.08 6.3 3.39 0 4.08 2.85 4.08 6.3 0-3.45 0.69-6.3 4.08-6.3-3.39 0-4.08-2.85-4.08-6.3z" fill="#6833ea"/><path d="M15.2 6.5c0 2.9-0.58 5.4-3.39 5.4 2.81 0 3.39 2.5 3.39 5.4 0-2.9 0.58-5.4 3.39-5.4-2.81 0-3.39-2.5-3.39-5.4z" fill="#ffd166"/></g>
                                </svg>
                                <h3 class="zcrb-empty-state__title"><?php echo esc_html( ZCRB_I18n::t( 'empty_state_title' ) ); ?></h3>
                                <p class="zcrb-empty-state__subtitle"><?php echo esc_html( ZCRB_I18n::t( 'empty_state_subtitle' ) ); ?></p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>

                    <?php $this->render_pagination( $paged, $max_pages, $total, $per_page, $query->post_count ); ?>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render numbered pagination + "Showing X-Y of Z" meta line.
     */
    public function render_pagination( int $current, int $max_pages, int $total, int $per_page, int $shown ): void {
        if ( $max_pages <= 1 && $total === 0 ) {
            return;
        }

        $first = ( ( $current - 1 ) * $per_page ) + 1;
        $last  = $first + max( 0, $shown - 1 );
        ?>
        <nav class="zcrb-pagination" aria-label="<?php echo esc_attr( ZCRB_I18n::t( 'pagination_label' ) ); ?>">
            <?php if ( $max_pages > 1 ) :
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
                    'mid_size'  => 1,
                    'end_size'  => 1,
                    'prev_text' => '&lsaquo; ' . ZCRB_I18n::t( 'prev_page' ),
                    'next_text' => ZCRB_I18n::t( 'next_page' ) . ' &rsaquo;',
                    'type'      => 'array',
                ) );

                if ( ! empty( $links ) && is_array( $links ) ) : ?>
                    <ul class="zcrb-pagination__list">
                        <?php foreach ( $links as $link ) : ?>
                            <li class="zcrb-pagination__item"><?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ( $total > 0 ) : ?>
                <p class="zcrb-pagination__meta">
                    <?php
                    printf(
                        /* translators: 1: first item, 2: last item, 3: total items */
                        esc_html( ZCRB_I18n::t( 'showing_results' ) ),
                        (int) $first,
                        (int) $last,
                        (int) $total
                    );
                    ?>
                </p>
            <?php endif; ?>
        </nav>
        <?php
    }

    /**
     * Render the privacy / data-retention notice block (sits below the form).
     */
    public function render_privacy_notice(): void {
        $retention_days = class_exists( 'ZCRB_Retention' ) ? ZCRB_Retention::configured_days() : 0;
        ?>
        <p class="zcrb-privacy-notice" role="note">
            <span class="zcrb-privacy-notice__icon" aria-hidden="true">🔒</span>
            <span class="zcrb-privacy-notice__text">
                <?php
                if ( $retention_days > 0 ) {
                    echo esc_html( sprintf( ZCRB_I18n::t( 'privacy_notice' ), (int) $retention_days ) );
                } else {
                    echo esc_html( ZCRB_I18n::t( 'privacy_no_delete' ) );
                }
                ?>
            </span>
        </p>
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
        $image_max_mb   = (int) self::setting( 'image_max_mb', 2 );
        ?>
        <section class="zcrb-form-wrap zcrb-glass" aria-labelledby="zcrb-form-title">
            <h2 id="zcrb-form-title" class="zcrb-form__title"><?php echo esc_html( ZCRB_I18n::t( 'submit_request' ) ); ?></h2>

            <?php if ( ! $is_logged_in ) : ?>
                <div class="zcrb-form__login">
                    <p><?php echo esc_html( ZCRB_I18n::t( 'must_login' ) ); ?></p>
                    <p>
                        <a class="zcrb-btn zcrb-btn--primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( ZCRB_I18n::t( 'login_now' ) ); ?></a>
                        <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( $register_url ); ?>" style="margin-top:8px;"><?php echo esc_html( ZCRB_I18n::t( 'register' ) ); ?></a>
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
                        <input id="zcrb-full-name" type="text" name="zcrb_full_name" required maxlength="100" value="<?php echo esc_attr( $full_name ); ?>" autocomplete="name" placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'placeholder_name' ) ); ?>" />
                    </div>

                    <div class="zcrb-field">
                        <label for="zcrb-phone">
                            <?php echo esc_html( ZCRB_I18n::t( 'phone_number' ) ); ?>
                            <?php if ( $phone_required ) : ?><span class="zcrb-req">*</span><?php endif; ?>
                        </label>
                        <input id="zcrb-phone" type="tel" name="zcrb_phone" <?php echo $phone_required ? 'required' : ''; ?> maxlength="32" autocomplete="tel" inputmode="tel" placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'placeholder_phone' ) ); ?>" />
                    </div>

                    <div class="zcrb-field">
                        <label for="zcrb-email">
                            <?php echo esc_html( ZCRB_I18n::t( 'email_address' ) ); ?>
                            <?php if ( $email_required ) : ?><span class="zcrb-req">*</span><?php endif; ?>
                        </label>
                        <input id="zcrb-email" type="email" name="zcrb_email" <?php echo $email_required ? 'required' : ''; ?> value="<?php echo esc_attr( $email ); ?>" autocomplete="email" placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'placeholder_email' ) ); ?>" />
                    </div>

                    <div class="zcrb-field">
                        <label for="zcrb-message"><?php echo esc_html( sprintf( ZCRB_I18n::t( 'request_message_with_limit' ), (int) $message_limit ) ); ?> <span class="zcrb-req">*</span></label>
                        <textarea id="zcrb-message" name="zcrb_message" required rows="4" maxlength="<?php echo (int) $message_limit; ?>" data-zcrb-message placeholder="<?php echo esc_attr( ZCRB_I18n::t( 'placeholder_message' ) ); ?>"></textarea>
                        <small class="zcrb-help">
                            <span data-zcrb-counter><?php echo (int) $message_limit; ?></span>
                            <?php echo esc_html( ZCRB_I18n::t( 'chars_remaining' ) ); ?>
                        </small>
                        <div class="zcrb-similar" data-zcrb-similar></div>
                    </div>

                    <?php if ( $image_enabled ) : ?>
                        <?php
                        $image_max_count = (int) self::setting( 'image_max_count', 1 );
                        for ( $img_i = 0; $img_i < $image_max_count; $img_i++ ) :
                            $input_name = 'zcrb_images[]';
                            $input_id   = 'zcrb-image-' . $img_i;
                            $is_first   = ( 0 === $img_i );
                        ?>
                        <div class="zcrb-field">
                            <label for="<?php echo esc_attr( $input_id ); ?>">
                                <?php
                                if ( $is_first ) {
                                    echo esc_html( $image_required ? ZCRB_I18n::t( 'image_required' ) : ZCRB_I18n::t( 'image_optional' ) );
                                    if ( $image_required ) {
                                        echo ' <span class="zcrb-req">*</span>';
                                    }
                                } else {
                                    echo esc_html( ZCRB_I18n::t( 'image_optional' ) );
                                }
                                ?>
                            </label>
                            <label class="zcrb-upload" for="<?php echo esc_attr( $input_id ); ?>">
                                <svg class="zcrb-upload__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M16 16l-4-4-4 4M12 12v9"/>
                                    <path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
                                    <path d="M16 16l-4-4-4 4"/>
                                </svg>
                                <p class="zcrb-upload__text"><?php echo esc_html( sprintf( ZCRB_I18n::t( 'upload_hint' ), $image_max_mb ) ); ?></p>
                                <span class="zcrb-upload__filename" data-zcrb-filename></span>
                                <input id="<?php echo esc_attr( $input_id ); ?>" type="file" name="<?php echo esc_attr( $input_name ); ?>" <?php echo ( $is_first && $image_required ) ? 'required' : ''; ?> accept="<?php echo esc_attr( $image_types ); ?>" />
                            </label>
                        </div>
                        <?php endfor; ?>
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
        $message     = wp_strip_all_tags( $post->post_content );
        $date        = get_the_date( '', $post );
        $iso_date    = get_the_date( 'c', $post );
        $ref         = self::ref_number( (int) $post->ID );
        $post_status = get_post_status( $post );
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
                    <time class="zcrb-card__date-badge" datetime="<?php echo esc_attr( $iso_date ); ?>" itemprop="dateCreated">
                        <?php echo esc_html( $date ); ?>
                    </time>
                    <?php
                    if ( $post_status && 'publish' !== $post_status ) :
                        $status_label = ZCRB_Status::get_status_label( $post_status );
                        $status_class = 'zcrb-status-badge zcrb-status-badge--' . sanitize_html_class( str_replace( 'zcrb_', '', $post_status ) );
                        ?>
                        <span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                    <?php endif; ?>
                </header>

                <h1 class="zcrb-single__title" itemprop="name"><?php echo esc_html( $message ); ?></h1>

                <?php if ( has_post_thumbnail( $post ) ) : ?>
                    <figure class="zcrb-single__media">
                        <?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </figure>
                <?php endif; ?>

                <div class="zcrb-single__meta">
                    <span class="zcrb-card__ref"><?php echo esc_html( ZCRB_I18n::t( 'ref_label' ) ); ?> #<?php echo esc_html( $ref ); ?></span>
                    <?php ZCRB_Upvote::render_button( (int) $post->ID ); ?>
                </div>

                <?php ZCRB_Vendor_Response::instance()->render_responses( (int) $post->ID ); ?>
                <?php ZCRB_Vendor_Response::instance()->render_response_form( (int) $post->ID ); ?>

                <p class="zcrb-single__back">
                    <a class="zcrb-btn zcrb-btn--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( ZCRB_POST_TYPE ) ); ?>">
                        &larr; <?php echo esc_html( ZCRB_I18n::t( 'page_title' ) ); ?>
                    </a>
                </p>
            </article>
        </div>
        <?php
    }
}
