<?php
/**
 * Function Registry JSON Generator
 * 
 * Generates a clean JSON file containing all PHP functions from the codebase.
 * This JSON can be rendered in various ways without regenerating the registry.
 * 
 * Usage: php tools/generate_registry_json.php
 * Output: docs/function_registry.json
 */

require_once __DIR__ . '/../includes/config_init.php';
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$registry = [];

// Directories to scan
$scanDirs = [
    __DIR__ . '/../lib',
    __DIR__ . '/../tools',
    __DIR__ . '/../admin',
    __DIR__ . '/..'
];

// File patterns to exclude
$excludePatterns = ['.backup', 'generate_registry', 'function_registry'];

/**
 * Extract comment block before a function
 */
function extractCommentBlock($content, $funcPos) {
    $beforeFunc = substr($content, 0, $funcPos);
    $lines = array_reverse(explode("\n", $beforeFunc));
    $commentLines = [];
    $inComment = false;
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        if (preg_match('/\*\/$/', $trimmed)) {
            $inComment = true;
            $commentLines[] = $trimmed;
        } elseif ($inComment) {
            $commentLines[] = $trimmed;
            if (preg_match('/\/\*/', $trimmed)) {
                break;
            }
        } elseif (!empty($trimmed) && $trimmed !== '*' && !preg_match('/^\*/', $trimmed)) {
            break;
        }
    }
    
    $comment = implode("\n", array_reverse($commentLines));
    return !empty($comment) ? trim($comment) : '';
}

/**
 * Extract PHP functions from a PHP file
 */
function extractFunctions($filePath) {
    $content = file_get_contents($filePath);
    $functions = [];
    
    // Split on php tags to isolate PHP code blocks
    $phpTag = '<' . '?php';
    $closeTag = '?' . '>';
    $parts = preg_split('/(' . preg_quote($phpTag) . '|' . preg_quote($closeTag) . ')/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $inPhp = false;
    $phpContent = '';
    
    foreach ($parts as $part) {
        if (strpos($part, $phpTag) !== false) {
            $inPhp = true;
        } elseif (strpos($part, $closeTag) !== false) {
            $inPhp = false;
        } elseif ($inPhp) {
            $phpContent .= $part;
        }
    }
    
    // Match PHP function definitions
    if (preg_match_all('/^\s*(?:public\s+)?(?:static\s+)?function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/m', $phpContent, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $idx => $match) {
            $funcName = $matches[1][$idx][0];
            $startPos = $match[1];
            $lineNum = substr_count($content, "\n", 0, strpos($content, $funcName)) + 1;
            
            // Extract comment
            $comment = extractCommentBlock($phpContent, $startPos);
            
            // Extract function code
            $braceCount = 0;
            $inFunction = false;
            $functionCode = '';
            $chars = str_split($phpContent);
            
            for ($i = $startPos; $i < strlen($phpContent); $i++) {
                $char = $chars[$i];
                $functionCode .= $char;
                
                if ($char === '{') {
                    $braceCount++;
                    $inFunction = true;
                } elseif ($char === '}') {
                    $braceCount--;
                    if ($inFunction && $braceCount === 0) {
                        break;
                    }
                }
            }
            
            $functions[] = [
                'name' => $funcName,
                'line' => $lineNum,
                'comment' => $comment,
                'code' => trim($functionCode)
            ];
        }
    }
    
    return $functions;
}

/**
 * Find function usages in codebase
 */
function findFunctionUsages($funcName, $scanDirs, $definitionFile) {
    $usages = [];
    $seen = [];
    $searchPattern = '/\b' . preg_quote($funcName, '/') . '\s*\(/';
    $excludeDirs = ['docs', 'logs', 'notes', 'not_used', '.git'];
    
    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            
            $filePath = $file->getRealPath();
            
            // Skip excluded directories
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($filePath, DIRECTORY_SEPARATOR . $excludeDir . DIRECTORY_SEPARATOR) !== false || 
                    strpos($filePath, DIRECTORY_SEPARATOR . $excludeDir) === strlen($filePath) - strlen(DIRECTORY_SEPARATOR . $excludeDir)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            if (strpos($filePath, 'function_registry') !== false || strpos($filePath, 'generate_registry') !== false) continue;
            
            $content = file_get_contents($filePath);
            
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    
                    $lines = explode("\n", $content);
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    if (preg_match('/^\s*(\/\/|#|\*)/', $contextLine) || preg_match('/\/\*.*\*\//', $contextLine)) {
                        continue;
                    }
                    
                    if (preg_match('/^\s*(?:public\s+)?(?:static\s+)?function\s+' . preg_quote($funcName, '/') . '\s*\(/', $contextLine)) {
                        continue;
                    }
                    
                    $relativeFilePath = str_replace(__DIR__ . '/../', '', $filePath);
                    $usageKey = $relativeFilePath . ':' . $lineNum;
                    
                    if (isset($seen[$usageKey])) {
                        continue;
                    }
                    $seen[$usageKey] = true;
                    
                    $usages[] = [
                        'file' => $relativeFilePath,
                        'line' => $lineNum,
                        'context' => $contextLine
                    ];
                }
            }
        }
    }
    
    return $usages;
}

