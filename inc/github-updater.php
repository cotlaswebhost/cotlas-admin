<?php
/**
 * Handles automatic plugin updates from a GitHub releases endpoint.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;
// ---------------------------------------------------------------------------
// GitHub Updater
// Public repo — no token required. Optionally define COTLAS_GITHUB_TOKEN in wp-config.php for higher API rate limits.
// ---------------------------------------------------------------------------
if ( is_admin() ) {
    new Cotlas_GitHub_Updater( defined( 'COTLAS_ADMIN_FILE' ) ? COTLAS_ADMIN_FILE : __FILE__ );
}

class Cotlas_GitHub_Updater {

    /** Option that stores a detected release-tag / plugin-header version mismatch. */
    const VERSION_MISMATCH_OPTION = 'cotlas_admin_release_version_mismatch';

    private $file;
    private $plugin_slug;
    private $plugin_data = array();

    /** Change this to your GitHub username/repo-name */
    private $github_repo = 'cotlaswebhost/cotlas-admin';

    public function __construct( $file ) {
        $this->file        = $file;
        $this->plugin_slug = plugin_basename( $file );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( $this, 'detect_release_version_drift' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'render_release_version_drift_notice' ) );
    }

    private function load_plugin_data() {
        if ( empty( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->file );
        }
    }

    /**
     * Inject GitHub auth token for all requests to github.com.
     * Required for private repositories.
     */
    public function add_auth_header( $args, $url ) {
        $token = defined( 'COTLAS_GITHUB_TOKEN' ) ? COTLAS_GITHUB_TOKEN : '';
        if ( $token && false !== strpos( $url, 'github.com' ) ) {
            if ( ! isset( $args['headers'] ) ) {
                $args['headers'] = array();
            }
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        return $args;
    }

    /**
     * Fetch latest release info from GitHub API.
     */
    private function get_release_info() {
        static $release = null;

        if ( null !== $release ) {
            return $release;
        }

        $args = array(
            'headers' => array(
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
            'timeout' => 10,
        );

        $token = defined( 'COTLAS_GITHUB_TOKEN' ) ? COTLAS_GITHUB_TOKEN : '';
        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $url      = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            $release = false;
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );
        return $release;
    }

    /**
     * Tell WordPress there is an update available.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $this->load_plugin_data();
        $release = $this->get_release_info();

        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'v' );

        if ( version_compare( $this->plugin_data['Version'], $remote_version, '<' ) ) {
            // Prefer a release asset ZIP; fall back to source zipball.
            $zip_url = $release['zipball_url'];
            if ( ! empty( $release['assets'] ) ) {
                foreach ( $release['assets'] as $asset ) {
                    if ( isset( $asset['content_type'] ) && $asset['content_type'] === 'application/zip' ) {
                        $zip_url = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . $this->github_repo,
                'package'     => $zip_url,
                'icons'       => array(),
            );
        }

        return $transient;
    }

    /**
     * Show plugin info in the update popup.
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( dirname( $this->plugin_slug ) !== $args->slug ) {
            return $result;
        }

        $this->load_plugin_data();
        $release = $this->get_release_info();

        if ( ! $release ) {
            return $result;
        }

        return (object) array(
            'name'          => $this->plugin_data['Name'],
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => ltrim( $release['tag_name'], 'v' ),
            'author'        => $this->plugin_data['Author'],
            'homepage'      => $this->plugin_data['PluginURI'],
            'sections'      => array(
                'description' => $this->plugin_data['Description'],
                'changelog'   => nl2br( isset( $release['body'] ) ? esc_html( $release['body'] ) : '' ),
            ),
            'download_link' => isset( $release['zipball_url'] ) ? $release['zipball_url'] : '',
        );
    }

    /**
     * Detect a release whose tag does not match the version in the plugin header.
     *
     * GitHub builds a release from a tag, so the header inside the package is
     * expected to carry that same version. When it does not, WordPress offers the
     * update forever: the files install correctly, but the freshly read header
     * still reports the old version, so version_compare() keeps matching.
     * Record the mismatch so it becomes a visible admin warning instead.
     *
     * @param WP_Upgrader $upgrader   Upgrader instance that ran the update.
     * @param array       $hook_extra Update context passed by the upgrader.
     */
    public function detect_release_version_drift( $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return;
        }

        // Only judge a completed install: a failed upgrade leaves the old files
        // (and their correct version header) in place.
        if ( ! isset( $upgrader->result ) || ! is_array( $upgrader->result ) ) {
            return;
        }

        if ( ! empty( $hook_extra['plugins'] ) ) {
            $updated = (array) $hook_extra['plugins'];
        } elseif ( ! empty( $hook_extra['plugin'] ) ) {
            $updated = array( $hook_extra['plugin'] );
        } else {
            return;
        }

        if ( ! in_array( $this->plugin_slug, $updated, true ) ) {
            return;
        }

        $release = $this->get_release_info();
        if ( ! $release || empty( $release['tag_name'] ) ) {
            return;
        }

        $target = ltrim( $release['tag_name'], 'v' );

        // Read the header straight off disk: this is the file just installed.
        $installed = get_plugin_data( $this->file, false, false );

        if ( ! empty( $installed['Version'] ) && version_compare( $installed['Version'], $target, '<' ) ) {
            update_option(
                self::VERSION_MISMATCH_OPTION,
                array(
                    'tag'       => $release['tag_name'],
                    'version'   => $target,
                    'installed' => $installed['Version'],
                ),
                false
            );
            return;
        }

        delete_option( self::VERSION_MISMATCH_OPTION );
    }

    /**
     * Surface a detected release/header version mismatch to administrators.
     */
    public function render_release_version_drift_notice() {
        $mismatch = get_option( self::VERSION_MISMATCH_OPTION );
        if ( ! is_array( $mismatch ) || empty( $mismatch['version'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Self-heal: once the header catches up, the warning is stale.
        $this->load_plugin_data();
        if ( version_compare( $this->plugin_data['Version'], $mismatch['version'], '>=' ) ) {
            delete_option( self::VERSION_MISMATCH_OPTION );
            return;
        }

        $message = sprintf(
            /* translators: 1: release tag, 2: version reported by the installed plugin header, 3: version the tag expects, 4: plugin main file. */
            __( 'Release %1$s was installed, but its plugin header still reports version %2$s. WordPress will keep offering this update until they match. Bump "Version:" in %4$s to %3$s, commit, and re-tag the release.', 'cotlas-admin' ),
            '<code>' . esc_html( $mismatch['tag'] ) . '</code>',
            '<code>' . esc_html( $mismatch['installed'] ) . '</code>',
            '<code>' . esc_html( $mismatch['version'] ) . '</code>',
            '<code>' . esc_html( plugin_basename( $this->file ) ) . '</code>'
        );

        echo wp_kses_post(
            '<div class="notice notice-error"><p><strong>' .
            esc_html__( 'Cotlas Admin updater:', 'cotlas-admin' ) .
            '</strong> ' . $message . '</p></div>'
        );
    }

    /**
     * After install: rename GitHub's auto-generated folder to cotlas-admin/.
     * GitHub source ZIPs extract to something like USERNAME-cotlas-admin-abc123/.
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $result;
        }

        $dest = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );

        if ( $wp_filesystem->exists( $dest ) ) {
            $wp_filesystem->delete( $dest, true );
        }

        $wp_filesystem->move( $result['destination'], $dest );
        $result['destination'] = $dest;

        return $result;
    }
}