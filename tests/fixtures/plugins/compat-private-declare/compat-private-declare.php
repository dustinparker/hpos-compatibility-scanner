<?php
/**
 * Plugin Name: Fixture: compat-private-declare
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_private_declare() {
	add_action( 'before_woocommerce_init', 'x' );
	$name = 'declare_compatibility_with';
}
