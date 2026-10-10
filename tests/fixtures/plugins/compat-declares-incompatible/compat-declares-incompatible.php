<?php
/**
 * Plugin Name: Fixture: compat-declares-incompatible
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_compat_declares_incompatible() {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, false );
}
