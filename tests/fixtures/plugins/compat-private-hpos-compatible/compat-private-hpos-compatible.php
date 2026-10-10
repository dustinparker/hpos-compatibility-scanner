<?php
/**
 * Plugin Name: Fixture: compat-private-hpos-compatible
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_private_hpos_compatible() {
	add_action( 'before_woocommerce_init', 'x' );
	$name = 'is_hpos_compatible';
}
