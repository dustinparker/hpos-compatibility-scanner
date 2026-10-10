<?php
/**
 * Plugin Name: Fixture: compat-helper-woocommerce-hpos
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_helper_woocommerce_hpos() {
	woocommerce_hpos_compatible();
}
