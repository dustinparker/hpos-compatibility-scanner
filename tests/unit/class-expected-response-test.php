<?php
/**
 * Tests for the expected response helper.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Unit;

use DPWD\HPOSCompatPlugin\Tests\Support\Expected_Response;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Expected_Response_Test class.
 */
final class Expected_Response_Test extends TestCase {

	/**
	 * Issues are grouped by file with the keys sorted and the inner order kept.
	 */
	public function test_normalize_groups_issues_by_file(): void {
		$response = array(
			'success' => true,
			'data'    => array(
				'hpos_compatible' => false,
				'issues'          => array(
					array(
						'file' => '/b/b.php',
						'line' => 9,
					),
					array(
						'file' => '/a/a.php',
						'line' => 4,
					),
					array(
						'file' => '/b/b.php',
						'line' => 2,
					),
				),
			),
		);

		$normalized = Expected_Response::normalize( $response );

		$this->assertSame( array( '/a/a.php', '/b/b.php' ), array_keys( $normalized['data']['issues'] ) );
		$this->assertSame( array( 9, 2 ), array_column( $normalized['data']['issues']['/b/b.php'], 'line' ) );
		$this->assertSame( array( 4 ), array_column( $normalized['data']['issues']['/a/a.php'], 'line' ) );
		$this->assertFalse( $normalized['data']['hpos_compatible'] );
	}

	/**
	 * An empty issue list stays a list.
	 */
	public function test_normalize_keeps_an_empty_issue_list(): void {
		$normalized = Expected_Response::normalize(
			array(
				'success' => true,
				'data'    => array( 'issues' => array() ),
			)
		);

		$this->assertSame( array(), $normalized['data']['issues'] );
	}

	/**
	 * A valid timestamp becomes a placeholder.
	 */
	public function test_normalize_replaces_a_valid_timestamp(): void {
		$normalized = Expected_Response::normalize(
			array(
				'success' => true,
				'data'    => array( 'last_updated' => '2026-10-10 12:30:45' ),
			)
		);

		$this->assertSame( '<datetime>', $normalized['data']['last_updated'] );
	}

	/**
	 * A timestamp in another format fails.
	 */
	public function test_normalize_rejects_an_invalid_timestamp(): void {
		$this->expectException( AssertionFailedError::class );

		Expected_Response::normalize(
			array(
				'success' => true,
				'data'    => array( 'last_updated' => 'not a date' ),
			)
		);
	}

	/**
	 * The directory follows the PHP family.
	 */
	public function test_directory_follows_the_php_family(): void {
		$directory = Expected_Response::directory();

		if ( PHP_VERSION_ID >= 80100 ) {
			$this->assertStringEndsWith( '/expected/php-81-plus', $directory );
		} else {
			$this->assertStringEndsWith( '/expected/php-pre-81', $directory );
		}
	}

	/**
	 * A missing file throws and names the path.
	 */
	public function test_load_throws_for_a_missing_file(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'does-not-exist.json' );

		Expected_Response::load( 'does-not-exist' );
	}

	/**
	 * A saved file loads back as it was saved, and ends with a newline.
	 */
	public function test_save_and_load_round_trip(): void {
		$name = 'unit-round-trip-' . getmypid();
		$data = array(
			'success' => true,
			'path'    => '/a/b',
			'text'    => "caf\u{e9} 'quoted'",
		);

		try {
			Expected_Response::save( $name, $data );

			$this->assertSame( $data, Expected_Response::load( $name ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file.
			$contents = file_get_contents( Expected_Response::directory() . '/' . $name . '.json' );
			$this->assertStringEndsWith( "}\n", $contents );
			$this->assertStringContainsString( '"/a/b"', $contents );
			$this->assertStringContainsString( "caf\u{e9}", $contents );
		} finally {
			@unlink( Expected_Response::directory() . '/' . $name . '.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- Test support; the file may not exist when save failed.
		}
	}

	/**
	 * A file that is not valid JSON throws.
	 */
	public function test_load_throws_for_invalid_json(): void {
		$name = 'unit-invalid-' . getmypid();
		$file = Expected_Response::directory() . '/' . $name . '.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		file_put_contents( $file, '{not json' );

		try {
			$this->expectException( RuntimeException::class );
			Expected_Response::load( $name );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test support outside WordPress.
			unlink( $file );
		}
	}

	/**
	 * The regenerate mode needs the exact value 1.
	 */
	public function test_updating_reads_the_environment(): void {
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- The test sets the variable it reads and restores it.
		$previous = getenv( 'HPOS_SCANNER_UPDATE_EXPECTED' );

		try {
			putenv( 'HPOS_SCANNER_UPDATE_EXPECTED=1' );
			$this->assertTrue( Expected_Response::updating() );

			putenv( 'HPOS_SCANNER_UPDATE_EXPECTED=yes' );
			$this->assertFalse( Expected_Response::updating() );

			putenv( 'HPOS_SCANNER_UPDATE_EXPECTED' );
			$this->assertFalse( Expected_Response::updating() );
		} finally {
			if ( false === $previous ) {
				putenv( 'HPOS_SCANNER_UPDATE_EXPECTED' );
			} else {
				putenv( 'HPOS_SCANNER_UPDATE_EXPECTED=' . $previous );
			}
		}
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}
}
