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
}
