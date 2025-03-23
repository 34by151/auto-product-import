<?php
// We don't have access to WP in our test environment, so let's simulate just the parts we need
class Auto_Product_Import {
    /**
     * Extract additional product information from the description HTML.
     *
     * @param string $description_html The product description HTML.
     * @param bool $debug Whether to enable debug mode.
     * @return array An array of additional product information.
     */
    public function get_additional_product_info($description_html, $debug = false) {
        return $this->extract_additional_product_info($description_html, $debug);
    }
    
    /**
     * Extract additional product information from the description HTML.
     *
     * @param string $description_html The product description HTML.
     * @param bool $debug Whether to enable debug mode.
     * @return array An array of additional product information.
     */
    private function extract_additional_product_info($description_html, $debug = false) {
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
                    if ($debug) {
                        echo "Scanning " . $li_nodes->length . " list items for field '$field'...<br>";
                    }
                    
                    foreach ($li_nodes as $li_node) {
                        $li_text = trim($li_node->textContent);
                        
                        // Debug - show list item text
                        if ($debug && ($field == 'Action' || $field == 'Barrel' || $field == 'Weight')) {
                            echo "Checking: '" . htmlspecialchars($li_text) . "' for field '$field'<br>";
                        }
                        
                        // Exact match at the beginning - remove the strict starting position ^ for more flexibility
                        if (stripos($li_text, $field) === 0) {
                            // Try different pattern formats
                            $patterns = array(
                                '/' . preg_quote($field, '/') . '\s*:(.+)$/i',  // Field: value
                                '/' . preg_quote($field, '/') . '\s*-(.+)$/i',  // Field - value
                                '/' . preg_quote($field, '/') . '\s+(.+)$/i'    // Field value
                            );
                            
                            foreach ($patterns as $pattern) {
                                if ($debug && ($field == 'Action' || $field == 'Barrel' || $field == 'Weight')) {
                                    echo "<strong>DEBUG - TRYING PATTERN:</strong> " . htmlspecialchars($pattern) . " on text: '" . htmlspecialchars($li_text) . "'<br>";
                                }
                                
                                if (preg_match($pattern, $li_text, $matches)) {
                                    $additional_info[$field] = trim($matches[1]);
                                    if ($debug) {
                                        echo "<strong>SUCCESS:</strong> Found '$field' via direct list item text extraction with pattern '$pattern': " . $additional_info[$field] . "<br>";
                                    }
                                    break 2; // Break out of both loops
                                }
                            }
                            
                            // If no pattern matched but field is at the beginning, try simple extraction
                            // Some list items might just have "Field: value" without specific formatting
                            $simple_parts = explode(':', $li_text, 2);
                            if (count($simple_parts) === 2) {
                                $potential_field = trim($simple_parts[0]);
                                $potential_value = trim($simple_parts[1]);
                                
                                if (strcasecmp($potential_field, $field) === 0) {
                                    $additional_info[$field] = $potential_value;
                                    if ($debug) {
                                        echo "<strong>SUCCESS:</strong> Found '$field' via simple split of list item: " . $additional_info[$field] . "<br>";
                                    }
                                    break;
                                }
                            }
                            
                            if ($debug && ($field == 'Action' || $field == 'Barrel' || $field == 'Weight')) {
                                echo "Field '$field' found at start but no patterns matched for: '" . htmlspecialchars($li_text) . "'<br>";
                            }
                        }
                        
                        // Simpler list item check - just look for field name followed by colon
                        // This handles cases where whitespace or other text might appear before the field
                        if (!isset($additional_info[$field])) {
                            $field_colon = $field . ':';
                            $pos = stripos($li_text, $field_colon);
                            if ($pos !== false) {
                                $potential_value = trim(substr($li_text, $pos + strlen($field_colon)));
                                if (!empty($potential_value)) {
                                    $additional_info[$field] = $potential_value;
                                    if ($debug) {
                                        echo "<strong>SUCCESS:</strong> Found '$field' via exact colon search in list item: " . $additional_info[$field] . "<br>";
                                    }
                                    break;
                                }
                            }
                        }
                        
                        // Check variations too
                        if (!isset($additional_info[$field]) && isset($field_variations[$field])) {
                            foreach ($field_variations[$field] as $variation) {
                                if (stripos($li_text, $variation) === 0) {
                                    // Try different pattern formats for variations
                                    $patterns = array(
                                        '/' . preg_quote($variation, '/') . '\s*:(.+)$/i',  // Variation: value
                                        '/' . preg_quote($variation, '/') . '\s*-(.+)$/i',  // Variation - value
                                        '/' . preg_quote($variation, '/') . '\s+(.+)$/i'    // Variation value
                                    );
                                    
                                    foreach ($patterns as $pattern) {
                                        if (preg_match($pattern, $li_text, $matches)) {
                                            $additional_info[$field] = trim($matches[1]);
                                            if ($debug) {
                                                echo "<strong>SUCCESS:</strong> Found '$field' via direct list item text extraction with variation '$variation' and pattern '$pattern': " . $additional_info[$field] . "<br>";
                                            }
                                            break 3; // Break out of all loops
                                        }
                                    }
                                    
                                    // If no pattern matched for variation, try simple extraction
                                    $simple_parts = explode(':', $li_text, 2);
                                    if (count($simple_parts) === 2) {
                                        $potential_field = trim($simple_parts[0]);
                                        $potential_value = trim($simple_parts[1]);
                                        
                                        if (strcasecmp($potential_field, $variation) === 0) {
                                            $additional_info[$field] = $potential_value;
                                            if ($debug) {
                                                echo "<strong>SUCCESS:</strong> Found '$field' via simple split of list item with variation '$variation': " . $additional_info[$field] . "<br>";
                                            }
                                            break 2;
                                        }
                                    }
                                }
                                
                                // Simpler check for variation with colon
                                if (!isset($additional_info[$field])) {
                                    $variation_colon = $variation . ':';
                                    $pos = stripos($li_text, $variation_colon);
                                    if ($pos !== false) {
                                        $potential_value = trim(substr($li_text, $pos + strlen($variation_colon)));
                                        if (!empty($potential_value)) {
                                            $additional_info[$field] = $potential_value;
                                            if ($debug) {
                                                echo "<strong>SUCCESS:</strong> Found '$field' via exact colon search in list item with variation '$variation': " . $additional_info[$field] . "<br>";
                                            }
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Method 6: Direct regex pattern matching on the original HTML
            // This avoids any potential issues with DOM parsing/normalization
            if (!isset($additional_info[$field])) {
                // Look for <li>Field: Value</li> pattern
                $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:\s*([^<]+)<\/li>/i';
                if (preg_match($pattern, $description_html, $matches)) {
                    $additional_info[$field] = trim($matches[1]);
                    if ($debug) {
                        echo "Found '$field' via direct HTML regex pattern: " . $additional_info[$field] . "<br>";
                    }
                    continue;
                }
                
                // Also try with different spacing
                $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*:([^<]+)<\/li>/i';
                if (preg_match($pattern, $description_html, $matches)) {
                    $additional_info[$field] = trim($matches[1]);
                    if ($debug) {
                        echo "Found '$field' via alternate HTML regex pattern: " . $additional_info[$field] . "<br>";
                    }
                    continue;
                }
                
                // Try variations
                if (isset($field_variations[$field])) {
                    foreach ($field_variations[$field] as $variation) {
                        $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*:\s*([^<]+)<\/li>/i';
                        if (preg_match($pattern, $description_html, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                echo "Found '$field' via direct HTML regex pattern with variation '$variation': " . $additional_info[$field] . "<br>";
                            }
                            break;
                        }
                        
                        // Also try with different spacing
                        $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*:([^<]+)<\/li>/i';
                        if (preg_match($pattern, $description_html, $matches)) {
                            $additional_info[$field] = trim($matches[1]);
                            if ($debug) {
                                echo "Found '$field' via alternate HTML regex pattern with variation '$variation': " . $additional_info[$field] . "<br>";
                            }
                            break;
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
        
        // Debug what we found at the end
        if ($debug) {
            echo "<br><hr><h3>FINAL DEBUG OUTPUT</h3>";
            if (empty($additional_info)) {
                echo "<p>No fields were extracted at all!</p>";
            } else {
                echo "<p>Successfully extracted " . count($additional_info) . " fields:</p>";
                echo "<ul>";
                foreach ($additional_info as $field => $value) {
                    echo "<li><strong>$field:</strong> " . htmlspecialchars($value) . "</li>";
                }
                echo "</ul>";
            }
            
            echo "<p>Fields we're still missing:</p>";
            echo "<ul>";
            foreach (array_diff($fields_to_extract, array_keys($additional_info)) as $missing) {
                echo "<li>$missing</li>";
            }
            echo "</ul>";
        }

        return $additional_info;
    }
}

// Create instance of the plugin
$plugin = new Auto_Product_Import();

// Test with the test description file
echo "<h1>Testing Plugin Extraction with Test Description</h1>";
$test_html = file_get_contents(__DIR__ . '/test-description.html');
if (!$test_html) {
    die("Could not read test-description.html");
}

// Enable debug mode
$additional_info = $plugin->get_additional_product_info($test_html, true);

// Display the results
echo "<pre>";
echo "Found " . count($additional_info) . " additional information fields:\n\n";
print_r($additional_info);
echo "</pre>";

// Display in a nice table
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Name</th><th>Value</th></tr>";
foreach ($additional_info as $name => $value) {
    echo "<tr><td>$name</td><td>$value</td></tr>";
}
echo "</table>";

// Add a status message
echo "<p>Extraction test completed!</p>";

// Add special debug for list items
echo "<h2>Debug List Items</h2>";
$dom = new DOMDocument();
libxml_use_internal_errors(true);
@$dom->loadHTML('<div>' . $test_html . '</div>');
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
        
        // Highlight specific fields
        foreach (array('Action', 'Barrel', 'Accessory Rail', 'Finish', 'Intended Use', 'Length', 'Safety', 'Sights', 'Trigger', 'Weight', 'Frame Material') as $field) {
            if (stripos($li_text, $field) === 0) {
                echo " - <span style='color: red;'>Contains '$field' at start!</span>";
                
                // Test direct regex for this field
                $pattern = '/' . preg_quote($field, '/') . '\s*:(.+)$/i';
                if (preg_match($pattern, $li_text, $matches)) {
                    echo " - <span style='color: green;'>Regex match found! Value: " . htmlspecialchars(trim($matches[1])) . "</span>";
                } else {
                    echo " - <span style='color: orange;'>NO regex match!!!</span>";
                    echo " - Pattern used: " . htmlspecialchars($pattern);
                }
            }
        }
    }
    echo "</ol>";
}

// Add special direct test of the field extraction
echo "<h2>Special Direct Field Extraction Test</h2>";
echo "<p>Testing extraction directly on specific list items:</p>";

// Direct test of field extraction for list items 13-22
$test_fields = array(
    "Action: Semi-Automatic with Blowback",
    "Barrel: Smooth Bore, 4.5 inches",
    "Accessory Rail: Picatinny Underbarrel",
    "Finish: Matte Black or Stainless Steel",
    "Intended Use: Target Shooting, Training, Plinking",
    "Length: 7.5 inches (19 cm)",
    "Safety: Manual, Ambidextrous",
    "Sights: Fixed Front, Adjustable Rear",
    "Trigger: Double/Single Action",
    "Weight: 1.8 lbs (approx. 816 grams)",
    "Frame Material: Polymer"
);

echo "<ul>";
foreach ($test_fields as $test_text) {
    echo "<li><strong>Testing:</strong> " . htmlspecialchars($test_text) . "</li>";
    
    // Extract field name
    $parts = explode(":", $test_text, 2);
    if (count($parts) === 2) {
        $field = trim($parts[0]);
        $expected_value = trim($parts[1]);
        
        // Test the regex directly
        $pattern = '/' . preg_quote($field, '/') . '\s*:(.+)$/i';
        if (preg_match($pattern, $test_text, $matches)) {
            echo "<span style='color: green;'>✓ Regex pattern works! Found: " . htmlspecialchars(trim($matches[1])) . "</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Regex pattern FAILED! Pattern: " . htmlspecialchars($pattern) . "</span><br>";
        }
        
        // Create a new instance to test just this field
        $mini_test = new Auto_Product_Import();
        $li_html = "<li>" . htmlspecialchars($test_text) . "</li>";
        $mini_result = $mini_test->get_additional_product_info($li_html, false);
        
        if (isset($mini_result[$field])) {
            echo "<span style='color: green;'>✓ Extract method works! Found: " . htmlspecialchars($mini_result[$field]) . "</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Extract method FAILED!</span><br>";
        }
    } else {
        echo "<span style='color: red;'>✗ Could not split into field:value</span><br>";
    }
}
echo "</ul>"; 