<?php
// Simple test script to verify our HTML extraction and cleaning functions
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load HTML file
$html = file_get_contents(__DIR__ . '/test.html');
if (!$html) {
    die("Could not read test.html");
}

// Create DOM document
$dom = new DOMDocument();
libxml_use_internal_errors(true);
@$dom->loadHTML($html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

// Define the extraction function
function extractDescriptionHTML($dom, $xpath) {
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
        '//div[@id="detailBullets")]',
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

// Define the cleanup function
function cleanupHTML($html) {
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

// Extract title
$title_nodes = $xpath->query('//h1[@class="product-title"] | //h1[@class="product_title"] | //h1[contains(@class, "product-title")] | //h1[contains(@class, "product_title")]');
if ($title_nodes && $title_nodes->length > 0) {
    $title = trim($title_nodes->item(0)->textContent);
    echo "Found Title: " . $title . "\n\n";
} else {
    echo "Title not found\n\n";
}

// Test extraction
$description_html = extractDescriptionHTML($dom, $xpath);
if (!empty($description_html)) {
    echo "Description HTML found!\n";
    echo "HTML Length: " . strlen($description_html) . " characters\n\n";
    
    // Save the raw description
    file_put_contents(__DIR__ . '/description-raw.html', $description_html);
    echo "Raw description saved to description-raw.html\n\n";
    
    // Test cleaning
    $cleaned_html = cleanupHTML($description_html);
    echo "Cleaned HTML!\n";
    echo "Cleaned HTML Length: " . strlen($cleaned_html) . " characters\n\n";
    
    // Save the cleaned description
    file_put_contents(__DIR__ . '/description-cleaned.html', $cleaned_html);
    echo "Cleaned description saved to description-cleaned.html\n\n";
} else {
    echo "Description HTML not found!\n\n";
    
    // Try to use the meta description as fallback
    $meta_desc = $xpath->query('//meta[@name="description"]/@content');
    if ($meta_desc && $meta_desc->length > 0) {
        $description = trim($meta_desc->item(0)->textContent);
        echo "Meta Description found: " . $description . "\n\n";
    }
}

echo "Test completed successfully!\n"; 