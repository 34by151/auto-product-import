<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro\Hooks;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundlesPro;

function calculate_discount( $value, $price, $discount, $discount_type ) {
	if ( 'fixed' === $discount_type ) {
		if ( '' !== $discount && 0 <= (float) $discount ) {
			$value = (float) $price - (float) $discount;
		}
	}

	return $value;
}
add_filter(
	'asnp_wepb_discount_calculator_calculate',
	__NAMESPACE__ . '\calculate_discount',
	10,
	4
);

function prepare_product_data( $data, $product, $item, $extra_data ) {
	if ( ! $product ) {
		return $data;
	}

	$data['images'] = ProductBundlesPro\get_product_gallery_images_src( $product );

	if ( $product->is_type( 'variable' ) ) {
		$data['select_attributes'] = ProductBundlesPro\get_variable_select_attributes( $product );
		$data['variations']        = ProductBundlesPro\prepare_variations( $product, $item );
		$data['variation']         = ProductBundlesPro\find_default_variation( $data['variations'], $data['select_attributes'] );
	} elseif ( $product->is_type( 'variation' ) ) {
		$data['select_attributes'] = ProductBundlesPro\get_variation_select_attributes( $product, $extra_data );
	}

	return $data;
}
add_filter(
	'asnp_wepb_prepare_product_data',
	__NAMESPACE__ . '\prepare_product_data',
	10,
	4
);
