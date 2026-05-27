<?php
/**
 * Shortcode wrapper so the board (and form) can be embedded inside any
 * Elementor / Astra page builder layout.
 *
 * Usage:
 *   [zymarg_community_board]                      Full board + form
 *   [zymarg_community_board show_form="0"]        Board only
 *   [zymarg_community_board show_header="0"]      Hide H1/subtitle (use Elementor heading instead)
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Shortcode {

    /** @var ZCRB_Shortcode|null */
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'zymarg_community_board', array( $this, 'render' ) );
        add_shortcode( 'zymarg_community_form', array( $this, 'render_form' ) );
    }

    public function render( $atts ): string {
        $atts = shortcode_atts( array(
            'show_form'   => '1',
            'show_header' => '1',
        ), $atts, 'zymarg_community_board' );

        return ZCRB_Template::instance()->render_board( array(
            'show_form'   => '1' === (string) $atts['show_form'],
            'show_header' => '1' === (string) $atts['show_header'],
        ) );
    }

    public function render_form( $atts ): string {
        ob_start();
        ?>
        <div class="zcrb-wrap zcrb-wrap--form-only">
            <?php ZCRB_Template::instance()->render_form(); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
