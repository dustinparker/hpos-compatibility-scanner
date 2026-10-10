<?php
/**
 * PHPUnit bootstrap: loads the plugin inside WordPress through the test library.
 *
 * @package DPWD\HPOSCompatPlugin
 */

use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;

global $table_prefix;

require_once __DIR__ . '/support/class-identifier.php';
require_once __DIR__ . '/support/class-fixture-plugins.php';

// The plugins folder has to exist before the config file names it and WordPress loads.
Fixture_Plugins::create();
register_shutdown_function(
	static function () {
		Fixture_Plugins::remove( Fixture_Plugins::path() );
		Fixture_Plugins::remove( Fixture_Plugins::content_path() );
	}
);

require_once __DIR__ . '/wp-tests-config.php';

// Create the test database when it is missing.
list( $hpos_db_host, $hpos_db_port ) = array_pad( explode( ':', DB_HOST, 2 ), 2, '3306' );
$hpos_db_link                        = @mysqli_connect( $hpos_db_host, DB_USER, DB_PASSWORD, '', (int) $hpos_db_port ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_connect, WordPress.PHP.NoSilencedErrors.Discouraged -- Runs before WordPress loads; the failure is reported below.

if ( false === $hpos_db_link || ! mysqli_query( $hpos_db_link, 'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`' ) ) { // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query, WordPress.DB.PreparedSQL.NotPrepared -- The name passed Identifier::check().
	fwrite( STDERR, 'Cannot reach the test database at ' . $hpos_db_host . ':' . $hpos_db_port . '. Run "wp-env start" to provide it.' . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output before WordPress loads.
	exit( 1 );
}
mysqli_close( $hpos_db_link ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_close -- Runs before WordPress loads.

$GLOBALS['hpos_scanner_tests_http_attempts'] = 0;
$GLOBALS['hpos_scanner_tests_http_urls']     = array();

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

$hpos_library = getenv( 'WP_PHPUNIT__DIR' );
if ( false === $hpos_library || '' === $hpos_library ) {
	$hpos_library = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $hpos_library . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/hpos-compatibility-scanner.php';
	}
);

// Block outbound HTTP and count the attempts, so a test can assert there were none.
tests_add_filter(
	'pre_http_request',
	static function ( $response, $args, $url ) {
		++$GLOBALS['hpos_scanner_tests_http_attempts'];
		$GLOBALS['hpos_scanner_tests_http_urls'][] = $url;

		return new WP_Error( 'hpos_scanner_tests_http_blocked', 'Outbound HTTP is blocked in tests.' );
	},
	10,
	3
);

// The update checker would call the VCS host.
tests_add_filter( 'puc_check_now-hpos-compatibility-scanner', '__return_false' );

require $hpos_library . '/includes/bootstrap.php';

// Two handlers call get_plugins(), which the test library does not load.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

require_once __DIR__ . '/support/class-ajax-test-case.php';
