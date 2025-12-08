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
 * Determine category based on filename and function name
 */
function determineCategory($filename, $funcName) {
    $filename = strtolower($filename);
    $funcName = strtolower($funcName);
    
    // Database functions
    if (strpos($filename, 'database') !== false || strpos($filename, 'queries') !== false || 
        strpos($funcName, 'fetch') !== false || strpos($funcName, 'query') !== false ||
        strpos($funcName, 'db') !== false) {
        return 'database';
    }
    
    // Filesystem functions
    if (strpos($filename, 'filesystem') !== false || strpos($filename, 'file') !== false ||
        strpos($funcName, 'file') !== false || strpos($funcName, 'directory') !== false ||
        strpos($funcName, 'path') !== false) {
        return 'filesystem';
    }
    
    // Validation/Security
    if (strpos($funcName, 'validate') !== false || strpos($funcName, 'sanitize') !== false ||
        strpos($funcName, 'escape') !== false || strpos($funcName, 'check') !== false ||
        strpos($funcName, 'permission') !== false) {
        return 'validation';
    }
    
    // Configuration
    if (strpos($filename, 'config') !== false || strpos($funcName, 'config') !== false) {
        return 'configuration';
    }
    
    // Access control
    if (strpos($filename, 'access') !== false || strpos($funcName, 'access') !== false ||
        strpos($funcName, 'auth') !== false) {
        return 'security';
    }
    
    // Data manipulation
    if (strpos($funcName, 'parse') !== false || strpos($funcName, 'extract') !== false ||
        strpos($funcName, 'transform') !== false || strpos($funcName, 'convert') !== false) {
        return 'data-processing';
    }
    
    // Organism/Biology related
    if (strpos($filename, 'organism') !== false || strpos($funcName, 'organism') !== false ||
        strpos($funcName, 'assembly') !== false || strpos($funcName, 'genome') !== false) {
        return 'organisms';
    }
    
    // BLAST/Tools
    if (strpos($filename, 'blast') !== false || strpos($funcName, 'blast') !== false) {
        return 'tools-blast';
    }
    
    // Search/Indexing
    if (strpos($funcName, 'search') !== false || strpos($funcName, 'index') !== false) {
        return 'search';
    }
    
    // UI/Display
    if (strpos($funcName, 'render') !== false || strpos($funcName, 'display') !== false ||
        strpos($funcName, 'html') !== false || strpos($funcName, 'format') !== false) {
        return 'ui';
    }
    
    return 'utility';
}

/**
 * Determine tags based on function characteristics
 */
function determineTags($comment, $code, $funcName) {
    $tags = [];
    
    // Check if function modifies data
    if (preg_match('/file_put_contents|fwrite|INSERT|UPDATE|DELETE|unlink|rename|mkdir|chmod/i', $code)) {
        $tags[] = 'mutation';
    } else {
        $tags[] = 'readonly';
    }
    
    // Check if it handles errors
    if (preg_match('/try\s*\{|catch|throw|error|exception/i', $code)) {
        $tags[] = 'error-handling';
    }
    
    // Check if it uses database
    if (preg_match('/\$db|\$pdo|PDO|mysqli|query|fetch|execute/i', $code)) {
        $tags[] = 'database-dependent';
    }
    
    // Check if it does file I/O
    if (preg_match('/file_get|file_put|fopen|fread|fwrite|file_exists|is_dir|glob|scandir|readdir/i', $code)) {
        $tags[] = 'file-io';
    }
    
    // Check if it's a helper/utility
    if (strpos($comment, 'helper') !== false || strpos($comment, 'utility') !== false) {
        $tags[] = 'helper';
    }
    
    // Check for security operations
    if (preg_match('/password|hash|encrypt|decrypt|token|auth|permission|privilege/i', $code)) {
        $tags[] = 'security-related';
    }
    
    // Check for loops (performance concern)
    if (preg_match('/foreach|for\s*\(|while\s*\(/i', $code)) {
        $tags[] = 'loops';
    }
    
    // Check for recursion
    if (preg_match('/\b' . preg_quote($funcName) . '\s*\(/', $code)) {
        $tags[] = 'recursive';
    }
    
    return array_values(array_unique($tags));
}

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
 * Parse PHPDoc comment to extract parameter and return type information
 */
function parsePhpDoc($comment) {
    $params = [];
    $returnType = 'void';
    $returnDescription = '';
    
    if (empty($comment)) {
        return ['params' => $params, 'returnType' => $returnType, 'returnDescription' => $returnDescription];
    }
    
    // Extract @param lines
    if (preg_match_all('/@param\s+(\S+)\s+\$(\w+)\s*-?\s*(.*)$/m', $comment, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $params[] = [
                'name' => $matches[2][$i],
                'type' => $matches[1][$i],
                'description' => trim($matches[3][$i])
            ];
        }
    }
    
    // Extract @return
    if (preg_match('/@return\s+(\S+)\s*-?\s*(.*)$/m', $comment, $matches)) {
        $returnType = $matches[1];
        $returnDescription = trim($matches[2]);
    }
    
    return ['params' => $params, 'returnType' => $returnType, 'returnDescription' => $returnDescription];
}

/**
 * Extract function calls from function body (internal dependencies)
 */
function extractInternalCalls($functionBody, $allFunctionNames) {
    $calls = [];
    
    // Find all function calls in the form: functionName(
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $functionBody, $matches)) {
        foreach ($matches[1] as $called) {
            if (in_array($called, $allFunctionNames) && $called !== '__construct') {
                $calls[] = $called;
            }
        }
    }
    
    return array_values(array_unique($calls));
}

/**
 * Extract PHP functions from a PHP file
 */
function extractFunctions($filePath, $allFunctionNames = []) {
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
            
            // Parse PHPDoc for parameters and return type
            $docInfo = parsePhpDoc($comment);
            
            // Extract internal function calls (dependencies)
            $internalCalls = extractInternalCalls($functionCode, $allFunctionNames);
            
            // Determine category based on filename and function name
            $category = determineCategory(basename($filePath), $funcName);
            
            // Determine tags based on function characteristics
            $tags = determineTags($comment, $functionCode, $funcName);
            
            $functions[] = [
                'name' => $funcName,
                'line' => $lineNum,
                'comment' => $comment,
                'code' => trim($functionCode),
                'parameters' => $docInfo['params'],
                'returnType' => $docInfo['returnType'],
                'returnDescription' => $docInfo['returnDescription'],
                'internalCalls' => $internalCalls,
                'category' => $category,
                'tags' => $tags
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

// Scan all directories - First pass: collect all function names
echo "🔍 Scanning directories (pass 1: collecting function names)...\n";
$allFunctionNames = [];
$tempRegistry = [];

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
        $functions = extractFunctions($file, []);  // First pass without dependencies
        
        if (!empty($functions)) {
            $tempRegistry[$relativePath] = $functions;
            foreach ($functions as $func) {
                $allFunctionNames[] = $func['name'];
            }
        }
    }
}

// Second pass: re-extract with dependency information
echo "🔍 Scanning directories (pass 2: extracting dependencies)...\n";
$registry = [];

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
        $functions = extractFunctions($file, $allFunctionNames);  // Second pass WITH dependencies
        
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
            'parameters' => $func['parameters'] ?? [],
            'returnType' => $func['returnType'] ?? 'void',
            'returnDescription' => $func['returnDescription'] ?? '',
            'internalCalls' => $func['internalCalls'] ?? [],
            'category' => $func['category'] ?? 'utility',
            'tags' => $func['tags'] ?? [],
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
