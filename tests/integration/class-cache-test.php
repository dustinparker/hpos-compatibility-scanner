<?php
/**
 * Pins what the overview reports after a plugin changes while its verdict is cached.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;

/**
 * Cache_Test class.
 */
final class Cache_Test extends Ajax_Test_Case {

	/**
	 * Restore the fixture copy, then run the base checks.
	 */
	public function tear_down(): void {
		Fixture_Plugins::reset();

		parent::tear_down();
	}

	/**
	 * Read the overview, add a declaring file to the cache-probe plugin, then read it again.
	 *
	 * @return array<string, mixed> The second response.
	 */
	private function second_read(): array {
		$first = $this->call( 'hpos_get_all_plugins_compatibility' );
		$this->assertFalse( $first['data']['plugins']['cache-probe/cache-probe.php']['compatible'] );

		$added = Fixture_Plugins::path() . '/cache-probe/added.php';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test support outside WordPress.
		$this->assertNotFalse( file_put_contents( $added, "<?php\nfunction hpos_fixture_added() {\n\tFeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );\n}\n" ) );

		return $this->call( 'hpos_get_all_plugins_compatibility' );
	}

	/**
	 * With the default cache the second read still reports not compatible.
	 *
	 * The first read stores false as an empty string in the options table. The second read takes that as a
	 * cached answer and does not scan again, so the added file is not seen.
	 */
	public function test_second_read_with_the_default_cache(): void {
		$response = $this->second_read();

		$this->assert_expected( 'cache-default-second-read', $response );
		$this->assertSame( 'cached', $response['data']['cache_status'] );
		$this->assertFalse( $response['data']['plugins']['cache-probe/cache-probe.php']['compatible'] );
	}

	/**
	 * With an object cache the second read reports compatible.
	 *
	 * The object cache returns a stored false as if the key was missing, so the second read scans the plugin
	 * again and finds the added file.
	 */
	public function test_second_read_with_an_object_cache(): void {
		wp_using_ext_object_cache( true );

		$response = $this->second_read();

		$this->assert_expected( 'cache-ext-object-cache-second-read', $response );
		$this->assertSame( 'cached', $response['data']['cache_status'] );
		$this->assertTrue( $response['data']['plugins']['cache-probe/cache-probe.php']['compatible'] );
	}
}
