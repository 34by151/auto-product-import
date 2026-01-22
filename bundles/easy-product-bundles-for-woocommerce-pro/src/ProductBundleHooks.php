<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundles;
use AsanaPlugins\WooCommerce\ProductBundles\Helpers\Cart;
use AsanaPlugins\WooCommerce\ProductBundles\ProductBundleHooks as BaseProductBundleHooks;
use AsanaPlugins\WooCommerce\ProductBundlesPro\Models\ItemsModel;

class ProductBundleHooks extends BaseProductBundleHooks {

	public function init() {
		parent::init();

		add_filter( 'asnp_wepb_get_bundle_item_data', array( $this, 'get_bundle_item_data' ) );
		add_filter( 'asnp_wepb_add_to_cart_validation_item_is_valid', array( $this, 'optional_item_check' ), 10, 3 );
	}

	public function cart_item_price( $price, $cart_item ) {
		if ( ProductBundles\is_cart_item_bundle_item( $cart_item ) ) {
			if ( 'false' === get_plugin()->settings->get_setting( 'show_item_price', 'true' ) ) {
				return '';
			}

			if ( ! empty( $cart_item['asnp_wepb_hide_price'] ) ) {
				if ( 'yes' === $cart_item['asnp_wepb_hide_price'] ) {
					return '';
				} elseif ( 'only_regular_price' === $cart_item['asnp_wepb_hide_price'] ) {
					if ( isset( $cart_item['asnp_wepb_price'] ) ) {
						if ( Cart\display_prices_including_tax() ) {
							$price = wc_get_price_including_tax( $cart_item['data'], [ 'price' => $cart_item['asnp_wepb_price'] ] );
						} else {
							$price = wc_get_price_excluding_tax( $cart_item['data'], [ 'price' => $cart_item['asnp_wepb_price'] ] );
						}
						return wc_price( $price );
					}
				}
			}
		}

		return parent::cart_item_price( $price, $cart_item );
	}

	public function cart_item_subtotal( $subtotal, $cart_item ) {
		if ( ProductBundles\is_cart_item_bundle_item( $cart_item ) ) {
			if ( 'false' === get_plugin()->settings->get_setting( 'show_item_price', 'true' ) ) {
				return '';
			}

			if ( ! empty( $cart_item['asnp_wepb_hide_price'] ) ) {
				if ( 'yes' === $cart_item['asnp_wepb_hide_price'] ) {
					return '';
				}
			}
		}

		return parent::cart_item_subtotal( $subtotal, $cart_item );
	}

	public function get_bundle_item_data( $item ) {
		if ( empty( $item ) ) {
			return $item;
		}

		if ( ! empty( $item['categories'] ) ) {
			$item['categories'] = ItemsModel::get_categories( array( 'include' => array_map( 'absint', $item['categories'] ) ) );;
		}

		if ( ! empty( $item['excluded_categories'] ) ) {
			$item['excluded_categories'] = ItemsModel::get_categories( array( 'include' => array_map( 'absint', $item['excluded_categories'] ) ) );
		}

		if ( ! empty( $item['tags'] ) ) {
			$item['tags'] = ItemsModel::get_tags( array( 'include' => array_map( 'absint', $item['tags'] ) ) );
		}

		if ( ! empty( $item['excluded_tags'] ) ) {
			$item['excluded_tags'] = ItemsModel::get_tags( array( 'include' => array_map( 'absint', $item['excluded_tags'] ) ) );
		}

		return $item;
	}

	public function optional_item_check( $is_valid, $item, $id ) {
		// Is item an optional item.
		if ( 0 >= $id ) {
			if ( ! empty( $item['optional'] ) && 'true' === $item['optional'] ) {
				return 'continue';
			} else {
				throw new \Exception( __( 'Please select a product for each of the required bundle items.', 'asnp-easy-product-bundles-pro' ) );
			}
		}

		return $is_valid;
	}

