<?php

namespace AsanaPlugins\WooCommerce\ProductBundlesPro\Admin;

defined( 'ABSPATH' ) || exit;

use AsanaPlugins\WooCommerce\ProductBundlesPro;
use AsanaPlugins\WooCommerce\ProductBundlesPro\Registry\Container;

class Admin {

	protected $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function init() {
		$this->update_checker();
		add_action( 'in_plugin_update_message-easy-product-bundles-for-woocommerce-pro/easy-product-bundles-pro.php', array( $this, 'in_plugin_update_message' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
	}

	/**
	 * Checking for plugin updates.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	private function update_checker() {
		if ( ! is_callable( '\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
			'https://wpupdate.asanaplugins.com/?action=get_metadata&slug=easy-product-bundles-for-woocommerce-pro',
			ASNP_WEPB_PRO_PLUGIN_FILE,
			'easy-product-bundles-for-woocommerce-pro'
		);
		$update_checker->addQueryArgFilter( [ &$this, 'filter_update_checks' ] );
	}

	/**
	 * Filtering update checks.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $query_args
	 *
	 * @return array
	 */
	public function filter_update_checks( array $query_args ) {
		$license = ProductBundlesPro\get_plugin()->settings->get_setting( 'license_key', '' );
		if ( ! empty( $license ) ) {
			$query_args['license_key'] = $license;
		}
		$query_args['host'] = preg_replace( '#^\w+://#', '', trim( get_option( 'siteurl' ) ) );

		return $query_args;
	}

	/**
	 * Showing message in plugin update message area.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function in_plugin_update_message() {
		$license = ProductBundlesPro\get_plugin()->settings->get_setting( 'license_key', '' );
		if ( empty( $license ) ) {
			$url = esc_url( admin_url( 'admin.php?page=asnp-product-bundles' ) );
			$redirect = sprintf( '<a href="%s" target="_blank">%s</a>', $url, __( 'settings', 'asnp-easy-product-bundles-pro' ) );

			echo sprintf( ' ' . __( 'To receive automatic updates, license activation is required. Please visit %s to activate your plugin.', 'asnp-easy-product-bundles-pro' ), $redirect );
		}
	}

	/**
	 * Plugin action links
	 * This function adds additional links below the plugin in admin plugins page.
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $links    The array having default links for the plugin.
	 * @param  string $file     The name of the plugin file.
	 *
	 * @return array  $links    Plugin default links and specific links.
	 */
	public function plugin_action_links( $links, $file ) {
		if ( false === strpos( $file, 'easy-product-bundles-pro.php' ) ) {
			return $links;
		}

		$extra = [ '<a href="' . admin_url( 'admin.php?page=asnp-product-bundles' ) . '">' . esc_html__( 'Settings', 'asnp-easy-product-bundles-pro' ) . '</a>' ];

		return array_merge( $links, $extra );
	}
}
