<?php
/**
 * JavaScript Function Registry JSON Generator
 * 
 * Generates a clean JSON file containing all JavaScript functions from the codebase.
 * Tracks both JS-to-JS usage and which PHP files include/use each JS file.
 * 
 * Usage: php tools/generate_js_registry_json.php
 * Output: docs/js_function_registry.json
 */

require_once __DIR__ . '/../includes/config_init.php';
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$jsDir = __DIR__ . '/../js';
$registry = [];

/**
 * Extract JSDoc comment to get parameter and return information
 */
function parseJsDoc($comment) {
    $params = [];
    $returnType = 'void';
    $returnDescription = '';
    
    if (empty($comment)) {
        return ['params' => $params, 'returnType' => $returnType, 'returnDescription' => $returnDescription];
    }
    
    // Extract @param lines
    if (preg_match_all('/@param\s+(?:\{([^}]+)\})?\s*(\w+)\s*-?\s*(.*)$/mi', $comment, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $params[] = [
                'name' => $matches[2][$i],
                'type' => !empty($matches[1][$i]) ? $matches[1][$i] : 'any',
                'description' => trim($matches[3][$i])
            ];
        }
    }
    
    // Extract @returns/@return
    if (preg_match('/@returns?\s+(?:\{([^}]+)\})?\s*-?\s*(.*)$/mi', $comment, $matches)) {
        $returnType = !empty($matches[1]) ? $matches[1] : 'any';
        $returnDescription = trim($matches[2]);
    }
    
    return ['params' => $params, 'returnType' => $returnType, 'returnDescription' => $returnDescription];
}

/**
 * Extract function calls from JS function body
 */
function extractJsFunctionCalls($code, $allFunctionNames) {
    $calls = [];
    
    // Find function calls: functionName( or this.functionName(
    if (preg_match_all('/(?:^|[\s\.])([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/', $code, $matches)) {
        foreach ($matches[1] as $called) {
            if (in_array($called, $allFunctionNames)) {
                $calls[] = $called;
            }
        }
    }
    
    return array_values(array_unique($calls));
}

/**
 * Determine category for JS function
 */
function determineJsCategory($filename, $funcName) {
    $filename = strtolower($filename);
    $funcName = strtolower($funcName);
    
    // UI/DOM manipulation
    if (strpos($filename, 'dom') !== false || strpos($filename, 'ui') !== false ||
        strpos($funcName, 'render') !== false || strpos($funcName, 'display') !== false ||
        strpos($funcName, 'show') !== false || strpos($funcName, 'hide') !== false) {
        return 'ui-dom';
    }
    
    // Event handling
    if (strpos($funcName, 'event') !== false || strpos($funcName, 'listener') !== false ||
        strpos($funcName, 'handler') !== false || strpos($funcName, 'click') !== false) {
        return 'event-handling';
    }
    
    // Data processing
    if (strpos($funcName, 'parse') !== false || strpos($funcName, 'extract') !== false ||
        strpos($funcName, 'filter') !== false || strpos($funcName, 'search') !== false) {
        return 'data-processing';
    }
    
    // Search/Filter
    if (strpos($filename, 'search') !== false || strpos($filename, 'filter') !== false ||
        strpos($funcName, 'search') !== false || strpos($funcName, 'filter') !== false) {
        return 'search-filter';
    }
    
    // Download/Export
    if (strpos($funcName, 'download') !== false || strpos($funcName, 'export') !== false) {
        return 'export';
    }
    
    // DataTables
    if (strpos($filename, 'datatable') !== false || strpos($filename, 'table') !== false) {
        return 'datatable';
    }
    
    // Admin tools
    if (strpos($filename, 'manage') !== false || strpos($filename, 'admin') !== false) {
        return 'admin';
    }
    
    // BLAST
    if (strpos($filename, 'blast') !== false) {
        return 'blast';
    }
    
    // Utilities
    if (strpos($funcName, 'escape') !== false || strpos($funcName, 'sanitize') !== false) {
        return 'utilities';
    }
    
    return 'general';
}