// Scan all directories
echo "🔍 Scanning directories...\n";
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $fileName = basename($file);
        
        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if (strpos($fileName, $pattern) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;
        
        $relativePath = str_replace(__DIR__ . '/../', '', $file);
        $functions = extractFunctions($file);
        
        if (!empty($functions)) {
            $registry[$relativePath] = $functions;
        }
    }
}

// Find duplicates and check for unused functions
$funcMap = [];
$duplicates = [];
$totalFuncs = 0;

foreach ($registry as $file => $functions) {
    foreach ($functions as $func) {
        $totalFuncs++;
        $name = $func['name'];
        
        if (isset($funcMap[$name])) {
            if (!isset($duplicates[$name])) {
                $duplicates[$name] = [$funcMap[$name]];
            }
            $duplicates[$name][] = ['file' => $file, 'line' => $func['line']];
        } else {
            $funcMap[$name] = ['file' => $file, 'line' => $func['line']];
        }
    }
}

// Build registry data structure
$registryData = [
    'metadata' => [
        'generated' => date('Y-m-d H:i:s'),
        'totalFunctions' => $totalFuncs,
        'totalFiles' => count($registry),
        'duplicates' => count($duplicates),
    ],
    'files' => [],
    'unused' => [],
];

// Build files array and find unused functions
foreach ($registry as $file => $functions) {
    $fileEntry = [
        'name' => $file,
        'count' => count($functions),
        'functions' => []
    ];
    
    foreach ($functions as $func) {
        $usages = findFunctionUsages($func['name'], $scanDirs, $file);
        
        $funcEntry = [
            'name' => $func['name'],
            'line' => $func['line'],
            'comment' => $func['comment'],
            'code' => $func['code'],
            'usageCount' => count($usages),
            'usages' => $usages
        ];
        
        $fileEntry['functions'][] = $funcEntry;
        
        // Track unused functions
        if (empty($usages)) {
            $registryData['unused'][] = [
                'name' => $func['name'],
                'file' => $file,
                'line' => $func['line']
            ];
        }
    }
    
    ksort($fileEntry['functions'], SORT_NATURAL);
    $registryData['files'][] = $fileEntry;
}

// Sort files by name
usort($registryData['files'], function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Save JSON
$jsonFile = $docs_path . '/function_registry.json';
@mkdir(dirname($jsonFile), 0755, true);

$json = json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonFile, $json)) {
    echo "\n✅ Registry generated successfully!\n";
    echo "   File: " . str_replace(__DIR__ . '/../', '', $jsonFile) . "\n";
    echo "   Total Functions: " . $totalFuncs . "\n";
    echo "   Files Scanned: " . count($registry) . "\n";
    echo "   Unused Functions: " . count($registryData['unused']) . "\n";
    
    if (!empty($duplicates)) {
        echo "\n⚠️  Duplicate Functions Found: " . count($duplicates) . "\n";
    }
} else {
    echo "\n❌ Error writing JSON file\n";
    exit(1);
}
?>
