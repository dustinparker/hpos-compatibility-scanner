<?php
/**
 * Plugin Name: Fixture: compat-hook-before-init
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_hook_before_init() {
	$feature = 'custom_order_tables';
	add_action( 'before_woocommerce_init', 'hpos_fixture_noop' );
}
