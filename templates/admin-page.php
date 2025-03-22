<?php
/**
 * Admin page template.
 *
 * @since      1.0.0
 * @package    Auto_Product_Import
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap">
    <h1><?php _e('Auto Product Import', 'auto-product-import'); ?></h1>
    
    <div class="auto-product-import-admin">
        <div class="auto-product-import-container">
            <div class="auto-product-import-form-container">
                <h2><?php _e('Import Product from URL', 'auto-product-import'); ?></h2>
                <p><?php _e('Enter a URL below to automatically import product data.', 'auto-product-import'); ?></p>
                
                <div id="auto-product-import-message" class="notice" style="display: none;"></div>
                
                <form id="auto-product-import-form" class="auto-product-import-form">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="product-url"><?php _e('Product URL', 'auto-product-import'); ?></label></th>
                                <td>
                                    <input type="url" name="product-url" id="product-url" class="regular-text" required>
                                    <p class="description"><?php _e('Enter the URL of the product page you want to import.', 'auto-product-import'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" id="auto-product-import-submit" class="button button-primary">
                            <?php _e('Import Product', 'auto-product-import'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-top: 0;"></span>
                    </p>
                </form>
                
                <div id="auto-product-import-result" style="display: none;">
                    <h3><?php _e('Import Result', 'auto-product-import'); ?></h3>
                    <div id="auto-product-import-result-content"></div>
                </div>
            </div>
            
            <div class="auto-product-import-settings-container">
                <h2><?php _e('Settings', 'auto-product-import'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('auto_product_import_settings');
                    do_settings_sections('auto_product_import_settings');
                    ?>
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="auto_product_import_default_category"><?php _e('Default Category', 'auto-product-import'); ?></label></th>
                                <td>
                                    <select name="auto_product_import_default_category" id="auto_product_import_default_category">
                                        <option value=""><?php _e('None', 'auto-product-import'); ?></option>
                                        <?php
                                        if (!empty($categories)) {
                                            foreach ($categories as $category) {
                                                echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($default_category, $category->term_id, false) . '>' . esc_html($category->name) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php _e('Select the default category for imported products.', 'auto-product-import'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="auto_product_import_default_status"><?php _e('Default Status', 'auto-product-import'); ?></label></th>
                                <td>
                                    <select name="auto_product_import_default_status" id="auto_product_import_default_status">
                                        <option value="draft" <?php selected($default_status, 'draft'); ?>><?php _e('Draft', 'auto-product-import'); ?></option>
                                        <option value="publish" <?php selected($default_status, 'publish'); ?>><?php _e('Published', 'auto-product-import'); ?></option>
                                        <option value="pending" <?php selected($default_status, 'pending'); ?>><?php _e('Pending', 'auto-product-import'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Select the default status for imported products.', 'auto-product-import'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'auto-product-import')); ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .auto-product-import-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 20px;
    }
    
    .auto-product-import-form-container,
    .auto-product-import-settings-container {
        flex: 1;
        min-width: 300px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 20px;
    }
    
    .auto-product-import-form-container h2,
    .auto-product-import-settings-container h2 {
        margin-top: 0;
    }
    
    #auto-product-import-result {
        margin-top: 20px;
        padding: 15px;
        background: #f8f8f8;
        border-radius: 4px;
    }
    
    .spinner.is-active {
        visibility: visible;
        display: inline-block;
    }
</style> 