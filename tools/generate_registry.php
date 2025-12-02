<?php
/**
 * Function Registry Generator
 * Scans lib/ and tools/ directories to create an auto-generated registry of all PHP functions
 * Excludes JavaScript functions embedded in PHP files
 * Usage: php tools/generate_registry.php
 */

// Load configuration
require_once __DIR__ . '/../includes/config_init.php';
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$registry = [];

// Directories to scan
$scanDirs = [
    __DIR__ . '/../lib',
    __DIR__ . '/../tools',
    __DIR__ . '/../admin',
    __DIR__ . '/..'  // Root directory for index.php, login.php, etc.
];

// File patterns to exclude
$excludePatterns = [
    '.backup',
    'generate_registry',
    'function_registry'
];

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
 * Extract PHP functions from a PHP file (excludes JavaScript functions)
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
    
    // Match PHP function definitions with their code
    if (preg_match_all('/^\s*(?:public\s+)?(?:static\s+)?function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/m', $phpContent, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $idx => $match) {
            $funcName = $matches[1][$idx][0];
            $startPos = $match[1];
            $lineNum = substr_count($content, "\n", 0, strpos($content, $funcName)) + 1;
            
            // Extract preceding comment
            $comment = extractCommentBlock($phpContent, $startPos);
            
            // Extract function code by finding matching braces
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
 * Find all files that use a specific function (excluding the definition line)
 */
function findFunctionUsages($funcName, $scanDirs, $definitionFile) {
    $usages = [];
    $seen = []; // Track file + line combinations to prevent duplicates
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
            
            // Don't match registry file
            if (strpos($filePath, 'function_registry.php') !== false) continue;
            
            $content = file_get_contents($filePath);
            
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    
                    // Get context (the line containing the function call)
                    $lines = explode("\n", $content);
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    // Skip if the line is a comment
                    if (preg_match('/^\s*(\/\/|#|\*)/', $contextLine) || preg_match('/\/\*.*\*\//', $contextLine)) {
                        continue;
                    }
                    
                    // Skip the function definition line itself
                    if (preg_match('/^\s*(?:public\s+)?(?:static\s+)?function\s+' . preg_quote($funcName, '/') . '\s*\(/', $contextLine)) {
                        continue;
                    }
                    
                    $relativeFilePath = str_replace(__DIR__ . '/../', '', $filePath);
                    $usageKey = $relativeFilePath . ':' . $lineNum;
                    
                    // Skip duplicate file + line combinations
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

// Scan directories
foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $fileName = basename($file);
        
        // Skip excluded files
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

// Count totals and check for duplicates
$totalFuncs = array_sum(array_map('count', $registry));
$funcMap = [];
$duplicates = [];

foreach ($registry as $file => $functions) {
    foreach ($functions as $func) {
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

echo "✅ Registry scanned successfully!\n";
echo "   Functions found: " . $totalFuncs . "\n";
echo "   Files scanned: " . count($registry) . "\n";

if (!empty($duplicates)) {
    echo "\n⚠️  WARNING: Found " . count($duplicates) . " duplicate function definitions:\n";
    foreach ($duplicates as $name => $locations) {
        echo "   - $name\n";
        foreach ($locations as $loc) {
            echo "     • {$loc['file']}:{$loc['line']}\n";
        }
    }
} else {
    echo "\n✅ No duplicate functions detected.\n";
}

// Generate HTML documentation
generateHtmlDocs($registry, $docs_path);

// Generate Markdown documentation
generateMarkdownDocs($registry, $docs_path);

/**
 * Generate HTML documentation
 */
function generateHtmlDocs($registry, $docs_path) {
    $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    $html .= "    <meta charset=\"UTF-8\">\n";
    $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    $html .= "    <title>Function Registry - MOOP</title>\n";
    $html .= "    <style>\n";
    $html .= "        * { margin: 0; padding: 0; }\n";
    $html .= "        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; }\n";
    $html .= "        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }\n";
    $html .= "        header { background: #2c3e50; color: white; padding: 30px 0; margin: -20px -20px 30px -20px; }\n";
    $html .= "        header h1 { margin-bottom: 10px; }\n";
    $html .= "        header p { opacity: 0.9; }\n";
    $html .= "        .search-box { margin: 20px 0; }\n";
    $html .= "        input[type=\"text\"] { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }\n";
    $html .= "        .unused-content { padding: 20px; }\n";
    $html .= "        .unused-content.hidden { display: none; }\n";
    $html .= "        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }\n";
    $html .= "        .stat-box { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }\n";
    $html .= "        .stat-box strong { display: block; font-size: 24px; color: #3498db; }\n";
    $html .= "        .stat-box span { font-size: 12px; color: #666; }\n";
    $html .= "        .file-section { background: white; margin: 20px 0; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }\n";
    $html .= "        .hidden { display: none !important; }\n";
    $html .= "        .file-header { background: #34495e; color: white; padding: 15px 20px; font-weight: bold; cursor: pointer; }\n";
    $html .= "        .file-header:hover { background: #2c3e50; }\n";
    $html .= "        .functions-list { padding: 0; display: none; }\n";
    $html .= "        .functions-list.open { display: block; }\n";
    $html .= "        .function-item { padding: 15px 20px; border-bottom: 1px solid #ecf0f1; }\n";
    $html .= "        .function-item:last-child { border-bottom: none; }\n";
    $html .= "        .function-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding-bottom: 10px; }\n";
    $html .= "        .function-header:hover { color: #3498db; }\n";
    $html .= "        .function-name { font-family: 'Courier New', monospace; color: #2980b9; font-weight: 500; }\n";
    $html .= "        .function-counter { display: inline-flex; align-items: center; justify-content: center; background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; font-size: 12px; font-weight: bold; margin-left: 8px; flex-shrink: 0; }\n";
    $html .= "        .expand-arrow { display: inline-flex; align-items: center; justify-content: center; font-size: 16px; margin-left: 10px; flex-shrink: 0; }\n";
    $html .= "        .function-line { font-size: 12px; color: #7f8c8d; }\n";
    $html .= "        .function-code { display: none; background: #f8f8f8; padding: 15px; margin-top: 10px; border-radius: 4px; border-left: 3px solid #3498db; overflow-x: auto; }\n";
    $html .= "        .function-code.open { display: block; }\n";
    $html .= "        code { font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; color: #2c3e50; white-space: pre; display: block; }\n";
    $html .= "        .function-comment { background: #fffacd; padding: 12px 15px; margin: 10px 0; border-left: 3px solid #f39c12; border-radius: 3px; }\n";
    $html .= "        .function-comment pre { margin: 0; font-family: 'Courier New', monospace; font-size: 12px; color: #555; white-space: pre-wrap; word-break: break-word; }\n";
    $html .= "        .function-usages { background: #e8f4f8; padding: 12px 15px; margin: 10px 0; border-left: 3px solid #3498db; border-radius: 3px; }\n";
    $html .= "        .function-usages strong { display: block; margin-bottom: 8px; color: #2c3e50; }\n";
    $html .= "        .function-usages ul { margin: 0; padding-left: 20px; }\n";
    $html .= "        .function-usages li { margin: 6px 0; font-size: 12px; color: #555; }\n";
    $html .= "        .function-usages code { display: inline; background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #bbb; }\n";
    $html .= "        .function-usages small { display: block; margin-top: 2px; color: #666; font-style: italic; }\n";
    $html .= "        .hidden { display: none; }\n";
    $html .= "    </style>\n</head>\n<body>\n";
    $html .= "    <header>\n";
    $html .= "        <div class=\"container\">\n";
    $html .= "            <h1>🔍 MOOP Function Registry</h1>\n";
    $html .= "            <p>Auto-generated function documentation • Generated: " . date('Y-m-d H:i:s') . "</p>\n";
    $html .= "        </div>\n";
    $html .= "    </header>\n";
    $html .= "    <div class=\"container\">\n";
    
    // Search box with toggle
    $html .= "        <div class=\"search-box\">\n";
    $html .= "            <div style=\"margin-bottom: 15px;\">\n";
    $html .= "                <input type=\"text\" id=\"searchInput\" placeholder=\"🔍 Search...\" onkeyup=\"filterFunctions()\">\n";
    $html .= "            </div>\n";
    $html .= "            <div style=\"display: flex; gap: 20px; flex-wrap: wrap; padding: 10px; background: #f9f9f9; border-radius: 4px;\">\n";
    $html .= "                <label style=\"display: flex; align-items: center; gap: 8px; cursor: pointer;\">\n";
    $html .= "                    <input type=\"radio\" name=\"searchMode\" value=\"all\" checked onchange=\"filterFunctions()\" style=\"cursor: pointer;\">\n";
    $html .= "                    <span>Search All</span>\n";
    $html .= "                </label>\n";
    $html .= "                <label style=\"display: flex; align-items: center; gap: 8px; cursor: pointer;\">\n";
    $html .= "                    <input type=\"radio\" name=\"searchMode\" value=\"name\" onchange=\"filterFunctions()\" style=\"cursor: pointer;\">\n";
    $html .= "                    <span>Function Names Only</span>\n";
    $html .= "                </label>\n";
    $html .= "                <label style=\"display: flex; align-items: center; gap: 8px; cursor: pointer;\">\n";
    $html .= "                    <input type=\"radio\" name=\"searchMode\" value=\"code\" onchange=\"filterFunctions()\" style=\"cursor: pointer;\">\n";
    $html .= "                    <span>Code Only</span>\n";
    $html .= "                </label>\n";
    $html .= "                <label style=\"display: flex; align-items: center; gap: 8px; cursor: pointer;\">\n";
    $html .= "                    <input type=\"radio\" name=\"searchMode\" value=\"comment\" onchange=\"filterFunctions()\" style=\"cursor: pointer;\">\n";
    $html .= "                    <span>Comments Only</span>\n";
    $html .= "                </label>\n";
    $html .= "            </div>\n";
    $html .= "        </div>\n";
    
     // Statistics
     $totalFuncs = array_sum(array_map('count', $registry));
     $html .= "        <div class=\"stats\">\n";
     $html .= "            <div class=\"stat-box\"><strong>" . $totalFuncs . "</strong><span>Total Functions</span></div>\n";
     $html .= "            <div class=\"stat-box\"><strong>" . count($registry) . "</strong><span>Files</span></div>\n";
     $html .= "        </div>\n";
     
     // Find unused functions
     $unusedFunctions = [];
     foreach ($registry as $file => $functions) {
         foreach ($functions as $func) {
             $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools', __DIR__ . '/../admin', __DIR__ . '/..'], $file);
             if (empty($usages)) {
                 $unusedFunctions[] = [
                     'name' => $func['name'],
                     'file' => $file,
                     'line' => $func['line']
                 ];
             }
         }
     }
     
     // Show unused functions alert
     if (!empty($unusedFunctions)) {
         $html .= "        <div style=\"background: #ffe6e6; border: 2px solid #cc0000; border-radius: 4px; overflow: hidden; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);\">\n";
         $html .= "            <div style=\"background: #cc0000; color: white; padding: 15px 20px; font-weight: bold; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;\" onclick=\"toggleUnused(this)\">\n";
         $html .= "                <span>⚠️ " . count($unusedFunctions) . " Unused Function(s) Found</span>\n";
         $html .= "                <span class=\"unusedArrow\">▶</span>\n";
         $html .= "            </div>\n";
         $html .= "            <div class=\"unused-content hidden\">\n";
         $html .= "                <p style=\"margin: 10px 0; color: #555;\">These functions are defined but never called:</p>\n";
         $html .= "                <ul style=\"margin: 10px 0; padding-left: 20px;\">\n";
         foreach ($unusedFunctions as $func) {
             $html .= "                    <li><strong style=\"color: #cc0000;\">" . htmlspecialchars($func['name']) . "()</strong> in <code>" . htmlspecialchars($func['file']) . "</code> (line " . $func['line'] . ")</li>\n";
         }
         $html .= "                </ul>\n";
         $html .= "            </div>\n";
         $html .= "        </div>\n";
     }
     
     // Expand/Collapse button
     $html .= "        <div style=\"display: flex; gap: 10px; margin: 20px 0;\">\n";
     $html .= "            <button id=\"toggleBtn\" onclick=\"toggleAll()\" style=\"flex: 1; padding: 10px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;\">📂 Expand All</button>\n";
     $html .= "        </div>\n";
     
     // Functions by file
    ksort($registry);
    foreach ($registry as $file => $functions) {
        $html .= "        <div class=\"file-section\">\n";
        $html .= "            <div class=\"file-header\" onclick=\"toggleFile(this)\">📄 " . htmlspecialchars($file) . " (" . count($functions) . ")</div>\n";
        $html .= "            <div class=\"functions-list\">\n";
        
        foreach ($functions as $func) {
            // Calculate usage count for this function
            $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools', __DIR__ . '/../admin', __DIR__ . '/..'], $file);
            
            $html .= "                <div class=\"function-item searchable\" data-func=\"" . htmlspecialchars($func['name']) . "\">\n";
            $html .= "                    <div class=\"function-header\" onclick=\"toggleCode(this)\">\n";
            $html .= "                        <div style=\"display: flex; align-items: center; gap: 10px;\">\n";
            $html .= "                            <div class=\"function-name\">" . htmlspecialchars($func['name']) . "()</div>\n";
            $html .= "                            <span class=\"function-counter\">" . count($usages) . "</span>\n";
            $html .= "                            <div class=\"function-line\">Line " . $func['line'] . "</div>\n";
            $html .= "                        </div>\n";
            $html .= "                        <span class=\"expand-arrow\">▶</span>\n";
            $html .= "                    </div>\n";
            $html .= "                    <div style=\"font-family: 'Courier New', monospace; font-size: 12px; color: #666; padding: 8px 0; border-bottom: 1px solid #ecf0f1; user-select: all; cursor: copy;\" title=\"Click to select\">" . htmlspecialchars($file) . ": " . htmlspecialchars($func['name']) . "()</div>\n";
            
            // Show comment if exists
            if (!empty($func['comment'])) {
                $html .= "                    <div class=\"function-comment\">\n";
                $html .= "                        <pre>" . htmlspecialchars($func['comment']) . "</pre>\n";
                $html .= "                    </div>\n";
            }
            
             // Show usages
            if (!empty($usages)) {
                // Group usages by file and count them
                $usagesByFile = [];
                foreach ($usages as $usage) {
                    if (!isset($usagesByFile[$usage['file']])) {
                        $usagesByFile[$usage['file']] = [];
                    }
                    $usagesByFile[$usage['file']][] = $usage;
                }
                
                $html .= "                    <div class=\"function-usages\">\n";
                $html .= "                        <strong>📍 Used in " . count($usagesByFile) . " unique file(s) (" . count($usages) . " total times):</strong>\n";
                $html .= "                        <ul>\n";
                foreach ($usagesByFile as $fileKey => $fileUsages) {
                    $html .= "                            <li><strong>" . htmlspecialchars($fileKey) . "</strong> (" . count($fileUsages) . "x)\n";
                    $html .= "                                <ul style=\"margin-top: 5px;\">\n";
                    foreach ($fileUsages as $usage) {
                        $html .= "                                    <li><code>line " . $usage['line'] . "</code>: <small>" . htmlspecialchars(substr($usage['context'], 0, 80)) . "</small></li>\n";
                    }
                    $html .= "                                </ul></li>\n";
                }
                $html .= "                        </ul>\n";
                $html .= "                    </div>\n";
            }
            
            if (isset($func['code'])) {
                $html .= "                    <div class=\"function-code\">\n";
                $html .= "                        <code>" . htmlspecialchars($func['code']) . "</code>\n";
                $html .= "                    </div>\n";
            }
            $html .= "                </div>\n";
        }
        
        $html .= "            </div>\n";
        $html .= "        </div>\n";
    }
    
    // JavaScript
    $html .= "        <script>\n";
    $html .= "            function toggleFile(header) {\n";
    $html .= "                header.nextElementSibling.classList.toggle('open');\n";
    $html .= "            }\n";
    $html .= "            function toggleAll() {\n";
    $html .= "                const lists = document.querySelectorAll('.functions-list');\n";
    $html .= "                const hiddenCount = document.querySelectorAll('.functions-list:not(.open)').length;\n";
    $html .= "                const btn = document.getElementById('toggleBtn');\n";
    $html .= "                if (hiddenCount > 0) {\n";
    $html .= "                    lists.forEach(list => list.classList.add('open'));\n";
    $html .= "                    btn.textContent = '📁 Collapse All';\n";
    $html .= "                } else {\n";
    $html .= "                    lists.forEach(list => list.classList.remove('open'));\n";
    $html .= "                    btn.textContent = '📂 Expand All';\n";
    $html .= "                }\n";
    $html .= "            }\n";
    $html .= "            function toggleCode(header) {\n";
    $html .= "                const codeBlock = header.parentElement.querySelector('.function-code');\n";
    $html .= "                if (codeBlock) {\n";
    $html .= "                    codeBlock.classList.toggle('open');\n";
    $html .= "                    const arrow = header.querySelector('.expand-arrow');\n";
    $html .= "                    arrow.textContent = codeBlock.classList.contains('open') ? '▼' : '▶';\n";
    $html .= "                }\n";
    $html .= "            }\n";
    $html .= "            function toggleUnused(header) {\n";
    $html .= "                const content = header.nextElementSibling;\n";
    $html .= "                const arrow = header.querySelector('.unusedArrow');\n";
    $html .= "                content.classList.toggle('hidden');\n";
    $html .= "                arrow.textContent = content.classList.contains('hidden') ? '▶' : '▼';\n";
    $html .= "            }\n";
    $html .= "            function filterFunctions() {\n";
    $html .= "                const searchTerm = document.getElementById('searchInput').value.toLowerCase();\n";
    $html .= "                const searchMode = document.querySelector('input[name=\"searchMode\"]:checked').value;\n";
    $html .= "                const fileSections = document.querySelectorAll('.file-section');\n";
    $html .= "                \n";
    $html .= "                fileSections.forEach(section => {\n";
    $html .= "                    const fileName = section.querySelector('.file-header').textContent.toLowerCase();\n";
    $html .= "                    const functionItems = section.querySelectorAll('.function-item');\n";
    $html .= "                    let hasVisibleFunction = false;\n";
    $html .= "                    \n";
    $html .= "                    functionItems.forEach(item => {\n";
    $html .= "                        const funcName = item.querySelector('.function-name').textContent.toLowerCase();\n";
    $html .= "                        const funcCode = item.querySelector('.function-code') ? item.querySelector('.function-code').textContent.toLowerCase() : '';\n";
    $html .= "                        const funcComment = item.querySelector('.function-comment') ? item.querySelector('.function-comment').textContent.toLowerCase() : '';\n";
    $html .= "                        \n";
    $html .= "                        let match = false;\n";
    $html .= "                        \n";
    $html .= "                        if (searchMode === 'all') {\n";
    $html .= "                            match = funcName.includes(searchTerm) || funcCode.includes(searchTerm) || funcComment.includes(searchTerm) || fileName.includes(searchTerm);\n";
    $html .= "                        } else if (searchMode === 'name') {\n";
    $html .= "                            match = funcName.includes(searchTerm);\n";
    $html .= "                        } else if (searchMode === 'code') {\n";
    $html .= "                            match = funcCode.includes(searchTerm);\n";
    $html .= "                        } else if (searchMode === 'comment') {\n";
    $html .= "                            match = funcComment.includes(searchTerm);\n";
    $html .= "                        }\n";
    $html .= "                        \n";
    $html .= "                        if (searchTerm === '' || match) {\n";
    $html .= "                            item.classList.remove('hidden');\n";
    $html .= "                            hasVisibleFunction = true;\n";
    $html .= "                        } else {\n";
    $html .= "                            item.classList.add('hidden');\n";
    $html .= "                        }\n";
    $html .= "                    });\n";
    $html .= "                    \n";
    $html .= "                    if (searchTerm === '' || hasVisibleFunction || fileName.includes(searchTerm)) {\n";
    $html .= "                        section.classList.remove('hidden');\n";
    $html .= "                        if (searchTerm !== '') {\n";
    $html .= "                            section.querySelector('.functions-list').classList.add('open');\n";
    $html .= "                        } else {\n";
    $html .= "                            section.querySelector('.functions-list').classList.remove('open');\n";
    $html .= "                        }\n";
    $html .= "                    } else {\n";
    $html .= "                        section.classList.add('hidden');\n";
    $html .= "                    }\n";
    $html .= "                });\n";
    $html .= "            }\n";
    $html .= "        </script>\n";
    $html .= "    </div>\n";
    $html .= "</body>\n</html>\n";
    
    $htmlFile = $docs_path . '/function_registry.html';
    @mkdir(dirname($htmlFile), 0755, true);
    if (file_put_contents($htmlFile, $html)) {
        echo "\n✅ HTML documentation: " . str_replace(dirname(__DIR__) . '/', '', $htmlFile) . "\n";
    } else {
        echo "\n⚠️  Could not write HTML file\n";
    }
}

/**
 * Generate Markdown documentation
 */
function generateMarkdownDocs($registry, $docs_path) {
    $md = "# Function Registry\n\n";
    $md .= "**Auto-generated documentation**\n\n";
    $md .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $totalFuncs = array_sum(array_map('count', $registry));
    $md .= "## Summary\n\n";
    $md .= "- **Total Functions**: " . $totalFuncs . "\n";
    $md .= "- **Files Scanned**: " . count($registry) . "\n\n";
    
    // Find unused functions for markdown
    $unusedFunctions = [];
    foreach ($registry as $file => $functions) {
        foreach ($functions as $func) {
            $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools', __DIR__ . '/../admin', __DIR__ . '/..'], $file);
            if (empty($usages)) {
                $unusedFunctions[] = [
                    'name' => $func['name'],
                    'file' => $file,
                    'line' => $func['line']
                ];
            }
        }
    }
    
    // Add unused functions to markdown
    if (!empty($unusedFunctions)) {
        $md .= "## ⚠️ Unused Functions (" . count($unusedFunctions) . ")\n\n";
        $md .= "These functions are defined but never called:\n\n";
        foreach ($unusedFunctions as $func) {
            $md .= "- `" . $func['name'] . "()` in `" . $func['file'] . "` (line " . $func['line'] . ")\n";
        }
        $md .= "\n---\n\n";
    }
    
    $md .= "## Quick Navigation\n\n";
    ksort($registry);
    foreach ($registry as $file => $functions) {
        $md .= "- [" . $file . "](#" . str_replace(['/', '.'], ['-', ''], $file) . ") - " . count($functions) . " functions\n";
    }
    $md .= "\n---\n\n";
    
    // Functions by file
    foreach ($registry as $file => $functions) {
        $md .= "## " . $file . "\n\n";
        $md .= "**" . count($functions) . " function(s)**\n\n";
        
        foreach ($functions as $func) {
            $md .= "### `" . $func['name'] . "()` (Line " . $func['line'] . ")\n\n";
            $md .= "Located in: `" . $file . "` at line " . $func['line'] . "\n\n";
            
            // Show usage comment if exists
            if (!empty($func['comment'])) {
                $md .= "**Description:**\n\n";
                $md .= "```\n" . $func['comment'] . "\n```\n\n";
            }
            
             // Get usages for this function
             $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools', __DIR__ . '/../admin', __DIR__ . '/..'], $file);
            if (!empty($usages)) {
                // Group usages by file and count them
                $usagesByFile = [];
                foreach ($usages as $usage) {
                    if (!isset($usagesByFile[$usage['file']])) {
                        $usagesByFile[$usage['file']] = [];
                    }
                    $usagesByFile[$usage['file']][] = $usage;
                }
                
                $md .= "**Used in " . count($usagesByFile) . " unique file(s) (" . count($usages) . " total times):**\n";
                foreach ($usagesByFile as $fileKey => $fileUsages) {
                    $md .= "- `" . $fileKey . "` (" . count($fileUsages) . "x):\n";
                    foreach ($fileUsages as $usage) {
                        $md .= "  - Line " . $usage['line'] . ": `" . addslashes($usage['context']) . "`\n";
                    }
                }
            } else {
                $md .= "**Used in: 0 files** (possibly unused)\n";
            }
            $md .= "\n";
        }
        
        $md .= "---\n\n";
    }
    
    $mdFile = $docs_path . '/FUNCTION_REGISTRY.md';
    @mkdir(dirname($mdFile), 0755, true);
    if (file_put_contents($mdFile, $md)) {
        echo "✅ Markdown documentation: " . str_replace(dirname(__DIR__) . '/', '', $mdFile) . "\n";
    } else {
        echo "⚠️  Could not write Markdown file\n";
    }
}
?>
