<?php
/**
 * Plugin Name: Auto Product Import
 * Description: Automatically import products from external sources
 * Version: 2.2.6
 * Author: Your Name
 * Text Domain: auto-product-import
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('APM_VERSION', '2.2.6');
define('APM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include helper functions (always needed)
require_once APM_PLUGIN_DIR . 'includes/helpers/functions-dom.php';
require_once APM_PLUGIN_DIR . 'includes/helpers/functions-url.php';
require_once APM_PLUGIN_DIR . 'includes/helpers/functions-validation.php';

// Include admin-only classes — skipped on frontend to avoid unnecessary file parsing
if ( is_admin() ) {

    // Admin classes
    require_once APM_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
    require_once APM_PLUGIN_DIR . 'includes/admin/class-settings-handler.php';
    require_once APM_PLUGIN_DIR . 'includes/admin/class-template-data.php';

    // AJAX handlers
    require_once APM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
    require_once APM_PLUGIN_DIR . 'includes/ajax/class-import-queue-ajax-handler.php';

    // Import classes - Core
    require_once APM_PLUGIN_DIR . 'includes/import/class-html-parser.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-product-scraper.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-product-scraper-extractors.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-product-scraper-sku.php';

    // Import classes - Extractors
    require_once APM_PLUGIN_DIR . 'includes/import/class-eastwesteng-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-bigcommerce-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-shopify-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-description-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-description-extractor-additional-info.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-specifications-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-specifications-extractor-shopify.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-specifications-extractor-magento.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-image-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-pdf-extractor.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-pdf-extractor-html-parser.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-pdf-extractor-js-parser.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-pdf-extractor-validator.php';

    // Import classes - Uploaders
    require_once APM_PLUGIN_DIR . 'includes/import/class-image-uploader.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-pdf-uploader.php';

    // Import classes - Product Creation
    require_once APM_PLUGIN_DIR . 'includes/import/class-product-creator.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-product-creator-sync-fields.php';
    require_once APM_PLUGIN_DIR . 'includes/import/class-specifications-tab-creator.php';
    // Documents tab creator removed in v2.2.2 - file deleted

    // Import queue classes
    require_once APM_PLUGIN_DIR . 'includes/import-queue/class-import-queue-database.php';
    require_once APM_PLUGIN_DIR . 'includes/import-queue/class-import-queue-batch-processor.php';
    require_once APM_PLUGIN_DIR . 'includes/import-queue/class-import-queue-table-renderer.php';
    require_once APM_PLUGIN_DIR . 'includes/import-queue/class-import-queue-manager.php';

}

/**
 * Initialize the plugin
 */
function apm_init() {
    if (is_admin()) {
        // Initialize settings handler (admin_init only)
        $settings_handler = new APM_Settings_Handler();
        $settings_handler->init();

        $admin_menu = new APM_Admin_Menu();
        $admin_menu->init();

        $ajax_handler = new APM_Ajax_Handler();
        $ajax_handler->init();

        // Initialize import queue manager
        $queue_manager = new APM_Import_Queue_Manager();
        $queue_manager->init();
    }

    // NOTE: Documents Tab Creator initialization removed in v2.2.2
    // PDFs still uploaded to media library but no Documents tab created
}
add_action('plugins_loaded', 'apm_init');

/**
 * Activation hook
 */
function apm_activate() {
    // Create import queue database table
    $queue_db = new APM_Import_Queue_Database();
    $queue_db->create_table();
    
    // Set default options
    add_option('apm_add_pdfs_to_documents', 'on');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'apm_activate');

/**
 * Deactivation hook
 */
function apm_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'apm_deactivate');

/**
 * Enqueue plugin scripts and styles
 */
function apm_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'apm-') === false && $hook !== 'toplevel_page_apm-auto-product-import') {
        return;
    }
    
    // Enqueue admin JS
    wp_enqueue_script(
        'apm-admin-script',
        APM_PLUGIN_URL . 'assets/admin.js',
        array('jquery'),
        APM_VERSION,
        true
    );
    
    // Enqueue import queue JS
    wp_enqueue_script(
        'apm-import-queue',
        APM_PLUGIN_URL . 'assets/import-queue.js',
        array('jquery'),
        APM_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('apm-admin-script', 'apmAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('apm_ajax_nonce')
    ));
    
    wp_localize_script('apm-import-queue', 'apmQueueAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('apm_queue_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'apm_enqueue_scripts');

/**
 * Log function for debugging
 */
function apm_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log('APM: ' . print_r($message, true));
        } else {
            error_log('APM: ' . $message);
        }
    }
}
