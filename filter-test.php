<?php
/**
 * Standalone test for image URL filtering logic
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Script starting...\n";

echo "==========================================\n";
echo "IMAGE FILTERING TEST - DIRECT IMPLEMENTATION\n";
echo "==========================================\n\n";

/**
 * Test data - real images from Impact Guns website
 */
echo "Setting up test data...\n";
$test_urls = [
    // Product Images - Should be KEPT
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/1280x1280/products/8954/38161/IMG_3797__06599.1622757891.jpg',
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/1280x1280/products/8954/38162/IMG_3796__48608.1622757891.jpg',
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/1280x1280/products/8954/38163/IMG_3798__37277.1622757891.jpg',
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/500x659/products/8954/38161/IMG_3797__06599.1622757891.jpg',
    'https://www.impactguns.com/products/ruger-wrangler-revolver-large.jpg',
    
    // Non-Product Images - Should be FILTERED
    'https://www.impactguns.com/images/nav-arrow.svg',
    'https://www.impactguns.com/images/icons/user.svg',
    'https://www.impactguns.com/images/icons/cart.svg',
    'https://www.impactguns.com/images/icons/search.svg',
    'https://www.impactguns.com/content/images/header-logo.png',
    'https://www.impactguns.com/images/social/facebook.png',
    'https://www.impactguns.com/images/payment/visa.png',
    'https://www.impactguns.com/images/carousel-arrow.png',
    'https://www.impactguns.com/images/star-rating.png',
    'https://www.impactguns.com/images/icons/checkmark.svg',
    'https://www.impactguns.com/images/loading-spinner.gif',
    'https://www.impactguns.com/images/placeholder-product.png',
    'https://www.impactguns.com/images/banner-free-shipping.jpg',
    'https://www.impactguns.com/images/footer-logo.svg',
    'https://www.impactguns.com/images/guarantee-badge.png'
];
echo "Test data set up with " . count($test_urls) . " URLs\n";

// Define the blacklist terms from our improved filtering
echo "Setting up blacklist terms...\n";
$blacklisted_terms = [
    'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading', 'banner',
    'button', 'thumbnail-default', 'social', 'facebook', 'twitter', 'instagram',
    'background', 'pattern', 'avatar', 'profile', 'cart', 'checkout', 'payment',
    'shipping', 'footer', 'header', 'navigation', 'menu', 'search', 'sprite', 'guarantee',
    'badge', 'star', 'rating', 'share', 'wishlist', 'compare', 'like', 'heart',
    'zoom', 'magnify', 'close', 'play', 'video-placeholder', 'arrow', 'user'
];
echo "Blacklist terms set up with " . count($blacklisted_terms) . " terms\n";

/**
 * Basic filtering implementation (original approach)
 */
echo "Defining filtering functions...\n";
function basic_filtering($url) {
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    // Skip small images, placeholders, and icons
    if (strpos($url, 'icon') !== false || 
        strpos($url, 'logo') !== false || 
        strpos($url, 'placeholder') !== false) {
        return false;
    }
    
    return true;
}

/**
 * Enhanced filtering implementation (new approach)
 */
function enhanced_filtering($url, $blacklisted_terms) {
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    // Check against blacklisted terms
    foreach ($blacklisted_terms as $term) {
        if (stripos($url, $term) !== false) {
            return false;
        }
    }
    
    // Prioritize high-res product images
    $is_high_res = preg_match('/\/\d+x\d+\//', $url) || 
                   preg_match('/[_-]\d+x\d+/', $url) ||
                   preg_match('/size=\d+x\d+/', $url) || 
                   preg_match('/w=\d+/', $url) ||
                   preg_match('/width=\d+/', $url);
                   
    // Check for product paths
    $is_product_path = strpos($url, '/product') !== false || 
                      strpos($url, '/products/') !== false;
    
    // Check for image file extensions
    $has_image_extension = preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $url);
    
    // If high-res or product path, prioritize
    if ($is_high_res || $is_product_path) {
        return true;
    }
    
    // For other images, they must have an image extension
    return $has_image_extension;
}

/**
 * Full implementation - combines multiple strategies
 */
function full_implementation($url, $blacklisted_terms) {
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    $url_lower = strtolower($url);
    
    // 1. First check against blacklisted terms
    foreach ($blacklisted_terms as $term) {
        if (stripos($url_lower, $term) !== false) {
            return false;
        }
    }
    
    // 2. Check for common non-product image patterns
    $common_non_product_patterns = [
        '/\.svg(\?.*)?$/', // SVG files are often icons/UI elements
        '/\.gif(\?.*)?$/', // GIFs are often spinners/animations
        '/^https?:\/\/[^\/]+\/images\//', // Generic images folder often contains UI elements
        '/\/(social|payment|icon)\//'
    ];
    
    foreach ($common_non_product_patterns as $pattern) {
        if (preg_match($pattern, $url_lower)) {
            return false;
        }
    }
    
    // 3. Check for product indicators
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
    
    // 4. Basic image extension check as fallback
    return preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/i', $url);
}
echo "Filtering functions defined\n";

// Test the filtering methods
echo "Starting filtering tests...\n";
$basic_filtered = [];
$enhanced_filtered = [];
$full_filtered = [];

echo "Processing URLs through filters...\n";
foreach ($test_urls as $url) {
    if (basic_filtering($url)) {
        $basic_filtered[] = $url;
    }
    
    if (enhanced_filtering($url, $blacklisted_terms)) {
        $enhanced_filtered[] = $url;
    }
    
    if (full_implementation($url, $blacklisted_terms)) {
        $full_filtered[] = $url;
    }
}
echo "Filtering tests completed\n";

// Output results
echo "COMPARISON OF FILTERING METHODS\n";
echo "-----------------------------------------\n";
echo "Total test URLs: " . count($test_urls) . "\n";
echo "Basic filtering kept: " . count($basic_filtered) . " images\n";
echo "Enhanced filtering kept: " . count($enhanced_filtered) . " images\n";
echo "Full implementation kept: " . count($full_filtered) . " images\n\n";

// Display detailed results
echo "DETAILED FILTERING RESULTS\n";
echo "-----------------------------------------\n";
printf("%-75s | %-10s | %-10s | %-10s\n", "URL", "BASIC", "ENHANCED", "FULL");
echo str_repeat("-", 110) . "\n";

echo "Printing detailed results...\n";
foreach ($test_urls as $url) {
    $basic_result = basic_filtering($url) ? "KEEP" : "FILTER";
    $enhanced_result = enhanced_filtering($url, $blacklisted_terms) ? "KEEP" : "FILTER";
    $full_result = full_implementation($url, $blacklisted_terms) ? "KEEP" : "FILTER";
    
    // Truncate URL for display if needed
    $display_url = strlen($url) > 75 ? substr($url, 0, 72) . "..." : $url;
    
    printf("%-75s | %-10s | %-10s | %-10s\n", $display_url, $basic_result, $enhanced_result, $full_result);
}

echo "\nTest completed!\n"; 