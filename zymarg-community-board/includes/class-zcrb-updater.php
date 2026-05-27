<?php
/**
 * GitHub Releases auto-updater.
 *
 * When a new GitHub release is published (e.g. tag v1.3.0), this class
 * makes WordPress treat it as a regular plugin update — it appears under
 * Dashboard → Updates and on the Plugins screen with an "Update now" link.
 *
 * Strategy:
 * 1. Periodically calls the GitHub Releases API.
 * 2. Compares the latest tag with the locally installed version.
 * 3. Prefers a release asset matching `zymarg-community-board*.zip`
 *    (built by the bundled GitHub Actions workflow); falls back to the
 *    repository zipball.
 * 4. After download, ensures the extracted folder is renamed to the
 *    plugin slug `zymarg-community-board` so WordPress can re-activate it.
 *
 * @package ZymargCommunityBoard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZCRB_Updater {

    /** @var string */
    private $owner;

    /** @var string */
    private $repo;

    /** @var string Plugin identifier as used by WP, e.g. zymarg-community-board/zymarg-community-board.php */
    private $plugin_file;

    /** @var string Plugin folder slug, e.g. zymarg-community-board */
    private $plugin_slug;

    /** @var string */
    private $current_version;

    /** @var string */
    private $token = '';

    /** @var string */
    private $cache_key;

    /** @var int */
    private $cache_ttl;

    /**
     * @param array{
     *   owner: string,
     *   repo: string,
     *   plugin_file: string,
     *   version: string,
     *   token?: string,
     *   cache_ttl?: int
     * } $args
     */
    public function __construct( array $args ) {
        $this->owner           = (string) $args['owner'];
        $this->repo            = (string) $args['repo'];
        $this->plugin_file     = (string) $args['plugin_file']; // e.g. zymarg-community-board/zymarg-community-board.php
        $this->plugin_slug     = dirname( $this->plugin_file );
        $this->current_version = (string) $args['version'];
        $this->token           = (string) ( $args['token'] ?? '' );
        $this->cache_ttl       = (int) ( $args['cache_ttl'] ?? 6 * HOUR_IN_SECONDS );
        $this->cache_key       = 'zcrb_gh_release_' . md5( $this->owner . '/' . $this->repo );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'rename_source' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );

        // Force re-check action from the Settings page.
        add_action( 'admin_init', array( $this, 'maybe_force_check' ) );
    }

    public function maybe_force_check(): void {
        if ( ! is_admin() || empty( $_GET['zcrb_action'] ) || 'check_updates' !== $_GET['zcrb_action'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'zcrb_check_updates' );

        delete_site_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type' => ZCRB_POST_TYPE,
                    'page'      => ZCRB_Settings::SETTINGS_SLUG,
                    'zcrb_msg'  => 'updates_checked',
                ),
                admin_url( 'edit.php' )
            )
        );
        exit;
    }

    /**
     * Fetch the latest release JSON from GitHub (cached).
     *
     * @return array<string,mixed>
     */
    private function fetch_latest_release(): array {
        $cached = get_site_transient( $this->cache_key );
        if ( false !== $cached ) {
            return is_array( $cached ) ? $cached : array();
        }

        $url     = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $this->owner ), rawurlencode( $this->repo ) );
        $headers = array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'ZYMARG-Community-Board/' . $this->current_version,
        );
        if ( $this->token ) {
            $headers['Authorization'] = 'token ' . $this->token;
        }

        $resp = wp_remote_get( $url, array(
            'headers' => $headers,
            'timeout' => 15,
        ) );

        if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
            // Cache an empty response briefly so we don't hammer the API on every page load.
            set_site_transient( $this->cache_key, array(), 30 * MINUTE_IN_SECONDS );
            return array();
        }

        $body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
        if ( ! is_array( $body ) ) {
            $body = array();
        }
        set_site_transient( $this->cache_key, $body, $this->cache_ttl );
        return $body;
    }

    /**
     * @return array{new_version:string,package:string,changelog:string,url:string}|null
     */
    private function get_update_info(): ?array {
        $release = $this->fetch_latest_release();
        if ( empty( $release['tag_name'] ) ) {
            return null;
        }

        $tag = ltrim( (string) $release['tag_name'], 'vV' );
        if ( '' === $tag || version_compare( $tag, $this->current_version, '<=' ) ) {
            return null;
        }

        // Prefer a release asset whose name starts with the plugin slug.
        $package = '';
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( ! is_array( $asset ) || empty( $asset['browser_download_url'] ) ) {
                    continue;
                }
                $name = strtolower( (string) ( $asset['name'] ?? '' ) );
                if ( substr( $name, -4 ) !== '.zip' ) {
                    continue;
                }
                if ( 0 === strpos( $name, $this->plugin_slug ) ) {
                    $package = (string) $asset['browser_download_url'];
                    break;
                }
            }
            // Fallback: any zip asset.
            if ( ! $package ) {
                foreach ( $release['assets'] as $asset ) {
                    if ( ! empty( $asset['browser_download_url'] ) && substr( strtolower( (string) ( $asset['name'] ?? '' ) ), -4 ) === '.zip' ) {
                        $package = (string) $asset['browser_download_url'];
                        break;
                    }
                }
            }
        }
        if ( ! $package && ! empty( $release['zipball_url'] ) ) {
            $package = (string) $release['zipball_url'];
        }

        return array(
            'new_version' => $tag,
            'package'     => $package,
            'changelog'   => isset( $release['body'] ) ? (string) $release['body'] : '',
            'url'         => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
        );
    }

    /**
     * Inject our update info into the plugin update transient.
     *
     * @param mixed $transient
     * @return mixed
     */
    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $info = $this->get_update_info();
        if ( null === $info ) {
            return $transient;
        }

        $update = (object) array(
            'id'           => 'zymarg-community-board/' . $this->plugin_file,
            'slug'         => $this->plugin_slug,
            'plugin'       => $this->plugin_file,
            'new_version'  => $info['new_version'],
            'url'          => $info['url'],
            'package'      => $info['package'],
            'tested'       => '6.5',
            'requires_php' => '7.4',
        );

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = array();
        }
        $transient->response[ $this->plugin_file ] = $update;

        return $transient;
    }

    /**
     * Provide details for the "View details" thickbox.
     *
     * @param mixed       $result
     * @param string|null $action
     * @param object|null $args
     * @return mixed
     */
    public function plugins_api( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( ! is_object( $args ) || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $release = $this->fetch_latest_release();
        $info    = $this->get_update_info();

        $changelog = $info ? $info['changelog'] : ( isset( $release['body'] ) ? (string) $release['body'] : '' );

        return (object) array(
            'name'          => 'ZYMARG Community Request Board',
            'slug'          => $this->plugin_slug,
            'version'       => $info ? $info['new_version'] : $this->current_version,
            'author'        => '<a href="https://zymarg.com">ZYMARG</a>',
            'homepage'      => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
            'requires'      => '5.8',
            'tested'        => '6.5',
            'requires_php'  => '7.4',
            'last_updated'  => isset( $release['published_at'] ) ? (string) $release['published_at'] : '',
            'sections'      => array(
                'description' => __( 'SEO-optimized Community Request Board for the ZYMARG marketplace. Bilingual (English/Bengali), schema-marked, with admin approval workflow.', 'zymarg-community-board' ),
                'changelog'   => '<pre style="white-space:pre-wrap;">' . esc_html( $changelog ) . '</pre>',
            ),
            'download_link' => $info ? $info['package'] : '',
        );
    }

    /**
     * After the zip is extracted, make sure the resulting folder is named
     * `zymarg-community-board` so the plugin reactivates correctly.
     *
     * GitHub's source code zipball extracts to e.g. `Aspeyash-Community-page-abc1234/`
     * and our plugin lives in `zymarg-community-board/` *inside* that folder.
     * If a release asset built by our workflow is used, it already extracts
     * straight to `zymarg-community-board/` and this method is a no-op.
     *
     * @param string|WP_Error $source
     * @param string          $remote_source
     * @param mixed           $upgrader
     * @param mixed           $hook_extra
     * @return string|WP_Error
     */
    public function rename_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        if ( is_wp_error( $source ) ) {
            return $source;
        }
        if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
            return $source;
        }

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            return $source;
        }

        $desired = trailingslashit( $remote_source ) . $this->plugin_slug;

        // Case A: $source already points at our plugin folder.
        if ( basename( untrailingslashit( $source ) ) === $this->plugin_slug ) {
            return $source;
        }

        // Case B: $source contains a subfolder that *is* our plugin (zipball-style).
        $candidate = trailingslashit( $source ) . $this->plugin_slug;
        if ( $wp_filesystem->is_dir( $candidate ) ) {
            if ( $wp_filesystem->is_dir( $desired ) ) {
                $wp_filesystem->delete( $desired, true );
            }
            if ( $wp_filesystem->move( $candidate, $desired, true ) ) {
                return $desired;
            }
        }

        // Case C: rename the whole extracted folder to our plugin slug.
        if ( $wp_filesystem->is_dir( $desired ) ) {
            $wp_filesystem->delete( $desired, true );
        }
        if ( $wp_filesystem->move( $source, $desired, true ) ) {
            return $desired;
        }

        return $source;
    }

    /**
     * Clear our release cache after a successful update.
     *
     * @param mixed $upgrader
     * @param array $options
     */
    public function clear_cache( $upgrader, $options ): void {
        if ( is_array( $options ) && ( $options['action'] ?? '' ) === 'update' && ( $options['type'] ?? '' ) === 'plugin' ) {
            delete_site_transient( $this->cache_key );
        }
    }
}
