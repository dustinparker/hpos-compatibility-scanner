<?php
/**
 * Pins the compatibility checks, the overview, the refresh action and the transient.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Compatibility\Compatibility;
use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use ReflectionClass;

/**
 * Compatibility_Test class.
 */
final class Compatibility_Test extends Ajax_Test_Case {

	/**
	 * Verdicts per fixture folder: the public check (overview), then the private check (scan).
	 *
	 * @var array<string, array{0: bool, 1: bool}>
	 */
	private const VERDICTS = array(
		'compat-featuresutil'               => array( true, true ),
		'compat-helper-declare-hpos'        => array( true, false ),
		'compat-helper-declare-wc-hpos'     => array( true, false ),
		'compat-helper-woocommerce-hpos'    => array( true, false ),
		'compat-hook-before-init'           => array( true, false ),
		'compat-hook-plugins-loaded'        => array( true, false ),
		'compat-private-declare'            => array( false, true ),
		'compat-private-hpos-compatibility' => array( false, true ),
		'compat-private-hpos-compatible'    => array( false, true ),
		'compat-private-split'              => array( false, true ),
		'compat-declares-incompatible'      => array( true, true ),
		'compat-all-patterns'               => array( true, true ),
		'compat-non-php-declaration'        => array( false, false ),
		'compat-uppercase-extension'        => array( true, true ),
		'cache-probe'                       => array( false, false ),
	);

	/**
	 * Folder made unreadable by a test, restored in tear_down().
	 *
	 * @var string
	 */
	private string $locked = '';

	/**
	 * Restore permissions and the fixture copy, then run the base checks.
	 */
	public function tear_down(): void {
		if ( '' !== $this->locked ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
			chmod( $this->locked, 0755 );
			$this->locked = '';
			Fixture_Plugins::reset();
		}

		parent::tear_down();
	}

	/**
	 * Call the overview as an administrator.
	 *
	 * @param array<string, mixed> $post Request values.
	 * @return array<string, mixed>
	 */
	private function overview( array $post = array() ): array {
		return $this->call( 'hpos_get_all_plugins_compatibility', $post );
	}

	/**
	 * Transient name used for a fixture folder.
	 *
	 * @param string $folder Folder in the plugins copy.
	 */
	private function transient_key( string $folder ): string {
		return 'hpos_compatibility_' . md5( WP_PLUGIN_DIR . '/' . $folder );
	}

	/**
	 * Read a transient's stored value straight from the options table.
	 *
	 * @param string $key Transient name.
	 * @return string|null The stored string (an empty string for false), or null when there is no row.
	 */
	private function stored_value( string $key ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading the raw row is the point.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_transient_' . $key ), ARRAY_A );

		return null === $row ? null : $row['option_value'];
	}

	/**
	 * The overview holds exactly the documented keys, and each fixture gets the public verdict.
	 */
	public function test_overview_matches_expected(): void {
		$response = $this->overview();
		$this->assert_expected( 'overview-default', $response );

		$this->assertTrue( $response['success'] );
		$this->assertEqualsCanonicalizing( array( 'plugins', 'cache_status', 'last_updated' ), array_keys( $response['data'] ) );
		$this->assertSame( 'cached', $response['data']['cache_status'] );

		foreach ( $response['data']['plugins'] as $plugin ) {
			$this->assertEqualsCanonicalizing( array( 'name', 'version', 'author', 'compatible' ), array_keys( $plugin ) );
		}

		foreach ( self::VERDICTS as $folder => $verdict ) {
			$this->assertSame( $verdict[0], $response['data']['plugins'][ $folder . '/' . $folder . '.php' ]['compatible'], $folder );
		}

		$this->assertFalse( $response['data']['plugins']['single-file.php']['compatible'] );
		$this->assertTrue( $response['data']['plugins']['single-file-with-folder.php']['compatible'] );
	}

	/**
	 * The scan reports the private verdict for each fixture, and the scan files hold no unexpected findings.
	 */
	public function test_scan_hpos_compatible_matches_private_check(): void {
		foreach ( self::VERDICTS as $folder => $verdict ) {
			$response = $this->call( 'hpos_scan_plugin', array( 'plugin' => $folder . '/' . $folder . '.php' ) );

			$this->assert_expected( 'scan-compat-' . $folder, $response );
			$this->assertTrue( $response['success'], $folder );
			$this->assertSame( $verdict[1], $response['data']['hpos_compatible'], $folder );
		}
	}

	/**
	 * The two checks give different answers for some plugins, in both directions.
	 */
	public function test_checks_disagree_for_at_least_one_fixture(): void {
		$plugins = $this->overview()['data']['plugins'];

		$helper_scan  = $this->call( 'hpos_scan_plugin', array( 'plugin' => 'compat-helper-declare-hpos/compat-helper-declare-hpos.php' ) );
		$private_scan = $this->call( 'hpos_scan_plugin', array( 'plugin' => 'compat-private-declare/compat-private-declare.php' ) );

		$this->assertTrue( $plugins['compat-helper-declare-hpos/compat-helper-declare-hpos.php']['compatible'] );
		$this->assertFalse( $helper_scan['data']['hpos_compatible'] );
		$this->assertFalse( $plugins['compat-private-declare/compat-private-declare.php']['compatible'] );
		$this->assertTrue( $private_scan['data']['hpos_compatible'] );
	}

