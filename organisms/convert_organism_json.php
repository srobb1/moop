#!/usr/bin/env php
<?php
/**
 * Script to convert organism.json files to use structured text format
 * Converts from HTML <p> tags to structured array format like group descriptions
 * 
 * Usage: php convert_organism_json.php <organism_name>
 *   or:  php convert_organism_json.php --all
 */

$organism_data = "/var/www/html/moop/organisms";

if ($argc < 2) {
    echo "Usage: php convert_organism_json.php <organism_name>\n";
    echo "   or: php convert_organism_json.php --all\n";
    exit(1);
}

function convert_html_to_structured($html_text) {
    // Remove outer HTML if present
    $html_text = trim($html_text);
    
    // Split by <p> tags
    preg_match_all('/<p>(.*?)<\/p>/s', $html_text, $matches);
    
    $paragraphs = [];
    foreach ($matches[1] as $p_content) {
        $paragraphs[] = trim($p_content);
    }
    
    return $paragraphs;
}

function convert_organism_json($organism_path) {
    $json_file = "$organism_path/organism.json";
    
    if (!file_exists($json_file)) {
        echo "Error: $json_file does not exist\n";
        return false;
    }
    
    $data = json_decode(file_get_contents($json_file), true);
    
    if (!$data) {
        echo "Error: Could not parse JSON in $json_file\n";
        return false;
    }
    
    // Backup original file
    $backup_file = "$json_file.backup_" . date('Y-m-d_His');
    copy($json_file, $backup_file);
    echo "Backed up to: $backup_file\n";
    
    // Convert text field if it exists and is HTML
    if (isset($data['text']) && strpos($data['text'], '<p>') !== false) {
        $paragraphs = convert_html_to_structured($data['text']);
        
        // Create new structure similar to group descriptions
        $data['text_html'] = [
            'p' => $paragraphs
        ];
        
        // Keep old text field but rename it
        $data['text_html_old'] = $data['text'];
        unset($data['text']);
    }
    
    // Convert image to images array format
    if (isset($data['image']) && !isset($data['images'])) {
        $data['images'] = [[
            'file' => $data['image'],
            'caption' => $data['image_src'] ?? ''
        ]];
        
        // Keep old fields but mark them
        $data['image_old'] = $data['image'];
        unset($data['image']);
        
        if (isset($data['image_src'])) {
            $data['image_src_old'] = $data['image_src'];
            unset($data['image_src']);
        }
    }
    
    // Write converted JSON with pretty printing
    $json_output = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($json_file, $json_output . "\n");
    
    echo "Converted: $json_file\n";
    return true;
}

// Main execution
$organism_name = $argv[1];

if ($organism_name === '--all') {
    // Process all organism directories
    $dirs = glob("$organism_data/*", GLOB_ONLYDIR);
    $count = 0;
    
    foreach ($dirs as $dir) {
        $basename = basename($dir);
        if ($basename === '.' || $basename === '..') continue;
        
        echo "\n--- Processing $basename ---\n";
        if (convert_organism_json($dir)) {
            $count++;
        }
    }
    
    echo "\n=== Converted $count organism(s) ===\n";
} else {
    // Process single organism
    $organism_path = "$organism_data/$organism_name";
    
    if (!is_dir($organism_path)) {
        echo "Error: Organism directory $organism_path does not exist\n";
        exit(1);
    }
    
    if (convert_organism_json($organism_path)) {
        echo "\nConversion completed successfully!\n";
        echo "Review the changes and update organism_display.php to use the new format.\n";
    } else {
        exit(1);
    }
}
?>
