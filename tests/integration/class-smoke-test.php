<?php
/**
 * Checks that the harness loads the plugin the way a site does.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin\Tests\Integration;

use DPWD\HPOSCompatPlugin\Tests\Support\Ajax_Test_Case;
use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;

/**
 * Smoke_Test class.
 */
final class Smoke_Test extends Ajax_Test_Case {

	/**
	 * The three actions are registered for logged-in users.
	 *
	 * @dataProvider action_names
	 *
	 * @param string $action Action name.
	 */
	public function test_actions_are_registered_for_logged_in_users( string $action ): void {
		$this->assertNotFalse( has_action( 'wp_ajax_' . $action ) );
	}

	/**
	 * No action is open to visitors.
	 *
	 * @dataProvider action_names
	 *
	 * @param string $action Action name.
	 */
	public function test_actions_are_not_registered_for_visitors( string $action ): void {
		$this->assertFalse( has_action( 'wp_ajax_nopriv_' . $action ) );
	}

	/**
	 * Action names.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function action_names(): array {
		return array(
			'scan'     => array( 'hpos_scan_plugin' ),
			'overview' => array( 'hpos_get_all_plugins_compatibility' ),
			'refresh'  => array( 'hpos_refresh_compatibility_cache' ),
		);
	}

	/**
	 * The plugin classes load through the autoloader.
	 */
	public function test_plugin_classes_load(): void {
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Plugin' ) );
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Admin\Admin' ) );
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Scanner\Scanner' ) );
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Compatibility\Compatibility' ) );
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Scanner\Directory_Scanner' ) );
		$this->assertTrue( class_exists( 'DPWD\HPOSCompatPlugin\Scanner\Directory_Open_Exception' ) );
	}

	/**
	 * The Jetpack classmap lists the classes, which is how a built zip finds them.
	 */
	public function test_classmap_lists_the_plugin_classes(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file.
		$classmap = file_get_contents( dirname( __DIR__, 2 ) . '/vendor/composer/jetpack_autoload_classmap.php' );

		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Plugin', $classmap );
		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Admin\\\\Admin', $classmap );
		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Scanner\\\\Scanner', $classmap );
		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Compatibility\\\\Compatibility', $classmap );
		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Scanner\\\\Directory_Scanner', $classmap );
		$this->assertStringContainsString( 'DPWD\\\\HPOSCompatPlugin\\\\Scanner\\\\Directory_Open_Exception', $classmap );
	}

	/**
	 * WordPress reads plugins from the temporary copy.
	 */
	public function test_plugin_dir_is_the_temporary_copy(): void {
		$this->assertSame( Fixture_Plugins::path(), WP_PLUGIN_DIR );
	}

	/**
	 * The overview lists the fixture and no request leaves the machine.
	 */
	public function test_overview_lists_fixtures_without_http(): void {
		$response = $this->call( 'hpos_get_all_plugins_compatibility' );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'non-php/non-php.php', $response['data']['plugins'] );
		$this->assertSame( 0, $GLOBALS['hpos_scanner_tests_http_attempts'] );
	}
}
