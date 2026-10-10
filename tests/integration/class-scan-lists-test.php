<?php
/**
 * Pins the scan results for the built-in search terms and safe strings, and for terms added by filter.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Expected_Response;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;

/**
 * Scan_Lists_Test class.
 */
final class Scan_Lists_Test extends Ajax_Test_Case {

	/**
	 * Built-in search terms, captured through the filter.
	 *
	 * @var array<int, mixed>
	 */
	private array $captured_terms = array();

	/**
	 * Built-in safe strings, captured through the filter.
	 *
	 * @var array<int, string>
	 */
	private array $captured_safe = array();

	/**
	 * Remove the filters a test added.
	 */
	public function tear_down(): void {
		remove_all_filters( 'hpos_compatibility_scanner_search_terms' );
		remove_all_filters( 'hpos_compatibility_scanner_safe_patterns' );

		parent::tear_down();
	}

	/**
	 * Scan a fixture plugin as an administrator.
	 *
	 * @param string $plugin Plugin file relative to the plugins folder.
	 * @return array<string, mixed>
	 */
	private function scan( string $plugin ): array {
		return $this->call( 'hpos_scan_plugin', array( 'plugin' => $plugin ) );
	}

	/**
	 * Read both built-in lists through the filters, which run on every scan.
	 */
	private function capture_built_in_lists(): void {
		add_filter(
			'hpos_compatibility_scanner_search_terms',
			function ( $terms ) {
				$this->captured_terms = $terms;

				return $terms;
			},
			1
		);
		add_filter(
			'hpos_compatibility_scanner_safe_patterns',
			function ( $patterns ) {
				$this->captured_safe = $patterns;

				return $patterns;
			},
			1
		);

		$this->scan( 'non-php/non-php.php' );
	}

	/**
	 * Lines of a fixture file that the scanner does not skip as comments, keyed by line number.
	 *
	 * @param string $relative_file File relative to the plugins folder.
	 * @return array<int, string>
	 */
	private function scannable_lines( string $relative_file ): array {
		$lines = file( Fixture_Plugins::path() . '/' . $relative_file, FILE_IGNORE_NEW_LINES );
		$this->assertIsArray( $lines, 'Cannot read ' . $relative_file );

		$scannable = array();
		foreach ( $lines as $index => $line ) {
			if ( 1 === preg_match( '/^\s*\/\//', $line ) || 1 === preg_match( '/^\s*\*/', $line ) ) {
				continue;
			}
			$scannable[ $index + 1 ] = $line;
		}

		return $scannable;
	}

	/**
	 * Whether a search term matches a line the way the scanner decides it.
	 *
	 * @param string|array<string, string> $term Search term.
	 * @param string                       $line Line of code.
	 */
	private function term_matches( $term, string $line ): bool {
		if ( is_array( $term ) ) {
			return 1 === preg_match( $term['pattern'], $line );
		}

		return false !== stripos( $line, $term );
	}

