<?php
/**
 * WordPress test library configuration.
 *
 * Every connection value can be overridden by an environment variable. The fallbacks are the
 * published defaults of the wp-env tests database.
 *
 * @package DPWD\HPOSCompatPlugin
 */

use DPWD\HPOSCompatPlugin\Tests\Support\Fixture_Plugins;
use DPWD\HPOSCompatPlugin\Tests\Support\Identifier;

require_once __DIR__ . '/support/class-identifier.php';
require_once __DIR__ . '/support/class-fixture-plugins.php';

/**
 * Read an environment variable, or fall back when it is unset or empty.
 *
 * @param string $name     Variable name.
 * @param string $fallback Value to use when the variable is not set.
 */
$hpos_env = static function ( string $name, string $fallback ): string {
	$value = getenv( $name );

	return false === $value || '' === $value ? $fallback : $value;
};

$hpos_token = getenv( 'TEST_TOKEN' );

define( 'DB_NAME', Identifier::check( $hpos_env( 'WP_TESTS_DB_NAME', 'hpos_scanner_tests' ) ) );
define( 'DB_USER', $hpos_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', $hpos_env( 'WP_TESTS_DB_PASSWORD', 'password' ) );
define( 'DB_HOST', $hpos_env( 'WP_TESTS_DB_HOST', '127.0.0.1' ) . ':' . $hpos_env( 'WP_TESTS_DB_PORT', '13507' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The test library reads the prefix from this variable.
$table_prefix = Identifier::check( 'wptests_' . ( false === $hpos_token || '' === $hpos_token ? '' : $hpos_token . '_' ) );

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );
define( 'WP_PLUGIN_DIR', Fixture_Plugins::path() );
define( 'WP_CONTENT_DIR', Fixture_Plugins::content_path() );

define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'HPOS Scanner tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );

unset( $hpos_env, $hpos_token );
