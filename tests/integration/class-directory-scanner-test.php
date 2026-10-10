<?php
/**
 * Pins what Directory_Scanner returns, without the AJAX handler in between.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Scanner\Directory_Open_Exception;
use DPWD\HPOSCompatPlugin\Scanner\Directory_Scanner;
use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Expected_Response;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use UnexpectedValueException;

/**
 * Directory_Scanner_Test class.
 */
final class Directory_Scanner_Test extends Ajax_Test_Case {

	/**
	 * Folders made unreadable by a test, restored in tear_down().
	 *
	 * @var string[]
	 */
	private array $locked = array();

	/**
	 * Restore permissions and the fixture copy, remove the test filters, then run the base checks.
	 */
	public function tear_down(): void {
		if ( array() !== $this->locked ) {
			foreach ( $this->locked as $path ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
				chmod( $path, 0755 );
			}
			$this->locked = array();
			Fixture_Plugins::reset();
		}

		remove_all_filters( 'hpos_compatibility_scanner_search_terms' );
		remove_all_filters( 'hpos_compatibility_scanner_safe_patterns' );

		parent::tear_down();
	}

	/**
	 * Make a folder in the plugins copy and lock it.
	 *
	 * @param string $relative Folder relative to the plugins folder.
	 * @return string Absolute path.
	 */
	private function make_locked_folder( string $relative ): string {
		$path = Fixture_Plugins::path() . '/' . $relative;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		$this->assertTrue( mkdir( $path, 0755 ) );
		$this->locked[] = $path;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		$this->assertTrue( chmod( $path, 0000 ) );

		return $path;
	}

	/**
	 * Fixture folders and the stored response each one is compared with.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function fixture_folders(): array {
		return array(
			'search terms'        => array( 'search-terms', 'scan-search-terms' ),
			'safe strings'        => array( 'safe-strings', 'scan-safe-strings' ),
			'snippet edges'       => array( 'snippet-edges', 'scan-snippet-edges' ),
			'comment rule'        => array( 'comment-rule', 'scan-comment-rule' ),
			'crlf'                => array( 'crlf', 'scan-crlf' ),
			'no final newline'    => array( 'no-final-newline', 'scan-no-final-newline' ),
			'uppercase extension' => array( 'uppercase-extension', 'scan-uppercase-extension' ),
			'filters'             => array( 'filters', 'scan-filters-default' ),
			'non php'             => array( 'non-php', 'scan-non-php' ),
		);
	}

	/**
	 * The findings for a fixture folder match the stored response.
	 *
	 * @dataProvider fixture_folders
	 *
	 * @param string $folder   Fixture folder.
	 * @param string $expected Stored response name.
	 */
	public function test_findings_match_the_stored_response( string $folder, string $expected ): void {
		$findings = ( new Directory_Scanner() )->scan( WP_PLUGIN_DIR . '/' . $folder );
		$grouped  = Expected_Response::normalize( array( 'data' => array( 'issues' => $findings ) ) );

		$this->assertSame( Expected_Response::load( $expected )['data']['issues'], $grouped['data']['issues'] );
	}

	/**
	 * Terms and safe strings added by filter change the findings.
	 */
	public function test_filters_add_terms_and_safe_strings(): void {
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

		$findings = ( new Directory_Scanner() )->scan( WP_PLUGIN_DIR . '/filters' );
		$grouped  = Expected_Response::normalize( array( 'data' => array( 'issues' => $findings ) ) );

		$this->assertSame( Expected_Response::load( 'scan-filters-applied' )['data']['issues'], $grouped['data']['issues'] );
	}

	/**
	 * Each filter fires once, search terms first, before the folder is opened.
	 */
	public function test_filters_fire_once_each_in_order_before_the_folder_is_opened(): void {
		$calls = array();
		$sizes = array();

		add_filter(
			'hpos_compatibility_scanner_search_terms',
			static function ( $items ) use ( &$calls, &$sizes ) {
				$calls[]               = 'search_terms';
				$sizes['search_terms'] = count( $items );

				return $items;
			}
		);
		add_filter(
			'hpos_compatibility_scanner_safe_patterns',
			static function ( $items ) use ( &$calls, &$sizes ) {
				$calls[]                = 'safe_patterns';
				$sizes['safe_patterns'] = count( $items );

				return $items;
			}
		);

		try {
			( new Directory_Scanner() )->scan( WP_PLUGIN_DIR . '/does-not-exist' );
			$this->fail( 'The missing folder did not throw.' );
		} catch ( Directory_Open_Exception $e ) {
			unset( $e );
		}

		$this->assertSame( array( 'search_terms', 'safe_patterns' ), $calls );
		$this->assertSame(
			array(
				'search_terms'  => 30,
				'safe_patterns' => 51,
			),
			$sizes
		);
	}

	/**
	 * The handler sends exactly what the scanner returns.
	 */
	public function test_handler_returns_what_scan_returns(): void {
		$findings = ( new Directory_Scanner() )->scan( WP_PLUGIN_DIR . '/search-terms' );
		$response = $this->call( 'hpos_scan_plugin', array( 'plugin' => 'search-terms/search-terms.php' ) );

		$this->assertNotEmpty( $findings );
		$this->assertSame( $findings, $response['data']['issues'] );
	}

	/**
	 * A folder that does not exist throws Directory_Open_Exception.
	 */
	public function test_missing_folder_throws_directory_open_exception(): void {
		$path = WP_PLUGIN_DIR . '/does-not-exist';

		try {
			( new Directory_Scanner() )->scan( $path );
		} catch ( Directory_Open_Exception $e ) {
			$this->assertStringContainsString( $path, $e->getMessage() );
			$this->assertInstanceOf( UnexpectedValueException::class, $e->getPrevious() );

			return;
		}

		$this->fail( 'No exception was thrown.' );
	}

	/**
	 * A path that is a file throws Directory_Open_Exception.
	 */
	public function test_file_path_throws_directory_open_exception(): void {
		$path = WP_PLUGIN_DIR . '/non-php/non-php.php';

		try {
			( new Directory_Scanner() )->scan( $path );
		} catch ( Directory_Open_Exception $e ) {
			$this->assertStringContainsString( $path, $e->getMessage() );
			$this->assertInstanceOf( UnexpectedValueException::class, $e->getPrevious() );

			return;
		}

		$this->fail( 'No exception was thrown.' );
	}

	/**
	 * An unreadable subfolder throws the iterator's own exception, not the wrapped one.
	 */
	public function test_unreadable_subfolder_throws_the_iterator_exception(): void {
		$this->skip_without_folder_permissions();
		$this->make_locked_folder( 'comment-rule/locked' );

		try {
			( new Directory_Scanner() )->scan( WP_PLUGIN_DIR . '/comment-rule' );
			$this->fail( 'The scan finished without an exception.' );
		} catch ( UnexpectedValueException $e ) {
			$this->assertNotInstanceOf( Directory_Open_Exception::class, $e );
			$this->assertStringContainsString( 'locked', $e->getMessage() );
			$this->assertStringContainsString( 'Permission denied', $e->getMessage() );
		}
	}
}
