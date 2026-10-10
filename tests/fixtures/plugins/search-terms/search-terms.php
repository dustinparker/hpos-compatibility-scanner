<?php
/**
 * Plugin Name: Fixture: search terms
 * Version: 1.0.0
 * Author: Fixture
 */

defined( 'ABSPATH' ) || exit;

function hpos_fixture_search_terms() {
	$wpdb = null;
	$a = array( 'post_type' => 'shop_order' );
	$a = "'post_type' = 'shop_order'";
	$a = "post_type = 'shop_order'";
	$a = array( 'post_type' => 'shop_order_refund' );
	$a = "'post_type' = 'shop_order_refund'";
	$a = "post_type = 'shop_order_refund'";
	$a = $wpdb->posts;
	$a = $wpdb->postmeta;
	$a = 'SELECT ID FROM posts';
	$a = 'SELECT meta_id FROM postmeta';
	$a = 'wp_posts';
	$a = 'wp_postmeta';
	$a = get_post( $order );
	$a = get_post_meta( $order_id, 'key' );
	update_post_meta( $order, 'key', 'value' );
	delete_post_meta( $order_id, 'key' );
	$a = new WP_Query( array( 'post_type' => 'shop_order' ) );
	$a = new WP_Query( array( 'post_type' => 'shop_order_refund' ) );
	$a = get_posts( array( 'post_type' => 'shop_order' ) );
	$a = get_posts( array( 'post_type' => 'shop_order_refund' ) );
	$a = new WC_Order( 1 );
	$a = new WC_Order_Query( array() );
	$a = WC()->order_factory;
	$a = 'woocommerce_order_data_store_cpt';
	$a = 'woocommerce_order_get_items';
	$a = 'woocommerce_before_order_object_save';
	$a = '/wc/v1/orders';
	$a = '/wc/v2/orders';
	$a = '/wc-api/v3/orders';
	$a = 'wc-api=wc-orders';
}
