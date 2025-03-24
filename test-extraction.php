<?php
/**
 * Test script for product image extraction
 */

// Enable error reporting at the beginning
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get current script directory
$script_dir = __DIR__;
echo "Script directory: $script_dir\n";

// Define needed constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
    echo "Defined ABSPATH as: " . ABSPATH . "\n";
}

// Define functions needed by the plugin
if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        global $wp_filters;
        $wp_filters[$tag][$priority][] = [
            'function' => $function_to_add,
            'accepted_args' => $accepted_args
        ];
        return true;
    }
    echo "Defined add_filter function\n";
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        global $wp_filters;
        if (!isset($wp_filters[$tag])) {
            return $value;
        }
        
        $args = func_get_args();
        array_shift($args); // Remove tag
        
        foreach ($wp_filters[$tag] as $priority => $functions) {
            foreach ($functions as $function_data) {
                $func = $function_data['function'];
                $accepted_args = $function_data['accepted_args'];
                $sliced_args = array_slice($args, 0, $accepted_args);
                $value = call_user_func_array($func, $sliced_args);
            }
        }
        
        return $value;
    }
    echo "Defined apply_filters function\n";
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
    echo "Defined __return_true function\n";
}

// Initialize needed globals
global $wp_filters;
$wp_filters = [];
echo "Initialized wp_filters global\n";

// Force debug mode on
define('WP_DEBUG', true);
echo "Enabled WP_DEBUG\n";

// Load the plugin class - use absolute path
$class_file = $script_dir . '/includes/class-auto-product-import.php';
echo "Loading plugin class from: $class_file\n";

if (!file_exists($class_file)) {
    echo "ERROR: Plugin class file does not exist at $class_file\n";
    die("Cannot proceed without plugin class file\n");
}

try {
    require_once $class_file;
    echo "Successfully loaded plugin class file\n";
} catch (Exception $e) {
    echo "ERROR loading class file: " . $e->getMessage() . "\n";
    die("Cannot proceed without plugin class\n");
}

// Check if class exists
if (!class_exists('Auto_Product_Import')) {
    echo "ERROR: Auto_Product_Import class not found after including the file\n";
    die("Cannot proceed without Auto_Product_Import class\n");
}

// URLs to test
$test_urls = [
    'https://www.impactguns.com/revolvers/ruger-wrangler-22-lr-4-62-barrel-6rd-burnt-bronze-736676020041-2004'
];

echo "Test URL configured\n";

// Create minimal WP_Error class if needed
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public function __construct($code = '', $message = '', $data = '') {
            $this->errors[$code] = [$message];
        }
        public function get_error_message() {
            return reset($this->errors)[0] ?? '';
        }
    }
    echo "Created WP_Error class\n";
}

// Mock remote get function
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url) {
        echo "Fetching URL: $url\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "cURL Error: $error\n";
            return new WP_Error('http_request_failed', $error);
        }
        
        echo "HTTP Response Code: $http_code\n";
        echo "Response Size: " . strlen($response) . " bytes\n";
        
        return [
            'response' => ['code' => $http_code],
            'body' => $response
        ];
    }
    echo "Created wp_remote_get function\n";
}

// Helper functions to work with responses
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return false;
        }
        return $response['response']['code'] ?? '';
    }
    echo "Created wp_remote_retrieve_response_code function\n";
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
    echo "Created wp_remote_retrieve_body function\n";
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
    echo "Created is_wp_error function\n";
}

try {
    echo "Creating plugin instance...\n";
    // Create an instance of the plugin
    $plugin = new Auto_Product_Import();
    echo "Successfully created plugin instance\n";
} catch (Throwable $e) {
    echo "ERROR creating plugin instance: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    die("Cannot proceed without plugin instance\n");
}

try {
    echo "Setting up reflection for extractProductImages method...\n";
    // Use reflection to make the private method accessible
    $extract_method = new ReflectionMethod(Auto_Product_Import::class, 'extractProductImages');
    $extract_method->setAccessible(true);
    echo "Successfully set up extract method reflection\n";
} catch (Throwable $e) {
    echo "ERROR setting up extract method reflection: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    die("Cannot proceed without extract method\n");
}

try {
    echo "Setting up reflection for html property...\n";
    // Set HTML property with reflection
    $html_property = new ReflectionProperty(Auto_Product_Import::class, 'html');
    $html_property->setAccessible(true);
    echo "Successfully set up html property reflection\n";
} catch (Throwable $e) {
    echo "ERROR setting up html property reflection: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    die("Cannot proceed without html property\n");
}

// Create a DOMDocument for testing
$dom = new DOMDocument();
echo "Created DOMDocument\n";

// Function to extract and display results
function test_extraction($url, $plugin, $extract_method, $html_property) {
    echo "\n==============================================\n";
    echo "TESTING URL: " . $url . "\n";
    echo "==============================================\n\n";
    
    // Fetch the HTML content from the URL
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        echo "ERROR: " . $response->get_error_message() . "\n";
        return;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        echo "ERROR: HTTP request failed with code " . $http_code . "\n";
        return;
    }
    
    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
        echo "ERROR: Empty HTML returned\n";
        return;
    }
    
    echo "Setting HTML property on plugin instance...\n";
    // Set the HTML property
    $html_property->setValue($plugin, $html);
    
    // Log HTML size for debug
    echo "HTML size: " . strlen($html) . " bytes\n\n";
    
    echo "Extracting product images...\n";
    // Extract product images
    $start_time = microtime(true);
    
    try {
        $images = $extract_method->invoke($plugin);
        
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        echo "Extraction completed in " . round($execution_time, 2) . " ms\n\n";
        
        // Display results
        echo "Number of product images found: " . count($images) . "\n\n";
        
        if (count($images) > 0) {
            echo "EXTRACTED IMAGES:\n";
            echo "---------------------------------------------\n";
            foreach ($images as $index => $image) {
                echo ($index + 1) . ". " . $image . "\n";
            }
        } else {
            echo "No product images were found.\n";
        }
    } catch (Throwable $e) {
        echo "ERROR during image extraction: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

echo "About to start testing URLs...\n";

// Test each URL
foreach ($test_urls as $url) {
    test_extraction($url, $plugin, $extract_method, $html_property);
}

echo "\nAll tests completed!\n"; 