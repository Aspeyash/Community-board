<?php
/**
 * Default archive template — used only if the theme does not provide
 * its own archive-zcrb_request.php. Wraps everything in get_header()/get_footer()
 * so it inherits Astra's chrome cleanly.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main zcrb-main">
    <?php echo ZCRB_Template::instance()->render_board( array(
        'show_form'   => true,
        'show_header' => true,
    ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php
get_footer();
