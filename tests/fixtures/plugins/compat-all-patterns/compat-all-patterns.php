<?php
/**
 * Plugin Name: Fixture: compat-all-patterns
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

// Holds every pattern the private check looks for.
function hpos_fixture_compat_all_patterns() {
	add_action( 'before_woocommerce_init', 'declare_hpos_compatibility' );
	add_action( 'before_woocommerce_init', 'declare_wc_hpos_compatibility' );
	add_action( 'before_woocommerce_init', 'woocommerce_hpos_compatible' );
	add_action( 'before_woocommerce_init', 'declare_compatibility_with' );
	FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
}
