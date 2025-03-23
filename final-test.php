<?php
/**
 * Final test script to verify the extraction of additional product information
 * This script uses the actual plugin class
 */

// Display all errors for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test script...\n";

// Define plugin constants if not already defined
if (!defined('AUTO_PRODUCT_IMPORT_PLUGIN_DIR')) {
    define('AUTO_PRODUCT_IMPORT_PLUGIN_DIR', __DIR__ . '/');
}

echo "Loading plugin class...\n";

// Include the main plugin class
require_once __DIR__ . '/includes/class-auto-product-import.php';

echo "Creating test instance...\n";

// Create an instance of the plugin class 
$test = new Auto_Product_Import();

echo "Running test 1...\n";

// Test 1: Original cleaned description file
echo "<h1>Test 1: Original Cleaned Description</h1>";
$description_html = file_get_contents(__DIR__ . '/description-cleaned.html');
if (!$description_html) {
    die("Could not read description-cleaned.html");
}
echo "Loaded description-cleaned.html: " . strlen($description_html) . " bytes\n";

// Use the public wrapper method
$additional_info = $test->get_additional_product_info($description_html, true);

// Display the results
echo "<pre>";
echo "Found " . count($additional_info) . " additional information fields:\n\n";
print_r($additional_info);
echo "</pre>";

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Name</th><th>Value</th></tr>";
foreach ($additional_info as $name => $value) {
    echo "<tr><td>$name</td><td>$value</td></tr>";
}
echo "</table>";

echo "Running test 2...\n";

// Test 2: Enhanced test description with more patterns
echo "<h1>Test 2: Enhanced Test Description</h1>";
$test_description_html = file_get_contents(__DIR__ . '/test-description.html');
if (!$test_description_html) {
    die("Could not read test-description.html");
}
echo "Loaded test-description.html: " . strlen($test_description_html) . " bytes\n";

// Use the public wrapper method
$additional_info2 = $test->get_additional_product_info($test_description_html, true);

// Display the results
echo "<pre>";
echo "Found " . count($additional_info2) . " additional information fields:\n\n";
print_r($additional_info2);
echo "</pre>";

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Name</th><th>Value</th></tr>";
foreach ($additional_info2 as $name => $value) {
    echo "<tr><td>$name</td><td>$value</td></tr>";
}
echo "</table>";

echo "<p>Test completed successfully!</p>"; 