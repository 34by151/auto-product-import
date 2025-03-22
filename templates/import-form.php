<?php
/**
 * Front-end import form template.
 *
 * @since      1.0.0
 * @package    Auto_Product_Import
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>
<div class="auto-product-import-form-wrapper">
    <h3><?php _e('Import Product from URL', 'auto-product-import'); ?></h3>
    
    <div id="auto-product-import-frontend-message" class="auto-product-import-message" style="display: none;"></div>
    
    <form id="auto-product-import-frontend-form" class="auto-product-import-form">
        <div class="auto-product-import-form-field">
            <label for="auto-product-import-url"><?php _e('Product URL', 'auto-product-import'); ?></label>
            <input type="url" id="auto-product-import-url" name="url" placeholder="<?php esc_attr_e('https://example.com/product', 'auto-product-import'); ?>" required>
            <p class="description"><?php _e('Enter the URL of the product page you want to import.', 'auto-product-import'); ?></p>
        </div>
        
        <div class="auto-product-import-form-submit">
            <button type="submit" class="auto-product-import-submit-button">
                <?php _e('Import Product', 'auto-product-import'); ?>
            </button>
            <span class="auto-product-import-spinner" style="display: none;"></span>
        </div>
    </form>
    
    <div id="auto-product-import-frontend-result" class="auto-product-import-result" style="display: none;">
        <h4><?php _e('Import Result', 'auto-product-import'); ?></h4>
        <div id="auto-product-import-frontend-result-content"></div>
    </div>
</div>

<style>
    .auto-product-import-form-wrapper {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .auto-product-import-form-wrapper h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #23282d;
    }
    
    .auto-product-import-form-field {
        margin-bottom: 15px;
    }
    
    .auto-product-import-form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .auto-product-import-form-field input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .auto-product-import-form-field .description {
        margin-top: 5px;
        font-size: 0.9em;
        color: #666;
    }
    
    .auto-product-import-form-submit {
        margin-top: 20px;
    }
    
    .auto-product-import-submit-button {
        background: #0073aa;
        border: none;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .auto-product-import-submit-button:hover {
        background: #005177;
    }
    
    .auto-product-import-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        margin-left: 10px;
        vertical-align: middle;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-left-color: #0073aa;
        border-radius: 50%;
        animation: auto-product-import-spin 1s linear infinite;
    }
    
    @keyframes auto-product-import-spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
    
    .auto-product-import-message {
        padding: 10px 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    
    .auto-product-import-message.success {
        background-color: #dff0d8;
        color: #3c763d;
        border: 1px solid #d6e9c6;
    }
    
    .auto-product-import-message.error {
        background-color: #f2dede;
        color: #a94442;
        border: 1px solid #ebccd1;
    }
    
    .auto-product-import-result {
        margin-top: 20px;
        padding: 15px;
        background: #fff;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .auto-product-import-result h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #23282d;
    }
</style> 