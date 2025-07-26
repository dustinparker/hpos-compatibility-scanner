<?php
/**
 * The main class of the HPOS Compatibility Scanner plugin.
 *
 * @package DPWD\HPOSCompatPlugin
 */

namespace DPWD\HPOSCompatPlugin;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DPWD\HPOSCompatPlugin\Admin\Admin;
use DPWD\HPOSCompatPlugin\Scanner\Scanner;
use DPWD\HPOSCompatPlugin\Compatibility\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Main class for the HPOS Compatibility Scanner plugin.
 */
class Plugin {

	/**
	 * Instance to call certain functions globally within the plugin.
	 *
	 * @var self|null $instance
	 */
	protected static ?Plugin $instance = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_plugin' ), 0 );
		add_action( 'hpos_compatibility_scanner_activated', array( $this, 'activation_hooks' ) );
		add_action( 'hpos_compatibility_scanner_deactivated', array( $this, 'deactivation_hooks' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_hpos_compatibility' ), 10 );
	}

	/**
	 * HPOS Compatibility Scanner.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @return Plugin|null Plugin instance.
	 */
	public static function instance(): ?Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin activation hooks.
	 */
	public function activation_hooks(): void {
		/**
		 * Run activation hooks.
		 *
		 * @since 1.0.0
		 */
		do_action( 'hpos_compatibility_scanner_activation_hooks' );
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hooks.
	 */
	public function deactivation_hooks(): void {
		flush_rewrite_rules();
	}

	/**
	 * Declare WooCommerce HPOS feature compatibility.
	 */
	public function declare_wc_hpos_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', HPOS_COMPATIBILITY_SCANNER_PLUGIN_FILE );
		}
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin(): void {
		$this->init_hooks();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ), 1 );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		new Admin();
		new Scanner();
		new Compatibility();

		/**
		 * Run plugin loaded hooks.
		 *
		 * @since 1.0.0
		 */
		do_action( 'hpos_compatibility_scanner_loaded' );
	}
}
