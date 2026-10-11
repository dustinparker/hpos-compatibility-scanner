<?php
/**
 * Scanner class for the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Scanner;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) || exit;

/**
 * Class Scanner
 *
 * Handles the scanning functionality for HPOS compatibility.
 */
class Scanner {

	/**
	 * Scanner that runs the scan, or null to create one when a scan runs.
	 *
	 * @var Directory_Scanner|null
	 */
	private ?Directory_Scanner $directory_scanner;

	/**
	 * Constructor.
	 *
	 * @param Directory_Scanner|null $directory_scanner Internal, for tests: the scanner that runs the scan. This parameter may change without notice.
	 */
	public function __construct( ?Directory_Scanner $directory_scanner = null ) {
		$this->directory_scanner = $directory_scanner;
		add_action( 'wp_ajax_hpos_scan_plugin', array( $this, 'scan_plugin' ) );
	}

	/**
	 * Handles the AJAX request to scan the selected plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function scan_plugin() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['plugin'] ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hpos_scanner_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'hpos-compatibility-scanner' ) ) );
		}

		$plugin      = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin;

		if ( ! file_exists( $plugin_path ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugin file does not exist.', 'hpos-compatibility-scanner' ) ) );
		}

		if ( is_file( $plugin_path ) ) {
			$plugin_path = dirname( $plugin_path );
		}

		if ( ! is_dir( $plugin_path ) || ! is_readable( $plugin_path ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plugin directory not found or inaccessible.', 'hpos-compatibility-scanner' ) ) );
		}

		// Variable to track if HPOS compatibility is declared.
		$hpos_compatible = $this->check_hpos_compatibility( $plugin_path );

		$scanner = $this->directory_scanner ?? new Directory_Scanner();

		try {
			$results = $scanner->scan( $plugin_path );
		} catch ( Directory_Open_Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error reading plugin directory: ', 'hpos-compatibility-scanner' ) . $e->getMessage() ) );
		}

		if ( empty( $results ) ) {
			wp_send_json_success(
				array(
					'hpos_compatible' => $hpos_compatible,
					'issues'          => array(),
					'message'         => esc_html__( 'No issues found in the selected plugin.', 'hpos-compatibility-scanner' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'hpos_compatible' => $hpos_compatible,
				'issues'          => $results,
			)
		);
	}

	/**
	 * Checks if a plugin declares HPOS compatibility.
	 *
	 * @param string $plugin_path Path to the plugin directory.
	 *
	 * @return bool True if the plugin declares HPOS compatibility, false otherwise.
	 * @since 1.0.2
	 */
	private function check_hpos_compatibility( string $plugin_path ): bool {
		if ( ! is_dir( $plugin_path ) || ! is_readable( $plugin_path ) ) {
			return false;
		}

		// Patterns to look for HPOS compatibility declarations.
		$hpos_compatibility_patterns = array(
			'FeaturesUtil::declare_compatibility',
			'declare_compatibility_with',
			'custom_order_tables',
			'before_woocommerce_init',
			'declare_hpos_compatibility',
			'declare_wc_hpos_compatibility',
			'woocommerce_hpos_compatible',
		);

		try {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_path ) );

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'php' ) {
					$file_path = $file->getPathname();

					// Skip unreadable files.
					if ( ! is_readable( $file_path ) ) {
						continue;
					}

					// Read file contents.
					$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					if ( false === $contents ) {
						continue;
					}

					// Check for HPOS compatibility patterns.
					foreach ( $hpos_compatibility_patterns as $pattern ) {
						if ( stripos( $contents, $pattern ) !== false ) {
							// If we find a pattern, check the context to confirm it's a compatibility declaration.
							if (
								( stripos( $contents, 'before_woocommerce_init' ) !== false &&
									( stripos( $contents, 'declare_compatibility' ) !== false ||
									stripos( $contents, 'hpos_compatibility' ) !== false ||
									stripos( $contents, 'hpos_compatible' ) !== false )
								) ||
								( stripos( $contents, 'FeaturesUtil::declare_compatibility' ) !== false &&
									stripos( $contents, 'custom_order_tables' ) !== false )
							) {
								return true;
							}
						}
					}
				}
			}
		} catch ( Exception $e ) {
			// If there's an error reading the directory, return false.
			return false;
		}

		return false;
	}
}
