<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundles;
use AsanaPlugins\WooCommerce\ProductBundles\ProductSelector as BaseProductSelector;
use AsanaPlugins\WooCommerce\ProductBundles\Helpers\Products;

class ProductSelector extends BaseProductSelector {

	protected function query( array $args ) {
		if ( empty( $args ) ) {
			throw new \Exception( __( 'Query args is required.', 'asnp-easy-product-bundles' ) );
		}

		$cat_tag_products = [];
		if ( ! empty( $args['categories'] ) || ! empty( $args['tags'] ) ) {
			$cat_tag_products = Products\get_products( [
				'category'           => $args['categories'],
				'tag'                => $args['tags'],
				'tax_query_relation' => ! empty( $args['query_relation'] ) && 'AND' === $args['query_relation'] ? 'AND' : 'OR',
				'limit'              => -1,
				'return'             => 'ids',
			] );
		}

		$exclude_cat_tag_products = [];
		if ( ! empty( $args['excluded_categories'] ) || ! empty( $args['excluded_tags'] ) ) {
			$exclude_cat_tag_products = Products\get_products( [
				'category'           => $args['excluded_categories'],
				'tag'                => $args['excluded_tags'],
				'tax_query_relation' => ! empty( $args['query_relation'] ) && 'AND' === $args['query_relation'] ? 'AND' : 'OR',
				'limit'              => -1,
				'return'             => 'ids',
			] );
		}

		$include = array_merge( $args['products'], $cat_tag_products );
		$exclude = array_merge( $args['excluded_products'], $exclude_cat_tag_products );

		// Exclude $exclude array from $include array when both have values.
		if ( ! empty( $include ) && ! empty( $exclude ) ) {
			$include = array_diff( $include, $exclude );
			$exclude = [];
		}

		if ( empty( $include ) && empty( $exclude ) ) {
			return (object) [
				'products' => [],
				'total'    => 0,
				'pages'    => 0,
			];
		}

		$args = wp_parse_args( $args, [
			'type'     => ProductBundles\get_product_types_for_bundle(),
			'status'   => [ 'publish' ],
			'limit'    => 12,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
		] );

		$tax_query = [];

		// Hide out of stock products.
		if ( ! empty( $args['hide_out_of_stock'] ) ) {
			$product_visibility_terms  = wc_get_product_visibility_term_ids();
			$product_visibility_not_in = [ $product_visibility_terms['outofstock'] ];
			$tax_query[]               = [
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $product_visibility_not_in,
				'operator' => 'NOT IN',
			];
		}

		return Products\get_products( [
			'return'    => ! empty( $args['return'] ) ? $args['return'] : 'objects',
			'status'    => $args['status'],
			'type'      => $args['type'],
			'include'   => $include,
			'exclude'   => $exclude,
			'tax_query' => $tax_query,
			'limit'     => ! empty( $args['limit'] ) && 0 < absint( $args['limit'] ) ? absint( $args['limit'] ) : 12,
			'paginate'  => $args['paginate'],
			'page'      => ! empty( $args['page'] ) && 0 < absint( $args['page'] ) ? absint( $args['page'] ) : 1,
			'orderby'   => $args['orderby'],
			'order'     => $args['order'],
			'search'    => ! empty( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
		] );
	}

}
