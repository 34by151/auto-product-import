<?php
/**
 * The main plugin class.
 * 
 * @since      1.0.0
 * @package    Auto_Product_Import
 */

if (!defined('WPINC')) {
    die;
}

class Auto_Product_Import {
    /**
     * The single instance of the class.
     *
     * @var Auto_Product_Import
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Main Auto_Product_Import Instance.
     *
     * Ensures only one instance of Auto_Product_Import is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return Auto_Product_Import - Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize the plugin.
     *
     * @since 1.0.0
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers
        add_action('wp_ajax_import_product_from_url', array($this, 'ajax_import_product_from_url'));
        
        // Add shortcode
        add_shortcode('auto_product_import_form', array($this, 'render_import_form_shortcode'));
    }

    /**
     * Add admin menu.
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Auto Product Import', 'auto-product-import'),
            __('Auto Product Import', 'auto-product-import'),
            'manage_woocommerce',
            'auto-product-import',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting('auto_product_import_settings', 'auto_product_import_default_category');
        register_setting('auto_product_import_settings', 'auto_product_import_default_status');
    }

    /**
     * Render admin page.
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        // Enqueue admin scripts and styles
        wp_enqueue_script('auto-product-import-admin', AUTO_PRODUCT_IMPORT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AUTO_PRODUCT_IMPORT_VERSION, true);
        wp_localize_script('auto-product-import-admin', 'autoProductImportAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('auto-product-import-nonce')
        ));
        
        // Get settings
        $default_category = get_option('auto_product_import_default_category', '');
        $default_status = get_option('auto_product_import_default_status', 'draft');
        
        // Get product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        // Include admin template
        include AUTO_PRODUCT_IMPORT_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * AJAX handler for importing a product from URL.
     *
     * @since 1.0.0
     */
    public function ajax_import_product_from_url() {
        // Check nonce
        check_ajax_referer('auto-product-import-nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to import products.', 'auto-product-import')));
            return;
        }
        
        // Get URL
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => __('Please provide a valid URL.', 'auto-product-import')));
            return;
        }
        
        // Get product data from URL
        $product_data = $this->fetch_product_data_from_url($url);
        if (is_wp_error($product_data)) {
            wp_send_json_error(array('message' => $product_data->get_error_message()));
            return;
        }
        
        // Create product
        $product_id = $this->create_woocommerce_product($product_data);
        if (is_wp_error($product_id)) {
            wp_send_json_error(array('message' => $product_id->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => __('Product imported successfully!', 'auto-product-import'),
            'product_id' => $product_id,
            'edit_link' => get_edit_post_link($product_id, 'raw'),
            'view_link' => get_permalink($product_id)
        ));
    }

    /**
     * Fetch product data from a URL.
     *
     * @since 1.0.0
     * @param string $url The URL to fetch product data from.
     * @return array|WP_Error An array of product data or a WP_Error object on failure.
     */
    public function fetch_product_data_from_url($url) {
        // Fetch the URL content
        $response = wp_remote_get($url, array(
            'timeout' => 60, // Increase timeout for larger pages
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('request_failed', __('Failed to fetch the URL. Response code: ', 'auto-product-import') . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_response', __('The URL returned an empty response.', 'auto-product-import'));
        }
        
        // Create a DOMDocument instance to parse the HTML
        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        libxml_use_internal_errors(true);
        @$dom->loadHTML($body);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        // Extract product data
        $product_data = array(
            'title' => '',
            'description' => '',
            'price' => '',
            'images' => array(),
        );
        
        // Try to get title (first look for product title, then fallback to page title)
        $title_nodes = $xpath->query('//h1[@class="product-title"] | //h1[@class="product_title"] | //h1[contains(@class, "product-title")] | //h1[contains(@class, "product_title")]');
        if ($title_nodes && $title_nodes->length > 0) {
            $product_data['title'] = trim($title_nodes->item(0)->textContent);
        } else {
            $title_tags = $dom->getElementsByTagName('title');
            if ($title_tags->length > 0) {
                $product_data['title'] = trim($title_tags->item(0)->textContent);
            }
        }
        
        // Try to get price
        $price_nodes = $xpath->query('//span[contains(@class, "price")] | //div[contains(@class, "price")] | //p[contains(@class, "price")]');
        if ($price_nodes && $price_nodes->length > 0) {
            $price_text = trim($price_nodes->item(0)->textContent);
            // Extract numbers from the price text
            preg_match('/[\d.,]+/', $price_text, $matches);
            if (!empty($matches)) {
                $product_data['price'] = $matches[0];
            }
        }
        
        // Try to get description - different approach to avoid DOM manipulation issues
        $description_html = $this->extractDescriptionHTML($dom, $xpath);
        
        // Call the review structures remover (this now just returns without doing anything)
        $this->removeExactReviewStructures($xpath);
        
        // Clean the HTML using regex pattern cleaning
        if (!empty($description_html)) {
            $cleaned_html = $this->cleanupHTML($description_html);
            $product_data['description'] = $cleaned_html;
        }
        
        // Try to get images
        $image_nodes = $xpath->query('//img[contains(@class, "product") or contains(@id, "product")]/@src');
        if ($image_nodes && $image_nodes->length > 0) {
            foreach ($image_nodes as $img) {
                $image_url = $img->textContent;
                // Make sure the image URL is absolute
                if (strpos($image_url, 'http') !== 0) {
                    $parsed_url = parse_url($url);
                    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    if (strpos($image_url, '/') === 0) {
                        $image_url = $base_url . $image_url;
                    } else {
                        $image_url = $base_url . '/' . $image_url;
                    }
                }
                $product_data['images'][] = $image_url;
            }
        }
        
        // If no specific product images found, look for any large images
        if (empty($product_data['images'])) {
            $all_images = $xpath->query('//img/@src');
            foreach ($all_images as $img) {
                $image_url = $img->textContent;
                // Skip small images and icons
                if (strpos($image_url, 'icon') !== false || strpos($image_url, 'logo') !== false) {
                    continue;
                }
                
                // Make sure the image URL is absolute
                if (strpos($image_url, 'http') !== 0) {
                    $parsed_url = parse_url($url);
                    $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    if (strpos($image_url, '/') === 0) {
                        $image_url = $base_url . $image_url;
                    } else {
                        $image_url = $base_url . '/' . $image_url;
                    }
                }
                
                $product_data['images'][] = $image_url;
                
                // Limit to 5 images
                if (count($product_data['images']) >= 5) {
                    break;
                }
            }
        }
        
        // Store the source URL
        $product_data['source_url'] = $url;
        
        return $product_data;
    }

    /**
     * Extract description HTML without modifying the DOM
     * 
     * @param DOMDocument $dom The DOM document
     * @param DOMXPath $xpath The XPath object
     * @return string The description HTML
     */
    private function extractDescriptionHTML($dom, $xpath) {
        // Array of selectors to try in order
        $selectors = [
            // Primary selectors for product descriptions
            '//div[contains(@class, "description") and not(contains(@class, "tab-title")) and not(contains(@class, "tab-heading"))]',
            '//div[contains(@class, "product-description") and not(contains(@class, "tab-title")) and not(contains(@class, "tab-heading"))]',
            '//div[@id="description" and not(contains(@class, "tab-title")) and not(contains(@class, "tab-heading"))]',
            
            // WooCommerce tab content 
            '//div[@id="tab-description"]',
            '//div[contains(@class, "woocommerce-Tabs-panel--description")]',
            
            // Other common description containers
            '//div[@class="tab-content"]//div[contains(@id, "description")]',
            '//div[@class="tab-content"]/div[contains(@class, "active")]',
            '//div[@id="product-description"]',
            '//section[contains(@class, "product-description")]',
            '//div[contains(@class, "product-details-description")]',
            '//div[contains(@class, "woocommerce-product-details__short-description")]',
            '//div[contains(@class, "product_description")]',
            '//div[contains(@class, "pdp-description")]'
        ];
        
        // Try each selector until we find content
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                return $dom->saveHTML($node);
            }
        }
        
        // Try broader selectors if specific ones didn't match
        $broader_selectors = [
            '//div[contains(@class, "tab-content")]',
            '//div[contains(@class, "product-details")]',
            '//div[contains(@class, "product-info")]',
            '//div[contains(@class, "product-specs")]',
            '//div[contains(@class, "product-specification")]',
            '//article[contains(@class, "product")]',
            '//div[@id="detailBullets"]',
            '//div[@id="productDescription"]'
        ];
        
        foreach ($broader_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                return $dom->saveHTML($node);
            }
        }
        
        // Last attempt - try to get content from the main product div
        $product_selectors = [
            '//div[contains(@class, "product")]',
            '//main[contains(@class, "product")]',
            '//div[@id="product"]',
            '//div[@itemtype="http://schema.org/Product"]'
        ];
        
        foreach ($product_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                return $dom->saveHTML($node);
            }
        }
        
        // Fallback to meta description if nothing else works
        $meta_desc = $xpath->query('//meta[@name="description"]/@content');
        if ($meta_desc && $meta_desc->length > 0) {
            return '<p>' . trim($meta_desc->item(0)->textContent) . '</p>';
        }
        
        return '';
    }

    /**
     * Clean HTML content using regex patterns to avoid DOM manipulation
     * 
     * @param string $html The HTML to clean
     * @return string The cleaned HTML
     */
    private function cleanupHTML($html) {
        // 1. First remove any script and iframe tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html);
        
        // 2. Remove tab navigation
        $html = preg_replace('/<ul[^>]*class=["\'][^"\']*(?:tabs|nav-tabs|wc-tabs)[^"\']*["\'][^>]*>.*?<\/ul>/is', '', $html);
        $html = preg_replace('/<nav[^>]*class=["\'][^"\']*(?:woocommerce-tabs|tabs)[^"\']*["\'][^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*(?:tab-nav|tab-header|wc-tabs-wrapper|product-tabs)[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        
        // 3. Remove tab headings and UI elements
        $html = preg_replace('/<div[^>]*role=["\']tablist["\'][^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<ul[^>]*role=["\']tablist["\'][^>]*>.*?<\/ul>/is', '', $html);
        $html = preg_replace('/<h[1-6][^>]*class=["\'][^"\']*tab[^"\']*["\'][^>]*>.*?<\/h[1-6]>/is', '', $html);
        $html = preg_replace('/<h[1-6][^>]*id=["\'][^"\']*tab[^"\']*["\'][^>]*>.*?<\/h[1-6]>/is', '', $html);
        
        // 4. Remove the exact blue review box and customer question section
        $html = preg_replace('/<div[^>]*style=["\'][^"\']*background(?:-color)?:\s*#[dD]9[eE][dD][fF]7[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<div[^>]*style=["\'][^"\']*background(?:-color)?:\s*#[eE][aA][fF][0-9a-fA-F][^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        $html = preg_replace('/<div[^>]*style=["\'][^"\']*background(?:-color)?:\s*rgb\(\s*217\s*,\s*237\s*,\s*247[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        
        // 5. Remove divs containing specific text
        $phrases_to_remove = [
            'Be first to review this item',
            'Ask our customer community',
            'Other customers may have experience',
            'Post Question'
        ];
        
        foreach ($phrases_to_remove as $phrase) {
            $html = preg_replace('/<div[^>]*>(?:(?!<div).)*' . preg_quote($phrase, '/') . '.*?<\/div>/is', '', $html);
            $html = preg_replace('/<p[^>]*>(?:(?!<p).)*' . preg_quote($phrase, '/') . '.*?<\/p>/is', '', $html);
        }
        
        // 6. Remove notification elements with common classes
        $classesToRemove = [
            'alert', 'info', 'notice', 'notification', 'comment-area', 'review-area', 
            'feedback', 'rating-widget', 'customer-feedback', 'review-banner',
            'review-section', 'qa-section', 'community-qa', 'product-qa'
        ];
        
        foreach ($classesToRemove as $className) {
            $html = preg_replace('/<div[^>]*class=["\'][^"\']*' . preg_quote($className, '/') . '[^"\']*["\'][^>]*>.*?<\/div>/is', '', $html);
        }
        
        // 7. Remove common tab headings
        $tab_headings = [
            'DESCRIPTION',
            'REVIEWS',
            'REVIEWS \(\d+\)',
            'Q & A'
        ];
        
        foreach ($tab_headings as $heading) {
            $html = preg_replace('/<div[^>]*>\s*' . $heading . '\s*<\/div>/is', '', $html);
            $html = preg_replace('/<h[1-6][^>]*>\s*' . $heading . '\s*<\/h[1-6]>/is', '', $html);
        }
        
        // 8. Remove buttons and action elements
        $html = preg_replace('/<button[^>]*>.*?(?:Post|Review|Question).*?<\/button>/is', '', $html);
        $html = preg_replace('/<a[^>]*class=["\'][^"\']*(?:btn|button)[^"\']*["\'][^>]*>.*?<\/a>/is', '', $html);
        
        // 9. Remove horizontal rules
        $html = preg_replace('/<hr[^>]*>/is', '', $html);
        
        // 10. Remove character counters
        $html = preg_replace('/\(\d+\/\d+\)/', '', $html);
        
        // 11. Final cleanup
        // Remove empty paragraphs and divs
        $html = preg_replace('/<p[^>]*>\s*<\/p>/is', '', $html);
        $html = preg_replace('/<div[^>]*>\s*<\/div>/is', '', $html);
        
        // Remove multiple consecutive breaks
        $html = preg_replace('/(\s*<br\s*\/?>\s*){3,}/is', '<br>', $html);
        
        return $html;
    }

    /**
     * Stub method to avoid errors from old code that might still be calling this
     * This method was replaced by regex-based HTML cleaning
     *
     * @param DOMXPath $xpath The XPath object
     * @return void
     */
    private function removeExactReviewStructures($xpath) {
        // This is just a stub - all cleaning is now done in cleanupHTML
        return;
    }

    /**
     * Create a WooCommerce product from the extracted data.
     *
     * @since 1.0.0
     * @param array $product_data The product data to create a product from.
     * @return int|WP_Error The product ID on success, or a WP_Error object on failure.
     */
    public function create_woocommerce_product($product_data) {
        // Get default settings
        $default_category = get_option('auto_product_import_default_category', '');
        $default_status = get_option('auto_product_import_default_status', 'draft');
        
        // Create new product
        $product = new WC_Product_Simple();
        
        // Set product name
        $product->set_name($product_data['title']);
        
        // Set product description - ensure HTML is preserved
        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }
        
        // Set product status
        $product->set_status($default_status);
        
        // Set price if available
        if (!empty($product_data['price'])) {
            $product->set_regular_price($product_data['price']);
        }
        
        // Set SKU - generate a random one
        $product->set_sku('API-' . wp_rand(1000, 9999));
        
        // Set product category
        if (!empty($default_category)) {
            $product->set_category_ids(array($default_category));
        }
        
        // Add source URL as meta
        $product->update_meta_data('_source_url', $product_data['source_url']);
        
        // Save the product to get an ID
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('product_creation_failed', __('Failed to create the product.', 'auto-product-import'));
        }
        
        // Upload and attach images
        if (!empty($product_data['images'])) {
            $product_images = array();
            
            foreach ($product_data['images'] as $image_url) {
                $attachment_id = $this->upload_remote_image($image_url, $product_id);
                if ($attachment_id) {
                    $product_images[] = $attachment_id;
                }
            }
            
            // Set product images
            if (!empty($product_images)) {
                // Set first image as the product image
                $product->set_image_id($product_images[0]);
                
                // Set remaining images as gallery images
                if (count($product_images) > 1) {
                    $gallery_image_ids = array_slice($product_images, 1);
                    $product->set_gallery_image_ids($gallery_image_ids);
                }
                
                // Save the product again with images
                $product->save();
            }
        }
        
        return $product_id;
    }

    /**
     * Upload a remote image and attach it to a product.
     *
     * @since 1.0.0
     * @param string $image_url The URL of the image to upload.
     * @param int $product_id The product ID to attach the image to.
     * @return int|false The attachment ID on success, or false on failure.
     */
    private function upload_remote_image($image_url, $product_id) {
        // Require WordPress media handling
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download the image
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = array();
        $file_array['name'] = basename($image_url);
        $file_array['tmp_name'] = $tmp;
        
        // Check file type
        $filetype = wp_check_filetype($file_array['name'], null);
        if (empty($filetype['ext']) || empty($filetype['type'])) {
            // If no extension, try to determine from the URL
            $url_parts = parse_url($image_url);
            $url_path = pathinfo($url_parts['path']);
            if (!empty($url_path['extension'])) {
                $file_array['name'] = 'product-image-' . time() . '.' . $url_path['extension'];
            } else {
                // Default to jpg
                $file_array['name'] = 'product-image-' . time() . '.jpg';
            }
        }
        
        // Upload the image
        $attachment_id = media_handle_sideload($file_array, $product_id);
        
        // Clean up the temporary file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return $attachment_id;
    }

    /**
     * Render import form shortcode.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     * @return string The HTML output.
     */
    public function render_import_form_shortcode($atts) {
        // Check if user has permission
        if (!current_user_can('manage_woocommerce')) {
            return '<p>' . __('You do not have permission to import products.', 'auto-product-import') . '</p>';
        }
        
        // Enqueue frontend scripts and styles
        wp_enqueue_script('auto-product-import-frontend', AUTO_PRODUCT_IMPORT_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AUTO_PRODUCT_IMPORT_VERSION, true);
        wp_localize_script('auto-product-import-frontend', 'autoProductImportFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('auto-product-import-nonce')
        ));
        
        // Start output buffering
        ob_start();
        
        // Include frontend template
        include AUTO_PRODUCT_IMPORT_PLUGIN_DIR . 'templates/import-form.php';
        
        // Return the buffer contents
        return ob_get_clean();
    }
} 