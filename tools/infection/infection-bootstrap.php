<?php
/**
 * Loads the mutated classes into the Infection process so it can resolve them by name.
 *
 * The plugin classes are not PSR-4 autoloadable, and the plugin autoloader needs WordPress.
 *
 * @package DPWD\HPOSCompatPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

require_once dirname( __DIR__, 2 ) . '/src/scanner/class-directory-open-exception.php';
require_once dirname( __DIR__, 2 ) . '/src/scanner/class-directory-scanner.php';
require_once dirname( __DIR__, 2 ) . '/src/scanner/class-scanner.php';
require_once dirname( __DIR__, 2 ) . '/src/compatibility/class-compatibility.php';