<?php
/**
 * Plugin Name: HPOS Compatibility Scanner
 * Description: Scans plugins for potential HPOS compatibility issues by checking for direct database access or inappropriate WordPress API usage.
 * Version: 1.0.2
 * Author: Robert DeVore (original), Dustin Parker (maintainer)
 * Author URI: https://dustinparkerwebdev.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hpos-compatibility-scanner
 * Domain Path: /languages
 * Update URI: https://github.com/dustinparker/hpos-compatibility-scanner/
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin;

// Exit if accessed directly.
use YahnisElsts\PluginUpdateChecker\v5p5\PucFactory;

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Init update checker.
add_action(
	'init',
	function () {
		( PucFactory::buildUpdateChecker(
			'https://github.com/dustinparker/hpos-compatibility-scanner/',
			__FILE__,
			'hpos-compatibility-scanner'
		) )->setBranch( 'main' );
	}
);

// Define plugin constants.
if ( ! defined( 'HPOS_COMPATIBILITY_SCANNER_VERSION' ) ) {
	define( 'HPOS_COMPATIBILITY_SCANNER_VERSION', '1.0.2' );
}

if ( ! defined( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE' ) ) {
	define( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_BASENAME' ) ) {
	define( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_BASENAME', plugin_basename( HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE ) );
}

if ( ! defined( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH' ) ) {
	define( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH', untrailingslashit( plugin_dir_path( HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE ) ) );
}

if ( ! defined( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_URL' ) ) {
	define( 'HPOS_COMPATIBILITY_SCANNER_PLUGIN_URL', untrailingslashit( plugins_url( '/', HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE ) ) );
}

/**
 * Load plugin textdomain for translations.
 *
 * @since  1.0.0
 * @return void
 */
function hpos_load_textdomain() {
	load_plugin_textdomain(
		'hpos-compatibility-scanner',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'DPWD\HPOSCompatPlugin\hpos_load_textdomain' );

/**
 * Register activation hook.
 */
register_activation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'hpos_compatibility_scanner_activated' );
	}
);

/**
 * Register deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'hpos_compatibility_scanner_deactivated' );
	}
);

// Autoload classes.
if ( file_exists( HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH . '/vendor/autoload_packages.php' ) ) {
	require_once HPOS_COMPATIBILITY_SCANNER_PLUGIN_DIR_PATH . '/vendor/autoload_packages.php';
}

// Initialize the plugin.
Plugin::instance();
