<?php
/**
 * Final Test: A standalone implementation of our product image extraction with improved filtering
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test URL
$test_url = 'https://www.impactguns.com/revolvers/ruger-wrangler-22-lr-4-62-barrel-6rd-burnt-bronze-736676020041-2004';

echo "PRODUCT IMAGE EXTRACTION TEST\n";
echo "====================================\n";
echo "Testing URL: $test_url\n\n";

// Blacklisted terms for filtering non-product images
$blacklisted_terms = [
    'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading', 'banner',
    'button', 'thumbnail-default', 'social', 'facebook', 'twitter', 'instagram',
    'background', 'pattern', 'avatar', 'profile', 'cart', 'checkout', 'payment',
    'shipping', 'footer', 'header', 'navigation', 'menu', 'search', 'sprite', 'guarantee',
    'badge', 'star', 'rating', 'share', 'wishlist', 'compare', 'like', 'heart',
    'zoom', 'magnify', 'close', 'play', 'video-placeholder', 'arrow', 'user'
];

// Helper function to check if a URL is a valid product image
function isValidProductImage($url, $blacklisted_terms) {
    // Skip empty URLs and data URIs
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    $url_lower = strtolower($url);
    
    // Check against blacklisted terms
    foreach ($blacklisted_terms as $term) {
        if (stripos($url_lower, $term) !== false) {
            return false;
        }
    }
    
    // Check for common non-product image patterns
    $common_non_product_patterns = [
        '/\.svg(\?.*)?$/', // SVG files are often icons/UI elements
        '/\.gif(\?.*)?$/', // GIFs are often spinners/animations
        '/^https?:\/\/[^\/]+\/images\//' // Generic images folder often contains UI elements
    ];
    
    foreach ($common_non_product_patterns as $pattern) {
        if (preg_match($pattern, $url_lower)) {
            return false;
        }
    }
    
    // Check for product indicators
    $product_indicators = [
        // BigCommerce specific patterns
        '/\/stencil\/\d+x\d+\/products\//',
        '/\/products\/.*\.(jpg|jpeg|png|webp)/',
        
        // Product path indicators
        '/\/product[s]?\//',
        '/\/(large|medium|zoom)\.(jpg|jpeg|png|webp)/',
        
        // Size indicators (common for product images)
        '/[\/_-](\d{3,4}x\d{3,4})/'
    ];
    
    foreach ($product_indicators as $pattern) {
        if (preg_match($pattern, $url_lower)) {
            return true;
        }
    }
    
    // Basic image extension check as fallback
    return preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/i', $url);
}

// Helper function to convert thumbnails to high-resolution (like our plugin)
function convertToHighRes($url) {
    // Convert BigCommerce thumbnail URLs to high-res (1280x1280)
    if (strpos($url, '/stencil/') !== false) {
        return preg_replace('/\/stencil\/[^\/]+\//', '/stencil/1280x1280/', $url);
    }
    
    return $url;
}

// DOM-safe version of hasAttribute
function domHasAttribute($node, $attr) {
    if (method_exists($node, 'hasAttribute')) {
        return $node->hasAttribute($attr);
    }
    return $node->getAttribute($attr) !== null && $node->getAttribute($attr) !== '';
}

// DOM-safe version of getAttribute
function domGetAttribute($node, $attr) {
    if (method_exists($node, 'getAttribute')) {
        return $node->getAttribute($attr);
    }
    return '';
}

// Main product image extraction function (simplified from our plugin)
function extractProductImages($html, $blacklisted_terms) {
    $images = [];
    $found_count = 0;
    
    echo "Starting image extraction...\n";
    
    // Create a new DOMDocument
    $dom = new DOMDocument();
    
    // Suppress warnings from malformed HTML
    $old_setting = libxml_use_internal_errors(true);
    
    // Load the HTML
    $dom->loadHTML($html);
    
    // Restore error handling
    libxml_use_internal_errors($old_setting);
    
    // Create a DOMXPath object to query the DOM
    $xpath = new DOMXPath($dom);
    
    echo "Loaded DOM and created XPath object\n";
    
    // 1. First try extracting from BigCommerce-specific selectors
    echo "\n1. Checking BigCommerce-specific selectors...\n";
    
    // Check for productView thumbnails (common in BigCommerce)
    $bigCommerceNodes = $xpath->query('//ul[contains(@class, "productView-thumbnails")]/li//img');
    if ($bigCommerceNodes && $bigCommerceNodes->length > 0) {
        echo "Found BigCommerce product thumbnails: " . $bigCommerceNodes->length . "\n";
        foreach ($bigCommerceNodes as $node) {
            $img_url = '';
            
            // Check for data-image-gallery attribute (high-quality images)
            if (domHasAttribute($node, 'data-image-gallery-new-image-url')) {
                $img_url = domGetAttribute($node, 'data-image-gallery-new-image-url');
                echo "- Found high-quality image URL from data-attribute\n";
            }
            // Fallback to src
            else if (domHasAttribute($node, 'src')) {
                $img_url = domGetAttribute($node, 'src');
                echo "- Found image URL from src attribute\n";
            }
            
            $img_url = convertToHighRes($img_url);
            
            if (!empty($img_url) && isValidProductImage($img_url, $blacklisted_terms) && !in_array($img_url, $images)) {
                $images[] = $img_url;
                $found_count++;
                echo "  Added BigCommerce image: " . $img_url . "\n";
            }
        }
    } else {
        echo "No BigCommerce thumbnails found\n";
    }
    
    // 2. If we don't have enough images, try other product image containers
    echo "\n2. Checking general product image containers...\n";
    
    if ($found_count < 3) {
        // Common product image container selectors
        $productSelectors = [
            '//div[contains(@class, "product-images")]//img',
            '//div[contains(@class, "product-gallery")]//img',
            '//div[contains(@id, "product-images")]//img',
            '//div[contains(@class, "product-detail")]//img',
            '//div[contains(@class, "product-media")]//img',
            '//div[contains(@class, "product-slider")]//img'
        ];
        
        foreach ($productSelectors as $selector) {
            $productNodes = $xpath->query($selector);
            
            if ($productNodes && $productNodes->length > 0) {
                echo "Found product images using selector '$selector': " . $productNodes->length . "\n";
                
                foreach ($productNodes as $node) {
                    $img_url = '';
                    
                    // Check for common image attributes
                    $attributes = ['data-zoom-image', 'data-large', 'data-src', 'src'];
                    foreach ($attributes as $attr) {
                        if (domHasAttribute($node, $attr)) {
                            $img_url = domGetAttribute($node, $attr);
                            break;
                        }
                    }
                    
                    $img_url = convertToHighRes($img_url);
                    
                    if (!empty($img_url) && isValidProductImage($img_url, $blacklisted_terms) && !in_array($img_url, $images)) {
                        $images[] = $img_url;
                        $found_count++;
                        echo "  Added product image: " . $img_url . "\n";
                    }
                }
            }
        }
    }
    
    // 3. If we still don't have enough images, try a broader approach
    echo "\n3. Checking main content for additional images...\n";
    
    if ($found_count < 3) {
        // Look for images in the main content area
        $mainContentSelectors = [
            '//div[contains(@class, "main-content")]//img',
            '//main//img',
            '//article//img',
            '//div[contains(@class, "content")]//img'
        ];
        
        foreach ($mainContentSelectors as $selector) {
            $contentNodes = $xpath->query($selector);
            
            if ($contentNodes && $contentNodes->length > 0) {
                echo "Found images in main content using selector '$selector': " . $contentNodes->length . "\n";
                
                foreach ($contentNodes as $node) {
                    if (domHasAttribute($node, 'src')) {
                        $img_url = domGetAttribute($node, 'src');
                        $img_url = convertToHighRes($img_url);
                        
                        if (!empty($img_url) && isValidProductImage($img_url, $blacklisted_terms) && !in_array($img_url, $images)) {
                            $images[] = $img_url;
                            $found_count++;
                            echo "  Added content image: " . $img_url . "\n";
                        }
                    }
                }
            }
        }
    }
    
    // 4. Final fallback: just get all images and filter
    echo "\n4. Final approach: Get all images and filter...\n";
    
    if ($found_count < 3) {
        $allImages = $xpath->query('//img');
        
        if ($allImages && $allImages->length > 0) {
            echo "Found " . $allImages->length . " total images, filtering for product images...\n";
            
            foreach ($allImages as $node) {
                if (domHasAttribute($node, 'src')) {
                    $img_url = domGetAttribute($node, 'src');
                    $img_url = convertToHighRes($img_url);
                    
                    if (!empty($img_url) && isValidProductImage($img_url, $blacklisted_terms) && !in_array($img_url, $images)) {
                        $images[] = $img_url;
                        $found_count++;
                        echo "  Added general image: " . $img_url . "\n";
                    }
                }
            }
        }
    }
    
    echo "\nExtraction complete.\n";
    return $images;
}

// Fetch HTML from the URL
echo "Fetching URL: $test_url\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$html = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Error: $error\n";
    exit(1);
}

echo "HTTP Response Code: $http_code\n";
echo "HTML size: " . strlen($html) . " bytes\n\n";

// Extract images
$images = extractProductImages($html, $blacklisted_terms);

// Display summary
echo "\nRESULTS SUMMARY\n";
echo "====================================\n";
echo "Number of product images found: " . count($images) . "\n\n";

if (count($images) > 0) {
    echo "PRODUCT IMAGES FOUND:\n";
    echo "------------------------------------\n";
    foreach ($images as $index => $url) {
        echo ($index + 1) . ". $url\n";
    }
} else {
    echo "No product images found.\n";
}

echo "\nTest completed!\n"; 