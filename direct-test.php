<?php
// Simple direct test to extract fields from HTML using regex and string methods
// This avoids the complexity of DOMDocument to isolate the issue

// Load the test HTML
$test_html = file_get_contents(__DIR__ . '/test-description.html');
if (!$test_html) {
    die("Could not read test-description.html");
}

echo "<h1>Direct Field Extraction Test</h1>";

// Fields to extract
$fields_to_extract = array(
    'Action',
    'Barrel',
    'Accessory Rail',
    'Finish',
    'Intended Use',
    'Length',
    'Safety',
    'Sights',
    'Trigger',
    'Weight',
    'Frame Material',
    'Power Source',
    'Velocity',
    'Caliber',
    'Magazine Capacity'
);

// Results array
$extracted_fields = array();

// For debugging
$debug_info = array();

// APPROACH 1: Extract directly using regex
echo "<h2>Approach 1: Direct Regex Extraction</h2>";

foreach ($fields_to_extract as $field) {
    // Look for <li>Field: Value</li> pattern
    $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:\s*([^<]+)<\/li>/i';
    if (preg_match($pattern, $test_html, $matches)) {
        $extracted_fields[$field] = trim($matches[1]);
        $debug_info[$field] = "Found via pattern match: $pattern";
        echo "<p>✓ Found <strong>$field</strong>: " . htmlspecialchars($extracted_fields[$field]) . "</p>";
    } else {
        // Also try with different spacing
        $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:([^<]+)<\/li>/i';
        if (preg_match($pattern, $test_html, $matches)) {
            $extracted_fields[$field] = trim($matches[1]);
            $debug_info[$field] = "Found via second pattern match: $pattern";
            echo "<p>✓ Found <strong>$field</strong>: " . htmlspecialchars($extracted_fields[$field]) . "</p>";
        }
    }
}

// APPROACH 2: Extract by finding all list items and checking each one
echo "<h2>Approach 2: List Item Extraction</h2>";

// Find all list items using regex
$list_items = array();
if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $test_html, $matches)) {
    $list_items = $matches[1];
}

echo "<p>Found " . count($list_items) . " list items</p>";

// Check each list item for our fields
foreach ($list_items as $index => $item) {
    $item = trim(strip_tags($item));
    echo "<p><strong>List item #" . ($index + 1) . ":</strong> " . htmlspecialchars($item) . "</p>";
    
    // Check each field
    foreach ($fields_to_extract as $field) {
        if (!isset($extracted_fields[$field]) && stripos($item, $field . ':') === 0) {
            $parts = explode(':', $item, 2);
            if (count($parts) === 2) {
                $extracted_fields[$field] = trim($parts[1]);
                $debug_info[$field] = "Found via list item direct extraction, item #$index";
                echo "<p class='indent'>✓ Extracted <strong>$field</strong>: " . htmlspecialchars($extracted_fields[$field]) . "</p>";
            }
        }
    }
}

// Summary of results
echo "<h2>Summary of Extracted Fields</h2>";
echo "<p>Found " . count($extracted_fields) . " out of " . count($fields_to_extract) . " fields</p>";

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Field</th><th>Value</th><th>Method</th></tr>";

foreach ($fields_to_extract as $field) {
    echo "<tr>";
    echo "<td>$field</td>";
    if (isset($extracted_fields[$field])) {
        echo "<td>" . htmlspecialchars($extracted_fields[$field]) . "</td>";
        echo "<td>" . htmlspecialchars($debug_info[$field]) . "</td>";
    } else {
        echo "<td colspan='2' style='color: red;'>Not found</td>";
    }
    echo "</tr>";
}

echo "</table>";

// Output the full results
echo "<h2>Full Extraction Results</h2>";
echo "<pre>";
print_r($extracted_fields);
echo "</pre>";

// Some CSS for better readability
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .indent { margin-left: 20px; }
    h1 { color: #333; }
    h2 { color: #666; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; }
    th { background-color: #f2f2f2; }
    td, th { padding: 8px; text-align: left; }
</style>"; 