/**
 * Determine tags for JS function
 */
function determineJsTags($comment, $code, $funcName) {
    $tags = [];
    
    // DOM manipulation
    if (preg_match('/\.innerHTML|\.textContent|appendChild|removeChild|classList|setAttribute|getElementById|querySelector/i', $code)) {
        $tags[] = 'dom-manipulation';
    }
    
    // Asynchronous
    if (preg_match('/async|await|\.then|\.catch|Promise|setTimeout/i', $code)) {
        $tags[] = 'asynchronous';
    }
    
    // AJAX/HTTP
    if (preg_match('/fetch|XMLHttpRequest|\.ajax|\.get|\.post/i', $code)) {
        $tags[] = 'ajax';
    }
    
    // Event listener
    if (preg_match('/addEventListener|on\w+\s*=|\.on\(/i', $code)) {
        $tags[] = 'event-listener';
    }
    
    // State modification
    if (preg_match('/\w+\s*=/i', $code)) {
        $tags[] = 'state-modifying';
    }
    
    // Loop operations
    if (preg_match('/forEach|for\s*\(|while\s*\(/i', $code)) {
        $tags[] = 'loops';
    }
    
    // Error handling
    if (preg_match('/try\s*\{|catch|throw|error/i', $code)) {
        $tags[] = 'error-handling';
    }
    
    // Validation
    if (preg_match('/validate|check|verify/i', $code)) {
        $tags[] = 'validation';
    }
    
    return array_values(array_unique($tags));
}

// Scan JS files and extract functions
echo "🔍 Scanning JavaScript files...\n";
$files = array_merge(
    glob($jsDir . '/*.js') ?: [],
    glob($jsDir . '/modules/*.js') ?: []
);

// First pass: collect all function names
echo "🔍 Scanning JavaScript files (pass 1: collecting function names)...\n";
$allJsFunctionNames = [];
$jsFilesContent = [];

foreach ($files as $file) {
    if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
    
    $content = file_get_contents($file);
    $jsFilesContent[$file] = $content;
    
    // Match function declarations and arrow functions
    $patterns = [
        '/(?:^|\s)function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/m',
        '/(?:^|\s)(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:function|\()/m',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $funcName) {
                if (!in_array($funcName, $allJsFunctionNames)) {
                    $allJsFunctionNames[] = $funcName;
                }
            }
        }
    }
}

// Second pass: extract functions with full metadata
echo "🔍 Scanning JavaScript files (pass 2: extracting metadata)...\n";

