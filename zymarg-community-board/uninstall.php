<?php
/**
 * Uninstall handler.
 * Only runs on explicit "Delete" from the Plugins screen — not on deactivation.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete community request posts and their meta.
$post_ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
    'zcrb_request'
) );

if ( ! empty( $post_ids ) ) {
    foreach ( $post_ids as $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }
}

// Best-effort cleanup of orphan meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_zcrb_%'" );
