<?php
/**
 * Simple image filtering test to demonstrate the improved filtering
 */

// Define a set of test image URLs - some should be filtered and some should pass
$test_images = [
    // Product images that should be kept
    'https://cdn11.bigcommerce.com/s-a5s1va/images/stencil/1280x1280/products/8954/38161/IMG_3797__06599.1622757891.jpg', // High-res product image
    'https://www.impactguns.com/products/ruger-wrangler-revolver.jpg', // Product image with product in path
    'https://cdn.shopify.com/s/files/1/0234/5963/products/ruger-wrangler-burnt-bronze-revolver_large.jpg', // Product image with sizing
    'https://assets.basspro.com/image/upload/c_scale,w_1000,h_1000/v1580493910/DigitalCreative/2020/New-Products/Week2/Ruger-Wrangler/Ruger_Wrangler_ProductImage.jpg', // Product image with dimensions
    
    // Non-product images that should be filtered out
    'https://www.impactguns.com/images/icons/cart-icon.png', // Icon image
    'https://www.impactguns.com/images/logo/impact-guns-logo.svg', // Logo image
    'https://cdn11.bigcommerce.com/s-a5s1va/images/social-icons/facebook.png', // Social media icon
    'https://www.impactguns.com/images/thumbnail-placeholder.png', // Placeholder image
    'https://www.impactguns.com/images/buttons/payment-button.jpg', // Button image
    'https://www.impactguns.com/images/spinner.gif', // Loading spinner
    'https://www.impactguns.com/images/rating-stars.png', // Rating stars
    'https://www.impactguns.com/content/banners/banner-header.jpg', // Banner image
    'https://impactguns.com/images/menu/dropdown-arrow.svg', // Menu element
];

echo "==============================================\n";
echo "IMAGE FILTERING TEST\n";
echo "==============================================\n\n";

// Define a set of blacklisted terms similar to what we're using in the code
$blacklisted_terms = array(
    'icon', 'logo', 'placeholder', 'pixel', 'spinner', 'loading', 'banner',
    'button', 'thumbnail-default', 'social', 'facebook', 'twitter', 'instagram',
    'background', 'pattern', 'avatar', 'profile', 'cart', 'checkout', 'payment',
    'shipping', 'footer', 'header', 'navigation', 'menu', 'search', 'sprite', 'guarantee',
    'badge', 'star', 'rating', 'share', 'wishlist', 'compare', 'like', 'heart',
    'zoom', 'magnify', 'close', 'play', 'video-placeholder'
);

// Basic filtering from old code (just checking a few terms)
function oldStyleFiltering($url) {
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    // Skip tiny images, placeholders, and icons
    if (strpos($url, 'icon') !== false || 
        strpos($url, 'logo') !== false || 
        strpos($url, 'placeholder') !== false || 
        strpos($url, 'pixel.') !== false) {
        return false;
    }
    
    return true;
}

// Improved filtering from new code
function newStyleFiltering($url, $blacklisted_terms) {
    if (empty($url) || strpos($url, 'data:image') === 0) {
        return false;
    }
    
    // Check against blacklisted terms
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
    
    return $has_image_extension || $has_image_path || $has_dimensions;
}

// Test each URL with both filtering methods
echo "COMPARING OLD VS NEW FILTERING\n";
echo "---------------------------------------------\n";
printf("%-65s | %-10s | %-10s\n", "IMAGE URL", "OLD FILTER", "NEW FILTER");
echo "---------------------------------------------\n";

foreach ($test_images as $url) {
    $old_result = oldStyleFiltering($url) ? "KEEP" : "FILTER OUT";
    $new_result = newStyleFiltering($url, $blacklisted_terms) ? "KEEP" : "FILTER OUT";
    
    // Truncate URL for display
    $display_url = strlen($url) > 65 ? substr($url, 0, 62) . "..." : $url;
    
    printf("%-65s | %-10s | %-10s\n", $display_url, $old_result, $new_result);
}

// Count how many images would be kept by each method
$old_kept = 0;
$new_kept = 0;
foreach ($test_images as $url) {
    if (oldStyleFiltering($url)) $old_kept++;
    if (newStyleFiltering($url, $blacklisted_terms)) $new_kept++;
}

echo "\n";
echo "Old filtering would keep $old_kept out of " . count($test_images) . " images.\n";
echo "New filtering would keep $new_kept out of " . count($test_images) . " images.\n";

echo "\nTest completed!\n"; 