<?php
/**
 * Simple test script for Auto Product Import image extraction
 * 
 * This script tests the image extraction functionality without requiring WordPress to be fully functional.
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define constants needed for the plugin
define('AUTO_PRODUCT_IMPORT_VERSION', '1.1.0');
define('AUTO_PRODUCT_IMPORT_PLUGIN_DIR', dirname(__FILE__) . '/');
define('AUTO_PRODUCT_IMPORT_PLUGIN_URL', 'placeholder://url/');
define('WPINC', 'wp-includes');

// Mock WordPress functions
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

// Initialize filters array
global $filters;
$filters = array();

// Force debug mode on
add_filter('auto_product_import_debug_mode', '__return_true');

// Load the plugin class
require_once('includes/class-auto-product-import.php');

// URLs to test
$test_urls = [
    'https://www.impactguns.com/revolvers/ruger-wrangler-22-lr-4-62-barrel-6rd-burnt-bronze-736676020041-2004'
];

// Define a minimal WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = array_keys($this->errors)[0] ?? '';
            }
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}

// Define a function to simulate wp_remote_get
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $args['user-agent'] ?? 'Mozilla/5.0');
        
        // Bypass SSL verification for testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('curl_error', $error);
        }
        
        return array(
            'body' => $response,
            'response' => array('code' => $http_code),
            'headers' => array(),
        );
    }
}

// Helper functions
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return 0;
        }
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Create an instance of the plugin class
$auto_product_import = new Auto_Product_Import();

// Use reflection to access the private method for testing
$reflector = new ReflectionClass('Auto_Product_Import');
$method = $reflector->getMethod('extractProductImages');
$method->setAccessible(true);

$fetch_method = $reflector->getMethod('fetch_product_data_from_url');
$fetch_method->setAccessible(true);

echo "Auto Product Import Image Extraction Test\n";
echo "----------------------------------------\n\n";

foreach ($test_urls as $url) {
    echo "Testing URL: $url\n";
    
    // Fetch HTML content
    echo "Fetching HTML content...\n";
    $response = wp_remote_get($url, array(
        'timeout' => 60,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ));
    
    if (is_wp_error($response)) {
        echo "Error: " . $response->get_error_message() . "\n";
        continue;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        echo "Error: Failed to fetch URL. Response code: $response_code\n";
        continue;
    }
    
    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        echo "Error: Empty response body\n";
        continue;
    }
    
    echo "Successfully fetched HTML content (" . strlen($body) . " bytes)\n";
    
    // Create DOM
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($body);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    echo "Testing image extraction method...\n";
    
    // Extract images using our method
    $data = $fetch_method->invoke($auto_product_import, $url);
    
    if (is_wp_error($data)) {
        echo "Error: " . $data->get_error_message() . "\n";
        continue;
    }
    
    $images = $data['images'];
    
    echo "Found " . count($images) . " product images:\n";
    foreach ($images as $index => $image_url) {
        echo ($index + 1) . ". $image_url\n";
    }
    
    echo "\n";
}

echo "Test completed.\n"; 