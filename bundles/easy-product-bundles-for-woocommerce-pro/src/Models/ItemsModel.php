<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro\Models;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundlesPro;
use AsanaPlugins\WooCommerce\ProductBundles\Models\ItemsModel as BaseItemsModel;

class ItemsModel extends BaseItemsModel {

	public static function get_categories( array $args = array() ) {
		$defaults = array(
			'separator'          => '/',
			'nicename'           => false,
			'pad_counts'         => 1,
			'show_count'         => 1,
			'hierarchical'       => 1,
			'hide_empty'         => 0,
			'show_uncategorized' => 0,
			'orderby'            => 'name',
			'menu_order'         => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( 'order' === $args['orderby'] ) {
			$args['menu_order'] = 'asc';
			$args['orderby']    = 'name';
		}

		$terms = get_terms( 'product_cat', apply_filters( 'asnp_wepb_get_categories_args', $args ) );
		if ( empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $category ) {
			$categories[] = (object) array(
				'value' => $category->term_id,
				'label' => rtrim( ProductBundlesPro\get_term_hierarchy_name( $category->term_id, 'product_cat', $args['separator'], $args['nicename'] ), $args['separator'] ),
				'slug'  => $category->slug,
				'name'  => $category->name,
			);
		}

		return $categories;
	}

	public static function get_tags( array $args = array() ) {
		$args  = wp_parse_args(
			$args,
			array(
				'hide_empty' => 0,
				'nicename'   => false,
			)
		);
		$terms = get_terms( 'product_tag', apply_filters( 'asnp_wepb_get_tags_args', $args ) );
		if ( empty( $terms ) ) {
			return array();
		}

		$ret_terms = array();
		foreach ( $terms as $term ) {
			$ret_terms[] = (object) array(
				'value' => $term->term_id,
				'label' => $args['nicename'] ? $term->slug : $term->name,
				'slug'  => $term->slug,
				'name'  => $term->name,
			);
		}

		return $ret_terms;
	}

}
