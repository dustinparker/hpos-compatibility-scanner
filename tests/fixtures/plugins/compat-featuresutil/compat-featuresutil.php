<?php
/**
 * Plugin Name: Fixture: compat-featuresutil
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_featuresutil() {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
}
