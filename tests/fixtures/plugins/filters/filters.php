<?php
/**
 * Plugin Name: Fixture: filters
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_filters() {
	$wpdb = null;
	$a = 'hpos_fixture_plain_term';
	$a = 'hpos_fixture_typed_pattern';
	$a = 'hpos_fixture_untyped_pattern';
	$a = $wpdb->posts; // hpos_fixture_safe_marker
}
