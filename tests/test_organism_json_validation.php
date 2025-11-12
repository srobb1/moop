<?php
/**
 * Test Suite: organism.json Validation
 * Tests the validateOrganismJson() function from moop_functions.php
 * 
 * This test suite verifies that organism.json files are:
 * 1. Present in the organism directory
 * 2. In correct JSON format
 * 3. Containing all required fields
 */

// Setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../tools/moop_functions.php';

class OrganismJsonValidationTest {
    private $test_dir = '/tmp/organism_json_tests';
    private $passed = 0;
    private $failed = 0;
    
    public function __construct() {
        if (!is_dir($this->test_dir)) {
            mkdir($this->test_dir, 0777, true);
        }
    }
    
    public function cleanup() {
        $this->removeDir($this->test_dir);
    }
    
    private function removeDir($dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        $this->removeDir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    public function run() {
        echo "=== Organism JSON Validation Tests ===\n\n";
        
        $this->testMissingFile();
        $this->testInvalidJson();
        $this->testMissingRequiredFields();
        $this->testValidJsonWithAllFields();
        $this->testUnreadableFile();
        $this->testWrappedJson();
        
        echo "\n=== Test Summary ===\n";
        echo "Passed: $this->passed\n";
        echo "Failed: $this->failed\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        return $this->failed === 0;
    }
    
    private function testMissingFile() {
        echo "Test 1: Missing organism.json file\n";
        $path = $this->test_dir . '/missing.json';
        $result = validateOrganismJson($path);
        
        if (!$result['exists'] && !empty($result['errors'])) {
            echo "✓ PASS: Correctly detects missing file\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Should detect missing file\n";
            $this->failed++;
        }
        echo "\n";
    }
    
    private function testInvalidJson() {
        echo "Test 2: Invalid JSON format\n";
        $path = $this->test_dir . '/invalid.json';
        file_put_contents($path, '{this is not valid json}');
        
        $result = validateOrganismJson($path);
        
        if ($result['exists'] && !$result['valid_json'] && !empty($result['errors'])) {
            echo "✓ PASS: Correctly identifies invalid JSON\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Should identify invalid JSON\n";
            $this->failed++;
        }
        echo "\n";
    }
    
    private function testMissingRequiredFields() {
        echo "Test 3: Missing required fields\n";
        $path = $this->test_dir . '/missing_fields.json';
        $data = [
            'genus' => 'Anoura',
            'species' => 'caudifer'
            // Missing: common_name, taxon_id
        ];
        file_put_contents($path, json_encode($data));
        
        $result = validateOrganismJson($path);
        
        if ($result['valid_json'] && !$result['has_required_fields'] && 
            in_array('common_name', $result['missing_fields']) && 
            in_array('taxon_id', $result['missing_fields'])) {
            echo "✓ PASS: Correctly identifies missing fields\n";
            echo "  Missing: " . implode(', ', $result['missing_fields']) . "\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Should identify missing required fields\n";
            $this->failed++;
        }
        echo "\n";
    }
    
    private function testValidJsonWithAllFields() {
        echo "Test 4: Valid JSON with all required fields\n";
        $path = $this->test_dir . '/valid.json';
        $data = [
            'genus' => 'Anoura',
            'species' => 'caudifer',
            'common_name' => 'Tailed Tailless Bat',
            'taxon_id' => '27642',
            'text_src' => 'https://example.com'
        ];
        file_put_contents($path, json_encode($data));
        
        $result = validateOrganismJson($path);
        
        if ($result['exists'] && $result['readable'] && $result['valid_json'] && 
            $result['has_required_fields'] && empty($result['errors'])) {
            echo "✓ PASS: Valid JSON with required fields passes all checks\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Valid JSON should pass all checks\n";
            var_dump($result);
            $this->failed++;
        }
        echo "\n";
    }
    
    private function testUnreadableFile() {
        echo "Test 5: Unreadable file (permission denied)\n";
        $path = $this->test_dir . '/unreadable.json';
        $data = [
            'genus' => 'Test',
            'species' => 'test',
            'common_name' => 'Test Organism',
            'taxon_id' => '12345'
        ];
        file_put_contents($path, json_encode($data));
        chmod($path, 0000);
        
        $result = validateOrganismJson($path);
        
        if ($result['exists'] && !$result['readable'] && !empty($result['errors'])) {
            echo "✓ PASS: Correctly detects unreadable file\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Should detect unreadable file\n";
            $this->failed++;
        }
        chmod($path, 0644); // Reset permissions for cleanup
        echo "\n";
    }
    
    private function testWrappedJson() {
        echo "Test 6: Wrapped JSON (single-level wrapping)\n";
        $path = $this->test_dir . '/wrapped.json';
        // JSON wrapped in an outer object with organism name as key
        $data = [
            'organism_name' => [
                'genus' => 'Anoura',
                'species' => 'caudifer',
                'common_name' => 'Tailed Tailless Bat',
                'taxon_id' => '27642'
            ]
        ];
        file_put_contents($path, json_encode($data));
        
        $result = validateOrganismJson($path);
        
        if ($result['valid_json'] && $result['has_required_fields'] && empty($result['errors'])) {
            echo "✓ PASS: Correctly handles wrapped JSON\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: Should handle wrapped JSON\n";
            var_dump($result);
            $this->failed++;
        }
        echo "\n";
    }
}

// Run tests
$test = new OrganismJsonValidationTest();
$success = $test->run();
$test->cleanup();

exit($success ? 0 : 1);
?>
