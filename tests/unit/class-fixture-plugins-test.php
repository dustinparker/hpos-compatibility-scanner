<?php
/**
 * Tests for the fixture folder helper.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Unit;

use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Fixture_Plugins_Test class.
 */
final class Fixture_Plugins_Test extends TestCase {

	/**
	 * Scratch folders made by a test, deleted afterwards.
	 *
	 * @var string[]
	 */
	private array $scratch = array();

	/**
	 * Delete scratch folders.
	 */
	protected function tearDown(): void {
		foreach ( $this->scratch as $folder ) {
			Fixture_Plugins::remove( $folder );
		}
		$this->scratch = array();

		parent::tearDown();
	}

	/**
	 * Make a folder under the removable root, for the test to fill.
	 *
	 * @param string $name Folder name suffix.
	 */
	private function make_scratch( string $name ): string {
		$folder = sys_get_temp_dir() . '/hpos-scanner-tests-scratch-' . $name . '-' . getmypid();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		mkdir( $folder, 0755, true );
		$this->scratch[] = $folder;

		return $folder;
	}

	/**
	 * The path sits in the temp folder and ends with the token.
	 */
	public function test_path_uses_the_temp_folder_and_the_token(): void {
		$path = Fixture_Plugins::path();

		$this->assertStringStartsWith( sys_get_temp_dir() . '/hpos-scanner-tests-', $path );
		$this->assertStringEndsWith( '-' . Fixture_Plugins::token(), $path );
	}

	/**
	 * The path carries a checkout part, so two checkouts never share a folder.
	 */
	public function test_path_names_the_checkout(): void {
		$expected = substr( md5( dirname( __DIR__, 2 ) ), 0, 8 );

		$this->assertSame( $expected, Fixture_Plugins::checkout_id() );
		$this->assertSame(
			sys_get_temp_dir() . '/hpos-scanner-tests-' . $expected . '-' . Fixture_Plugins::token(),
			Fixture_Plugins::path()
		);
	}

	/**
	 * The copy holds the fixtures.
	 */
	public function test_create_copies_the_fixtures(): void {
		$path = Fixture_Plugins::create();

		$this->assertSame( Fixture_Plugins::path(), $path );
		$this->assertFileExists( $path . '/non-php/non-php.php' );
		$this->assertFileEquals( Fixture_Plugins::source() . '/non-php/notes', $path . '/non-php/notes' );
	}

	/**
	 * The content folder has a themes folder and sits beside the plugins folder.
	 */
	public function test_content_path_holds_a_themes_folder(): void {
		$path = Fixture_Plugins::content_path();

		$this->assertSame( Fixture_Plugins::path() . '-content', $path );
		$this->assertDirectoryExists( $path . '/themes' );
	}

	/**
	 * Paths outside the root are refused and nothing is deleted.
	 *
	 * @dataProvider outside_paths
	 *
	 * @param string $path Path to refuse.
	 */
	public function test_remove_refuses_paths_outside_the_root( string $path ): void {
		$scratch = $this->make_scratch( 'marker' );
		$marker  = $scratch . '/marker.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		file_put_contents( $marker, 'keep' );

		try {
			Fixture_Plugins::remove( $path );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertFileExists( $marker );
		}
	}

	/**
	 * Paths that must be refused.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function outside_paths(): array {
		return array(
			'other temp entry'     => array( sys_get_temp_dir() . '/not-ours' ),
			'root'                 => array( '/' ),
			'temp folder itself'   => array( sys_get_temp_dir() ),
			'traversal after root' => array( sys_get_temp_dir() . '/hpos-scanner-tests-1/../../etc' ),
			'empty'                => array( '' ),
		);
	}

	/**
	 * Unreadable files and folders do not block removal.
	 */
	public function test_remove_deletes_unreadable_entries(): void {
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'Permissions do not apply to root.' );
		}

		$scratch = $this->make_scratch( 'locked' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		mkdir( $scratch . '/locked-dir' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		file_put_contents( $scratch . '/locked-dir/inner.php', 'x' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		file_put_contents( $scratch . '/locked-file.php', 'x' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		chmod( $scratch . '/locked-file.php', 0000 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		chmod( $scratch . '/locked-dir', 0000 );

		Fixture_Plugins::remove( $scratch );

		$this->assertDirectoryDoesNotExist( $scratch );
	}

	/**
	 * Reset brings back a file the test deleted.
	 */
	public function test_reset_restores_a_deleted_file(): void {
		$path = Fixture_Plugins::create();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test support outside WordPress.
		unlink( $path . '/non-php/notes' );
		$this->assertFileDoesNotExist( $path . '/non-php/notes' );

		Fixture_Plugins::reset();

		$this->assertFileExists( $path . '/non-php/notes' );
	}
}
