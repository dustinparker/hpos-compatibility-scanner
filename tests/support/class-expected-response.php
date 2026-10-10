<?php
/**
 * Reads, writes and normalizes the stored expected responses.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Support;

use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Expected responses are stored as JSON, one set per PHP family.
 */
final class Expected_Response {

	/**
	 * Folder holding the set for the running PHP.
	 *
	 * The split is at 8.1, where the default flags of htmlspecialchars() start encoding single quotes.
	 */
	public static function directory(): string {
		return dirname( __DIR__ ) . '/expected/' . ( PHP_VERSION_ID >= 80100 ? 'php-81-plus' : 'php-pre-81' );
	}

	/**
	 * Make a response comparable.
	 *
	 * Issues are grouped by file because the directory walk order differs between systems. The order inside
	 * a file is kept. A timestamp is checked for its format and replaced by a placeholder.
	 *
	 * @param array<string, mixed> $response Decoded response.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $response ): array {
		if ( isset( $response['data']['issues'] ) && is_array( $response['data']['issues'] ) && array() !== $response['data']['issues'] ) {
			$grouped = array();
			foreach ( $response['data']['issues'] as $issue ) {
				$grouped[ $issue['file'] ][] = $issue;
			}
			ksort( $grouped );
			$response['data']['issues'] = $grouped;
		}

		if ( isset( $response['data']['last_updated'] ) ) {
			Assert::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $response['data']['last_updated'] );
			$response['data']['last_updated'] = '<datetime>';
		}

		return $response;
	}

	/**
	 * Whether the expected files should be rewritten from the current responses.
	 */
	public static function updating(): bool {
		return '1' === getenv( 'HPOS_SCANNER_UPDATE_EXPECTED' );
	}

	/**
	 * Read a stored response.
	 *
	 * @param string $name File name without the extension.
	 * @return array<string, mixed>
	 * @throws RuntimeException When the file is missing or is not valid JSON.
	 */
	public static function load( string $name ): array {
		$file = self::directory() . '/' . $name . '.json';

		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( 'Expected response file is missing: ' . $file ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not rendered in a browser.
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file.
		$decoded = json_decode( (string) file_get_contents( $file ), true );

		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Expected response file is not valid JSON: ' . $file ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not rendered in a browser.
		}

		return $decoded;
	}

	/**
	 * Write a normalized response.
	 *
	 * @param string               $name       File name without the extension.
	 * @param array<string, mixed> $normalized Normalized response.
	 * @throws RuntimeException When the file cannot be written.
	 */
	public static function save( string $name, array $normalized ): void {
		$directory = self::directory();

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		}

		$json = wp_json_encode( $normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		if ( false === $json || false === file_put_contents( $directory . '/' . $name . '.json', $json . "\n" ) ) {
			throw new RuntimeException( 'Cannot write the expected response: ' . $name ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not rendered in a browser.
		}
	}
}
