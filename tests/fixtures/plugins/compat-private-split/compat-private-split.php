<?php
/**
 * Plugin Name: Fixture: compat-private-split
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_private_split() {
	FeaturesUtil::declare_compatibility( 'analytics', __FILE__, true );
	$feature = 'custom_order_tables';
}
