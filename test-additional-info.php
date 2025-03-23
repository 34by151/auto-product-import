<?php
ini_set('display_errors', 1);

// Simulate the class environment
class Test_Additional_Info {
    /**
     * Extract additional product information from the description HTML.
     *
     * @param string $description_html The product description HTML.
     * @param bool $debug Whether to enable debug mode.
     * @return array An array of additional product information.
     */
    public function extract_additional_product_info($description_html, $debug = false) {
        // Fields to extract (matching the ones shown in the screenshot)
        $fields_to_extract = array(
            'Caliber',
            'Power Source',
            'Velocity',
            'Magazine Capacity',
            'Action',
            'Frame Material',
            'Barrel',
            'Accessory Rail',
            'Finish',
            'Intended Use',
            'Length',
            'Safety',
            'Sights',
            'Trigger',
            'Weight'
        );
        
        // Field variations/aliases (some sites use different terms for the same field)
        $field_variations = array(
            'Magazine Capacity' => array('Capacity', 'Mag Capacity', 'Mag. Capacity', 'Magazine Size'),
            'Frame Material' => array('Frame', 'Material', 'Construction'),
            'Accessory Rail' => array('Rail', 'Rails', 'Accessory', 'Rail Type'),
            'Intended Use' => array('Use', 'Purpose', 'Application'),
            'Power Source' => array('Power', 'Power Type', 'Source'),
            'Barrel' => array('Barrel Length', 'Barrel Size', 'Barrel Details', 'Barrel Specs'),
            'Action' => array('Action Type', 'Operating System'),
            'Finish' => array('Finish Type', 'Surface Finish', 'Color'),
            'Sights' => array('Sight', 'Sight System', 'Sight Type'),
            'Trigger' => array('Trigger Type', 'Trigger System', 'Trigger Pull'),
            'Weight' => array('Gun Weight', 'Product Weight', 'Total Weight'),
            'Safety' => array('Safety Type', 'Safety System', 'Safety Features'),
            'Length' => array('Overall Length', 'Total Length', 'Gun Length')
        );
        
        $additional_info = array();
        
        // Create a DOMDocument to parse the HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<div>' . $description_html . '</div>');
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        if ($debug) {
            echo "<strong>Starting extraction of additional product information from HTML...</strong><br>";
            echo "<strong>HTML content length: " . strlen($description_html) . " characters</strong><br>";
        }
        
        // Method 1: Look for schema.org formatted data (as seen in the cleaned description)
        foreach ($fields_to_extract as $field) {
            // Skip if we already found this field
            if (isset($additional_info[$field])) {
                continue;
            }
            
            // Convert field name to lowercase for case-insensitive matching
            $field_lower = strtolower($field);
            
            // Try to find the field in schema.org formatted list items
            $nodes = $xpath->query('//li[.//span[translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "' . $field_lower . '"]]');
            
            if ($nodes && $nodes->length > 0) {
                $value_nodes = $xpath->query('.//span[@itemprop="value"]', $nodes->item(0));
                if ($value_nodes && $value_nodes->length > 0) {
                    $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                    if ($debug) {
                        echo "Found '$field' via schema.org format: " . $additional_info[$field] . "<br>";
                    }
                    continue;
                }
            }
            
            // Try variations of the field name
            if (isset($field_variations[$field])) {
                foreach ($field_variations[$field] as $variation) {
                    $variation_lower = strtolower($variation);
                    $nodes = $xpath->query('//li[.//span[translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "' . $variation_lower . '"]]');
                    
                    if ($nodes && $nodes->length > 0) {
                        $value_nodes = $xpath->query('.//span[@itemprop="value"]', $nodes->item(0));
                        if ($value_nodes && $value_nodes->length > 0) {
                            $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                            if ($debug) {
                                echo "Found '$field' via schema.org format (variation '$variation'): " . $additional_info[$field] . "<br>";
                            }
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
            
            // Method 2: Look for field name followed by value separator in any list item (like "Power Source: CO2 Pneumatic")
            $separators = array(':', '-', '=', '|');
            
            // First look in list items
            $li_nodes = $xpath->query('//li');
            if ($li_nodes && $li_nodes->length > 0) {
                foreach ($li_nodes as $li_node) {
                    $li_text = trim($li_node->textContent);
                    
                    // Direct match for exact field name with separator
                    foreach ($separators as $separator) {
                        // Try exact field name without word boundary for more flexible matching
                        $pattern = '/' . preg_quote($field, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^' . preg_quote($separator, '/') . '\n\r]+)/i';
                        if (preg_match($pattern, $li_text, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                echo "Found '$field' via direct list item matching with separator '$separator': " . $additional_info[$field] . "<br>";
                            }
                            break 2; // Break out of both loops
                        }
                    }
                    
                    // Try variations
                    if (isset($field_variations[$field])) {
                        foreach ($field_variations[$field] as $variation) {
                            foreach ($separators as $separator) {
                                // Try variation without word boundary for more flexible matching
                                $pattern = '/' . preg_quote($variation, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^' . preg_quote($separator, '/') . '\n\r]+)/i';
                                if (preg_match($pattern, $li_text, $matches)) {
                                    $additional_info[$field] = trim($matches[1]);
                                    if ($debug) {
                                        echo "Found '$field' via direct list item matching with variation '$variation' and separator '$separator': " . $additional_info[$field] . "<br>";
                                    }
                                    break 3; // Break out of all loops
                                }
                            }
                        }
                    }
                    
                    // If the list item only contains one separator, it might be a field:value pair
                    foreach ($separators as $separator) {
                        if (substr_count($li_text, $separator) === 1) {
                            $parts = explode($separator, $li_text);
                            if (count($parts) === 2) {
                                $potential_field = trim($parts[0]);
                                $potential_value = trim($parts[1]);
                                
                                // Check if the potential field matches our field or any of its variations
                                if (strcasecmp($potential_field, $field) === 0) {
                                    $additional_info[$field] = $potential_value;
                                    if ($debug) {
                                        echo "Found '$field' via simple list item separator '$separator': " . $additional_info[$field] . "<br>";
                                    }
                                    break 2; // Break out of both loops
                                }
                                
                                // Check variations
                                if (isset($field_variations[$field])) {
                                    foreach ($field_variations[$field] as $variation) {
                                        if (strcasecmp($potential_field, $variation) === 0) {
                                            $additional_info[$field] = $potential_value;
                                            if ($debug) {
                                                echo "Found '$field' via simple list item with variation '$variation' and separator '$separator': " . $additional_info[$field] . "<br>";
                                            }
                                            break 4; // Break out of all loops
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Regular pattern matching in whole text if list item matching didn't work
            if (!isset($additional_info[$field])) {
                foreach ($separators as $separator) {
                    // Removed word boundary (\b) for more flexible matching
                    $pattern = '/' . preg_quote($field, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<\n\r' . preg_quote($separator, '/') . ']+)/i';
                    if (preg_match($pattern, $description_html, $matches)) {
                        $additional_info[$field] = trim($matches[1]);
                        if ($debug) {
                            echo "Found '$field' via whole text pattern matching with separator '$separator': " . $additional_info[$field] . "<br>";
                        }
                        break;
                    }
                    
                    // Try variations
                    if (isset($field_variations[$field])) {
                        foreach ($field_variations[$field] as $variation) {
                            // Removed word boundary (\b) for more flexible matching
                            $pattern = '/' . preg_quote($variation, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<\n\r' . preg_quote($separator, '/') . ']+)/i';
                            if (preg_match($pattern, $description_html, $matches)) {
                                $additional_info[$field] = trim($matches[1]);
                                if ($debug) {
                                    echo "Found '$field' via whole text pattern matching with variation '$variation' and separator '$separator': " . $additional_info[$field] . "<br>";
                                }
                                break 3; // Break out of all three loops
                            }
                        }
                    }
                }
            }
            
            // Method 3: Look for table rows with field name in first column
            $nodes = $xpath->query('//tr[./td[1][contains(translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $field_lower . '")]]');
            if ($nodes && $nodes->length > 0) {
                $value_nodes = $xpath->query('./td[2]', $nodes->item(0));
                if ($value_nodes && $value_nodes->length > 0) {
                    $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                    if ($debug) {
                        echo "Found '$field' via table row: " . $additional_info[$field] . "<br>";
                    }
                    continue;
                }
            }
            
            // Try variations for table rows
            if (isset($field_variations[$field])) {
                foreach ($field_variations[$field] as $variation) {
                    $variation_lower = strtolower($variation);
                    $nodes = $xpath->query('//tr[./td[1][contains(translate(normalize-space(text()), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "' . $variation_lower . '")]]');
                    if ($nodes && $nodes->length > 0) {
                        $value_nodes = $xpath->query('./td[2]', $nodes->item(0));
                        if ($value_nodes && $value_nodes->length > 0) {
                            $additional_info[$field] = trim($value_nodes->item(0)->textContent);
                            if ($debug) {
                                echo "Found '$field' via table row with variation '$variation': " . $additional_info[$field] . "<br>";
                            }
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
            
            // Method 4: Special pattern match for common pattern in our test files
            // Look for specific pattern: <li>Field: Value</li>
            if (!isset($additional_info[$field])) {
                $simple_pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:\s*([^<]+)<\/li>/i';
                if (preg_match($simple_pattern, $description_html, $matches)) {
                    $additional_info[$field] = trim($matches[1]);
                    if ($debug) {
                        echo "Found '$field' via special list item pattern matching: " . $additional_info[$field] . "<br>";
                    }
                    continue;
                }
                
                // Try variations
                if (isset($field_variations[$field])) {
                    foreach ($field_variations[$field] as $variation) {
                        $simple_pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*:\s*([^<]+)<\/li>/i';
                        if (preg_match($simple_pattern, $description_html, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                echo "Found '$field' via special list item pattern matching with variation '$variation': " . $additional_info[$field] . "<br>";
                            }
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
            
            // Method 5: Direct list item extraction - scan all list items for exact field matches
            if (!isset($additional_info[$field])) {
                // Find all list items
                $li_nodes = $xpath->query('//li');
                if ($li_nodes && $li_nodes->length > 0) {
                    foreach ($li_nodes as $li_node) {
                        $li_text = trim($li_node->textContent);
                        
                        // Check each list item for simple format: "Field Name: Value"
                        if (preg_match('/^' . preg_quote($field, '/') . '\s*:(.+)$/i', $li_text, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                echo "Found '$field' via direct list item text extraction: " . $additional_info[$field] . "<br>";
                            }
                            break;
                        }
                        
                        // Check variations too
                        if (isset($field_variations[$field])) {
                            foreach ($field_variations[$field] as $variation) {
                                if (preg_match('/^' . preg_quote($variation, '/') . '\s*:(.+)$/i', $li_text, $matches)) {
                                    $additional_info[$field] = trim($matches[1]);
                                    if ($debug) {
                                        echo "Found '$field' via direct list item text extraction with variation '$variation': " . $additional_info[$field] . "<br>";
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($debug) {
            echo "<br><strong>Completed extraction of additional product information.</strong><br>";
            echo "Found " . count($additional_info) . " fields: " . implode(", ", array_keys($additional_info)) . "<br>";
            echo "Missing " . (count($fields_to_extract) - count($additional_info)) . " fields: " . 
                implode(", ", array_diff($fields_to_extract, array_keys($additional_info))) . "<br><br>";
        }
        
        return $additional_info;
    }
}

// Create an instance of the test class
$test = new Test_Additional_Info();

// Test 1: Original cleaned description file
echo "<h1>Test 1: Original Cleaned Description</h1>";
$description_html = file_get_contents(__DIR__ . '/description-cleaned.html');
if (!$description_html) {
    die("Could not read description-cleaned.html");
}

$additional_info = $test->extract_additional_product_info($description_html, true);

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

// Add HTML preview for debugging
echo "<h2>HTML Content for Debugging (First 500 chars)</h2>";
echo "<pre>";
$html_preview = htmlspecialchars(substr($description_html, 0, 500));
echo "$html_preview\n...\n";
echo "</pre>";

// Test 2: Enhanced test description with more patterns
echo "<h1>Test 2: Enhanced Test Description</h1>";
$test_description_html = file_get_contents(__DIR__ . '/test-description.html');
if (!$test_description_html) {
    die("Could not read test-description.html");
}

$additional_info2 = $test->extract_additional_product_info($test_description_html, true);

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

// Add HTML preview for debugging
echo "<h2>HTML Content for Debugging (Full)</h2>";
echo "<pre>";
$html_preview = htmlspecialchars($test_description_html);
echo "$html_preview";
echo "</pre>";

// Add special debug for list items
echo "<h2>Debug List Items</h2>";
$dom = new DOMDocument();
libxml_use_internal_errors(true);
@$dom->loadHTML('<div>' . $test_description_html . '</div>');
libxml_clear_errors();
$xpath = new DOMXPath($dom);

// Get all list items
$li_nodes = $xpath->query('//li');
if ($li_nodes && $li_nodes->length > 0) {
    echo "<p>Found " . $li_nodes->length . " list items</p>";
    echo "<ol>";
    foreach ($li_nodes as $index => $li_node) {
        $li_text = trim($li_node->textContent);
        echo "<li><strong>List item #" . ($index + 1) . ":</strong> " . htmlspecialchars($li_text) . "</li>";
        
        // Check for Action, Barrel, etc. fields
        foreach (array('Action', 'Barrel', 'Accessory Rail', 'Finish', 'Intended Use', 'Length', 'Safety', 'Sights', 'Trigger', 'Weight') as $field) {
            if (stripos($li_text, $field) === 0) {
                echo " - <span style='color: red;'>Contains '$field' at start!</span>";
            }
        }
    }
    echo "</ol>";
}

echo "<p>Test completed successfully!</p>"; 