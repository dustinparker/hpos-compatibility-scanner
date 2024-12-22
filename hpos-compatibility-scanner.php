<?php
/**
 * Plugin Name: HPOS Compatibility Scanner
 * Description: Scans plugins for potential HPOS compatibility issues by checking for direct database access or inappropriate WordPress API usage.
 * Version: 1.0.0
 * Author: Robert DeVore
 * Author URI: https://robertdevore.com/
 * License: GPL-2.0+
 * Text Domain: hpos-compatibility-scanner
 * Domain Path: /languages
 * Update URI: https://github.com/robertdevore/hpos-compatibility-scanner/
 */

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Set up update checker.
require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/robertdevore/hpos-compatibility-scanner/',
    __FILE__,
    'hpos-compatibility-scanner'
);
$myUpdateChecker->setBranch( 'main' );

// Current plugin version.
define( 'HPOS_COMPATIBILITY_SCANNER_VERSION', '1.0.0' );

/**
 * Load plugin textdomain for translations.
 * 
 * @since  1.0.0
 * @return void
 */
function hpos_load_textdomain() {
    load_plugin_textdomain(
        'hpos-compatibility-scanner',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'hpos_load_textdomain' );

/**
 * Class HPOS_Compatibility_Scanner
 *
 * This class handles the functionality for the HPOS Compatibility Scanner plugin.
 * It registers the admin settings page, enqueues required assets, and processes
 * AJAX requests to scan selected plugins for potential HPOS compatibility issues.
 *
 * @since 1.0.0
 */
class HPOS_Compatibility_Scanner {

    /**
     * Constructor.
     *
     * Initializes the plugin by setting up hooks for admin menu registration,
     * script and style enqueuing, and AJAX request handling.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_hpos_scan_plugin', [ $this, 'scan_plugin' ] );
    }

    /**
     * Registers the settings page in the WordPress admin menu.
     * 
     * @since  1.0.0
     * @return void
     */
    public function register_settings_page() {
        add_menu_page(
            esc_html__( 'HPOS Scanner', 'hpos-compatibility-scanner' ),
            esc_html__( 'HPOS Scanner', 'hpos-compatibility-scanner' ),
            'manage_options',
            'hpos-scanner',
            [ $this, 'render_settings_page' ],
            'dashicons-yes-alt'
        );
    }

    /**
     * Enqueues scripts and styles for the settings page.
     *
     * @param string $hook Current admin page hook.
     * 
     * @since  1.0.0
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'toplevel_page_hpos-scanner' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'hpos-scanner', plugin_dir_url( __FILE__ ) . 'assets/js/hpos-scanner.js', [ 'jquery' ], HPOS_COMPATIBILITY_SCANNER_VERSION, true );
        wp_localize_script( 'hpos-scanner', 'HPOSScanner', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'i18n'     => [
                'select_plugin'         => esc_html__( 'Please select a plugin to scan.', 'hpos-compatibility-scanner' ),
                'scanning'              => esc_html__( 'Scanning...', 'hpos-compatibility-scanner' ),
                'error'                 => esc_html__( 'Error: ', 'hpos-compatibility-scanner' ),
                'unable_to_complete'    => esc_html__( 'Unable to complete the scan. Please check the server logs for details.', 'hpos-compatibility-scanner' ),
                'no_issues_found'       => esc_html__( 'No issues found in the selected plugin.', 'hpos-compatibility-scanner' ),
                'download_csv'          => esc_html__( 'Download CSV', 'hpos-compatibility-scanner' ),
                'file'                  => esc_html__( 'File', 'hpos-compatibility-scanner' ),
                'term'                  => esc_html__( 'Term', 'hpos-compatibility-scanner' )
            ]
        ] );

        wp_enqueue_style( 'hpos-scanner-styles', plugin_dir_url( __FILE__ ) . 'assets/css/hpos-scanner.css', [], HPOS_COMPATIBILITY_SCANNER_VERSION );
    }

    /**
     * Renders the settings page HTML.
     * 
     * @since  1.0.0
     * @return void
     */
    public function render_settings_page() {
        $plugins = get_plugins();
        echo '<div class="wrap hpos-settings-page">';
        echo '<h1>' . esc_html__( 'HPOS Compatibility Scanner', 'hpos-compatibility-scanner' );
        echo '<a id="hpos-scanner-support-btn" href="https://robertdevore.com/contact/" target="_blank" class="button button-alt" style="margin-left: 10px;">
                <span class="dashicons dashicons-format-chat" style="vertical-align: middle;"></span> ' . esc_html__( 'Support', 'hpos-compatibility-scanner' ) . '
            </a>
            <a id="hpos-scanner-docs-btn" href="https://robertdevore.com/articles/hpos-compatibility-scanner/" target="_blank" class="button button-alt" style="margin-left: 5px;">
                <span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span> ' . esc_html__( 'Documentation', 'hpos-compatibility-scanner' ) . '
            </a>';
        echo '</h1><hr />';
        echo '<div class="hpos-controls">';
        echo '<label for="hpos-plugin-selector" class="hpos-label">' . esc_html__( 'Select a Plugin to Scan:', 'hpos-compatibility-scanner' ) . '</label>';
        echo '<select id="hpos-plugin-selector" class="hpos-select">';
        echo '<option value="">' . esc_html__( 'Select a Plugin', 'hpos-compatibility-scanner' ) . '</option>';
        foreach ( $plugins as $path => $details ) {
            echo '<option value="' . esc_attr( $path ) . '">' . esc_html( $details['Name'] ) . '</option>';
        }
        echo '</select>';
        echo '<button id="hpos-scan-button" class="button button-primary hpos-scan-button">' . esc_html__( 'Scan Plugin', 'hpos-compatibility-scanner' ) . '</button>';
        echo '</div>';
        echo '<div id="hpos-scan-results" class="hpos-results"></div>';
        echo '</div>';
    }

    /**
     * Handles the AJAX request to scan the selected plugin.
     * 
     * @since  1.0.0
     * @return void
     */
    public function scan_plugin() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['plugin'] ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Unauthorized access.', 'hpos-compatibility-scanner' ) ] );
        }

        $plugin      = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;

        if ( ! file_exists( $plugin_path ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Plugin file does not exist.', 'hpos-compatibility-scanner' ) ] );
        }

        if ( is_file( $plugin_path ) ) {
            $plugin_path = dirname( $plugin_path );
        }

        if ( ! is_dir( $plugin_path ) || ! is_readable( $plugin_path ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Plugin directory not found or inaccessible.', 'hpos-compatibility-scanner' ) ] );
        }

        $search_terms = [
            'wpdb',
            'get_post',
            'get_post_field',
            'get_post_status',
            'get_post_type',
            'get_post_type_object',
            'get_posts',
            'metadata_exists',
            'get_post_meta',
            'get_metadata',
            'get_metadata_raw',
            'get_metadata_default',
            'get_metadata_by_mid',
            'wp_insert_post',
            'add_metadata',
            'add_post_meta',
            'wp_update_post',
            'update_post_meta',
            'update_metadata',
            'update_metadata_by_mid',
            'delete_metadata',
            'delete_post_meta',
            'delete_metadata_by_mid',
            'delete_post_meta_by_key',
            'wp_delete_post',
            'wp_trash_post',
            'wp_untrash_post',
            'wp_transition_post_status',
            'clean_post_cache',
            'update_post_caches',
            'update_postmeta_cache',
            'post_exists',
            'wp_count_post',
            'shop_order'
        ];

        // Filter the search terms.
        $search_terms = apply_filters( 'hpos_compatibility_scanner_search_terms', $search_terms );

        $results = [];

        try {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_path ) );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Error reading plugin directory: ', 'hpos-compatibility-scanner' ) . $e->getMessage() ] );
        }

        foreach ( $iterator as $file ) {
            if ( $file->isFile() && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'php' ) {
                $contents = @file_get_contents( $file->getPathname() );
                // Skip unreadable files.
                if ( $contents === false ) {
                    continue;
                }
                // Loop through the search terms.
                foreach ( $search_terms as $term ) {
                    if ( stripos( $contents, $term ) !== false ) {
                        $results[] = [
                            'file' => str_replace( WP_PLUGIN_DIR, '', $file->getPathname() ),
                            'term' => $term
                        ];
                    }
                }
            }
        }

        if ( empty( $results ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'No issues found in the selected plugin.', 'hpos-compatibility-scanner' ) ] );
        }

        wp_send_json_success( $results );
    }
}

