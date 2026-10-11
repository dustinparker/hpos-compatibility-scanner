<?php
/**
 * Directory scanner for the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Scanner;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined( 'ABSPATH' ) || exit;

/**
 * Class Directory_Scanner
 *
 * Walks a folder and returns the lines that match the search terms.
 *
 * @internal This class may change without notice.
 * @since 1.0.3
 */
class Directory_Scanner {

	/**
	 * Scan a folder for lines that may be HPOS compatibility problems.
	 *
	 * A subfolder that cannot be read makes the walk throw its own UnexpectedValueException, which is not wrapped.
	 *
	 * @param string $directory Folder to scan.
	 *
	 * @return array List of findings in the order of the walk.
	 * @throws Directory_Open_Exception When the folder cannot be opened.
	 * @since 1.0.3
	 */
	public function scan( string $directory ): array {
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
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );
		} catch ( Exception $e ) {
			throw new Directory_Open_Exception( $e->getMessage(), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The handler sends the message in JSON, as it did before the move.
		}

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) === 'php' ) {
				$file_path     = $file->getPathname();
				$relative_path = str_replace( WP_PLUGIN_DIR, '', $file_path );

				// Skip unreadable files.
				if ( ! is_readable( $file_path ) ) {
					continue;
				}

				// Read the file line by line.
				$lines = file( $file_path, FILE_IGNORE_NEW_LINES );
				if ( false === $lines ) {
					continue;
				}

				// Process each line.
				foreach ( $lines as $line_number => $line ) {
					++$line_number; // Line numbers start at 1, not 0.

					// Skip if it's a comment line.
					if ( preg_match( '/^\s*\/\//', $line ) || preg_match( '/^\s*\*/', $line ) ) {
						continue;
					}

					// Check each search term.
					foreach ( $search_terms as $term ) {
						$is_match         = false;
						$term_display     = '';
						$term_description = '';

						// Handle both string terms and regex pattern arrays.
						if ( is_array( $term ) ) {
							// This is a regex pattern with context.
							if ( preg_match( $term['pattern'], $line ) ) {
								$is_match = true;
								// Use type or a shorter identifier for term_display.
								$term_display = isset( $term['type'] ) ? ucfirst( str_replace( '_', ' ', $term['type'] ) ) : 'Pattern match';
								// Use the full description for term_description.
								$term_description = $term['description'];
							}
						} elseif ( stripos( $line, $term ) !== false ) {
							$is_match = true;
							// Use the actual term for term_display.
							$term_display = $term;
							// Generate a more descriptive explanation for term_description.
							$term_description = 'Found "' . $term . '" which may indicate direct database access or use of deprecated APIs.';
						}

						if ( $is_match ) {
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

							// For multi-line context analysis (if needed).
							$context_before = '';
							$context_after  = '';

							// Get a few lines before for context.
							$context_start = max( 0, $line_number - 5 );
							if ( $context_start < $line_number - 1 ) {
								$context_before = implode( "\n", array_slice( $lines, $context_start, $line_number - $context_start - 1 ) );
							}

							// Get a few lines after for context.
							$context_end = min( count( $lines ) - 1, $line_number + 5 );
							if ( $line_number < $context_end ) {
								$context_after = implode( "\n", array_slice( $lines, $line_number, $context_end - $line_number ) );
							}

							// Extract the code snippet with context.
							$start_line = max( 0, $line_number - $context_lines - 1 );
							$end_line   = min( count( $lines ) - 1, $line_number + $context_lines - 1 );
							$snippet    = array_slice( $lines, $start_line, $end_line - $start_line + 1 );

							// Format the snippet with line numbers.
							$formatted_snippet = '';
							$snippet_count     = count( $snippet );
							for ( $i = 0; $i < $snippet_count; $i++ ) {
								$current_line = $start_line + $i + 1;
								$line_marker  = ( $line_number === $current_line ) ? '>' : ' ';

								$formatted_snippet .= sprintf( "%s %4d: %s\n", $line_marker, $current_line, htmlspecialchars( $snippet[ $i ] ) );
							}

							// Add to results.
							$results[] = array(
								'file'        => $relative_path,
								'term'        => $term_display,
								'description' => $term_description,
								'line'        => $line_number,
								'snippet'     => trim( $formatted_snippet ),
								'code'        => htmlspecialchars( $line ),
								'context'     => array(
									'before' => $context_before,
									'after'  => $context_after,
								),
							);
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Get the search terms for scanning.
	 *
	 * Each search term can be either:
	 * 1. A simple string (for backward compatibility and simple cases)
	 * 2. An array with 'pattern' (regex), 'type' (for categorization), and 'description' (for reporting)
	 *
	 * @return array Array of search terms and patterns.
	 */
	private function get_search_terms(): array {
		return array(
			// --- General Order Post Type Identifiers ---
			// More specific regex to match actual order post-type usage
			array(
				'pattern'     => '/[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order post type in array',
			),
			array(
				'pattern'     => '/[\'"]post_type[\'"]\\s*=\\s*[\'"]shop_order[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order post type in query',
			),
			array(
				'pattern'     => '/post_type\\s*=\\s*[\'"]shop_order[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order post type in query string',
			),
			// Similar patterns for shop_order_refund.
			array(
				'pattern'     => '/[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order_refund[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order refund post type in array',
			),
			array(
				'pattern'     => '/[\'"]post_type[\'"]\\s*=\\s*[\'"]shop_order_refund[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order refund post type in query',
			),
			array(
				'pattern'     => '/post_type\\s*=\\s*[\'"]shop_order_refund[\'"]/',
				'type'        => 'order_post_type',
				'description' => 'Order refund post type in query string',
			),

			// --- Core Table References ---
			// Direct table references with context
			array(
				'pattern'     => '/\\$wpdb->(?:prefix\\s*\\.\\s*)?[\'"]?posts[\'"]?/',
				'type'        => 'db_table',
				'description' => 'Direct reference to posts table',
			),
			array(
				'pattern'     => '/\\$wpdb->(?:prefix\\s*\\.\\s*)?[\'"]?postmeta[\'"]?/',
				'type'        => 'db_table',
				'description' => 'Direct reference to postmeta table',
			),
			array(
				'pattern'     => '/(?:FROM|JOIN|UPDATE|INTO)\\s+(?:\\w+_)?posts\\b/',
				'type'        => 'db_query',
				'description' => 'SQL query referencing posts table',
			),
			array(
				'pattern'     => '/(?:FROM|JOIN|UPDATE|INTO)\\s+(?:\\w+_)?postmeta\\b/',
				'type'        => 'db_query',
				'description' => 'SQL query referencing postmeta table',
			),
			// Direct table names (less reliable but still needed).
			array(
				'pattern'     => '/\\bwp_posts\\b/',
				'type'        => 'db_table',
				'description' => 'Direct wp_posts table name',
			),
			array(
				'pattern'     => '/\\bwp_postmeta\\b/',
				'type'        => 'db_table',
				'description' => 'Direct wp_postmeta table name',
			),

			// --- Legacy Post Access ---
			// WordPress post functions with context
			array(
				'pattern'     => '/get_post\\s*\\(\\s*\\$(?:order|order_id)/',
				'type'        => 'wp_function',
				'description' => 'get_post with order variable',
			),
			array(
				'pattern'     => '/get_post_meta\\s*\\(\\s*\\$(?:order|order_id)/',
				'type'        => 'wp_function',
				'description' => 'get_post_meta with order variable',
			),
			array(
				'pattern'     => '/update_post_meta\\s*\\(\\s*\\$(?:order|order_id)/',
				'type'        => 'wp_function',
				'description' => 'update_post_meta with order variable',
			),
			array(
				'pattern'     => '/delete_post_meta\\s*\\(\\s*\\$(?:order|order_id)/',
				'type'        => 'wp_function',
				'description' => 'delete_post_meta with order variable',
			),
			// WP_Query with order context.
			array(
				'pattern'     => '/new\\s+WP_Query\\s*\\(\\s*\\{?[^}]*[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order[\'"]/',
				'type'        => 'wp_class',
				'description' => 'WP_Query with shop_order post type',
			),
			array(
				'pattern'     => '/new\\s+WP_Query\\s*\\(\\s*\\{?[^}]*[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order_refund[\'"]/',
				'type'        => 'wp_class',
				'description' => 'WP_Query with shop_order_refund post type',
			),
			// get_posts with order context.
			array(
				'pattern'     => '/get_posts\\s*\\(\\s*\\{?[^}]*[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order[\'"]/',
				'type'        => 'wp_function',
				'description' => 'get_posts with shop_order post type',
			),
			array(
				'pattern'     => '/get_posts\\s*\\(\\s*\\{?[^}]*[\'"]post_type[\'"]\\s*=>\\s*[\'"]shop_order_refund[\'"]/',
				'type'        => 'wp_function',
				'description' => 'get_posts with shop_order_refund post type',
			),

			// --- Legacy WooCommerce Order APIs ---
			// WC_Order instantiation
			array(
				'pattern'     => '/new\\s+WC_Order\\s*\\(/',
				'type'        => 'wc_class',
				'description' => 'WC_Order instantiation',
			),
			array(
				'pattern'     => '/new\\s+WC_Order_Query\\s*\\(/',
				'type'        => 'wc_class',
				'description' => 'WC_Order_Query instantiation',
			),
			// Other WooCommerce specific patterns.
			'WC()->order_factory',
			'woocommerce_order_data_store_cpt',
			'woocommerce_order_get_items',
			'woocommerce_before_order_object_save',

			// --- Legacy REST API Endpoints ---
			// These are specific enough to keep as strings
			'/wc/v1/orders',
			'/wc/v2/orders',
			'/wc-api/v3/orders',
			'wc-api=wc-orders',
		);
	}

	/**
	 * Get the safe patterns to exclude from scanning.
	 *
	 * @return array Array of safe patterns.
	 */
	private function get_safe_patterns(): array {
		return array(
			// --- HPOS-Compatible WooCommerce Functions ---
			'wc_get_order',
			'wc_update_order',
			'wc_delete_order',
			'wc_get_orders',
			'wc_get_order_id_by_order_key',
			'wc_get_order_types',
			'wc_get_order_statuses',
			'WC_Order_Data_Store_CPT',
			'WC_Order_Data_Store_Custom_Table',

			// --- Comments / Docblocks / Documentation ---
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

			// --- Language / Structure Keywords ---
			'function',
			'class',
			'interface',
			'trait',
			'abstract',
			'extends',
			'implements',

			// --- Other CPTs (safe) ---
			'post_type = \'product\'',
			'post_type = "product"',
			'post_type = \'page\'',
			'post_type = "page"',
			'post_type = \'post\'',
			'post_type = "post"',

			// --- Safe DB schema / install logic ---
			'CREATE TABLE',
			'ALTER TABLE',
			'DROP TABLE',
			'dbDelta',

			// --- Debug/Test Code ---
			'test_',
			'debug_',
			'is_admin()',
			'if ( is_admin() )',
			'if (is_admin())',
		);
	}
}
