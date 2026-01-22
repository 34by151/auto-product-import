<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro;

defined( 'ABSPATH' ) || exit;

class Assets {

    public function init() {
		// add_action( 'asnp_wepb_before_load_product_shared_scripts', array( $this, 'load_product_shared_scripts' ) );
        add_action( 'asnp_wepb_before_load_product_scripts', array( $this, 'load_product_scripts' ) );
    }

	public function load_product_shared_scripts() {
		wp_enqueue_script(
			'asnp-easy-product-bundles-pro-utils',
			$this->get_url( 'utils/index', 'js' ),
			[
				'react-dom',
				'wp-hooks',
				'wp-i18n',
				'wp-api-fetch',
			],
			ASNP_WEPB_PRO_VERSION,
			true
		);
	}

	public function load_product_scripts() {
		wp_register_script(
			'asnp-easy-product-bundles-pro-utils',
			$this->get_url( 'utils/index', 'js' ),
			[ 'asnp-easy-product-bundles-shared' ],
			ASNP_WEPB_PRO_VERSION,
			true
		);
		wp_enqueue_style(
			'asnp-easy-product-bundles-pro-product-bundle',
			$this->get_url( 'product/style', 'css' ),
			[ 'dashicons' ],
			ASNP_WEPB_PRO_VERSION
		);
		wp_enqueue_script(
			'asnp-easy-product-bundles-pro-product-bundle',
			$this->get_url( 'product/index', 'js' ),
			[ 'asnp-easy-product-bundles-pro-utils' ],
			ASNP_WEPB_PRO_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'asnp-easy-product-bundles-pro-product-bundle', 'asnp-easy-product-bundles-pro', ASNP_WEPB_PRO_ABSPATH . 'languages' );
		}
	}

    public function get_url( $file, $ext ) {
		return plugins_url( $this->get_path( $ext ) . $file . '.' . $ext, ASNP_WEPB_PRO_PLUGIN_FILE );
    }

    protected function get_path( $ext ) {
        return 'css' === $ext ? 'assets/css/' : 'assets/js/';
    }

}
