<?php
// Simple mini-test for product image filtering
echo "Starting simple filter test...\n\n";

// Just a few test URLs
$test_urls = [
    // Should KEEP these product images
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/1280x1280/products/8954/38161/IMG_3797__06599.1622757891.jpg',
    'https://www.impactguns.com/products/ruger-wrangler-revolver-large.jpg',
    
    // Should FILTER these non-product images
    'https://www.impactguns.com/images/icons/cart.svg',
    'https://www.impactguns.com/content/images/header-logo.png',
    'https://www.impactguns.com/images/placeholder-product.png'
];

// Blacklisted terms
$blacklisted_terms = [
    'icon', 'logo', 'placeholder', 'cart', 'button', 'banner'
];

// Simple filtering function
function filterProductImage($url, $blacklisted_terms) {
    // Skip empty URLs and data URIs
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    // Check against blacklisted terms
    foreach ($blacklisted_terms as $term) {
        if (stripos($url, $term) !== false) {
            echo "  - Filtered out by term: $term\n";
            return false;
        }
    }
    
    // Check for product indicators
    $is_product = false;
    
    // Check for high-res product image patterns
    if (preg_match('/\/\d+x\d+\/products\//', $url)) {
        echo "  - Kept: Contains dimensions and products path\n";
        $is_product = true;
    }
    // Check for product path
    else if (strpos($url, '/products/') !== false) {
        echo "  - Kept: Contains products path\n";
        $is_product = true;
    }
    // Check for image extensions for everything else
    else if (preg_match('/\.(jpg|jpeg|png)(\?.*)?$/i', $url)) {
        echo "  - Kept: Has valid image extension\n";
        $is_product = true;
    }
    else {
        echo "  - Filtered: Not matching product patterns\n";
    }
    
    return $is_product;
}

// Test each URL
echo "TESTING URLS:\n";
echo "==============================================\n";

$kept_count = 0;
$total_count = count($test_urls);

foreach ($test_urls as $index => $url) {
    echo ($index + 1) . ". " . $url . "\n";
    $result = filterProductImage($url, $blacklisted_terms);
    
    if ($result) {
        $kept_count++;
    }
    
    echo "\n";
}

echo "==============================================\n";
echo "Summary: Kept $kept_count out of $total_count images\n";
echo "Test complete!\n"; 