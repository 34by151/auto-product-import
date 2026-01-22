<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro\API;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundlesPro\Models\ItemsModel;

class ItemsHooks {

	public static function init() {
		add_filter( 'asnp_wepb_items_api_search_items', array( __CLASS__, 'search_items' ), 10, 3 );
		add_filter( 'asnp_wepb_items_api_get_items', array( __CLASS__, 'get_items' ), 10, 3 );
	}

	/**
	 * Search items.
	 *
	 * @param array           $items
	 * @param string          $search
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array
	 */
	public static function search_items( $items, $search, $request ) {
		if ( empty( $request['type'] ) || empty( $search ) ) {
			return $items;
		}

		switch ( $request['type'] ) {
			case 'categories':
				$items = ItemsModel::get_categories( array( 'name__like' => $search ) );
				break;

			case 'tags':
				$items = ItemsModel::get_tags( array( 'name__like' => $search ) );
				break;
		}

		return $items;
	}

	/**
	 * Get items.
	 *
	 * @param array           $items
	 * @param array           $req_items
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array
	 */
	public static function get_items( $items, $req_items, $request ) {
		if ( empty( $request['type'] ) || empty( $req_items ) ) {
			return $items;
		}

		switch ( $request['type'] ) {
			case 'categories':
				$items = ItemsModel::get_categories( array( 'include' => array_filter( array_map( 'absint', $req_items ) ) ) );
				break;

			case 'tags':
				$items = ItemsModel::get_tags( array( 'include' => array_filter( array_map( 'absint', $req_items ) ) ) );
				break;
		}

		return $items;
	}

}
