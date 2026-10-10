<?php
/**
 * Plugin Name: Fixture: compat-hook-plugins-loaded
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_hook_plugins_loaded() {
	$feature = 'custom_order_tables';
	add_action( 'plugins_loaded', 'hpos_fixture_noop' );
}
