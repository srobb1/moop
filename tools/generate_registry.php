<?php
/**
 * Function Registry Generator
 * Scans lib/ and tools/ directories to create an auto-generated registry of all PHP functions
 * Excludes JavaScript functions embedded in PHP files
 * Usage: php tools/generate_registry.php
 */

$registry = [];

// Directories to scan
$scanDirs = [
    __DIR__ . '/../lib',
    __DIR__ . '/../tools'
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
 * Find all files that use a specific function
 */
function findFunctionUsages($funcName, $scanDirs, $definitionFile) {
    $usages = [];
    $searchPattern = '/\b' . preg_quote($funcName, '/') . '\s*\(/';
    
    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            
            $filePath = $file->getRealPath();
            
            // Don't match the definition file
            if ($filePath === $definitionFile) continue;
            
            $content = file_get_contents($filePath);
            
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    
                    // Get context (the line containing the function call)
                    $lines = explode("\n", $content);
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    $usages[] = [
                        'file' => str_replace(__DIR__ . '/../', '', $filePath),
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

// Generate PHP registry file
$registryContent = "<?php\n";
$registryContent .= "/**\n";
$registryContent .= " * AUTO-GENERATED FUNCTION REGISTRY\n";
$registryContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$registryContent .= " * To regenerate, run: php tools/generate_registry.php\n";
$registryContent .= " */\n\n";
$registryContent .= "\$FUNCTION_REGISTRY = " . var_export($registry, true) . ";\n\n";

// Add helper function: findFunction
$registryContent .= "function findFunction(\$funcName) {\n";
$registryContent .= "    global \$FUNCTION_REGISTRY;\n";
$registryContent .= "    foreach (\$FUNCTION_REGISTRY as \$file => \$functions) {\n";
$registryContent .= "        foreach (\$functions as \$func) {\n";
$registryContent .= "            if (\$func['name'] === \$funcName) {\n";
$registryContent .= "                return ['file' => \$file, 'line' => \$func['line']];\n";
$registryContent .= "            }\n";
$registryContent .= "        }\n";
$registryContent .= "    }\n";
$registryContent .= "    return null;\n";
$registryContent .= "}\n\n";

// Add helper function: getAllFunctions
$registryContent .= "function getAllFunctions() {\n";
$registryContent .= "    global \$FUNCTION_REGISTRY;\n";
$registryContent .= "    \$all = [];\n";
$registryContent .= "    foreach (\$FUNCTION_REGISTRY as \$file => \$functions) {\n";
$registryContent .= "        foreach (\$functions as \$func) {\n";
$registryContent .= "            \$all[\$func['name']] = ['file' => \$file, 'line' => \$func['line']];\n";
$registryContent .= "        }\n";
$registryContent .= "    }\n";
$registryContent .= "    return \$all;\n";
$registryContent .= "}\n\n";

// Add helper function: checkDuplicates
$registryContent .= "function checkDuplicates() {\n";
$registryContent .= "    global \$FUNCTION_REGISTRY;\n";
$registryContent .= "    \$funcMap = [];\n";
$registryContent .= "    \$duplicates = [];\n";
$registryContent .= "    foreach (\$FUNCTION_REGISTRY as \$file => \$functions) {\n";
$registryContent .= "        foreach (\$functions as \$func) {\n";
$registryContent .= "            \$name = \$func['name'];\n";
$registryContent .= "            if (isset(\$funcMap[\$name])) {\n";
$registryContent .= "                if (!isset(\$duplicates[\$name])) {\n";
$registryContent .= "                    \$duplicates[\$name] = [\$funcMap[\$name]];\n";
$registryContent .= "                }\n";
$registryContent .= "                \$duplicates[\$name][] = ['file' => \$file, 'line' => \$func['line']];\n";
$registryContent .= "            } else {\n";
$registryContent .= "                \$funcMap[\$name] = ['file' => \$file, 'line' => \$func['line']];\n";
$registryContent .= "            }\n";
$registryContent .= "        }\n";
$registryContent .= "    }\n";
$registryContent .= "    return \$duplicates;\n";
$registryContent .= "}\n";
$registryContent .= "?" . ">\n";

// Write registry file
$registryFile = __DIR__ . '/../lib/function_registry.php';
if (file_put_contents($registryFile, $registryContent)) {
    echo "✅ Registry generated successfully!\n";
    echo "   File: lib/function_registry.php\n";
    
    $totalFuncs = array_sum(array_map('count', $registry));
    echo "   Functions found: " . $totalFuncs . "\n";
    echo "   Files scanned: " . count($registry) . "\n";
    
    // Check for duplicates
    require $registryFile;
    $dups = checkDuplicates();
    if (!empty($dups)) {
        echo "\n⚠️  WARNING: Found " . count($dups) . " duplicate function definitions:\n";
        foreach ($dups as $name => $locations) {
            echo "   - $name\n";
            foreach ($locations as $loc) {
                echo "     • {$loc['file']}:{$loc['line']}\n";
            }
        }
    } else {
        echo "\n✅ No duplicate functions detected.\n";
    }
    
    // Generate HTML documentation
    generateHtmlDocs($registry);
    
    // Generate Markdown documentation
    generateMarkdownDocs($registry);
    
} else {
    echo "❌ Failed to write registry file.\n";
    exit(1);
}

/**
 * Generate HTML documentation
 */
function generateHtmlDocs($registry) {
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
    $html .= "        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }\n";
    $html .= "        .stat-box { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }\n";
    $html .= "        .stat-box strong { display: block; font-size: 24px; color: #3498db; }\n";
    $html .= "        .stat-box span { font-size: 12px; color: #666; }\n";
    $html .= "        .file-section { background: white; margin: 20px 0; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }\n";
    $html .= "        .file-header { background: #34495e; color: white; padding: 15px 20px; font-weight: bold; cursor: pointer; }\n";
    $html .= "        .file-header:hover { background: #2c3e50; }\n";
    $html .= "        .functions-list { padding: 0; display: none; }\n";
    $html .= "        .functions-list.open { display: block; }\n";
    $html .= "        .function-item { padding: 15px 20px; border-bottom: 1px solid #ecf0f1; }\n";
    $html .= "        .function-item:last-child { border-bottom: none; }\n";
    $html .= "        .function-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding-bottom: 10px; }\n";
    $html .= "        .function-header:hover { color: #3498db; }\n";
    $html .= "        .function-name { font-family: 'Courier New', monospace; color: #2980b9; font-weight: 500; }\n";
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
    
    // Search box
    $html .= "        <div class=\"search-box\">\n";
    $html .= "            <input type=\"text\" id=\"searchInput\" placeholder=\"🔍 Search functions...\" onkeyup=\"filterFunctions()\">\n";
    $html .= "        </div>\n";
    
    // Statistics
    $totalFuncs = array_sum(array_map('count', $registry));
    $html .= "        <div class=\"stats\">\n";
    $html .= "            <div class=\"stat-box\"><strong>" . $totalFuncs . "</strong><span>Total Functions</span></div>\n";
    $html .= "            <div class=\"stat-box\"><strong>" . count($registry) . "</strong><span>Files</span></div>\n";
    $html .= "        </div>\n";
    
    // Functions by file
    ksort($registry);
    foreach ($registry as $file => $functions) {
        $html .= "        <div class=\"file-section\">\n";
        $html .= "            <div class=\"file-header\" onclick=\"toggleFile(this)\">📄 " . htmlspecialchars($file) . " (" . count($functions) . ")</div>\n";
        $html .= "            <div class=\"functions-list\">\n";
        
        foreach ($functions as $func) {
            $html .= "                <div class=\"function-item searchable\" data-func=\"" . htmlspecialchars($func['name']) . "\">\n";
            $html .= "                    <div class=\"function-header\" onclick=\"toggleCode(this)\">\n";
            $html .= "                        <div>\n";
            $html .= "                            <div class=\"function-name\">" . htmlspecialchars($func['name']) . "()</div>\n";
            $html .= "                            <div class=\"function-line\">Line " . $func['line'] . "</div>\n";
            $html .= "                        </div>\n";
            $html .= "                        <span style=\"font-size: 20px; margin-left: 10px;\">▶</span>\n";
            $html .= "                    </div>\n";
            
            // Show comment if exists
            if (!empty($func['comment'])) {
                $html .= "                    <div class=\"function-comment\">\n";
                $html .= "                        <pre>" . htmlspecialchars($func['comment']) . "</pre>\n";
                $html .= "                    </div>\n";
            }
            
            // Show usages
            $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools'], $file);
            if (!empty($usages)) {
                $html .= "                    <div class=\"function-usages\">\n";
                $html .= "                        <strong>📍 Used in " . count($usages) . " file(s):</strong>\n";
                $html .= "                        <ul>\n";
                foreach ($usages as $usage) {
                    $html .= "                            <li><code>" . htmlspecialchars($usage['file']) . ":" . $usage['line'] . "</code><br>\n";
                    $html .= "                                <small>" . htmlspecialchars(substr($usage['context'], 0, 80)) . "</small></li>\n";
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
    $html .= "            function toggleCode(header) {\n";
    $html .= "                const codeBlock = header.parentElement.querySelector('.function-code');\n";
    $html .= "                if (codeBlock) {\n";
    $html .= "                    codeBlock.classList.toggle('open');\n";
    $html .= "                    const arrow = header.querySelector('span');\n";
    $html .= "                    arrow.textContent = codeBlock.classList.contains('open') ? '▼' : '▶';\n";
    $html .= "                }\n";
    $html .= "            }\n";
    $html .= "            function filterFunctions() {\n";
    $html .= "                const search = document.getElementById('searchInput').value.toLowerCase();\n";
    $html .= "                const items = document.querySelectorAll('.function-item');\n";
    $html .= "                items.forEach(item => {\n";
    $html .= "                    const name = item.dataset.func.toLowerCase();\n";
    $html .= "                    item.classList.toggle('hidden', !name.includes(search));\n";
    $html .= "                });\n";
    $html .= "            }\n";
    $html .= "        </script>\n";
    $html .= "    </div>\n";
    $html .= "</body>\n</html>\n";
    
    $htmlFile = __DIR__ . '/../docs/function_registry.html';
    @mkdir(dirname($htmlFile), 0755, true);
    if (file_put_contents($htmlFile, $html)) {
        echo "\n✅ HTML documentation: docs/function_registry.html\n";
    } else {
        echo "\n⚠️  Could not write HTML file\n";
    }
}

/**
 * Generate Markdown documentation
 */
function generateMarkdownDocs($registry) {
    $md = "# Function Registry\n\n";
    $md .= "**Auto-generated documentation**\n\n";
    $md .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $totalFuncs = array_sum(array_map('count', $registry));
    $md .= "## Summary\n\n";
    $md .= "- **Total Functions**: " . $totalFuncs . "\n";
    $md .= "- **Files Scanned**: " . count($registry) . "\n\n";
    
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
            $usages = findFunctionUsages($func['name'], [__DIR__ . '/../lib', __DIR__ . '/../tools'], $file);
            if (!empty($usages)) {
                $md .= "**Used in " . count($usages) . " file(s):**\n";
                foreach ($usages as $usage) {
                    $md .= "- `" . $usage['file'] . "` line " . $usage['line'] . ": `" . addslashes($usage['context']) . "`\n";
                }
            } else {
                $md .= "**Used in: 0 files** (possibly unused)\n";
            }
            $md .= "\n";
        }
        
        $md .= "---\n\n";
    }
    
    $mdFile = __DIR__ . '/../docs/FUNCTION_REGISTRY.md';
    @mkdir(dirname($mdFile), 0755, true);
    if (file_put_contents($mdFile, $md)) {
        echo "✅ Markdown documentation: docs/FUNCTION_REGISTRY.md\n";
    } else {
        echo "⚠️  Could not write Markdown file\n";
    }
}
?>
