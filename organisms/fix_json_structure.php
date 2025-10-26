#!/usr/bin/env php
<?php
/**
 * Fix improperly nested JSON files in organism directories
 * 
 * Usage: php fix_json_structure.php <organism_name>
 *   or:  php fix_json_structure.php --all
 */

$organism_data = "/var/www/html/organisms";

if ($argc < 2) {
    echo "Usage: php fix_json_structure.php <organism_name>\n";
    echo "   or: php fix_json_structure.php --all\n";
    exit(1);
}

function fix_organism_json($organism_path) {
    $json_file = "$organism_path/organism.json";
    
    if (!file_exists($json_file)) {
        echo "Error: $json_file does not exist\n";
        return false;
    }
    
    $content = file_get_contents($json_file);
    
    // Try to parse
    $data = json_decode($content, true);
    $original_error = json_last_error();
    
    if ($original_error !== JSON_ERROR_NONE) {
        echo "Error parsing JSON: " . json_last_error_msg() . "\n";
        
        // Try to fix extra closing brace at the end
        $lines = explode("\n", $content);
        
        // Remove empty lines and extra closing braces from the end
        while (count($lines) > 0) {
            $last_line = trim($lines[count($lines) - 1]);
            if ($last_line === '' || $last_line === '}') {
                array_pop($lines);
                if ($last_line === '}') {
                    // Try parsing after removing this brace
                    $test_content = implode("\n", $lines);
                    $test_data = json_decode($test_content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $content = $test_content;
                        $data = $test_data;
                        echo "Fixed by removing extra closing brace\n";
                        break;
                    }
                }
            } else {
                break;
            }
        }
    }
    
    if (!$data) {
        echo "Error: Could not parse or fix JSON\n";
        return false;
    }
    
    // Check if data needs unwrapping
    $needs_fix = false;
    if (!isset($data['genus']) && !isset($data['common_name'])) {
        $keys = array_keys($data);
        if (count($keys) > 0 && is_array($data[$keys[0]])) {
            if (isset($data[$keys[0]]['genus']) || isset($data[$keys[0]]['common_name'])) {
                echo "Found improperly nested structure, unwrapping...\n";
                $data = $data[$keys[0]];
                $needs_fix = true;
            }
        }
    }
    
    if ($needs_fix || $original_error !== JSON_ERROR_NONE) {
        // Backup original
        $backup_file = "$json_file.backup_" . date('Y-m-d_His');
        copy($json_file, $backup_file);
        echo "Backed up to: $backup_file\n";
        
        // Write fixed JSON
        $json_output = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($json_file, $json_output . "\n");
        
        echo "✓ Fixed: $json_file\n";
        return true;
    } else {
        echo "✓ JSON is valid, no fix needed\n";
        return true;
    }
}

// Main execution
$organism_name = $argv[1];

if ($organism_name === '--all') {
    $dirs = glob("$organism_data/*", GLOB_ONLYDIR);
    $count = 0;
    
    foreach ($dirs as $dir) {
        $basename = basename($dir);
        if ($basename === '.' || $basename === '..') continue;
        
        echo "\n--- Processing $basename ---\n";
        if (fix_organism_json($dir)) {
            $count++;
        }
    }
    
    echo "\n=== Processed $count organism(s) ===\n";
} else {
    $organism_path = "$organism_data/$organism_name";
    
    if (!is_dir($organism_path)) {
        echo "Error: Organism directory $organism_path does not exist\n";
        exit(1);
    }
    
    if (fix_organism_json($organism_path)) {
        echo "\nFix completed successfully!\n";
    } else {
        exit(1);
    }
}
?>
