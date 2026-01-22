<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundles;
use AsanaPlugins\WooCommerce\ProductBundles\Helpers\Products;

function get_plugin() {
	return Plugin::instance();
}

/**
 * Getting term hierarchy name.
 *
 * @since  1.0.0
 *
 * @param  int|WP_Term|object $term_id
 * @param  string             $taxonomy
 * @param  string             $separator
 * @param  boolean            $nicename
 * @param  array              $visited
 *
 * @return string
 */
function get_term_hierarchy_name( $term_id, $taxonomy, $separator = '/', $nicename = false, $visited = array() ) {
	$chain = '';
	$term = get_term( $term_id, $taxonomy );

	if ( is_wp_error( $term ) ) {
		return '';
	}

	$name = $term->name;
	if ( $nicename ) {
		$name = $term->slug;
	}

	if ( $term->parent && ( $term->parent != $term->term_id ) && ! in_array( $term->parent, $visited ) ) {
		$visited[] = $term->parent;
		$chain     .= get_term_hierarchy_name( $term->parent, $taxonomy, $separator, $nicename, $visited );
	}

	$chain .= $name . $separator;

	return $chain;
}

function get_product_gallery_images_src( $product ) {
	$size    = apply_filters( 'woocommerce_gallery_image_size', version_compare( WC_VERSION, '3.3.0', '<' ) ? 'shop_single' : 'woocommerce_single' );
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	if ( ! $product ) {
		return apply_filters( 'asnp_wepb_pro_get_product_gallery_images_src', [], $product, $size );
	}

	$attachment_ids = $product->get_gallery_image_ids();
	if ( empty( $attachment_ids ) ) {
		return apply_filters( 'asnp_wepb_pro_get_product_gallery_images_src', [], $product, $size );
	}

	$src = [];
	foreach ( $attachment_ids as $id ) {
		$image = wp_get_attachment_image_src( $id, $size );
		if ( ! empty( $image ) && ! empty( $image[0] ) ) {
			$src[] = $image;
		}
	}

	return apply_filters( 'asnp_wepb_pro_get_product_gallery_images_src', $src, $product, $size );
}

function get_variable_select_attributes( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

	if ( ! $product || ! $product->is_type( 'variable' ) ) {
		return null;
	}

	$attributes = $product->get_variation_attributes();

	$data = [];
	foreach ( $attributes as $attribute_name => $options ) {
		if ( empty( $options ) ) {
			continue;
		}

		$attribute_options = [];
		if ( $product && taxonomy_exists( $attribute_name ) ) {
			// Get terms if this is a taxonomy - ordered. We need the names too.
			$terms = wc_get_product_terms(
				$product->get_id(),
				$attribute_name,
				[
					'fields' => 'all',
				]
			);

			foreach ( $terms as $term ) {
				if ( in_array( $term->slug, $options, true ) ) {
					$attribute_options[ $term->slug ] = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute_name, $product );
				}
			}
		} else {
			foreach ( $options as $option ) {
				$attribute_options[ $option ] =  apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute_name, $product );
			}
		}

		if ( ! empty( $attribute_options ) ) {
			$data[ sanitize_title( $attribute_name ) ] = [
				'label'   => wc_attribute_label( $attribute_name ),
				'options' => $attribute_options,
				'value'   => $product->get_variation_default_attribute( $attribute_name ),
			];
		}
	}

	return apply_filters( 'asnp_wepb_pro_get_variable_select_attributes', $data, $product );
}

function get_variation_select_attributes( $product, $extra_data ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;

	if ( ! $product || ! $product->is_type( 'variation' ) ) {
		return null;
	}

	$variation_attributes = $product->get_variation_attributes( false );
	$any_attributes       = ProductBundles\get_any_value_attributes( $variation_attributes );
	if ( empty( $any_attributes ) ) {
		return null;
	}

	$variable = wc_get_product( $product->get_parent_id() );
	if ( ! $variable ) {
		return null;
	}

	// Check if all attribute values are set and non-empty in extra_data['attributes']
	if ( ! empty( $extra_data['attributes'] ) ) {
		$all_set = true;
		foreach ( $extra_data['attributes'] as $attribute ) {
			if ( ! isset( $attribute['value'] ) || '' === $attribute['value'] ) {
				$all_set = false;
				break;
			}
		}
		if ( $all_set ) {
			return null;
		}
	}

	$attributes = $variable->get_variation_attributes();

	$data = [];

	foreach ( $any_attributes as $any_attr ) {
		if ( ! is_array( $attributes[ $any_attr ] ) ) {
			continue;
		}

		$attribute_options = [];
		foreach ( $attributes[ $any_attr ] as $option ) {
			if ( $variable && taxonomy_exists( $any_attr ) ) { 
				$terms = wc_get_product_terms(
					$variable->get_id(),
					$any_attr,
					[
						'fields' => 'all',
					]
				);

				foreach ( $terms as $term ) {
					if ( in_array( $term->slug, $attributes[ $any_attr ], true ) ) {
						$attribute_options[ $term->slug ] = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $any_attr, $variable );
					}
				}
			} else {
				foreach ( $attributes[ $any_attr ] as $option ) {
					$attribute_options[ $option ] = apply_filters( 'woocommerce_variation_option_name', $option, null, $any_attr, $variable );
				}
			}

			if ( ! empty( $attribute_options ) ) {
				$data[ sanitize_title( $any_attr ) ] = [
					'label'   => wc_attribute_label( $any_attr ),
					'options' => $attribute_options,
					'value'   => $variable->get_variation_default_attribute( $any_attr ),
				];
			}
		}
	}

	return apply_filters( 'asnp_wepb_pro_get_variation_select_attributes', $data, $product );
}

