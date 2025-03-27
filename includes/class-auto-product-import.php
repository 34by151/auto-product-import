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
        register_setting('auto_product_import_settings', 'auto_product_import_max_images', array(
            'default' => 20,
            'sanitize_callback' => array($this, 'sanitize_max_images')
        ));
    }
    
    /**
     * Sanitize the max images setting.
     *
     * @since 1.0.0
     * @param mixed $value The value to sanitize.
     * @return int The sanitized value.
     */
    public function sanitize_max_images($value) {
        $value = absint($value);
        if ($value < 1) {
            $value = 1;
        } elseif ($value > 50) {
            $value = 50;
        }
        return $value;
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
        $max_images = get_option('auto_product_import_max_images', 20);
        
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
        // Enable debug for impactguns.com specifically
        if (strpos($url, 'impactguns.com') !== false) {
            add_filter('auto_product_import_debug_mode', '__return_true');
            error_log('Auto Product Import - Debug mode enabled for Impact Guns URL: ' . $url);
        }
        
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
            'source_url' => $url,  // Include the source URL in the product data
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
        
        // Enhanced image extraction
        $debug_mode = apply_filters('auto_product_import_debug_mode', false);
        if ($debug_mode) {
            error_log("Starting enhanced image extraction for URL: " . $url);
        }
        
        $product_data['images'] = $this->extractProductImages($xpath, $url);
        
        if ($debug_mode) {
            error_log("Completed image extraction. Found " . count($product_data['images']) . " images");
        }
        
        return $product_data;
    }

    /**
     * Extract product images from the HTML
     * 
     * @param DOMXPath $xpath The XPath object
     * @param string $url The URL of the product page
     * @return array Array of product images
     */
    private function extractProductImages($xpath, $url) {
        $images = [];
        $image_urls_set = []; // Use as a set to track unique URLs
        $debug_mode = apply_filters('auto_product_import_debug_mode', false);
        
        if ($debug_mode) {
            error_log("Starting product image extraction");
        }
        
        // Define blacklisted terms
        $blacklisted_terms = [
            'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading', 'banner',
            'button', 'thumbnail-default', 'social', 'facebook', 'twitter', 'instagram',
            'background', 'pattern', 'avatar', 'profile', 'cart', 'checkout', 'payment',
            'shipping', 'footer', 'header', 'navigation', 'menu', 'search', 'sprite', 'guarantee',
            'badge', 'star', 'rating', 'share', 'wishlist', 'compare', 'like', 'heart',
            'zoom', 'magnify', 'close', 'play', 'video-placeholder'
        ];
        
        // 1. First try BigCommerce specific extraction
        if ($debug_mode) {
            error_log("Trying BigCommerce specific extraction");
        }
        
        $this->extractBigCommerceImages($xpath, $url, $images, $image_urls_set, $debug_mode);
        
        // If we still don't have enough images, try other approaches
        if (count($images) < 3) {
            if ($debug_mode) {
                error_log("Not enough images from BigCommerce extraction, trying fallback methods");
            }
            
            $this->extractFallbackImages($xpath, $url, $images, $image_urls_set, $blacklisted_terms, $debug_mode);
        }
        
        // Filter and deduplicate images
        $images = array_unique($images);
        $images = array_values($images); // Reset array keys
        
        if ($debug_mode) {
            error_log("Total images extracted: " . count($images));
        }
        
        return $images;
    }

    /**
     * Extract images from BigCommerce specific selectors
     *
     * @param DOMXPath $xpath The XPath object
     * @param string $url The base URL
     * @param array $images Reference to images array
     * @param array $image_urls_set Reference to image URLs set
     * @param bool $debug_mode Whether to output debug information
     */
    private function extractBigCommerceImages($xpath, $url, &$images, &$image_urls_set, $debug_mode) {
        // Common BigCommerce selectors for product images
        $selectors = [
            '//ul[contains(@class, "productView-thumbnails")]/li//img',
            '//figure[contains(@class, "productView-image")]//img',
            '//div[contains(@class, "productView-img-container")]//img',
            '//a[contains(@class, "cloud-zoom-gallery")]',
            '//div[contains(@class, "productView")]//img[contains(@class, "main-image")]'
        ];
        
        $blacklisted_terms = [
            'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading'
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            if ($nodes && $nodes->length > 0) {
                if ($debug_mode) {
                    error_log("Found " . $nodes->length . " nodes using selector: $selector");
                }
                
                foreach ($nodes as $node) {
                    // Skip nodes in related products sections
                    if ($this->isInRelatedProductsSection($node)) {
                        if ($debug_mode) {
                            error_log("Skipping image in related products section");
                        }
                        continue;
                    }
                
                    if ($selector === '//a[contains(@class, "cloud-zoom-gallery")]') {
                        $this->extractBigCommerceImageFromLink($node, $url, $images, $image_urls_set, $debug_mode);
                    } else {
                        $this->extractAndFilterImageFromNode($node, $images, $image_urls_set, $blacklisted_terms, $debug_mode, $url);
                    }
                }
            }
        }
    }

    /**
     * Extract images using fallback methods for non-BigCommerce sites
     *
     * @param DOMXPath $xpath The XPath object
     * @param string $url The base URL
     * @param array $images Reference to images array
     * @param array $image_urls_set Reference to image URLs set
     * @param array $blacklisted_terms Terms to blacklist
     * @param bool $debug_mode Whether to output debug information
     */
    private function extractFallbackImages($xpath, $url, &$images, &$image_urls_set, $blacklisted_terms, $debug_mode) {
        // 1. Try common product image containers
        $productContainerSelectors = [
            '//div[contains(@class, "product-images")]//img',
            '//div[contains(@class, "product-gallery")]//img',
            '//div[contains(@id, "product-images")]//img',
            '//div[contains(@class, "product-detail")]//img',
            '//div[contains(@class, "product-media")]//img',
            '//div[contains(@class, "product-slider")]//img',
            '//div[contains(@class, "woocommerce-product-gallery")]//img'
        ];
        
        foreach ($productContainerSelectors as $selector) {
            $nodes = $xpath->query($selector);
            
            if ($nodes && $nodes->length > 0) {
                if ($debug_mode) {
                    error_log("Found " . $nodes->length . " nodes using selector: $selector");
                }
                
                foreach ($nodes as $node) {
                    // Skip if in related products section
                    if ($this->isInRelatedProductsSection($node)) {
                        continue;
                    }
                    
                    $this->extractAndFilterImageFromNode($node, $images, $image_urls_set, $blacklisted_terms, $debug_mode, $url);
                }
            }
        }
        
        // 2. If we still don't have enough images, try main content areas
        if (count($images) < 3) {
            $mainContentSelectors = [
                '//div[contains(@class, "main-content")]//img',
                '//main//img',
                '//article//img',
                '//div[contains(@class, "content")]//img'
            ];
            
            foreach ($mainContentSelectors as $selector) {
                $nodes = $xpath->query($selector);
                
                if ($nodes && $nodes->length > 0) {
                    if ($debug_mode) {
                        error_log("Found " . $nodes->length . " nodes using selector: $selector");
                    }
                    
                    foreach ($nodes as $node) {
                        // Skip if in related products section
                        if ($this->isInRelatedProductsSection($node)) {
                            continue;
                        }
                        
                        $this->extractAndFilterImageFromNode($node, $images, $image_urls_set, $blacklisted_terms, $debug_mode, $url);
                    }
                }
            }
        }
        
        // 3. Finally, get all images as a last resort
        if (count($images) < 2) {
            $allImages = $xpath->query('//img');
            
            if ($allImages && $allImages->length > 0) {
                if ($debug_mode) {
                    error_log("Found " . $allImages->length . " total images, filtering for product images");
                }
                
                foreach ($allImages as $node) {
                    // Skip if in related products section
                    if ($this->isInRelatedProductsSection($node)) {
                        continue;
                    }
                    
                    $this->extractAndFilterImageFromNode($node, $images, $image_urls_set, $blacklisted_terms, $debug_mode, $url);
                    
                    // Stop if we have enough images
                    if (count($images) >= 5) {
                        break;
                    }
                }
            }
        }
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
     * Extract additional product information from the description HTML.
     *
     * @since 1.0.0
     * @param string $description_html The product description HTML.
     * @param bool $debug Whether to enable debug mode.
     * @return array An array of additional product information.
     */
    private function extract_additional_product_info($description_html, $debug = false) {
        // Fields to extract (matching the ones shown in the screenshot)
        $fields_to_extract = array(
            'Caliber',
            'Power Source',
            'Velocity',
            'Magazine Capacity',
            'Action',
            'Frame Material',
            'Barrel',
            'Accessory Rail',
            'Finish',
            'Intended Use',
            'Length',
            'Safety',
            'Sights',
            'Trigger',
            'Weight'
        );
        
        // Field variations/aliases (some sites use different terms for the same field)
        $field_variations = array(
            'Magazine Capacity' => array('Capacity', 'Mag Capacity', 'Mag. Capacity', 'Magazine Size'),
            'Frame Material' => array('Frame', 'Material', 'Construction'),
            'Accessory Rail' => array('Rail', 'Rails', 'Accessory', 'Rail Type'),
            'Intended Use' => array('Use', 'Purpose', 'Application'),
            'Power Source' => array('Power', 'Power Type', 'Source'),
            'Barrel' => array('Barrel Length', 'Barrel Size', 'Barrel Details', 'Barrel Specs'),
            'Action' => array('Action Type', 'Operating System'),
            'Finish' => array('Finish Type', 'Surface Finish', 'Color'),
            'Sights' => array('Sight', 'Sight System', 'Sight Type'),
            'Trigger' => array('Trigger Type', 'Trigger System', 'Trigger Pull'),
            'Weight' => array('Gun Weight', 'Product Weight', 'Total Weight'),
            'Safety' => array('Safety Type', 'Safety System', 'Safety Features'),
            'Length' => array('Overall Length', 'Total Length', 'Gun Length')
        );
        
        $additional_info = array();
        
        if ($debug) {
            error_log('Starting extraction of additional product information from HTML...');
            error_log('HTML content length: ' . strlen($description_html) . ' characters');
        }
        
        // Step 1: Look for schema.org formatted data with DOMDocument (for structured data)
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<div>' . $description_html . '</div>');
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        foreach ($fields_to_extract as $field) {
            // Skip if we already found this field
            if (isset($additional_info[$field])) {
                continue;
            }
            
            // Convert field name to lowercase for case-insensitive matching
            $field_lower = strtolower($field);
            
            // Try to find the field in schema.org formatted list items
            $nodes = $xpath->query('//li[.//span[translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "' . $field_lower . '"]]');
            
            if ($nodes && $nodes->length > 0) {
                $value_nodes = $xpath->query('.//span[@itemprop="value"]', $nodes->item(0));
                if ($value_nodes && $value_nodes->length > 0) {
                    $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                    if ($debug) {
                        error_log("Found '$field' via schema.org format: " . $additional_info[$field]);
                    }
                    continue;
                }
            }
            
            // Try variations of the field name
            if (isset($field_variations[$field])) {
                foreach ($field_variations[$field] as $variation) {
                    $variation_lower = strtolower($variation);
                    $nodes = $xpath->query('//li[.//span[translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "' . $variation_lower . '"]]');
                    
                    if ($nodes && $nodes->length > 0) {
                        $value_nodes = $xpath->query('.//span[@itemprop="value"]', $nodes->item(0));
                        if ($value_nodes && $value_nodes->length > 0) {
                            $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                            if ($debug) {
                                error_log("Found '$field' via schema.org format (variation '$variation'): " . $additional_info[$field]);
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // Step 2: Direct regex pattern matching on the HTML (this works for both structured and unstructured data)
        foreach ($fields_to_extract as $field) {
            // Skip if we already found this field
            if (isset($additional_info[$field])) {
                continue;
            }
            
            // Look for <li>Field: Value</li> pattern
            $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:\s*([^<]+)<\/li>/i';
            if (preg_match($pattern, $description_html, $matches)) {
                $additional_info[$field] = trim($matches[1]);
                if ($debug) {
                    error_log("Found '$field' via direct HTML regex pattern: " . $additional_info[$field]);
                }
                continue;
            }
            
            // Also try with different spacing
            $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:([^<]+)<\/li>/i';
            if (preg_match($pattern, $description_html, $matches)) {
                $additional_info[$field] = trim($matches[1]);
                if ($debug) {
                    error_log("Found '$field' via alternate HTML regex pattern: " . $additional_info[$field]);
                }
                continue;
            }
            
            // Try direct text extraction with any separator
            $separators = array(':', '-', '–', '=', '|');
            foreach ($separators as $separator) {
                $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<]+)<\/li>/i';
                if (preg_match($pattern, $description_html, $matches)) {
                    $additional_info[$field] = trim($matches[1]);
                    if ($debug) {
                        error_log("Found '$field' via direct HTML regex with separator '$separator': " . $additional_info[$field]);
                    }
                    break;
                }
            }
            
            // Try variations with direct regex pattern
            if (!isset($additional_info[$field]) && isset($field_variations[$field])) {
                foreach ($field_variations[$field] as $variation) {
                    // Look for <li>Variation: Value</li> pattern
                    $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*:\s*([^<]+)<\/li>/i';
                    if (preg_match($pattern, $description_html, $matches)) {
                        $additional_info[$field] = trim($matches[1]);
                        if ($debug) {
                            error_log("Found '$field' via direct HTML regex pattern with variation '$variation': " . $additional_info[$field]);
                        }
                        break;
                    }
                    
                    // Try other separators for variations
                    foreach ($separators as $separator) {
                        $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<]+)<\/li>/i';
                        if (preg_match($pattern, $description_html, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                error_log("Found '$field' via direct HTML regex with variation '$variation' and separator '$separator': " . $additional_info[$field]);
                            }
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Step 3: Look for table-based data
        foreach ($fields_to_extract as $field) {
            // Skip if we already found this field
            if (isset($additional_info[$field])) {
                continue;
            }
            
            // Convert field name to lowercase for case-insensitive matching
            $field_lower = strtolower($field);
            
            // Look for table rows with field name in first column
            $nodes = $xpath->query('//tr[./td[1][contains(translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $field_lower . '")]]');
            if ($nodes && $nodes->length > 0) {
                $value_nodes = $xpath->query('./td[2]', $nodes->item(0));
                if ($value_nodes && $value_nodes->length > 0) {
                    $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                    if ($debug) {
                        error_log("Found '$field' via table row: " . $additional_info[$field]);
                    }
                    continue;
                }
            }
            
            // Try variations for table rows
            if (isset($field_variations[$field])) {
                foreach ($field_variations[$field] as $variation) {
                    $variation_lower = strtolower($variation);
                    $nodes = $xpath->query('//tr[./td[1][contains(translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $variation_lower . '")]]');
                    if ($nodes && $nodes->length > 0) {
                        $value_nodes = $xpath->query('./td[2]', $nodes->item(0));
                        if ($value_nodes && $value_nodes->length > 0) {
                            $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                            if ($debug) {
                                error_log("Found '$field' via table row with variation '$variation': " . $additional_info[$field]);
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // Step 4: Last resort - look through all list items for any text that matches our field patterns
        if (count($additional_info) < count($fields_to_extract)) {
            $li_nodes = $xpath->query('//li');
            if ($li_nodes && $li_nodes->length > 0) {
                foreach ($li_nodes as $li_node) {
                    $li_text = trim($li_node->textContent);
                    
                    foreach ($fields_to_extract as $field) {
                        // Skip if we already found this field
                        if (isset($additional_info[$field])) {
                            continue;
                        }
                        
                        // Direct match for field at start of text
                        if (stripos($li_text, $field . ':') === 0) {
                            $parts = explode(':', $li_text, 2);
                            if (count($parts) === 2) {
                                $additional_info[$field] = trim($parts[1]);
                                if ($debug) {
                                    error_log("Found '$field' via direct list item text extraction: " . $additional_info[$field]);
                                }
                            }
                        }
                        
                        // Check variations too
                        if (!isset($additional_info[$field]) && isset($field_variations[$field])) {
                            foreach ($field_variations[$field] as $variation) {
                                if (stripos($li_text, $variation . ':') === 0) {
                                    $parts = explode(':', $li_text, 2);
                                    if (count($parts) === 2) {
                                        $additional_info[$field] = trim($parts[1]);
                                        if ($debug) {
                                            error_log("Found '$field' via direct list item text extraction with variation '$variation': " . $additional_info[$field]);
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($debug) {
            error_log('Completed extraction of additional product information.');
            error_log('Found ' . count($additional_info) . ' fields: ' . implode(', ', array_keys($additional_info)));
            error_log('Missing ' . (count($fields_to_extract) - count($additional_info)) . ' fields: ' . 
                implode(', ', array_diff($fields_to_extract, array_keys($additional_info))));
        }
        
        return $additional_info;
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
            
            // Extract additional product information from the description
            $debug_mode = apply_filters('auto_product_import_debug_mode', false);
            $additional_info = $this->extract_additional_product_info($product_data['description'], $debug_mode);
            
            // Add the additional information as product attributes
            if (!empty($additional_info)) {
                $attributes = array();
                
                foreach ($additional_info as $name => $value) {
                    if (!empty($value)) {
                        $attribute = new WC_Product_Attribute();
                        $attribute->set_name($name);
                        $attribute->set_options(array($value));
                        $attribute->set_visible(true);
                        $attributes[] = $attribute;
                    }
                }
                
                if (!empty($attributes)) {
                    $product->set_attributes($attributes);
                }
            }
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
            // Add the total count of found images as meta data
            $total_images_found = count($product_data['images']);
            update_post_meta($product_id, '_total_images_found', $total_images_found);
            
            $product_images = array();
            $processed_count = 0;
            
            // Get the maximum number of images to process from settings
            $max_images_to_process = get_option('auto_product_import_max_images', 20);
            $max_images_to_process = apply_filters('auto_product_import_max_images', $max_images_to_process);
            $images_to_process = array_slice($product_data['images'], 0, $max_images_to_process);
            
            // Track progress for larger image sets
            $total_images = count($images_to_process);
            if ($total_images > 5) {
                // Optional: add a progress note as post meta
                update_post_meta($product_id, '_auto_import_processing_images', $total_images);
            }
            
            foreach ($images_to_process as $image_url) {
                // Skip URLs that don't look like images
                $file_extension = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
                $valid_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                
                // If no extension or not a valid image extension, try to infer from URL
                if (empty($file_extension) || !in_array($file_extension, $valid_extensions)) {
                    // Check if URL has image-like patterns
                    if (strpos($image_url, '/images/') === false && 
                        strpos($image_url, '/img/') === false && 
                        strpos($image_url, 'image') === false) {
                        continue; // Skip URLs that don't seem to be images
                    }
                }
                
                $attachment_id = $this->upload_remote_image($image_url, $product_id);
                if ($attachment_id) {
                    $product_images[] = $attachment_id;
                    $processed_count++;
                }
                
                // Add a small delay between image uploads to prevent overwhelming the server
                if ($processed_count > 5) {
                    usleep(100000); // 100ms pause
                }
            }
            
            // Remove the processing flag
            delete_post_meta($product_id, '_auto_import_processing_images');
            
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
                
                // Store the count of imported images
                update_post_meta($product_id, '_imported_image_count', count($product_images));
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
        
        // Allow longer timeouts for downloading images
        $timeout = apply_filters('auto_product_import_image_timeout', 60);
        
        // Download the image with increased timeout
        $tmp = download_url($image_url, $timeout);
        
        if (is_wp_error($tmp)) {
            // Log the error but continue with other images
            error_log('Auto Product Import - Failed to download image: ' . $image_url . ' - Error: ' . $tmp->get_error_message());
            return false;
        }
        
        $file_array = array();
        $file_array['name'] = basename($image_url);
        $file_array['tmp_name'] = $tmp;
        
        // Check file type
        $filetype = wp_check_filetype($file_array['name'], null);
        if (empty($filetype['ext']) || empty($filetype['type'])) {
            // If no extension, try to determine from the URL or file contents
            $url_parts = parse_url($image_url);
            $url_path = pathinfo($url_parts['path']);
            
            // Try to get extension from URL
            if (!empty($url_path['extension'])) {
                $ext = strtolower($url_path['extension']);
                // Verify it's a valid image extension
                if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                    $file_array['name'] = 'product-image-' . time() . '.' . $ext;
            } else {
                    $file_array['name'] = 'product-image-' . time() . '.jpg'; // Default
                }
            } else {
                // Try to determine file type from content
                $file_info = getimagesize($tmp);
                if ($file_info && isset($file_info['mime'])) {
                    $mime = $file_info['mime'];
                    switch ($mime) {
                        case 'image/jpeg':
                            $ext = 'jpg';
                            break;
                        case 'image/png':
                            $ext = 'png';
                            break;
                        case 'image/gif':
                            $ext = 'gif';
                            break;
                        case 'image/webp':
                            $ext = 'webp';
                            break;
                        default:
                            $ext = 'jpg'; // Default
                    }
                    $file_array['name'] = 'product-image-' . time() . '.' . $ext;
                } else {
                    // Default to jpg if can't determine
                $file_array['name'] = 'product-image-' . time() . '.jpg';
                }
            }
        }
        
        // Verify this is an image file before attempting to process
        $image_info = @getimagesize($tmp);
        if (!$image_info) {
            // Not a valid image
            @unlink($tmp);
            return false;
        }
        
        // Check image dimensions to filter out tiny images
        list($width, $height) = $image_info;
        if ($width < 200 || $height < 200) {
            // Image too small to be useful
            @unlink($tmp);
            return false;
        }
        
        // Upload the image
        $attachment_id = media_handle_sideload($file_array, $product_id);
        
        // Clean up the temporary file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        
        if (is_wp_error($attachment_id)) {
            error_log('Auto Product Import - Failed to process image: ' . $image_url . ' - Error: ' . $attachment_id->get_error_message());
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

    /**
     * Public wrapper function for extract_additional_product_info to use in tests
     * 
     * @since 1.0.0
     * @param string $description_html The product description HTML.
     * @param bool $debug Whether to enable debug mode.
     * @return array An array of additional product information.
     */
    public function get_additional_product_info($description_html, $debug = false) {
        return $this->extract_additional_product_info($description_html, $debug);
    }

    /**
     * Convert a relative URL to an absolute URL
     * 
     * @param string $relative_url The potentially relative URL
     * @param string $base_url The base URL to resolve against
     * @return string The absolute URL
     */
    private function makeUrlAbsolute($relative_url, $base_url) {
        // Already absolute
        if (strpos($relative_url, 'http') === 0) {
            return $relative_url;
        }
        
        $parsed_url = parse_url($base_url);
        $base = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        
        // URL starts with '//'
        if (strpos($relative_url, '//') === 0) {
            return $parsed_url['scheme'] . ':' . $relative_url;
        }
        
        // URL starts with '/'
        if (strpos($relative_url, '/') === 0) {
            return $base . $relative_url;
        }
        
        // Relative URL
        if (isset($parsed_url['path'])) {
            $path = dirname($parsed_url['path']);
            return $base . $path . '/' . $relative_url;
        }
        
        return $base . '/' . $relative_url;
    }
    
    /**
     * Safely check if a DOM element has an attribute
     * 
     * @param DOMElement|DOMNode $element The DOM element to check
     * @param string $attribute The attribute to check for
     * @return bool Whether the element has the attribute
     */
    private function domHasAttribute($element, $attribute) {
        return method_exists($element, 'hasAttribute') && $element->hasAttribute($attribute);
    }
    
    /**
     * Safely get an attribute value from a DOM element
     * 
     * @param DOMElement|DOMNode $element The DOM element
     * @param string $attribute The attribute to get
     * @return string The attribute value or empty string if not found
     */
    private function domGetAttribute($element, $attribute) {
        if (method_exists($element, 'getAttribute')) {
            return $element->getAttribute($attribute);
        }
        return '';
    }

    /**
     * Extract and filter image from a node, checking for related products sections
     *
     * @param DOMNode $node The node to extract an image from
     * @param array $images The array of already found images
     * @param array $image_urls_set A set of already found image URLs to avoid duplicates
     * @param array $blacklisted_terms Terms to blacklist from URLs
     * @param bool $debug_mode Whether to output debug information
     * @param string $base_url The base URL for resolving relative URLs
     */
    private function extractAndFilterImageFromNode($node, &$images, &$image_urls_set, $blacklisted_terms, $debug_mode, $base_url = '') {
        // Skip nodes in related products sections
        if ($this->isInRelatedProductsSection($node)) {
            if ($debug_mode) {
                error_log("Skipping image in related products section");
            }
            return;
        }
        
        $img_url = '';
        
        // Try to get image URL from various attributes
        $image_attributes = ['data-image-gallery-new-image-url', 'data-zoom-image', 'data-large', 'data-src', 'src'];
        foreach ($image_attributes as $attr) {
            if ($this->domHasAttribute($node, $attr)) {
                $img_url = $this->domGetAttribute($node, $attr);
                break;
            }
        }
        
        // Skip empty URLs
        if (empty($img_url)) {
            return;
        }
        
        // Convert to high-res if possible
        $img_url = $this->convertToHighRes($img_url);
        
        // Make URL absolute if it's relative
        if (!empty($base_url) && strpos($img_url, 'http') !== 0) {
            $img_url = $this->makeUrlAbsolute($img_url, $base_url);
        }
        
        // Add to images array if valid and not already present
        if ($this->isValidProductImage($img_url, $blacklisted_terms) && !isset($image_urls_set[$img_url])) {
            $images[] = $img_url;
            $image_urls_set[$img_url] = true;
            
            if ($debug_mode) {
                error_log("Added image: $img_url");
            }
        }
    }
    
    /**
     * Helper to convert BigCommerce thumbnails to high-resolution images
     * 
     * @param string $url The image URL to convert
     * @return string The high-resolution image URL
     */
    private function convertToHighRes($url) {
        // Convert BigCommerce thumbnail URLs to high-res (1280x1280)
        if (strpos($url, '/stencil/') !== false) {
            return preg_replace('/\/stencil\/[^\/]+\//', '/stencil/1280x1280/', $url);
        }
        
        return $url;
    }

    /**
     * Checks if a DOM node is inside a "related products" section
     * 
     * @param DOMNode $node The node to check
     * @return bool True if the node is in a related products section
     */
    private function isInRelatedProductsSection($node) {
        $debug_mode = apply_filters('auto_product_import_debug_mode', false);
        
        // Walk up the DOM tree to check parent elements
        $current = $node;
        $max_levels = 10; // Limit how far up we check to avoid performance issues
        $level = 0;
        
        while ($current && $level < $max_levels) {
            // Check for common related products container identifiers in class, id, or data attributes
            $class = $this->domGetAttribute($current, 'class');
            $id = $this->domGetAttribute($current, 'id');
            
            $related_product_patterns = [
                '/related[-_]?products?/i',
                '/similar[-_]?products?/i',
                '/recommended[-_]?products?/i',
                '/you[-_]?may[-_]?also[-_]?like/i',
                '/cross[-_]?sell/i',
                '/up[-_]?sell/i',
                '/product[-_]?recommendations?/i',
                '/product[-_]?suggestions?/i'
            ];
            
            // Check class and id for related product patterns
            foreach ($related_product_patterns as $pattern) {
                if (preg_match($pattern, $class) || preg_match($pattern, $id)) {
                    if ($debug_mode) {
                        error_log("Found image in related products section: class=$class, id=$id");
                    }
                    return true;
                }
            }
            
            // Also check for heading elements with related products text
            if ($current->nodeName === 'h1' || $current->nodeName === 'h2' || 
                $current->nodeName === 'h3' || $current->nodeName === 'h4') {
                $text = $current->textContent;
                if (preg_match('/related\s+products/i', $text) || 
                    preg_match('/similar\s+products/i', $text) || 
                    preg_match('/you\s+may\s+also\s+like/i', $text) ||
                    preg_match('/recommended\s+for\s+you/i', $text)) {
                    return true;
                }
            }
            
            $current = $current->parentNode;
            $level++;
        }
        
        return false;
    }

    /**
     * Check if an image URL is a valid product image
     * 
     * @param string $url Image URL to check
     * @param array $blacklisted_terms Terms that indicate non-product images
     * @return bool Whether the URL appears to be a valid product image
     */
    private function isValidProductImage($url, $blacklisted_terms) {
        // Skip empty URLs
        if (empty($url)) {
            return false;
        }
        
        // Skip URLs with blacklisted terms
        foreach ($blacklisted_terms as $term) {
            if (stripos($url, $term) !== false) {
                return false;
            }
        }
        
        // Skip related product image patterns
        $related_patterns = ['/related/', '/similar/', '/recommended/'];
        foreach ($related_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        
        // Check for common image extensions
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // If it has a valid extension, it's likely an image
        if (in_array($ext, $valid_extensions)) {
            return true;
        }
        
        // If no extension, check for common patterns that indicate an image
        if (strpos($url, '/images/') !== false || 
            strpos($url, '/img/') !== false || 
            strpos($url, 'image') !== false ||
            strpos($url, 'product') !== false) {
            return true;
        }
        
        // Default to false for URLs that don't match any criteria
        return false;
    }
    
    /**
     * Extract image URL from a BigCommerce cloud-zoom-gallery link
     * 
     * @param DOMNode $node The link node
     * @param string $base_url The base URL for resolving relative URLs
     * @param array $images The array to add images to
     * @param array $image_urls_set Set of already processed URLs
     * @param bool $debug_mode Whether to output debug info
     */
    private function extractBigCommerceImageFromLink($node, $base_url, &$images, &$image_urls_set, $debug_mode) {
        // Look for the href attribute first
        if ($this->domHasAttribute($node, 'href')) {
            $img_url = $this->domGetAttribute($node, 'href');
            
            // Convert to absolute URL if needed
            if (!empty($img_url) && strpos($img_url, 'http') !== 0) {
                $img_url = $this->makeUrlAbsolute($img_url, $base_url);
            }
            
            // Convert to high-res if possible
            $img_url = $this->convertToHighRes($img_url);
            
            // Add to images if not already added
            if (!empty($img_url) && !isset($image_urls_set[$img_url])) {
                $images[] = $img_url;
                $image_urls_set[$img_url] = true;
                
                if ($debug_mode) {
                    error_log("Added image from link href: $img_url");
                }
            }
        }
        
        // Check for data-zoom-image attribute
        if ($this->domHasAttribute($node, 'data-zoom-image')) {
            $img_url = $this->domGetAttribute($node, 'data-zoom-image');
            
            // Convert to absolute URL if needed
            if (!empty($img_url) && strpos($img_url, 'http') !== 0) {
                $img_url = $this->makeUrlAbsolute($img_url, $base_url);
            }
            
            // Add to images if not already added
            if (!empty($img_url) && !isset($image_urls_set[$img_url])) {
                $images[] = $img_url;
                $image_urls_set[$img_url] = true;
                
                if ($debug_mode) {
                    error_log("Added image from link data-zoom-image: $img_url");
                }
            }
        }
    }
} 