<?php
/**
 * Compatibility class for the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Compatibility;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compatibility
 *
 * Handles the HPOS compatibility checking and caching functionality.
 */
class Compatibility {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_hpos_get_all_plugins_compatibility', array( $this, 'get_all_plugins_compatibility' ) );
		add_action( 'wp_ajax_hpos_refresh_compatibility_cache', array( $this, 'refresh_compatibility_cache' ) );
	}

	/**
	 * Checks if a plugin declares HPOS compatibility.
	 *
	 * @param string $plugin_path   Path to the plugin directory.
	 * @param bool   $force_refresh Whether to force a refresh of the cached result.
	 *
	 * @return bool True if the plugin declares HPOS compatibility, false otherwise.
	 * @since 1.0.2
	 */
	public function check_hpos_compatibility( string $plugin_path, bool $force_refresh = false ): bool {
		if ( ! is_dir( $plugin_path ) || ! is_readable( $plugin_path ) ) {
			return false;
		}

		$transient_key = 'hpos_compatibility_' . md5( $plugin_path );

		if ( ! $force_refresh ) {
			$cached_result = get_transient( $transient_key );
			if ( false !== $cached_result ) {
				return (bool) $cached_result;
			}
		}

		$is_compatible = false;

		try {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_path ) );

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'php' ) {
					$file_path = $file->getPathname();

					if ( ! is_readable( $file_path ) ) {
						continue;
					}

					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$contents = file_get_contents( $file_path );
					if ( false === $contents ) {
						continue;
					}

					// Primary and most accurate check using regex.
					if ( preg_match( "/FeaturesUtil::declare_compatibility\s*\(\s*['\"]custom_order_tables['\"]\s*,/i", $contents ) ) {
						$is_compatible = true;
						break;
					}

					// Secondary: look for custom-named helper functions.
					if (
						stripos( $contents, 'declare_hpos_compatibility' ) !== false ||
						stripos( $contents, 'declare_wc_hpos_compatibility' ) !== false ||
						stripos( $contents, 'woocommerce_hpos_compatible' ) !== false
					) {
						$is_compatible = true;
						break;
					}

					// Check for common hook usage (less reliable but signals intent).
					if (
						stripos( $contents, 'custom_order_tables' ) !== false &&
						( stripos( $contents, 'before_woocommerce_init' ) !== false || stripos( $contents, 'plugins_loaded' ) !== false )
					) {
						$is_compatible = true;
						break;
					}
				}
			}
		} catch ( Exception $e ) {
			$is_compatible = false;
		}

		set_transient( $transient_key, $is_compatible, 86400 );

		return $is_compatible;
	}

	/**
	 * AJAX handler to get compatibility status for all plugins.
	 *
	 * @return void
	 * @since 1.0.2
	 */
	public function get_all_plugins_compatibility() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hpos_scanner_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'hpos-compatibility-scanner' ) ) );
		}

		// Check if we should force a refresh of the cache.
		$force_refresh = isset( $_POST['force_refresh'] ) && 'true' === $_POST['force_refresh'];

		$plugins      = get_plugins();
		$results      = array();
		$cache_status = $force_refresh ? 'refreshed' : 'cached';

		foreach ( $plugins as $path => $details ) {
			$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $path );

			// Skip files directly in the plugins directory.
			if ( dirname( $path ) === '.' ) {
				$plugin_dir = WP_PLUGIN_DIR . '/' . basename( $path, '.php' );

				// If it's still not a directory, skip it.
				if ( ! is_dir( $plugin_dir ) ) {
					$results[ $path ] = array(
						'name'       => $details['Name'],
						'version'    => $details['Version'],
						'author'     => $details['Author'],
						'compatible' => false,
					);
					continue;
				}
			}

			$is_compatible = $this->check_hpos_compatibility( $plugin_dir, $force_refresh );

			$results[ $path ] = array(
				'name'       => $details['Name'],
				'version'    => $details['Version'],
				'author'     => $details['Author'],
				'compatible' => $is_compatible,
			);
		}

		wp_send_json_success(
			array(
				'plugins'      => $results,
				'cache_status' => $cache_status,
				'last_updated' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * AJAX handler to refresh the compatibility cache for all plugins.
	 *
	 * @return void
	 * @since 1.0.2
	 */
	public function refresh_compatibility_cache() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'hpos_scanner_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'hpos-compatibility-scanner' ) ) );
		}

		// Get all plugins.
		$plugins = get_plugins();

		// Clear and refresh the cache for each plugin.
		foreach ( $plugins as $path => $details ) {
			$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $path );

			// Skip files directly in the plugins directory.
			if ( dirname( $path ) === '.' ) {
				$plugin_dir = WP_PLUGIN_DIR . '/' . basename( $path, '.php' );

				// If it's still not a directory, skip it.
				if ( ! is_dir( $plugin_dir ) ) {
					continue;
				}
			}

			// Create the transient key.
			$transient_key = 'hpos_compatibility_' . md5( $plugin_dir );

			// Delete the transient to force a refresh.
			delete_transient( $transient_key );

			// Refresh the cache.
			$this->check_hpos_compatibility( $plugin_dir, true );
		}

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Compatibility cache refreshed successfully.', 'hpos-compatibility-scanner' ),
				'last_updated' => current_time( 'mysql' ),
			)
		);
	}
}