	public function add_to_cart_validation( $passed, $product_id, $product_quantity, $variation_id = null, $variations = null, $cart_item_data = null ) {
		try {
			$passed = parent::add_to_cart_validation( $passed, $product_id, $product_quantity, $variation_id, $variations, $cart_item_data );
			if ( ! $passed ) {
				return $passed;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_type( Plugin::PRODUCT_TYPE ) ) {
				return $passed;
			}

			$min_items_quantity = $product->get_min_items_quantity();
			$max_items_quantity = $product->get_max_items_quantity();

			if ( empty( trim( $min_items_quantity ) ) && empty( trim( $max_items_quantity ) ) ) {
				return $passed;
			}

			$min_items_quantity = absint( $min_items_quantity );
			$max_items_quantity = absint( $max_items_quantity );

			$req_items = ! empty( $_REQUEST['asnp_wepb_items'] ) ? ProductBundles\maybe_convert_items_to_json( wp_unslash( $_REQUEST['asnp_wepb_items'] ) ) : '';
			if ( empty( $req_items ) && ! empty( $cart_item_data[ self::CART_ITEM_ITEMS ] ) ) {
				$req_items = ProductBundles\maybe_convert_items_to_json( $cart_item_data[ self::CART_ITEM_ITEMS ] );
			}

			$quantities = ! empty( $req_items ) ? ProductBundles\get_quantities_from_bundle_items( $req_items ) : [];
			$total      = ! empty( $quantities ) ? array_sum( $quantities ) : 0;

			if ( 0 < $min_items_quantity && $total < $min_items_quantity ) {
				/* translators: %d: minimum items quantity */
				$message = sprintf( __( 'Total items quantity should be at least %d or more.', 'asnp-easy-product-bundles' ), $min_items_quantity );

				/**
				 * Filters message about product being out of stock.
				 *
				 * @since 1.0.0
				 * @param string     $message Message.
				 * @param WC_Product $item_product Product data.
				 */
				$message = apply_filters( 'asnp_wepb_cart_bundle_min_items_quantity_message', $message, $min_items_quantity, $product );
				throw new \Exception( $message );
			}

			if ( 0 < $max_items_quantity && $total > $max_items_quantity ) {
				/* translators: %d: maximum items quantity */
				$message = sprintf( __( 'Total items quantity should not exceed %d.', 'asnp-easy-product-bundles' ), $max_items_quantity );

				/**
				 * Filters message about product being out of stock.
				 *
				 * @since 1.0.0
				 * @param string     $message Message.
				 * @param WC_Product $item_product Product data.
				 */
				$message = apply_filters( 'asnp_wepb_cart_bundle_max_items_quantity_message', $message, $max_items_quantity, $product );
				throw new \Exception( $message );
			}

			return $passed;
		} catch ( \Exception $e ) {
			if ( $e->getMessage() ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
			return false;
		}
	}

	protected function update_cart_validation_bundle( $passed, $cart_item, $quantity ) {
		$items = isset( $cart_item['asnp_wepb_items'] ) ? ProductBundles\maybe_convert_items_to_json( $cart_item['asnp_wepb_items'] ) : '';
		if ( empty( $items ) ) {
			return $passed;
		}

		$ids           = ProductBundles\get_product_ids_from_bundle_items( $items );
		$quantities    = ProductBundles\get_quantities_from_bundle_items( $items );
		$product_items = $cart_item['data']->get_items();
		if ( empty( $ids ) || empty( $quantities ) || count( $ids ) !== count( $product_items ) ) {
			return false;
		}

		$i = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! ProductBundles\is_cart_item_bundle_item( $item ) ) {
				continue;
			}

			// Is it item of the updated product.
			if (
				! isset( $item['asnp_wepb_parent_key'] ) ||
				$cart_item['key'] != $item['asnp_wepb_parent_key']
			) {
				continue;
			}

			// Skip optional items that does not set any product to them.
			while ( true ) {
				if ( isset( $ids[ $i ] ) && 0 == $ids[ $i ] ) {
					++$i;
					continue;
				}
				break;
			}

			$item_quantity = $quantity * $quantities[ $i ];

			if ( 1 < $item_quantity && $item['data']->is_sold_individually() ) {
				/* Translators: %s Product title. */
				wc_add_notice( sprintf( __( 'You can only have 1 %s in your cart.', 'asnp-easy-product-bundles' ), $item['data']->get_name() ), 'error' );
				return false;
			} else {
				if ( ! empty( $product_items[ $i ]['edit_quantity'] ) && 'true' === $product_items[ $i ]['edit_quantity'] ) {
					$min_quantity = ! empty( $product_items[ $i ]['min_quantity'] ) ? (int) $product_items[ $i ]['min_quantity'] * $quantity : '';
					$max_quantity = ! empty( $product_items[ $i ]['max_quantity'] ) ? (int) $product_items[ $i ]['max_quantity'] * $quantity : '';

					if ( $min_quantity && $item_quantity < $min_quantity ) {
						wc_add_notice( sprintf( __( 'Cart update failed. The quantity of &quot;%1$s&quot; must be at least %2$d.', 'asnp-easy-product-bundles' ), $item['data']->get_name(), $min_quantity ), 'error' );
						return false;
					}
					if ( $max_quantity && $item_quantity > $max_quantity ) {
						wc_add_notice( sprintf( __( 'Cart update failed. The quantity of &quot;%1$s&quot; cannot be higher than %2$d.', 'asnp-easy-product-bundles' ), $item['data']->get_name(), $max_quantity ), 'error' );
						return false;
					}
					if ( ! $min_quantity && ! $max_quantity && $item_quantity !== (int) $product_items[ $i ]['quantity'] * $quantity ) {
						wc_add_notice( sprintf( __( 'Cart update failed. The quantity of &quot;%1$s&quot; must be equal to %2$d.', 'asnp-easy-product-bundles' ), $item['data']->get_name(), (int) $product_items[ $i ]['quantity'] * $quantity ), 'error' );
						return false;
					}
				} elseif ( $item_quantity !== (int) $product_items[ $i ]['quantity'] * $quantity ) {
					wc_add_notice( sprintf( __( 'Cart update failed. The quantity of &quot;%1$s&quot; must be equal to %2$d.', 'asnp-easy-product-bundles' ), $item['data']->get_name(), (int) $product_items[ $i ]['quantity'] * $quantity ), 'error' );
					return false;
				}
			}

			if ( ! $item['data']->has_enough_stock( $item_quantity ) ) {
				$stock_quantity = $item['data']->get_stock_quantity();
				/* translators: 1: product name 2: quantity in stock */
				wc_add_notice( sprintf( __( 'You cannot add that amount of &quot;%1$s&quot; to the cart because there is not enough stock (%2$s remaining).', 'asnp-easy-product-bundles' ), $item['data']->get_name(), wc_format_stock_quantity_for_display( $stock_quantity, $item['data'] ) ), 'error' );
				return false;
			}

			++$i;
		}

		return $passed;
	}

}
