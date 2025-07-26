<?php
/**
 * Admin class for the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin\Admin
 */

namespace DPWD\HPOSCompatPlugin\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 *
 * Handles the admin interface and settings page.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
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
			array( $this, 'render_settings_page' ),
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

		wp_enqueue_script(
			'hpos-scanner',
			HPOS_COMPATIBILITY_SCANNER_PLUGIN_URL . '/assets/js/hpos-scanner.js',
			array(),
			HPOS_COMPATIBILITY_SCANNER_VERSION,
			true
		);

		wp_localize_script(
			'hpos-scanner',
			'HPOSScanner',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hpos_scanner_nonce' ),
				'i18n'     => array(
					'select_plugin'       => esc_html__( 'Please select a plugin to scan.', 'hpos-compatibility-scanner' ),
					'scanning'            => esc_html__( 'Scanning...', 'hpos-compatibility-scanner' ),
					'error'               => esc_html__( 'Error: ', 'hpos-compatibility-scanner' ),
					'unable_to_complete'  => esc_html__( 'Unable to complete the scan. Please check the server logs for details.', 'hpos-compatibility-scanner' ),
					'no_issues_found'     => esc_html__( 'No issues found in the selected plugin.', 'hpos-compatibility-scanner' ),
					'download_csv'        => esc_html__( 'Download CSV', 'hpos-compatibility-scanner' ),
					'file'                => esc_html__( 'File', 'hpos-compatibility-scanner' ),
					'term'                => esc_html__( 'Term', 'hpos-compatibility-scanner' ),
					'description'         => esc_html__( 'Description', 'hpos-compatibility-scanner' ),
					'line'                => esc_html__( 'Line', 'hpos-compatibility-scanner' ),
					'code'                => esc_html__( 'Code', 'hpos-compatibility-scanner' ),
					'snippet'             => esc_html__( 'Snippet', 'hpos-compatibility-scanner' ),
					'view_snippet'        => esc_html__( 'View Snippet', 'hpos-compatibility-scanner' ),
					'hide_snippet'        => esc_html__( 'Hide Snippet', 'hpos-compatibility-scanner' ),
					'hpos_compatible'     => esc_html__( 'This plugin declares HPOS compatibility.', 'hpos-compatibility-scanner' ),
					'hpos_not_compatible' => esc_html__( 'This plugin does not declare HPOS compatibility.', 'hpos-compatibility-scanner' ),
					'scan_plugin'         => esc_html__( 'Scan Plugin', 'hpos-compatibility-scanner' ),
					'refreshing_cache'    => esc_html__( 'Refreshing compatibility cache...', 'hpos-compatibility-scanner' ),
					'cache_refreshed'     => esc_html__( 'Compatibility cache refreshed successfully.', 'hpos-compatibility-scanner' ),
				),
			)
		);

		wp_enqueue_style(
			'hpos-scanner-styles',
			HPOS_COMPATIBILITY_SCANNER_PLUGIN_URL . '/assets/css/hpos-scanner.css',
			array(),
			HPOS_COMPATIBILITY_SCANNER_VERSION
		);
	}

	/**
	 * Renders the settings page HTML.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		$plugins = get_plugins();

		// Load the template.
		// @phpstan-ignore-next-line.
		require_once HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH . '/templates/admin/settings-page.php';
	}
}
