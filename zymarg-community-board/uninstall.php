<?php
/**
 * Uninstall handler.
 * Only runs on explicit "Delete" from the Plugins screen — not on deactivation.
 *
 * NON-DESTRUCTIVE by default. Data is only removed if the site administrator
 * has opted in by defining the constant ZCRB_REMOVE_ALL_DATA as truthy
 * (e.g., in wp-config.php: define( 'ZCRB_REMOVE_ALL_DATA', true ); ).
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Bail early unless the site owner explicitly opted in to data removal.
if ( ! defined( 'ZCRB_REMOVE_ALL_DATA' ) || ! ZCRB_REMOVE_ALL_DATA ) {
    return;
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
