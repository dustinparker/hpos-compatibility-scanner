<?php
/**
 * Scanner class for the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin\Scanner
 */

namespace DPWD\HPOSCompatPlugin\Scanner;

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
	 * Constructor.
	 */
	public function __construct() {
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

		$search_terms = $this->get_search_terms();

		/**
		 * Filter the search terms used for scanning.
		 *
		 * @since 1.0.0
		 * @param array $search_terms Array of search terms.
		 */
		$search_terms = apply_filters( 'hpos_compatibility_scanner_search_terms', $search_terms );

		// Define safe patterns that should be excluded (to reduce false positives).
		$safe_patterns = $this->get_safe_patterns();

		/**
		 * Filter the safe patterns used to exclude false positives.
		 *
		 * @since 1.0.0
		 * @param array $safe_patterns Array of safe patterns.
		 */
		$safe_patterns = apply_filters( 'hpos_compatibility_scanner_safe_patterns', $safe_patterns );

		$results       = array();
		$context_lines = 3; // Number of lines before and after the match to include in the snippet.

		try {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_path ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error reading plugin directory: ', 'hpos-compatibility-scanner' ) . $e->getMessage() ) );
		}

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'php' ) {
				$file_path     = $file->getPathname();
				$relative_path = str_replace( WP_PLUGIN_DIR, '', $file_path );

				// Skip unreadable files.
				if ( ! is_readable( $file_path ) ) {
					continue;
				}

				// Read file line by line.
				$lines = file( $file_path, FILE_IGNORE_NEW_LINES );
				if ( false === $lines ) {
					continue;
				}

				// Process each line.
				foreach ( $lines as $line_number => $line ) {
					++$line_number; // Line numbers start at 1, not 0.

					// Check each search term.
					foreach ( $search_terms as $term ) {
						if ( stripos( $line, $term ) !== false ) {
							// Check if this is a false positive by looking for safe patterns.
							$is_false_positive = false;
							foreach ( $safe_patterns as $safe_pattern ) {
								if ( stripos( $line, $safe_pattern ) !== false ) {
									$is_false_positive = true;
									break;
								}
							}

							// Skip if it's a false positive.
							if ( $is_false_positive ) {
								continue;
							}

							// Skip if it's in a comment.
							if ( preg_match( '/^\s*\/\//', $line ) || preg_match( '/^\s*\*/', $line ) ) {
								continue;
							}

							// Extract code snippet with context.
							$start_line = max( 0, $line_number - $context_lines - 1 );
							$end_line   = min( count( $lines ) - 1, $line_number + $context_lines - 1 );
							$snippet    = array_slice( $lines, $start_line, $end_line - $start_line + 1 );

							// Format the snippet with line numbers.
							$formatted_snippet = '';
							$snippet_count     = count( $snippet );
							for ( $i = 0; $i < $snippet_count; $i++ ) {
								$current_line       = $start_line + $i + 1;
								$line_marker        = ( $line_number === $current_line ) ? '>' : ' ';
								$formatted_snippet .= sprintf( "%s %4d: %s\n", $line_marker, $current_line, htmlspecialchars( $snippet[ $i ] ) );
							}

							// Add to results.
							$results[] = array(
								'file'    => $relative_path,
								'term'    => $term,
								'line'    => $line_number,
								'snippet' => trim( $formatted_snippet ),
								'code'    => htmlspecialchars( $line ),
							);
						}
					}
				}
			}
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
	 * @return bool True if the plugin declares HPOS compatibility, false otherwise.
	 * @since 1.0.2
	 */
	private function check_hpos_compatibility( $plugin_path ) {
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
		} catch ( \Exception $e ) {
			// If there's an error reading the directory, return false.
			return false;
		}

		return false;
	}

	/**
	 * Get the search terms for scanning.
	 *
	 * @return array Array of search terms.
	 */
	private function get_search_terms() {
		return array(
			// SQL query patterns for posts table.
			'wp_posts',
			'wp_post',
			'{$wpdb->posts}',
			'{$wpdb->prefix}posts',
			'$wpdb->posts',
			'$wpdb->prefix . "posts"',
			'$wpdb->prefix . \'posts\'',
			'$wpdb->prefix}posts',

			// SQL query patterns for postmeta table.
			'wp_postmeta',
			'{$wpdb->postmeta}',
			'{$wpdb->prefix}postmeta',
			'$wpdb->postmeta',
			'$wpdb->prefix . "postmeta"',
			'$wpdb->prefix . \'postmeta\'',
			'$wpdb->prefix}postmeta',

			// Common table aliases in queries.
			'AS p ON',
			'AS pm ON',
			'JOIN wp_posts',
			'JOIN wp_postmeta',
			'FROM wp_posts',
			'FROM wp_postmeta',

			// Common SQL operations on these tables.
			'SELECT * FROM wp_posts',
			'SELECT * FROM wp_postmeta',
			'INSERT INTO wp_posts',
			'INSERT INTO wp_postmeta',
			'UPDATE wp_posts',
			'UPDATE wp_postmeta',
			'DELETE FROM wp_posts',
			'DELETE FROM wp_postmeta',

			// WooCommerce specific post types in SQL.
			'post_type = \'shop_order\'',
			'post_type = "shop_order"',
			'post_type=\'shop_order\'',
			'post_type="shop_order"',
			'post_type IN (\'shop_order\'',
			'post_type IN ("shop_order"',
		);
	}

	/**
	 * Get the safe patterns to exclude from scanning.
	 *
	 * @return array Array of safe patterns.
	 */
	private function get_safe_patterns() {
		return array(
			// WooCommerce HPOS compatibility function calls.
			'wc_get_order',
			'wc_update_order',
			'wc_delete_order',
			'wc_get_orders',

			// Common exclusions in comments and documentation.
			'// wp_posts',
			'// wp_postmeta',
			'/* wp_posts',
			'/* wp_postmeta',
			'* wp_posts',
			'* wp_postmeta',
			'// $wpdb->posts',
			'// $wpdb->postmeta',
			'/* $wpdb->posts',
			'/* $wpdb->postmeta',
			'* $wpdb->posts',
			'* $wpdb->postmeta',
			'@param',
			'@return',
			'@var',
			'@since',
			'Example:',
			'example:',
			'Example query:',
			'example query:',

			// Function and class definitions (not actual queries).
			'function',
			'class',
			'interface',
			'trait',
			'abstract',
			'extends',
			'implements',

			// Safe usage patterns - non-order related queries.
			'post_type = \'product\'',
			'post_type = "product"',
			'post_type=\'product\'',
			'post_type="product"',
			'post_type = \'page\'',
			'post_type = "page"',
			'post_type=\'page\'',
			'post_type="page"',
			'post_type = \'post\'',
			'post_type = "post"',
			'post_type=\'post\'',
			'post_type="post"',

			// Schema definitions and migrations.
			'CREATE TABLE',
			'ALTER TABLE',
			'DROP TABLE',
			'dbDelta',

			// WooCommerce HPOS compatible methods.
			'wc_get_order_id_by_order_key',
			'wc_get_order_types',
			'wc_get_order_statuses',
			'WC_Order_Data_Store_CPT',
			'WC_Order_Data_Store_Custom_Table',

			// Testing and debugging code.
			'test_',
			'debug_',
			'is_admin()',
			'if ( is_admin() )',
			'if (is_admin())',
		);
	}
}
