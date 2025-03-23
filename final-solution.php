<?php
/**
 * Final solution for product information extraction.
 * This demonstrates the recommended approach to extract additional product information from HTML descriptions.
 */

/**
 * Extract additional product information from the description HTML.
 *
 * @param string $description_html The product description HTML.
 * @param bool $debug Whether to enable debug mode.
 * @return array An array of additional product information.
 */
function extract_additional_product_info($description_html, $debug = false) {
    // Fields to extract
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
    
    if ($debug) {
        echo "<strong>Starting extraction of additional product information from HTML...</strong><br>";
        echo "<strong>HTML content length: " . strlen($description_html) . " characters</strong><br>";
    }
    
    // Step 1: Look for schema.org formatted data with DOMDocument (for structured data)
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<div>' . $description_html . '</div>');
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
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
                        break;
                    }
                }
            }
        }
    }
    
    // Step 2: Direct regex pattern matching on the HTML (this works for both structured and unstructured data)
    foreach ($fields_to_extract as $field) {
        // Skip if we already found this field
        if (isset($additional_info[$field])) {
            continue;
        }
        
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
        
        // Try direct text extraction with any separator
        $separators = array(':', '-', '–', '=', '|');
        foreach ($separators as $separator) {
            $pattern = '/<li[^>]*>\s*' . preg_quote($field, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<]+)<\/li>/i';
            if (preg_match($pattern, $description_html, $matches)) {
                $additional_info[$field] = trim($matches[1]);
                if ($debug) {
                    echo "Found '$field' via direct HTML regex with separator '$separator': " . $additional_info[$field] . "<br>";
                }
                break;
            }
        }
        
        // Try variations with direct regex pattern
        if (!isset($additional_info[$field]) && isset($field_variations[$field])) {
            foreach ($field_variations[$field] as $variation) {
                // Look for <li>Variation: Value</li> pattern
                $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*:\s*([^<]+)<\/li>/i';
                if (preg_match($pattern, $description_html, $matches)) {
                    $additional_info[$field] = trim($matches[1]);
                    if ($debug) {
                        echo "Found '$field' via direct HTML regex pattern with variation '$variation': " . $additional_info[$field] . "<br>";
                    }
                    break;
                }
                
                // Try other separators for variations
                foreach ($separators as $separator) {
                    $pattern = '/<li[^>]*>\s*' . preg_quote($variation, '/') . '\s*' . preg_quote($separator, '/') . '\s*([^<]+)<\/li>/i';
                    if (preg_match($pattern, $description_html, $matches)) {
                        $additional_info[$field] = trim($matches[1]);
                        if ($debug) {
                            echo "Found '$field' via direct HTML regex with variation '$variation' and separator '$separator': " . $additional_info[$field] . "<br>";
                        }
                        break 2;
                    }
                }
            }
        }
    }
    
    // Step 3: Look for table-based data
    foreach ($fields_to_extract as $field) {
        // Skip if we already found this field
        if (isset($additional_info[$field])) {
            continue;
        }
        
        // Convert field name to lowercase for case-insensitive matching
        $field_lower = strtolower($field);
        
        // Look for table rows with field name in first column
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
                        break;
                    }
                }
            }
        }
    }
    
    // Step 4: Last resort - look through all list items for any text that matches our field patterns
    if (count($additional_info) < count($fields_to_extract)) {
        $li_nodes = $xpath->query('//li');
        if ($li_nodes && $li_nodes->length > 0) {
            foreach ($li_nodes as $li_node) {
                $li_text = trim($li_node->textContent);
                
                foreach ($fields_to_extract as $field) {
                    // Skip if we already found this field
                    if (isset($additional_info[$field])) {
                        continue;
                    }
                    
                    // Direct match for field at start of text
                    if (stripos($li_text, $field . ':') === 0) {
                        $parts = explode(':', $li_text, 2);
                        if (count($parts) === 2) {
                            $additional_info[$field] = trim($parts[1]);
                            if ($debug) {
                                echo "Found '$field' via direct list item text extraction: " . $additional_info[$field] . "<br>";
                            }
                        }
                    }
                    
                    // Check variations too
                    if (!isset($additional_info[$field]) && isset($field_variations[$field])) {
                        foreach ($field_variations[$field] as $variation) {
                            if (stripos($li_text, $variation . ':') === 0) {
                                $parts = explode(':', $li_text, 2);
                                if (count($parts) === 2) {
                                    $additional_info[$field] = trim($parts[1]);
                                    if ($debug) {
                                        echo "Found '$field' via direct list item text extraction with variation '$variation': " . $additional_info[$field] . "<br>";
                                    }
                                    break;
                                }
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

// Test the function with our test HTML
$test_html = file_get_contents(__DIR__ . '/test-description.html');
if (!$test_html) {
    die("Could not read test-description.html");
}

// Enable debug mode to see the extraction process
$additional_info = extract_additional_product_info($test_html, true);

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