new HPOS_Compatibility_Scanner();

/**
 * Helper function to handle WordPress.com environment checks.
 *
 * @param string $plugin_slug     The plugin slug.
 * @param string $learn_more_link The link to more information.
 * 
 * @since  1.1.0
 * @return bool
 */
function wp_com_plugin_check( $plugin_slug, $learn_more_link ) {
    // Check if the site is hosted on WordPress.com.
    if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
        // Ensure the deactivate_plugins function is available.
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Deactivate the plugin if in the admin area.
        if ( is_admin() ) {
            deactivate_plugins( $plugin_slug );

            // Add a deactivation notice for later display.
            add_option( 'wpcom_deactivation_notice', $learn_more_link );

            // Prevent further execution.
            return true;
        }
    }

    return false;
}

/**
 * Auto-deactivate the plugin if running in an unsupported environment.
 *
 * @since  1.1.0
 * @return void
 */
function wpcom_auto_deactivation() {
    if ( wp_com_plugin_check( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ) ) {
        return; // Stop execution if deactivated.
    }
}
add_action( 'plugins_loaded', 'wpcom_auto_deactivation' );

/**
 * Display an admin notice if the plugin was deactivated due to hosting restrictions.
 *
 * @since  1.1.0
 * @return void
 */
function wpcom_admin_notice() {
    $notice_link = get_option( 'wpcom_deactivation_notice' );
    if ( $notice_link ) {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        __( 'HPOS Compatibility Scanner has been deactivated because it cannot be used on WordPress.com-hosted websites. %s', 'hpos-compatibility-scanner' ),
                        '<a href="' . esc_url( $notice_link ) . '" target="_blank" rel="noopener">' . __( 'Learn more', 'hpos-compatibility-scanner' ) . '</a>'
                    )
                );
                ?>
            </p>
        </div>
        <?php
        delete_option( 'wpcom_deactivation_notice' );
    }
}
add_action( 'admin_notices', 'wpcom_admin_notice' );