foreach ($files as $file) {
    if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
    
    $content = $jsFilesContent[$file];
    $functions = [];
    $fileName = basename($file);
    
    // Match function declarations and arrow functions
    $patterns = [
        '/(?:^|\s)function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/m',
        '/(?:^|\s)(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:function|\()/m',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $match) {
                $funcName = $matches[1][$idx][0];
                $startPos = $match[1];
                $lineNum = substr_count($content, "\n", 0, $startPos) + 1;
                
                // Extract function code by finding matching braces
                $braceCount = 0;
                $functionCode = '';
                $chars = str_split($content);
                
                // Find opening brace
                $bodyStart = $startPos;
                for ($i = $startPos; $i < strlen($content); $i++) {
                    if ($chars[$i] === '{') {
                        $bodyStart = $i;
                        break;
                    }
                }
                
                // Extract until matching closing brace
                for ($i = $bodyStart; $i < strlen($content) && $i < $bodyStart + 2000; $i++) {
                    $char = $chars[$i];
                    $functionCode .= $char;
                    
                    if ($char === '{') $braceCount++;
                    elseif ($char === '}') {
                        $braceCount--;
                        if ($braceCount === 0) break;
                    }
                }
                
                // Extract JSDoc comment (look for /** ... */ immediately before function)
                $jsDocComment = '';
                // Get only the last 1000 chars before function to avoid matching file header
                $startSearch = max(0, $startPos - 1000);
                $nearFunc = substr($content, $startSearch, $startPos - $startSearch);
                // Find the LAST occurrence of /** ... */ in this section
                // Use non-greedy match with limited scope
                if (preg_match('/\/\*\*[^*]*(?:\*(?!\/)[^*]*)*\*\/(?:\s|\/\/[^\n]*\n)*$/s', $nearFunc, $jsDocMatch)) {
                    $jsDocComment = $jsDocMatch[0];
                }
                
                // Parse JSDoc for parameters and return type
                $docInfo = parseJsDoc($jsDocComment);
                
                // Extract internal function calls
                $internalCalls = extractJsFunctionCalls($functionCode, $allJsFunctionNames);
                
                // Determine category and tags
                $category = determineJsCategory($fileName, $funcName);
                $tags = determineJsTags($jsDocComment, $functionCode, $funcName);
                
                $functions[] = [
                    'name' => $funcName,
                    'line' => $lineNum,
                    'comment' => $jsDocComment,
                    'code' => trim($functionCode),
                    'parameters' => $docInfo['params'],
                    'returnType' => $docInfo['returnType'],
                    'returnDescription' => $docInfo['returnDescription'],
                    'internalCalls' => $internalCalls,
                    'category' => $category,
                    'tags' => $tags,
                    'usageCount' => 0,
                    'usages' => [],
                    'phpFilesCount' => 0,
                    'phpFiles' => []
                ];
            }
        }
    }
    
    if (!empty($functions)) {
        $relativePath = str_replace(__DIR__ . '/../', '', $file);
        $registry[$relativePath] = $functions;
    }
}

// Find which PHP files include/use each JS file
echo "🔍 Scanning for PHP files using JavaScript...\n";
$phpFilesByJsFile = [];
foreach ($registry as $jsFile => $funcs) {
    $jsFileName = basename($jsFile);
    $phpFiles = [];
    
    $searchDirs = [
        __DIR__ . '/../*.php',
        __DIR__ . '/../admin/*.php',
        __DIR__ . '/../admin/**/*.php',
        __DIR__ . '/../tools/*.php',
        __DIR__ . '/../tools/**/*.php',
    ];
    
    $allPhpFiles = [];
    foreach ($searchDirs as $pattern) {
        $allPhpFiles = array_merge($allPhpFiles, glob($pattern));
    }
    
    foreach (array_unique($allPhpFiles) as $phpFile) {
        if (file_exists($phpFile) && strpos(file_get_contents($phpFile), $jsFileName) !== false) {
            $phpFiles[] = str_replace(__DIR__ . '/../', '', $phpFile);
        }
    }
    
    if (!empty($phpFiles)) {
        $phpFilesByJsFile[$jsFile] = array_unique($phpFiles);
    }
}

// Find function usages in JS and PHP files
echo "🔍 Scanning for function usages...\n";
$scanDirs = [
    __DIR__ . '/../js',
    __DIR__ . '/../admin',
    __DIR__ . '/../tools',
    __DIR__ . '/..'
];

