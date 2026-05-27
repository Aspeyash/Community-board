<?php
/**
 * Default single template — used only if the theme does not provide
 * its own single-zcrb_request.php.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="site-main zcrb-main">
    <?php
    while ( have_posts() ) :
        the_post();
        // the_content filter (ZCRB_Template::filter_single_content) renders the public-safe layout.
        the_content();
    endwhile;
    ?>
</main>
<?php
get_footer();
