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

// Scan JS files and extract functions
echo "🔍 Scanning JavaScript files...\n";
$files = array_merge(
    glob($jsDir . '/*.js') ?: [],
    glob($jsDir . '/modules/*.js') ?: []
);

foreach ($files as $file) {
    if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
    
    $content = file_get_contents($file);
    $functions = [];
    
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
                
                $functions[] = [
                    'name' => $funcName,
                    'line' => $lineNum,
                    'comment' => '',
                    'code' => trim($functionCode),
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

