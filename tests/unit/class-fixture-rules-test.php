<?php
/**
 * Rules every fixture file has to follow.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Unit;

use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fixture_Rules_Test class.
 */
final class Fixture_Rules_Test extends TestCase {

	/**
	 * Every path under the fixtures folder, relative to it.
	 *
	 * @return string[]
	 */
	private function fixture_entries(): array {
		$source   = Fixture_Plugins::source();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$entries  = array();

		foreach ( $iterator as $entry ) {
			$entries[] = substr( $entry->getPathname(), strlen( $source ) + 1 );
		}

		sort( $entries );

		return $entries;
	}

	/**
	 * Names are limited because the scan page prints file names without escaping them.
	 */
	public function test_names_use_only_safe_characters(): void {
		$entries = $this->fixture_entries();
		$this->assertNotEmpty( $entries );

		foreach ( $entries as $entry ) {
			foreach ( explode( '/', $entry ) as $segment ) {
				$this->assertMatchesRegularExpression( '/^[a-z0-9-]+(\.php|\.PHP)?$/', $segment, 'Unsafe fixture name: ' . $entry );
			}
		}
	}

	/**
	 * Every PHP fixture parses on the running PHP.
	 */
	public function test_php_files_parse(): void {
		$count = 0;

		foreach ( $this->fixture_entries() as $entry ) {
			if ( 1 !== preg_match( '/\.php$/i', $entry ) ) {
				continue;
			}

			$output = array();
			$status = 0;
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Lint check on a local file.
			exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( Fixture_Plugins::source() . '/' . $entry ) . ' 2>&1', $output, $status );

			$this->assertSame( 0, $status, 'Parse error in ' . $entry . ': ' . implode( ' ', $output ) );
			++$count;
		}

		$this->assertGreaterThan( 0, $count );
	}

	/**
	 * The line ending fixture keeps its CRLF endings on every line.
	 */
	public function test_crlf_fixture_uses_crlf_endings(): void {
		$contents = file_get_contents( Fixture_Plugins::source() . '/crlf/crlf.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$this->assertIsString( $contents );
		$this->assertStringContainsString( "\r\n", $contents );
		$this->assertSame( substr_count( $contents, "\n" ), substr_count( $contents, "\r\n" ), 'A line ends without CR.' );
	}

	/**
	 * The final newline fixture ends without a newline.
	 */
	public function test_no_final_newline_fixture_has_no_final_newline(): void {
		$contents = file_get_contents( Fixture_Plugins::source() . '/no-final-newline/no-final-newline.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$this->assertIsString( $contents );
		$this->assertNotSame( '', $contents );
		$this->assertStringEndsNotWith( "\n", $contents );
	}

	/**
	 * Every top-level fixture is mapped into the wp-env development site only, and every mapping has a fixture.
	 */
	public function test_every_fixture_has_a_mapping_and_every_mapping_a_fixture(): void {
		$expected = array();

		foreach ( scandir( Fixture_Plugins::source() ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}

			$expected[ 'wp-content/plugins/' . $name ] = './tests/fixtures/plugins/' . $name;
		}

		$config = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/.wp-env.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

		$this->assertIsArray( $config );
		$this->assertArrayNotHasKey( 'mappings', $config, 'Root-level mappings also reach the tests site.' );
		$this->assertArrayNotHasKey( 'mappings', $config['env']['tests'], 'The tests site gets no fixture mounts.' );
		$this->assertArrayHasKey( 'mappings', $config['env']['development'] );

		$actual = $config['env']['development']['mappings'];

		ksort( $expected );
		ksort( $actual );

		$this->assertSame( $expected, $actual );
	}
}