foreach ($registry as $jsFile => &$functions) {
    foreach ($functions as &$func) {
        $usages = [];
        $seen = [];
        $searchPattern = '/\b' . preg_quote($func['name'], '/') . '\s*\(/';
        
        // Search in JS files
        $jsFiles = array_merge(
            glob($jsDir . '/*.js') ?: [],
            glob($jsDir . '/modules/*.js') ?: []
        );
        
        foreach ($jsFiles as $file) {
            if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
            
            $content = file_get_contents($file);
            
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    
                    $lines = explode("\n", $content);
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    // Skip comments
                    if (preg_match('/^\s*(\/\/|\/\*|\*|#)/', $contextLine)) continue;
                    
                    // Skip function definition
                    if (preg_match('/(?:^|\s)(?:function|const|let|var)\s+' . preg_quote($func['name']) . '\s*[=:\(]/', $contextLine)) continue;
                    
                    $relativeFile = str_replace(__DIR__ . '/../', '', $file);
                    $usageKey = $relativeFile . ':' . $lineNum;
                    
                    if (isset($seen[$usageKey])) continue;
                    $seen[$usageKey] = true;
                    
                    $usages[] = [
                        'file' => $relativeFile,
                        'line' => $lineNum,
                        'context' => $contextLine
                    ];
                }
            }
        }
        
        // Also search in PHP files for inline JS calls
        $phpFiles = array_merge(
            glob(__DIR__ . '/../*.php') ?: [],
            glob(__DIR__ . '/../admin/*.php') ?: [],
            glob(__DIR__ . '/../admin/**/*.php') ?: [],
            glob(__DIR__ . '/../tools/*.php') ?: [],
            glob(__DIR__ . '/../tools/**/*.php') ?: []
        );
        
        foreach (array_unique($phpFiles) as $phpFile) {
            if (!file_exists($phpFile) || strpos($phpFile, 'function_registry') !== false) continue;
            
            $content = file_get_contents($phpFile);
            
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    
                    $lines = explode("\n", $content);
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    // Skip PHP comments
                    if (preg_match('/^\s*(\/\/|\/\*|\*|#)/', $contextLine)) continue;
                    
                    $relativeFile = str_replace(__DIR__ . '/../', '', $phpFile);
                    $usageKey = $relativeFile . ':' . $lineNum;
                    
                    if (isset($seen[$usageKey])) continue;
                    $seen[$usageKey] = true;
                    
                    $usages[] = [
                        'file' => $relativeFile,
                        'line' => $lineNum,
                        'context' => $contextLine
                    ];
                }
            }
        }
        
        $func['usageCount'] = count($usages);
        $func['usages'] = $usages;
        
        // Add PHP files that include this JS file
        if (isset($phpFilesByJsFile[$jsFile])) {
            $func['phpFilesCount'] = count($phpFilesByJsFile[$jsFile]);
            $func['phpFiles'] = $phpFilesByJsFile[$jsFile];
        }
    }
}
unset($func);

// Build registry data structure
$totalFuncs = 0;
$fileArray = [];

foreach ($registry as $file => $functions) {
    $totalFuncs += count($functions);
    $fileArray[] = [
        'name' => $file,
        'count' => count($functions),
        'functions' => $functions
    ];
}

// Find unused functions
$unused = [];
foreach ($fileArray as $fileEntry) {
    foreach ($fileEntry['functions'] as $func) {
        if ($func['usageCount'] === 0) {
            $unused[] = [
                'name' => $func['name'],
                'file' => $fileEntry['name'],
                'line' => $func['line']
            ];
        }
    }
}

// Sort files by name
usort($fileArray, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Build final registry
$registryData = [
    'metadata' => [
        'generated' => date('Y-m-d H:i:s'),
        'totalFunctions' => $totalFuncs,
        'totalFiles' => count($fileArray),
        'duplicates' => 0,
    ],
    'files' => $fileArray,
    'unused' => $unused,
];

// Save JSON
$jsonFile = $docs_path . '/js_function_registry.json';
@mkdir(dirname($jsonFile), 0755, true);

$json = json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonFile, $json)) {
    echo "\n✅ JavaScript Registry generated successfully!\n";
    echo "   File: " . str_replace(__DIR__ . '/../', '', $jsonFile) . "\n";
    echo "   Total Functions: " . $totalFuncs . "\n";
    echo "   Files Scanned: " . count($fileArray) . "\n";
    echo "   Unused Functions: " . count($unused) . "\n";
} else {
    echo "\n❌ Error writing JSON file\n";
    exit(1);
}
?>

