<?php
/**
 * Plugin Name: Fixture: compat-private-hpos-compatibility
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_private_hpos_compatibility() {
	add_action( 'before_woocommerce_init', 'x' );
	$name = 'my_hpos_compatibility';
}