/**
 * Prevent plugin activation on WordPress.com-hosted sites.
 *
 * @since  1.1.0
 * @return void
 */
function wpcom_activation_check() {
    if ( wp_com_plugin_check( plugin_basename( __FILE__ ), 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ) ) {
        // Display an error message and stop activation.
        wp_die(
            wp_kses_post(
                sprintf(
                    '<h1>%s</h1><p>%s</p><p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
                    __( 'Plugin Activation Blocked', 'hpos-compatibility-scanner' ),
                    __( 'This plugin cannot be activated on WordPress.com-hosted websites. It is restricted due to concerns about WordPress.com policies impacting the community.', 'hpos-compatibility-scanner' ),
                    esc_url( 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' ),
                    __( 'Learn more', 'hpos-compatibility-scanner' )
                )
            ),
            esc_html__( 'Plugin Activation Blocked', 'hpos-compatibility-scanner' ),
            [ 'back_link' => true ]
        );
    }
}
register_activation_hook( __FILE__, 'wpcom_activation_check' );

/**
 * Add a deactivation flag when the plugin is deactivated.
 *
 * @since  1.1.0
 * @return void
 */
function wpcom_deactivation_flag() {
    add_option( 'wpcom_deactivation_notice', 'https://robertdevore.com/why-this-plugin-doesnt-support-wordpress-com-hosting/' );
}
register_deactivation_hook( __FILE__, 'wpcom_deactivation_flag' );