	/**
	 * Whether any captured term matches a line.
	 *
	 * @param string $line Line of code.
	 */
	private function any_term_matches( string $line ): bool {
		foreach ( $this->captured_terms as $term ) {
			if ( $this->term_matches( $term, $line ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a line holds any captured safe string.
	 *
	 * @param string $line Line of code.
	 */
	private function holds_safe_string( string $line ): bool {
		foreach ( $this->captured_safe as $safe ) {
			if ( false !== stripos( $line, $safe ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Line numbers that carry a finding in one file of a normalized response.
	 *
	 * @param array<string, mixed> $normalized Normalized response.
	 * @param string               $file       File key, relative to the plugins folder with a leading slash.
	 * @return int[]
	 */
	private function finding_lines( array $normalized, string $file ): array {
		return array_values( array_unique( array_column( $normalized['data']['issues'][ $file ] ?? array(), 'line' ) ) );
	}

	/**
	 * The search terms fixture is pinned, and every issue has the documented shape.
	 */
	public function test_search_terms_fixture_matches_expected(): void {
		$response   = $this->scan( 'search-terms/search-terms.php' );
		$normalized = $this->assert_expected( 'scan-search-terms', $response );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['hpos_compatible'] );
		$this->assertArrayNotHasKey( 'message', $response['data'] );
		$this->assertNotEmpty( $response['data']['issues'] );

		foreach ( $response['data']['issues'] as $issue ) {
			$this->assertEqualsCanonicalizing(
				array( 'file', 'term', 'description', 'line', 'snippet', 'code', 'context' ),
				array_keys( $issue )
			);
			$this->assertEqualsCanonicalizing( array( 'before', 'after' ), array_keys( $issue['context'] ) );
		}

		$this->assertSame( array( '/search-terms/search-terms.php' ), array_keys( $normalized['data']['issues'] ) );
	}

	/**
	 * Every built-in search term has a fixture line that it matches and that no safe string drops.
	 */
	public function test_every_built_in_search_term_has_a_fixture_line(): void {
		$this->capture_built_in_lists();

		$this->assertCount( 30, $this->captured_terms );
		$lines = $this->scannable_lines( 'search-terms/search-terms.php' );

		foreach ( $this->captured_terms as $term ) {
			$label = is_array( $term ) ? $term['pattern'] : $term;
			$found = false;

			foreach ( $lines as $line ) {
				if ( $this->term_matches( $term, $line ) && ! $this->holds_safe_string( $line ) ) {
					$found = true;
					break;
				}
			}

			$this->assertTrue( $found, 'No fixture line reports the built-in search term: ' . $label );
		}
	}

	/**
	 * Every built-in safe string has a line that is dropped and a following line that is reported.
	 */
	public function test_every_built_in_safe_string_has_a_dropped_and_a_reported_line(): void {
		$this->capture_built_in_lists();

		$this->assertCount( 51, $this->captured_safe );

		$normalized = Expected_Response::normalize( $this->scan( 'safe-strings/safe-strings.php' ) );
		$reported   = $this->finding_lines( $normalized, '/safe-strings/safe-strings.php' );
		$lines      = $this->scannable_lines( 'safe-strings/safe-strings.php' );

		foreach ( $this->captured_safe as $safe ) {
			$dropped_candidates = array();
			$has_reported_pair  = false;

			foreach ( $lines as $number => $line ) {
				if ( false === stripos( $line, $safe ) || ! $this->any_term_matches( $line ) ) {
					continue;
				}

				$dropped_candidates[] = $number;
				$next                 = $lines[ $number + 1 ] ?? null;

				if (
					null !== $next
					&& $this->any_term_matches( $next )
					&& ! $this->holds_safe_string( $next )
					&& in_array( $number + 1, $reported, true )
				) {
					$has_reported_pair = true;
				}
			}

			$this->assertNotEmpty( $dropped_candidates, 'No fixture line holds the built-in safe string: ' . $safe );
			$this->assertTrue( $has_reported_pair, 'No reported line follows a line that holds the safe string: ' . $safe );

			foreach ( $dropped_candidates as $number ) {
				$this->assertNotContains( $number, $reported, 'A line with the safe string was reported: ' . $safe . ' on line ' . $number );
			}
		}
	}

	/**
	 * The safe strings fixture is pinned.
	 */
	public function test_safe_strings_fixture_matches_expected(): void {
		$response = $this->scan( 'safe-strings/safe-strings.php' );

		$this->assert_expected( 'scan-safe-strings', $response );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['hpos_compatible'] );
	}

	/**
	 * Without filters, only the line with the safe marker text is reported.
	 */
	public function test_filters_fixture_without_filters(): void {
		$response   = $this->scan( 'filters/filters.php' );
		$normalized = $this->assert_expected( 'scan-filters-default', $response );

		$marker_line = $this->line_of( 'filters/filters.php', 'hpos_fixture_safe_marker' );

		$this->assertSame( array( $marker_line ), $this->finding_lines( $normalized, '/filters/filters.php' ) );
		$this->assertCount( 1, $normalized['data']['issues']['/filters/filters.php'] );
	}

	/**
	 * Terms and safe strings added by filter work in all three shapes.
	 */
	public function test_filters_fixture_with_filters(): void {
		add_filter(
			'hpos_compatibility_scanner_search_terms',
			static function ( $terms ) {
				$terms[] = 'hpos_fixture_plain_term';
				$terms[] = array(
					'pattern'     => '/hpos_fixture_typed_pattern/',
					'type'        => 'fixture_type',
					'description' => 'Typed fixture pattern',
				);
				$terms[] = array(
					'pattern'     => '/hpos_fixture_untyped_pattern/',
					'description' => 'Untyped fixture pattern',
				);

				return $terms;
			}
		);
		add_filter(
			'hpos_compatibility_scanner_safe_patterns',
			static function ( $patterns ) {
				$patterns[] = 'hpos_fixture_safe_marker';

				return $patterns;
			}
		);

		$response   = $this->scan( 'filters/filters.php' );
		$normalized = $this->assert_expected( 'scan-filters-applied', $response );
		$issues     = $normalized['data']['issues']['/filters/filters.php'];

		$this->assertSame(
			array( 'hpos_fixture_plain_term', 'Fixture type', 'Pattern match' ),
			array_column( $issues, 'term' )
		);
		$this->assertNotContains( $this->line_of( 'filters/filters.php', 'hpos_fixture_safe_marker' ), array_column( $issues, 'line' ) );
	}

	/**
	 * Line number of the first line of a fixture that holds a text.
	 *
	 * @param string $relative_file File relative to the plugins folder.
	 * @param string $text          Text to find.
	 */
	private function line_of( string $relative_file, string $text ): int {
		foreach ( $this->scannable_lines( $relative_file ) as $number => $line ) {
			if ( false !== strpos( $line, $text ) ) {
				return $number;
			}
		}

		$this->fail( 'No line holds ' . $text . ' in ' . $relative_file );
	}
}