	/**
	 * Only the exact string "true" forces a refresh.
	 */
	public function test_force_refresh_values(): void {
		$response = $this->overview( array( 'force_refresh' => 'true' ) );
		$this->assert_expected( 'overview-refreshed', $response );
		$this->assertSame( 'refreshed', $response['data']['cache_status'] );

		foreach ( array( '1', 'TRUE', 'yes', true ) as $value ) {
			$response = $this->overview( array( 'force_refresh' => $value ) );
			$this->assertSame( 'cached', $response['data']['cache_status'], (string) wp_json_encode( $value ) );
		}
	}

	/**
	 * The refresh action rebuilds the transient of every plugin folder.
	 */
	public function test_refresh_action(): void {
		global $wpdb;

		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_hpos_compatibility_' ) . '%', $wpdb->esc_like( '_transient_timeout_hpos_compatibility_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test setup.
		foreach ( $names as $name ) {
			delete_option( $name );
		}

		// The refresh deletes each key and never reads the old value.
		$deleted = array();
		$read    = array();
		$folders = array_merge( array_keys( self::VERDICTS ), array( 'single-file-with-folder' ) );

		foreach ( $folders as $folder ) {
			$key = $this->transient_key( $folder );

			add_action(
				'delete_transient_' . $key,
				static function () use ( &$deleted, $folder ) {
					$deleted[] = $folder;
				}
			);
			add_filter(
				'pre_transient_' . $key,
				static function ( $pre ) use ( &$read, $folder ) {
					$read[] = $folder;
					return $pre;
				}
			);
		}

		$response = $this->call( 'hpos_refresh_compatibility_cache' );
		$this->assert_expected( 'refresh', $response );

		$this->assertTrue( $response['success'] );
		$this->assertEqualsCanonicalizing( array( 'message', 'last_updated' ), array_keys( $response['data'] ) );
		$this->assertSame( 'Compatibility cache refreshed successfully.', $response['data']['message'] );
		$this->assertEqualsCanonicalizing( $folders, $deleted );
		$this->assertSame( array(), $read );

		foreach ( self::VERDICTS as $folder => $verdict ) {
			$key = $this->transient_key( $folder );

			$this->assertNotFalse( get_option( '_transient_timeout_' . $key ), $folder );
			$this->assertSame( $verdict[0] ? '1' : '', $this->stored_value( $key ), $folder );
		}

		// A single file at the plugins root refreshes the folder with its name, when there is one, and nothing else.
		$this->assertNotFalse( get_option( '_transient_timeout_' . $this->transient_key( 'single-file-with-folder' ) ) );
		$this->assertSame( '1', $this->stored_value( $this->transient_key( 'single-file-with-folder' ) ) );
		$this->assertNull( $this->stored_value( $this->transient_key( 'single-file' ) ) );
		$this->assertNull( $this->stored_value( 'hpos_compatibility_' . md5( WP_PLUGIN_DIR . '/' ) ) );
	}

	/**
	 * The public check can be called directly: without the second argument it reads the cache.
	 */
	public function test_direct_check_reads_the_cache_by_default(): void {
		$checker = ( new ReflectionClass( Compatibility::class ) )->newInstanceWithoutConstructor();
		$folder  = Fixture_Plugins::path() . '/cache-probe';
		$key     = 'hpos_compatibility_' . md5( $folder );

		$this->assertFalse( $checker->check_hpos_compatibility( $folder ) );
		$this->assertSame( '', $this->stored_value( $key ) );

		set_transient( $key, true, 86400 );

		$this->assertTrue( $checker->check_hpos_compatibility( $folder ) );
		$this->assertFalse( $checker->check_hpos_compatibility( $folder, true ) );
		$this->assertSame( '', $this->stored_value( $key ) );
	}

	/**
	 * A path that is not a folder gives false and leaves no transient.
	 */
	public function test_direct_check_ignores_paths_that_are_not_folders(): void {
		$checker = ( new ReflectionClass( Compatibility::class ) )->newInstanceWithoutConstructor();

		foreach ( array( Fixture_Plugins::path() . '/single-file.php', Fixture_Plugins::path() . '/missing' ) as $path ) {
			$this->assertFalse( $checker->check_hpos_compatibility( $path, true ), $path );
			$this->assertNull( $this->stored_value( 'hpos_compatibility_' . md5( $path ) ), $path );
		}
	}

	/**
	 * The transient is named after the md5 of the plugin folder path and lives for a day.
	 */
	public function test_transient_key_and_lifetime(): void {
		$key      = $this->transient_key( 'compat-featuresutil' );
		$recorded = array();

		add_filter(
			'expiration_of_transient_' . $key,
			static function ( $expiration ) use ( &$recorded ) {
				$recorded[] = $expiration;
				return $expiration;
			}
		);

		$this->overview( array( 'force_refresh' => 'true' ) );

		$this->assertSame( array( 86400 ), $recorded );
		$this->assertSame( '1', $this->stored_value( $key ) );
	}

	/**
	 * A subfolder the process cannot open makes the check give up and report not compatible.
	 *
	 * The plugin has no readable declaring file, so the walk always reaches the locked folder. A readable
	 * declaring file listed before the folder would end the walk early and give compatible instead.
	 */
	public function test_overview_with_unreadable_subfolder_reports_not_compatible(): void {
		$this->skip_without_folder_permissions();

		$path = Fixture_Plugins::path() . '/cache-probe/locked';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test support outside WordPress.
		$this->assertTrue( mkdir( $path, 0755 ) );
		$this->locked = $path;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		$this->assertNotFalse( file_put_contents( $path . '/declare.php', "<?php\nFeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );\n" ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Test support outside WordPress.
		$this->assertTrue( chmod( $path, 0000 ) );

		$response = $this->overview( array( 'force_refresh' => 'true' ) );

		$this->assertFalse( $response['data']['plugins']['cache-probe/cache-probe.php']['compatible'] );
	}
}
