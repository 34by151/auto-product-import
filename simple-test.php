<?php
/**
 * Simple test script for Auto Product Import image extraction
 * This will test if our improved filtering logic reduces unrelated images
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define constants needed by the plugin
define('AUTO_PRODUCT_IMPORT_PLUGIN_URL', '');
define('AUTO_PRODUCT_IMPORT_PLUGIN_DIR', dirname(__FILE__) . '/');
define('AUTO_PRODUCT_IMPORT_VERSION', '1.0.0');

// Define WordPress functions needed by the plugin
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback) {
        global $filters;
        $filters[$hook][] = $callback;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        global $filters;
        if (isset($filters[$hook])) {
            foreach ($filters[$hook] as $callback) {
                $value = call_user_func($callback, $value);
            }
        }
        return $value;
    }
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
}

// Initialize global filters array
global $filters;
$filters = array();

// Force debug mode on
add_filter('auto_product_import_debug_mode', '__return_true');

// Load the plugin class file
require_once('includes/class-auto-product-import.php');

// URLs to test
$test_urls = [
    'https://www.impactguns.com/revolvers/ruger-wrangler-22-lr-4-62-barrel-6rd-burnt-bronze-736676020041-2004'
];

echo "======================================================\n";
echo "IMPROVED IMAGE EXTRACTION TEST\n";
echo "======================================================\n\n";

// Create a new instance of the plugin class
$import = new Auto_Product_Import();

// Create a minimal WP_Error class
class WP_Error {
    protected $code;
    protected $message;
    
    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }
    
    public function get_error_message() {
        return $this->message;
    }
}

// Simple custom implementation of wp_remote_get
function wp_remote_get($url, $args = array()) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        return new WP_Error('request_failed', 'cURL error: ' . curl_error($ch));
    }
    
    return array(
        'body' => $response,
        'response' => array('code' => $http_code)
    );
}

// Helper functions
function wp_remote_retrieve_response_code($response) {
    return isset($response['response']['code']) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response) {
    return isset($response['body']) ? $response['body'] : '';
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

// Make the private extractProductImages method accessible
$reflection = new ReflectionClass('Auto_Product_Import');
$extract_method = $reflection->getMethod('extractProductImages');
$extract_method->setAccessible(true);

// Test each URL
foreach ($test_urls as $test_url) {
    echo "Testing URL: $test_url\n\n";
    
    // Fetch the HTML content
    $response = wp_remote_get($test_url);
    
    if (is_wp_error($response)) {
        echo "Error: " . $response->get_error_message() . "\n";
        continue;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        echo "Error: Failed to fetch the URL. Response code: " . $response_code . "\n";
        continue;
    }
    
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        echo "Error: The URL returned an empty response.\n";
        continue;
    }
    
    // Create DOM objects
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($body);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    echo "Successfully loaded HTML content.\n";
    
    // Extract images
    try {
        $images = $extract_method->invokeArgs($import, array($xpath, $test_url));
        
        // Display results
        echo "Number of Product Images Found: " . count($images) . "\n\n";
        
        if (count($images) > 0) {
            echo "Product Images Found:\n";
            foreach ($images as $index => $image_url) {
                echo ($index + 1) . ". " . $image_url . "\n";
            }
        } else {
            echo "No product images found.\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n======================================================\n\n";
}

echo "Test completed!\n"; 