function prepare_variations( $product, $item ) {
	if ( ! $product->is_type( 'variable' ) ) {
		return [];
	}

	$variations = $product->get_available_variations( 'objects' );
	if ( empty( $variations ) ) {
		return [];
	}

	$hide_out_of_stock = 'true' === get_plugin()->settings->get_setting( 'hide_out_of_stock', 'false' );
	$show_stock        = 'true' === get_plugin()->settings->get_setting( 'show_stock', 'false' );

	$data = [];
	foreach ( $variations as $variation ) {
		if ( $hide_out_of_stock && ! $variation->is_in_stock() ) {
			continue;
		} elseif ( ! $variation->is_purchasable() ) {
			continue;
		}

		$var_arr = [
			'id'                   => $variation->get_id(),
			'variation_id'         => $variation->get_id(),
			'attributes'           => $variation->get_variation_attributes(),
			'image'                => wc_get_product_attachment_props( $variation->get_image_id() ),
			'image_id'             => $variation->get_image_id(),
			'is_downloadable'      => $variation->is_downloadable(),
			'is_in_stock'          => $variation->is_in_stock(),
			'is_purchasable'       => $variation->is_purchasable(),
			'is_sold_individually' => $variation->is_sold_individually() ? 'yes' : 'no',
			'max_qty'              => 0 < $variation->get_max_purchase_quantity() ? $variation->get_max_purchase_quantity() : '',
			'min_qty'              => $variation->get_min_purchase_quantity(),
			'description'          => Products\get_description( $variation ),
		];

		if ( $show_stock ) {
			$var_arr['stock'] = wc_get_stock_html( $variation );
		}

		$data[] = array_merge( $var_arr, ProductBundles\prepare_product_prices( $variation, $item ) );
	}

	return apply_filters( 'asnp_wepb_pro_prepare_variations', $data, $product, $item );
}

function get_chosen_attributes( $select_attributes ) {
	$data = [];
	$count = $chosen = 0;

	foreach ( $select_attributes as $key => $attribute ) {
		if ( ! empty( $attribute['value'] ) ) {
			++$chosen;
		}

		++$count;
		$data[ 'attribute_' . $key ] = ! empty( $attribute['value'] ) ? $attribute['value'] : '';
	}

	return [
		'count' => $count,
		'chosen_count' => $chosen,
		'data' => $data,
	];
}

function find_matching_variation( $variations, $attributes ) {
	for ( $i = 0; $i < count( $variations ); $i++ ) {
		$variation = $variations[ $i ];

		if ( variation_is_match( $variation['attributes'], $attributes ) ) {
			return $variation;
		}
	}
	return null;
}

function variation_is_match( $variation_attributes, $attributes ) {
	foreach ( $variation_attributes as $key => $value ) {
		if ( isset( $attributes[ $key ] ) ) {
			// If attribute is set and not empty, must match or be empty (for 'any' attribute)
			if ( '' !== $attributes[ $key ] && '' !== $value && $value !== $attributes[ $key ] ) {
				return false;
			}
		} elseif ( '' !== $value ) {
			// If attribute is not set and variation requires a value, not a match
			return false;
		}
	}
	return true;
}

function find_default_variation( $variations, $select_attributes ) {
	if ( empty( $variations ) || empty( $select_attributes ) ) {
		return null;
	}

	$attributes = get_chosen_attributes( $select_attributes );
	$current_attributes = $attributes['data'];

	if ( isset( $attributes['count'] ) && $attributes['count'] === $attributes['chosen_count'] ) {
		return find_matching_variation( $variations, $current_attributes );
	}

	return null;
}
