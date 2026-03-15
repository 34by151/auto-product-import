<?php
/**
 * AJAX Handler class
 *
 * @package Auto_Product_Import
 * @since 2.1.1
 */

if (!defined('WPINC')) {
    die;
}

class APM_Ajax_Handler {
    
    /**
     * Initialize AJAX handler
     */
    public function init() {
        add_action('wp_ajax_import_product_from_url', array($this, 'handle_import_request'));
        add_action('wp_ajax_apm_toggle_pdf_documents', array($this, 'handle_toggle_pdf_documents'));
        add_action('wp_ajax_apm_eastwesteng_preview', array($this, 'handle_eastwesteng_preview'));
        add_action('wp_ajax_apm_eastwesteng_import_selected', array($this, 'handle_eastwesteng_import_selected'));
    }
    
    /**
     * Handle import request
     */
    public function handle_import_request() {
        check_ajax_referer('auto-product-import-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to import products.', 'auto-product-import')));
            return;
        }
        
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        if (!apm_validate_url($url)) {
            wp_send_json_error(array('message' => __('Please provide a valid URL.', 'auto-product-import')));
            return;
        }
        
        // Prevent concurrent imports of the same URL (e.g. single form + batch processor running together)
        $lock_key = 'apm_import_lock_' . md5($url);
        if (get_transient($lock_key)) {
            wp_send_json_error(array('message' => __('This URL is already being imported. Please wait and try again.', 'auto-product-import')));
            return;
        }
        set_transient($lock_key, 1, 300); // Lock for up to 5 minutes

        $scraper = new APM_Product_Scraper();

        try {
            $product_data = $scraper->fetch($url);
        } catch (Exception $e) {
            delete_transient($lock_key);
            error_log('APM: Import failed with exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
            return;
        }

        if (is_wp_error($product_data)) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => $product_data->get_error_message()));
            return;
        }

        $creator = new APM_Product_Creator();

        try {
            $product_id = $creator->create($product_data);
        } catch (Exception $e) {
            delete_transient($lock_key);
            error_log('APM: Product creation failed with exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
            return;
        }

        if (is_wp_error($product_id)) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => $product_id->get_error_message()));
            return;
        }

        delete_transient($lock_key);

        // Keep the import queue in sync: if this URL exists as a pending queue
        // item, mark it as imported so the batch processor won't re-import it.
        $queue_db = new APM_Import_Queue_Database();
        $queue_db->mark_as_imported_by_url($url, $product_id);

        wp_send_json_success(array(
            'message' => __('Product imported successfully!', 'auto-product-import'),
            'product_id' => $product_id,
            'edit_link' => get_edit_post_link($product_id, 'raw'),
            'view_link' => get_permalink($product_id)
        ));
    }
    
    /**
     * Handle East West Engineering preview request.
     *
     * Scrapes the URL, extracts product rows and stores shared page data
     * (images, PDFs, specs, description) in a transient so the confirm step
     * can reuse it without a second HTTP fetch.
     *
     * Returns JSON:
     *   success + { rows, cache_key }  – one or more rows found
     *   success + { single: true, product_id, edit_link, view_link }  – exactly one row, auto-imported
     *   error   + { message }
     */
    public function handle_eastwesteng_preview() {
        check_ajax_referer('auto-product-import-nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to import products.', 'auto-product-import')));
            return;
        }

        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        if (!apm_validate_url($url)) {
            wp_send_json_error(array('message' => __('Please provide a valid URL.', 'auto-product-import')));
            return;
        }

        if (!APM_EastWestEng_Extractor::is_eastwesteng_url($url)) {
            wp_send_json_error(array('message' => __('URL is not an East West Engineering product page.', 'auto-product-import')));
            return;
        }

        $scraper = new APM_Product_Scraper();

        try {
            $product_data = $scraper->fetch($url);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
            return;
        }

        $rows = isset($product_data['eastwesteng_rows']) ? $product_data['eastwesteng_rows'] : array();

        if (empty($rows)) {
            wp_send_json_error(array('message' => __('No product rows found in the East West Engineering price table.', 'auto-product-import')));
            return;
        }

        // Cache shared page data so the confirm step can use it without re-fetching
        $cache_key = 'apm_ewe_' . md5($url . time());
        $page_data  = $product_data;
        unset($page_data['eastwesteng_rows'], $page_data['html_content']); // strip large fields
        set_transient($cache_key, $page_data, 5 * MINUTE_IN_SECONDS);

        // Single row: import immediately, no selection UI needed
        if (count($rows) === 1) {
            $row        = $rows[0];
            $import_data = $page_data;
            $import_data['title'] = $row['title'];
            $import_data['price'] = $row['price'];
            $import_data['sku']   = $row['sku'];

            // Skip if SKU already exists
            $existing_id = wc_get_product_id_by_sku($row['sku']);
            if ($existing_id) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('A product with SKU "%s" already exists (ID: %d). Import skipped.', 'auto-product-import'),
                        $row['sku'],
                        $existing_id
                    )
                ));
                return;
            }

            $creator    = new APM_Product_Creator();
            try {
                $product_id = $creator->create($import_data);
            } catch (Exception $e) {
                wp_send_json_error(array('message' => $e->getMessage()));
                return;
            }

            if (is_wp_error($product_id)) {
                wp_send_json_error(array('message' => $product_id->get_error_message()));
                return;
            }

            $queue_db = new APM_Import_Queue_Database();
            $queue_db->mark_as_imported_by_url($url, $product_id);

            wp_send_json_success(array(
                'single'     => true,
                'message'    => __('Product imported successfully!', 'auto-product-import'),
                'product_id' => $product_id,
                'edit_link'  => get_edit_post_link($product_id, 'raw'),
                'view_link'  => get_permalink($product_id),
            ));
            return;
        }

        // Multiple rows: return them to the client for selection
        wp_send_json_success(array(
            'rows'      => $rows,
            'cache_key' => $cache_key,
        ));
    }

    /**
     * Handle East West Engineering confirmed import of selected rows.
     *
     * Expects POST fields:
     *   cache_key  – transient key from preview step
     *   rows       – JSON array of { sku, title, price } objects to import
     *   url        – original product URL
     */
    public function handle_eastwesteng_import_selected() {
        check_ajax_referer('auto-product-import-nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to import products.', 'auto-product-import')));
            return;
        }

        $cache_key = isset($_POST['cache_key']) ? sanitize_text_field($_POST['cache_key']) : '';
        $url       = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        $rows_raw  = isset($_POST['rows']) ? wp_unslash($_POST['rows']) : '[]';

        $selected_rows = json_decode($rows_raw, true);
        if (!is_array($selected_rows) || empty($selected_rows)) {
            wp_send_json_error(array('message' => __('No rows selected for import.', 'auto-product-import')));
            return;
        }

        // Retrieve cached page data
        $page_data = get_transient($cache_key);
        if (!$page_data) {
            // Cache expired: re-fetch the page
            if (!apm_validate_url($url)) {
                wp_send_json_error(array('message' => __('Session expired and URL is invalid. Please try again.', 'auto-product-import')));
                return;
            }
            $scraper = new APM_Product_Scraper();
            try {
                $fetched   = $scraper->fetch($url);
                $page_data = $fetched;
                unset($page_data['eastwesteng_rows'], $page_data['html_content']);
            } catch (Exception $e) {
                wp_send_json_error(array('message' => __('Session expired. Please reload the page and try again.', 'auto-product-import')));
                return;
            }
        }

        $creator  = new APM_Product_Creator();
        $results  = array();
        $last_id  = null;

        foreach ($selected_rows as $row) {
            $sku   = isset($row['sku'])   ? sanitize_text_field($row['sku'])   : '';
            $title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
            $price = isset($row['price']) ? sanitize_text_field($row['price']) : '';

            if (empty($sku)) {
                continue;
            }

            // Skip duplicate SKUs
            $existing_id = wc_get_product_id_by_sku($sku);
            if ($existing_id) {
                $results[] = array(
                    'sku'     => $sku,
                    'status'  => 'skipped',
                    'message' => sprintf(__('SKU "%s" already exists (ID: %d)', 'auto-product-import'), $sku, $existing_id),
                );
                continue;
            }

            // Build per-row import data from shared page data
            $import_data          = $page_data;
            $import_data['title'] = $title;
            $import_data['price'] = $price;
            $import_data['sku']   = $sku;

            try {
                $product_id = $creator->create($import_data);
            } catch (Exception $e) {
                $results[] = array(
                    'sku'     => $sku,
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                );
                continue;
            }

            if (is_wp_error($product_id)) {
                $results[] = array(
                    'sku'     => $sku,
                    'status'  => 'error',
                    'message' => $product_id->get_error_message(),
                );
                continue;
            }

            $last_id   = $product_id;
            $results[] = array(
                'sku'        => $sku,
                'title'      => $title,
                'status'     => 'imported',
                'product_id' => $product_id,
                'edit_link'  => get_edit_post_link($product_id, 'raw'),
                'view_link'  => get_permalink($product_id),
            );
        }

        // Mark URL as done in the import queue (use last successfully created product)
        if ($last_id && apm_validate_url($url)) {
            $queue_db = new APM_Import_Queue_Database();
            $queue_db->mark_as_imported_by_url($url, $last_id);
        }

        delete_transient($cache_key);

        wp_send_json_success(array(
            'results' => $results,
        ));
    }

    /**
     * Handle toggle PDF documents setting
     */
    public function handle_toggle_pdf_documents() {
        check_ajax_referer('auto-product-import-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'auto-product-import')));
            return;
        }
        
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : 'on';
        
        // Validate state
        if (!in_array($state, array('on', 'off'))) {
            $state = 'on';
        }
        
        // Save setting
        update_option('apm_add_pdfs_to_documents', $state);
        
        wp_send_json_success(array(
            'message' => __('Setting saved', 'auto-product-import'),
            'state' => $state
        ));
    }
}
