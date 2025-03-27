<?php
/**
 * Plugin Name: Auto Product Import
 * Plugin URI: https://github.com/kadafs
 * Description: Automatically add WooCommerce products from URLs
 * Version: 1.2.1
 * Author: Kadafs
 * Author URI: https://github.com/kadafs
 * Text Domain: auto-product-import
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AUTO_PRODUCT_IMPORT_VERSION', '1.2.1');
define('AUTO_PRODUCT_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTO_PRODUCT_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function auto_product_import_check_woocommerce() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', 'auto_product_import_woocommerce_missing_notice');
        return false;
    }
    return true;
}

// Display notice if WooCommerce is not active
function auto_product_import_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('Auto Product Import requires WooCommerce to be installed and active.', 'auto-product-import'); ?></p>
    </div>
    <?php
}

// Plugin activation hook
register_activation_hook(__FILE__, 'auto_product_import_activate');
function auto_product_import_activate() {
    // Activation code here
    if (!auto_product_import_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Auto Product Import requires WooCommerce to be installed and active.', 'auto-product-import'));
    }
}

// Plugin initialization
add_action('plugins_loaded', 'auto_product_import_init');
function auto_product_import_init() {
    if (!auto_product_import_check_woocommerce()) {
        return;
    }
    
    // Load plugin text domain
    load_plugin_textdomain('auto-product-import', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Include required files
    require_once AUTO_PRODUCT_IMPORT_PLUGIN_DIR . 'includes/class-auto-product-import.php';
    
    // Initialize the main plugin class
    $auto_product_import = new Auto_Product_Import();
    $auto_product_import->init();
} 