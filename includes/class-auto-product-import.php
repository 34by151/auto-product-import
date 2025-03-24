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
        $product_data['images'] = $this->extractProductImages($xpath, $url);
        
        return $product_data;
    }

    /**
     * Extract product images from the page.
     * 
     * @param DOMXPath $xpath The XPath object
     * @param string $url The source URL for resolving relative paths
     * @return array Array of image URLs
     */
    private function extractProductImages($xpath, $url) {
        $images = array();
        $image_urls_set = array(); // To track unique image URLs
        $debug_mode = apply_filters('auto_product_import_debug_mode', false);
        
        // Blacklist of terms that indicate non-product images
        $blacklisted_terms = array(
            'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading', 'banner',
            'button', 'thumbnail-default', 'social', 'facebook', 'twitter', 'instagram',
            'background', 'pattern', 'avatar', 'profile', 'cart', 'checkout', 'payment',
            'shipping', 'footer', 'header', 'navigation', 'menu', 'search', 'sprite', 'guarantee',
            'badge', 'star', 'rating', 'share', 'wishlist', 'compare', 'like', 'heart',
            'zoom', 'magnify', 'close', 'play', 'video-placeholder'
        );
        
        if ($debug_mode) {
            error_log('Auto Product Import - Starting image extraction for URL: ' . $url);
        }
        
        // PART 1: First try to get images from product-specific containers (most reliable)
        // --------------------------------------------------
        $product_containers = array(
            // Main product image containers
            '//div[contains(@class, "product-gallery") or contains(@class, "product-images") or contains(@class, "product-photo") or contains(@id, "product-gallery") or contains(@id, "product-images")]',
            '//div[contains(@class, "productView-images") or contains(@class, "product-media")]',
            '//div[@id="product-image" or @id="product-images" or @id="main-product-image"]',
            '//figure[contains(@class, "product-image") or contains(@class, "product-gallery")]',
            '//div[contains(@class, "woocommerce-product-gallery") or contains(@id, "woocommerce-product-gallery")]',
            '//div[contains(@class, "product_images") or contains(@id, "product_images")]'
        );
        
        // Check each container for images
        foreach ($product_containers as $container_query) {
            $containers = $xpath->query($container_query);
            if ($containers && $containers->length > 0) {
                foreach ($containers as $container) {
                    // Check for data-zoom attributes first as they usually hold high-res URLs
                    $data_imgs = $xpath->query('.//img[@data-zoom-image or @data-large-image or @data-full-image or @data-image]', $container);
                    if ($data_imgs && $data_imgs->length > 0) {
                        foreach ($data_imgs as $img) {
                            // Try different data attributes for high-res images
                            foreach (array('data-zoom-image', 'data-large-image', 'data-full-image', 'data-image') as $attr) {
                                if ($this->domHasAttribute($img, $attr)) {
                                    $img_url = $this->domGetAttribute($img, $attr);
                                    if ($this->isValidProductImage($img_url, $blacklisted_terms)) {
                                        $absolute_url = $this->makeUrlAbsolute($img_url, $url);
                                        if (!isset($image_urls_set[$absolute_url])) {
                                            $image_urls_set[$absolute_url] = true;
                                            $images[] = $absolute_url;
                                            if ($debug_mode) {
                                                error_log('Auto Product Import - Found high-res product image: ' . $absolute_url);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Check for regular large images
                    $product_imgs = $xpath->query('.//img', $container);
                    if ($product_imgs && $product_imgs->length > 0) {
                        foreach ($product_imgs as $img) {
                            $src = $this->domGetAttribute($img, 'src');
                            if ($this->isValidProductImage($src, $blacklisted_terms)) {
                                $absolute_url = $this->makeUrlAbsolute($src, $url);
                                if (!isset($image_urls_set[$absolute_url])) {
                                    $image_urls_set[$absolute_url] = true;
                                    $images[] = $absolute_url;
                                    if ($debug_mode) {
                                        error_log('Auto Product Import - Found product image: ' . $absolute_url);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Look for <a> links with href pointing to images
                    $product_links = $xpath->query('.//a[contains(@class, "product") or @data-image]', $container);
                    if ($product_links && $product_links->length > 0) {
                        foreach ($product_links as $link) {
                            $href = $this->domGetAttribute($link, 'href');
                            if ($this->isValidProductImage($href, $blacklisted_terms)) {
                                $absolute_url = $this->makeUrlAbsolute($href, $url);
                                if (!isset($image_urls_set[$absolute_url])) {
                                    $image_urls_set[$absolute_url] = true;
                                    $images[] = $absolute_url;
                                    if ($debug_mode) {
                                        error_log('Auto Product Import - Found product image link: ' . $absolute_url);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // PART 2: BigCommerce specific extraction for sites like impactguns.com
        // --------------------------------------------------
        if (strpos($url, 'impactguns.com') !== false || strpos($url, 'bigcommerce.com') !== false) {
            $this->extractBigCommerceImages($xpath, $url, $images, $image_urls_set, $debug_mode);
        }
        
        // PART 3: Look for thumbnails that might be in their own container
        // --------------------------------------------------
        if (count($images) < 10) { // Only try this if we don't have many images yet
            $thumbnail_containers = array(
                '//ul[contains(@class, "thumbnails") or contains(@class, "product-thumbnails")]',
                '//ol[contains(@class, "thumbnails") or contains(@class, "product-thumbnails")]',
                '//div[contains(@class, "thumbnails") or contains(@class, "thumbnail-container") or contains(@id, "thumbnails")]',
                '//div[contains(@class, "productView-thumbnails")]'
            );
            
            foreach ($thumbnail_containers as $container_query) {
                $containers = $xpath->query($container_query);
                if ($containers && $containers->length > 0) {
                    foreach ($containers as $container) {
                        $thumbnail_links = $xpath->query('.//a', $container);
                        if ($thumbnail_links && $thumbnail_links->length > 0) {
                            foreach ($thumbnail_links as $link) {
                                // Check for href attribute
                                $href = $this->domGetAttribute($link, 'href');
                                if ($this->isValidProductImage($href, $blacklisted_terms)) {
                                    $absolute_url = $this->makeUrlAbsolute($href, $url);
                                    if (!isset($image_urls_set[$absolute_url])) {
                                        $image_urls_set[$absolute_url] = true;
                                        $images[] = $absolute_url;
                                        if ($debug_mode) {
                                            error_log('Auto Product Import - Found product thumbnail link: ' . $absolute_url);
                                        }
                                    }
                                }
                                
                                // Also check for data attributes
                                foreach (array('data-image', 'data-large-image', 'data-zoom-image', 'data-original') as $attr) {
                                    if ($this->domHasAttribute($link, $attr)) {
                                        $img_url = $this->domGetAttribute($link, $attr);
                                        if ($this->isValidProductImage($img_url, $blacklisted_terms)) {
                                            $absolute_url = $this->makeUrlAbsolute($img_url, $url);
                                            if (!isset($image_urls_set[$absolute_url])) {
                                                $image_urls_set[$absolute_url] = true;
                                                $images[] = $absolute_url;
                                                if ($debug_mode) {
                                                    error_log('Auto Product Import - Found product thumbnail image: ' . $absolute_url);
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Also check for img tags inside the link
                                $thumbnail_imgs = $xpath->query('.//img', $link);
                                if ($thumbnail_imgs && $thumbnail_imgs->length > 0) {
                                    foreach ($thumbnail_imgs as $img) {
                                        $src = $this->domGetAttribute($img, 'src');
                                        if ($this->isValidProductImage($src, $blacklisted_terms)) {
                                            $absolute_url = $this->makeUrlAbsolute($src, $url);
                                            if (!isset($image_urls_set[$absolute_url])) {
                                                $image_urls_set[$absolute_url] = true;
                                                $images[] = $absolute_url;
                                                if ($debug_mode) {
                                                    error_log('Auto Product Import - Found product thumbnail: ' . $absolute_url);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // PART 4: Allow developers to add or modify images
        // --------------------------------------------------
        $images = apply_filters('auto_product_import_found_images', $images, $xpath, $url);
        
        // PART 5: Fallback approaches if we still don't have enough images
        // --------------------------------------------------
        if (count($images) < 3) {
            $this->extractFallbackImages($xpath, $url, $images, $image_urls_set, $blacklisted_terms, $debug_mode);
        }
        
        // Post-processing: Prioritize images by likely quality and relevance
        $processed_images = $this->prioritizeProductImages($images, $url);
        
        // Apply max image count limit
        $max_images = apply_filters('auto_product_import_max_images', 20);
        if (count($processed_images) > $max_images) {
            $processed_images = array_slice($processed_images, 0, $max_images);
        }
        
        if ($debug_mode) {
            error_log('Auto Product Import - Extracted ' . count($processed_images) . ' product images');
        }
        
        return $processed_images;
    }
    
    /**
     * Extract fallback images when primary methods don't find enough
     *
     * @param DOMXPath $xpath The XPath object
     * @param string $url The source URL
     * @param array &$images The images array to add to
     * @param array &$image_urls_set The set of already found image URLs
     * @param array $blacklisted_terms Terms that indicate non-product images
     * @param bool $debug_mode Whether debug mode is enabled
     */
    private function extractFallbackImages($xpath, $url, &$images, &$image_urls_set, $blacklisted_terms, $debug_mode) {
        // Fallback 1: Look for large images by size
        $large_imgs = $xpath->query('//img[@width > 300 or @height > 300]');
        if ($large_imgs && $large_imgs->length > 0) {
            foreach ($large_imgs as $img) {
                $src = $this->domGetAttribute($img, 'src');
                if ($this->isValidProductImage($src, $blacklisted_terms)) {
                    $absolute_url = $this->makeUrlAbsolute($src, $url);
                    if (!isset($image_urls_set[$absolute_url])) {
                        $image_urls_set[$absolute_url] = true;
                        $images[] = $absolute_url;
                        if ($debug_mode) {
                            error_log('Auto Product Import - Found large image: ' . $absolute_url);
                        }
                    }
                }
            }
        }
        
        // Fallback 2: Look for images with product-related classes
        $product_class_imgs = $xpath->query('//img[contains(@class, "product") or contains(@id, "product")]');
        if ($product_class_imgs && $product_class_imgs->length > 0) {
            foreach ($product_class_imgs as $img) {
                $src = $this->domGetAttribute($img, 'src');
                if ($this->isValidProductImage($src, $blacklisted_terms)) {
                    $absolute_url = $this->makeUrlAbsolute($src, $url);
                    if (!isset($image_urls_set[$absolute_url])) {
                        $image_urls_set[$absolute_url] = true;
                        $images[] = $absolute_url;
                        if ($debug_mode) {
                            error_log('Auto Product Import - Found product class image: ' . $absolute_url);
                        }
                    }
                }
            }
        }
        
        // Fallback 3: Look for images within main content area
        $content_containers = $xpath->query('//div[contains(@class, "content") or contains(@class, "main") or contains(@id, "content") or contains(@id, "main")]');
        if ($content_containers && $content_containers->length > 0) {
            foreach ($content_containers as $container) {
                $content_imgs = $xpath->query('.//img', $container);
                if ($content_imgs && $content_imgs->length > 0) {
                    foreach ($content_imgs as $img) {
                        $src = $this->domGetAttribute($img, 'src');
                        if ($this->isValidProductImage($src, $blacklisted_terms)) {
                            $absolute_url = $this->makeUrlAbsolute($src, $url);
                            if (!isset($image_urls_set[$absolute_url])) {
                                $image_urls_set[$absolute_url] = true;
                                $images[] = $absolute_url;
                                if ($debug_mode) {
                                    error_log('Auto Product Import - Found content area image: ' . $absolute_url);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Check if an image URL appears to be a valid product image
     * 
     * @param string $url The image URL to check
     * @param array $blacklisted_terms Array of terms that indicate non-product images
     * @return bool Whether the image appears to be a valid product image
     */
    private function isValidProductImage($url, $blacklisted_terms) {
        if (empty($url)) {
            return false;
        }
        
        // Skip data URIs
        if (strpos($url, 'data:image') === 0) {
            return false;
        }
        
        // Skip blacklisted terms
        foreach ($blacklisted_terms as $term) {
            if (stripos($url, $term) !== false) {
                return false;
            }
        }
        
        // Check for common image extensions
        $has_image_extension = preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $url);
        
        // Check for image-like paths
        $has_image_path = strpos($url, '/images/') !== false || 
                          strpos($url, '/img/') !== false || 
                          strpos($url, '/photos/') !== false || 
                          strpos($url, '/product/') !== false ||
                          strpos($url, '/products/') !== false;
        
        // Check for image dimensions in URL (common for product images)
        $has_dimensions = preg_match('/\/\d+x\d+\//', $url) || 
                          preg_match('/[_-]\d+x\d+/', $url) ||
                          preg_match('/size=\d+x\d+/', $url) ||
                          preg_match('/width=\d+/', $url);
        
        // If image has a valid extension or appears to be an image path, consider it valid
        return $has_image_extension || $has_image_path || $has_dimensions;
    }
    
    /**
     * Prioritize and filter product images based on likely relevance and quality
     * 
     * @param array $images Array of image URLs
     * @param string $url The source URL
     * @return array Prioritized array of image URLs
     */
    private function prioritizeProductImages($images, $url) {
        if (empty($images) || count($images) <= 1) {
            return $images;
        }
        
        $prioritized = array();
        $regular = array();
        $lower_priority = array();
        
        // Get the domain from the URL to identify images from the same site
        $parsed_url = parse_url($url);
        $domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        
        foreach ($images as $image_url) {
            // Skip empty URLs
            if (empty($image_url)) {
                continue;
            }
            
            $parsed_image = parse_url($image_url);
            $image_domain = isset($parsed_image['host']) ? $parsed_image['host'] : '';
            
            // 1. Prioritize known high-res indicators
            if (strpos($image_url, 'large') !== false || 
                strpos($image_url, 'zoom') !== false ||
                strpos($image_url, 'full') !== false ||
                preg_match('/\/1280x1280\//', $image_url) ||
                preg_match('/\/1000x1000\//', $image_url) ||
                preg_match('/\/800x800\//', $image_url)) {
                $prioritized[] = $image_url;
            }
            // 2. For BigCommerce sites, prioritize enhanced URLs
            else if (strpos($url, 'bigcommerce.com') !== false || strpos($url, 'impactguns.com') !== false) {
                // Enhance to high resolution
                $high_res_url = preg_replace('/\/\d+x\d+\//', '/1280x1280/', $image_url);
                if ($high_res_url !== $image_url) {
                    $prioritized[] = $high_res_url;
                } else {
                    $high_res_url = preg_replace('/\/\d+w\//', '/1280w/', $image_url);
                    if ($high_res_url !== $image_url) {
                        $prioritized[] = $high_res_url;
                    } else {
                        $regular[] = $image_url;
                    }
                }
            }
            // 3. Images from same domain get regular priority
            else if ($image_domain === $domain) {
                $regular[] = $image_url;
            }
            // 4. CDN domains usually have good images too
            else if (strpos($image_domain, 'cdn') !== false) {
                $regular[] = $image_url;
            }
            // 5. Other images get lower priority
            else {
                $lower_priority[] = $image_url;
            }
        }
        
        // Combine the arrays in priority order
        $result = array_merge($prioritized, $regular, $lower_priority);
        
        // Remove duplicates (in case the same URL got into different priority levels)
        return array_values(array_unique($result));
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
     * Extract images from BigCommerce sites like impactguns.com
     * 
     * @param DOMXPath $xpath The XPath object
     * @param string $url The source URL
     * @param array &$images The images array to add to
     * @param array &$image_urls_set The set of already found image URLs
     * @param bool $debug_mode Whether to enable debug mode
     */
    private function extractBigCommerceImages($xpath, $url, &$images, &$image_urls_set, $debug_mode) {
        if ($debug_mode) {
            error_log('Auto Product Import - Starting BigCommerce image extraction');
        }
        
        // 1. Try to get productView-thumbnails links
        $thumbnail_links = $xpath->query('//ul[contains(@class, "productView-thumbnails")]//a[@data-image-gallery-item]');
        if ($thumbnail_links && $thumbnail_links->length > 0) {
            if ($debug_mode) {
                error_log('Auto Product Import - Found ' . $thumbnail_links->length . ' productView thumbnail links');
            }
            
            foreach ($thumbnail_links as $link) {
                $this->extractBigCommerceImageFromLink($link, $url, $images, $image_urls_set, $debug_mode);
            }
        }
        
        // 2. Try data-image-gallery-new-image-url attributes
        $gallery_url_attrs = $xpath->query('//*[@data-image-gallery-new-image-url]');
        if ($gallery_url_attrs && $gallery_url_attrs->length > 0) {
            if ($debug_mode) {
                error_log('Auto Product Import - Found ' . $gallery_url_attrs->length . ' data-image-gallery-new-image-url attributes');
            }
            
            foreach ($gallery_url_attrs as $elem) {
                $image_url = $this->domGetAttribute($elem, 'data-image-gallery-new-image-url');
                if (!empty($image_url)) {
                    // Get highest quality version by replacing resolution in URL
                    $high_res_url = preg_replace('/\/\d+x\d+\//', '/1280x1280/', $image_url);
                    $absolute_url = $this->makeUrlAbsolute($high_res_url ? $high_res_url : $image_url, $url);
                    
                    if (!isset($image_urls_set[$absolute_url])) {
                        $image_urls_set[$absolute_url] = true;
                        $images[] = $absolute_url;
                        
                        if ($debug_mode) {
                            error_log('Auto Product Import - Added BigCommerce gallery image: ' . $absolute_url);
                        }
                    }
                }
            }
        }
        
        // 3. Try direct image tags with high-res pattern
        $product_images = $xpath->query('//img[contains(@src, "cdn") and contains(@src, "bigcommerce")]');
        if ($product_images && $product_images->length > 0) {
            foreach ($product_images as $img) {
                $src = $this->domGetAttribute($img, 'src');
                if (!empty($src) && 
                    strpos($src, 'icon') === false && 
                    strpos($src, 'logo') === false &&
                    strpos($src, 'placeholder') === false &&
                    strpos($src, 'pixel') === false) {
                    
                    // Get highest quality version 
                    $high_res_url = preg_replace('/\/\d+x\d+\//', '/1280x1280/', $src);
                    // Also try to get highest quality version from product path
                    $high_res_url = preg_replace('/\/\d+w\//', '/1280w/', $high_res_url);
                    
                    $absolute_url = $this->makeUrlAbsolute($high_res_url ? $high_res_url : $src, $url);
                    
                    if (!isset($image_urls_set[$absolute_url])) {
                        $image_urls_set[$absolute_url] = true;
                        $images[] = $absolute_url;
                        
                        if ($debug_mode) {
                            error_log('Auto Product Import - Added BigCommerce product image: ' . $absolute_url);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Extract a BigCommerce image from a thumbnail link
     *
     * @param DOMElement $link The link element
     * @param string $url The source URL
     * @param array &$images The images array to add to
     * @param array &$image_urls_set The set of already found image URLs
     * @param bool $debug_mode Whether to enable debug mode
     */
    private function extractBigCommerceImageFromLink($link, $url, &$images, &$image_urls_set, $debug_mode) {
        // First try data attribute
        if ($this->domHasAttribute($link, 'data-image-gallery-new-image-url')) {
            $image_url = $this->domGetAttribute($link, 'data-image-gallery-new-image-url');
            if (!empty($image_url)) {
                // Convert to highest resolution by replacing size in URL
                $high_res_url = preg_replace('/\/\d+x\d+\//', '/1280x1280/', $image_url);
                $absolute_url = $this->makeUrlAbsolute($high_res_url ? $high_res_url : $image_url, $url);
                
                if (!isset($image_urls_set[$absolute_url])) {
                    $image_urls_set[$absolute_url] = true;
                    $images[] = $absolute_url;
                    
                    if ($debug_mode) {
                        error_log('Auto Product Import - Added BigCommerce thumbnail image from data attribute: ' . $absolute_url);
                    }
                    return; // Done with this link
                }
            }
        }
        
        // Then try href
        if ($this->domHasAttribute($link, 'href')) {
            $image_url = $this->domGetAttribute($link, 'href');
            if (!empty($image_url)) {
                // Convert to highest resolution
                $high_res_url = preg_replace('/\/\d+x\d+\//', '/1280x1280/', $image_url);
                $absolute_url = $this->makeUrlAbsolute($high_res_url ? $high_res_url : $image_url, $url);
                
                if (!isset($image_urls_set[$absolute_url])) {
                    $image_urls_set[$absolute_url] = true;
                    $images[] = $absolute_url;
                    
                    if ($debug_mode) {
                        error_log('Auto Product Import - Added BigCommerce thumbnail image from href: ' . $absolute_url);
                    }
                }
            }
        }
    }
} 