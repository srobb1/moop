<?php
/**
 * JavaScript Function Registry Generator
 * Similar to PHP registry - tracks function definitions and usage
 * Usage: php tools/generate_js_registry.php
 */

// Load configuration
require_once __DIR__ . '/../includes/ConfigManager.php';
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$jsDir = __DIR__ . '/../js';
$registry = [];

// Scan JS files and extract functions with code
$files = array_merge(glob($jsDir . '/*.js') ?: [], glob($jsDir . '/modules/*.js') ?: []);
foreach ($files as $file) {
    if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
    
    $content = file_get_contents($file);
    $functions = [];
    
    $patterns = [
        '/(?:^|\s)function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/m',
        '/(?:^|\s)(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*function\s*\(/m',
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
                
                $bodyStart = $startPos;
                for ($i = $startPos; $i < strlen($content); $i++) {
                    if ($chars[$i] === '{') {
                        $bodyStart = $i;
                        break;
                    }
                }
                
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
                    'code' => trim(substr($functionCode, 0, 500)) // Limit to 500 chars
                ];
            }
        }
    }
    
    if (!empty($functions)) {
        $relativePath = str_replace(__DIR__ . '/../', '', $file);
        $registry[$relativePath] = $functions;
    }
}

// Find PHP includes for each JS file
$phpIncludes = [];
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
        $phpIncludes[$jsFile] = array_unique($phpFiles);
    }
}

// Find where each function is called (usage tracking) - excluding the definition line
function findJsFunctionUsages($funcName, $jsDir, $definitionFile = null, $definitionLine = null) {
    $usages = [];
    $searchPattern = '/\b' . preg_quote($funcName, '/') . '\s*\(/';
    
    // Search in JS files
    $files = array_merge(glob($jsDir . '/*.js') ?: [], glob($jsDir . '/modules/*.js') ?: []);
    foreach ($files as $file) {
        if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
        
        $content = file_get_contents($file);
        if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                $lines = explode("\n", $content);
                $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                
                // Skip comments
                if (preg_match('/^\s*(\/\/|\/\*)/', $contextLine)) continue;
                
                // Skip the function definition line itself
                if (preg_match('/(?:^|\s)(?:function|const|let|var)\s+' . preg_quote($funcName) . '\s*[=:\(]/', $contextLine)) continue;
                
                $relativeFile = str_replace(__DIR__ . '/../', '', $file);
                
                // Skip if this is the definition file and line
                if ($definitionFile && $definitionLine && $relativeFile === $definitionFile && $lineNum === $definitionLine) {
                    continue;
                }
                
                $usages[] = [
                    'file' => $relativeFile,
                    'line' => $lineNum,
                    'context' => $contextLine
                ];
            }
        }
    }
    
    // Also search in PHP files for inline JS calls (onclick, etc.)
    $phpFiles = array_merge(
        glob(__DIR__ . '/../*.php') ?: [],
        glob(__DIR__ . '/../admin/*.php') ?: [],
        glob(__DIR__ . '/../admin/**/*.php') ?: [],
        glob(__DIR__ . '/../tools/*.php') ?: [],
        glob(__DIR__ . '/../tools/**/*.php') ?: []
    );
    
    foreach (array_unique($phpFiles) as $phpFile) {
        if (!file_exists($phpFile)) continue;
        
        $content = file_get_contents($phpFile);
        if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                $lines = explode("\n", $content);
                $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                
                // Skip PHP comments and function definitions
                if (preg_match('/^\s*(\/\/|\/\*|#)/', $contextLine)) continue;
                if (preg_match('/function\s+' . preg_quote($funcName) . '\s*\(/', $contextLine)) continue;
                
                // Must be in an HTML onclick, inline script, or similar
                if (strpos($contextLine, $funcName) === false) continue;
                
                $usages[] = [
                    'file' => str_replace(__DIR__ . '/../', '', $phpFile),
                    'line' => $lineNum,
                    'context' => $contextLine
                ];
            }
        }
    }
    
    return $usages;
}

// Generate HTML
$html = "<!DOCTYPE html>\n<html>\n<head>\n";
$html .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
$html .= "<title>JS Function Registry - MOOP</title>\n";
$html .= "<style>\n";
$html .= "* { margin: 0; padding: 0; }\n";
$html .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }\n";
$html .= ".container { max-width: 1400px; margin: 0 auto; padding: 20px; }\n";
$html .= "header { background: #2c3e50; color: white; padding: 30px 0; margin: -20px -20px 30px -20px; }\n";
$html .= "header h1 { margin-bottom: 10px; }\n";
$html .= "header p { opacity: 0.9; }\n";
$html .= ".stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }\n";
$html .= ".stat-box { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }\n";
$html .= ".stat-box strong { display: block; font-size: 24px; color: #3498db; }\n";
$html .= ".stat-box span { font-size: 12px; color: #666; }\n";
$html .= ".file-section { background: white; margin: 20px 0; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }\n";
$html .= ".file-header { background: #34495e; color: white; padding: 15px 20px; font-weight: bold; cursor: pointer; user-select: none; }\n";
$html .= ".file-header:hover { background: #2c3e50; }\n";
$html .= ".file-header.collapsed:before { content: '▶ '; }\n";
$html .= ".file-header:not(.collapsed):before { content: '▼ '; }\n";
$html .= ".file-info { background: #e8f4f8; padding: 15px 20px; border-bottom: 1px solid #bdc3c7; font-size: 12px; color: #555; }\n";
$html .= ".file-info strong { color: #2c3e50; }\n";
$html .= ".php-pages { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }\n";
$html .= ".php-page { background: white; padding: 4px 10px; border-radius: 3px; border: 1px solid #3498db; color: #3498db; font-family: monospace; font-size: 11px; }\n";
$html .= ".functions-list { display: block; }\n";
$html .= ".functions-list.hidden { display: none; }\n";
$html .= ".function-item { padding: 0; border-bottom: 1px solid #ecf0f1; }\n";
$html .= ".function-item:last-child { border-bottom: none; }\n";
$html .= ".function-header { background: #f0f0f0; padding: 12px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }\n";
$html .= ".function-header:hover { background: #e0e0e0; }\n";
$html .= ".function-name { font-family: 'Courier New', monospace; color: #2980b9; font-weight: 500; }\n";
$html .= ".function-counter { display: inline-block; background: #3498db; color: white; border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 24px; font-size: 12px; font-weight: bold; margin-left: 8px; }\n";
$html .= ".function-line { font-size: 11px; color: #7f8c8d; }\n";
$html .= ".function-details { padding: 15px 20px; background: white; display: none; }\n";
$html .= ".function-details.shown { display: block; }\n";
$html .= ".function-usages { background: #e8f4f8; padding: 12px 15px; margin: 10px 0; border-left: 3px solid #3498db; border-radius: 3px; }\n";
$html .= ".function-usages strong { display: block; margin-bottom: 8px; color: #2c3e50; }\n";
$html .= ".function-usages ul { margin: 0; padding-left: 20px; }\n";
$html .= ".function-usages li { margin: 6px 0; font-size: 12px; color: #555; }\n";
$html .= ".function-usages code { display: inline; background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #bbb; }\n";
$html .= ".function-code { background: #f8f8f8; padding: 15px; margin: 15px 0; border-radius: 4px; border-left: 3px solid #3498db; overflow-x: auto; }\n";
$html .= ".function-code code { display: block; font-family: 'Courier New', monospace; font-size: 12px; color: #2c3e50; white-space: pre-wrap; word-break: break-word; line-height: 1.5; }\n";
$html .= ".search-box { margin: 20px 0; }\n";
$html .= "input[type=\"text\"] { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }\n";
$html .= ".file-section.hidden-search { display: none; }\n";
$html .= ".function-item.hidden-search { display: none; }\n";
$html .= ".unused-section { background: #ffe6e6; border: 2px solid #cc0000; border-radius: 4px; overflow: hidden; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }\n";
$html .= ".unused-header { background: #cc0000; color: white; padding: 15px 20px; font-weight: bold; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center; }\n";
$html .= ".unused-header:hover { background: #bb0000; }\n";
$html .= ".unused-content { padding: 20px; }\n";
$html .= ".hidden { display: none !important; }\n";
$html .= "</style>\n</head>\n<body>\n";
$html .= "<header>\n<div class=\"container\">\n";
$html .= "<h1>🔍 MOOP JavaScript Function Registry</h1>\n";
$html .= "<p>Auto-generated function documentation • Generated: " . date('Y-m-d H:i:s') . "</p>\n";
$html .= "</div>\n</header>\n<div class=\"container\">\n";

// Search box
$html .= "<div class=\"search-box\">\n";
$html .= "<input type=\"text\" id=\"searchInput\" placeholder=\"🔍 Search functions, files, or usage...\" onkeyup=\"filterSearch()\">\n";
$html .= "</div>\n";

// Statistics
$html .= "<div class=\"stats\">\n";
$html .= "<div class=\"stat-box\"><strong>" . array_sum(array_map('count', $registry)) . "</strong><span>Total Functions</span></div>\n";
$html .= "<div class=\"stat-box\"><strong>" . count($registry) . "</strong><span>Files</span></div>\n";
$html .= "</div>\n";

// Find unused functions
$unusedFunctions = [];
foreach ($registry as $jsFile => $functions) {
    foreach ($functions as $func) {
        $usages = findJsFunctionUsages($func['name'], $jsDir, $jsFile, $func['line']);
        if (empty($usages)) {
            $unusedFunctions[] = [
                'name' => $func['name'],
                'file' => $jsFile,
                'line' => $func['line']
            ];
        }
    }
}

// Show unused functions section
if (!empty($unusedFunctions)) {
    $html .= "<div class=\"unused-section\">\n";
    $html .= "<div class=\"unused-header\" onclick=\"toggleUnused(this)\">\n";
    $html .= "<span>⚠️ " . count($unusedFunctions) . " Unused Function(s) Found</span>\n";
    $html .= "<span class=\"unusedArrow\">▶</span>\n";
    $html .= "</div>\n";
    $html .= "<div class=\"unused-content hidden\">\n";
    $html .= "<p style=\"margin: 10px 0; color: #555;\">These functions are defined but never called:</p>\n";
    $html .= "<ul style=\"margin: 10px 0; padding-left: 20px;\">\n";
    foreach ($unusedFunctions as $func) {
        $html .= "<li><strong style=\"color: #cc0000;\">" . htmlspecialchars($func['name']) . "()</strong> in <code>" . htmlspecialchars($func['file']) . "</code> (line " . $func['line'] . ")</li>\n";
    }
    $html .= "</ul>\n";
    $html .= "</div>\n";
    $html .= "</div>\n";
}

// Expand/Collapse buttons
$html .= "<div style=\"display: flex; gap: 10px; margin: 20px 0;\">\n";
$html .= "<button id=\"toggleBtn\" onclick=\"toggleAll()\" style=\"flex: 1; padding: 10px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;\">📂 Expand All</button>\n";
$html .= "</div>\n";

// Files by file
ksort($registry);
foreach ($registry as $jsFile => $functions) {
    $html .= "<div class=\"file-section\">\n";
    $html .= "<div class=\"file-header\">📄 " . htmlspecialchars($jsFile) . " (" . count($functions) . ")</div>\n";
    
    // Show PHP includes
    $fileInfo = "";
    if (isset($phpIncludes[$jsFile])) {
        $fileInfo .= "<div class=\"file-info\">\n";
        $fileInfo .= "<strong>📋 Included in:</strong>\n";
        $fileInfo .= "<div class=\"php-pages\">\n";
        foreach ($phpIncludes[$jsFile] as $phpFile) {
            $fileInfo .= "<span class=\"php-page\">" . htmlspecialchars($phpFile) . "</span>\n";
        }
        $fileInfo .= "</div>\n";
        $fileInfo .= "</div>\n";
    }
    
    $html .= "<div class=\"functions-list hidden\">\n";
    $html .= $fileInfo;
    
    foreach ($functions as $func) {
        $usages = findJsFunctionUsages($func['name'], $jsDir, $jsFile, $func['line']);
        
        $html .= "<div class=\"function-item\">\n";
        $html .= "<div class=\"function-header\" onclick=\"event.stopPropagation(); this.nextElementSibling.classList.toggle('shown')\">\n";
        $html .= "<div><span class=\"function-name\">" . htmlspecialchars($func['name']) . "()</span><span class=\"function-counter\">" . count($usages) . "</span> <span class=\"function-line\">Line " . $func['line'] . "</span></div>\n";
        $html .= "<span>▶</span>\n";
        $html .= "</div>\n";
        $html .= "<div style=\"font-family: 'Courier New', monospace; font-size: 12px; color: #666; padding: 8px 15px; border-bottom: 1px solid #ecf0f1; user-select: all; cursor: copy;\" title=\"Click to select\">" . htmlspecialchars($jsFile) . ": " . htmlspecialchars($func['name']) . "()</div>\n";
        
        $html .= "<div class=\"function-details\">\n";
        if (!empty($usages)) {
            $usagesByFile = [];
            foreach ($usages as $usage) {
                if (!isset($usagesByFile[$usage['file']])) {
                    $usagesByFile[$usage['file']] = [];
                }
                $usagesByFile[$usage['file']][] = $usage;
            }
            
            $html .= "<div class=\"function-usages\">\n";
            $html .= "<strong>📍 Called in " . count($usagesByFile) . " file(s) (" . count($usages) . " times):</strong>\n";
            $html .= "<ul>\n";
            foreach ($usagesByFile as $fileKey => $fileUsages) {
                $html .= "<li><strong>" . htmlspecialchars($fileKey) . "</strong> (" . count($fileUsages) . "x)\n";
                $html .= "<ul style=\"margin-top: 5px;\">\n";
                foreach ($fileUsages as $usage) {
                    $html .= "<li><code>line " . $usage['line'] . "</code>: <small>" . htmlspecialchars(substr($usage['context'], 0, 100)) . "</small></li>\n";
                }
                $html .= "</ul></li>\n";
            }
            $html .= "</ul>\n";
            $html .= "</div>\n";
        } else {
            $html .= "<p><em>Not called anywhere</em></p>\n";
        }
        
        // Show function code
        if (!empty($func['code'])) {
            $html .= "<div class=\"function-code\">\n";
            $html .= "<code>" . htmlspecialchars($func['code']) . "</code>\n";
            $html .= "</div>\n";
        }
        
        $html .= "</div>\n";
        $html .= "</div>\n";
    }
    
    $html .= "</div>\n";
    $html .= "</div>\n";
}

$html .= "</div>\n";
$html .= "<script>\n";
$html .= "document.addEventListener('DOMContentLoaded', function() {\n";
$html .= "  document.querySelectorAll('.file-header').forEach(h => h.addEventListener('click', function() {\n";
$html .= "    this.closest('.file-section').querySelector('.functions-list').classList.toggle('hidden');\n";
$html .= "  }));\n";
$html .= "});\n";
$html .= "\n";
$html .= "function toggleUnused(header) {\n";
$html .= "  const content = header.nextElementSibling;\n";
$html .= "  const arrow = header.querySelector('.unusedArrow');\n";
$html .= "  content.classList.toggle('hidden');\n";
$html .= "  arrow.textContent = content.classList.contains('hidden') ? '▶' : '▼';\n";
$html .= "}\n";
$html .= "\n";
$html .= "function expandAll() {\n";
$html .= "  document.querySelectorAll('.functions-list').forEach(list => list.classList.remove('hidden'));\n";
$html .= "}\n";
$html .= "\n";
$html .= "function collapseAll() {\n";
$html .= "  document.querySelectorAll('.functions-list').forEach(list => list.classList.add('hidden'));\n";
$html .= "}\n";
$html .= "\n";
$html .= "function toggleAll() {\n";
$html .= "  const lists = document.querySelectorAll('.functions-list');\n";
$html .= "  const hiddenCount = document.querySelectorAll('.functions-list.hidden').length;\n";
$html .= "  const btn = document.getElementById('toggleBtn');\n";
$html .= "  if (hiddenCount > 0) {\n";
$html .= "    expandAll();\n";
$html .= "    btn.textContent = '📁 Collapse All';\n";
$html .= "  } else {\n";
$html .= "    collapseAll();\n";
$html .= "    btn.textContent = '📂 Expand All';\n";
$html .= "  }\n";
$html .= "}\n";
$html .= "\n";
$html .= "function updateToggleButton() {\n";
$html .= "  const btn = document.getElementById('toggleBtn');\n";
$html .= "  const hiddenCount = document.querySelectorAll('.functions-list.hidden').length;\n";
$html .= "  if (hiddenCount === 0) {\n";
$html .= "    btn.textContent = '📁 Collapse All';\n";
$html .= "  } else {\n";
$html .= "    btn.textContent = '📂 Expand All';\n";
$html .= "  }\n";
$html .= "}\n";
$html .= "\n";
$html .= "function filterSearch() {\n";
$html .= "  const searchTerm = document.getElementById('searchInput').value.toLowerCase();\n";
$html .= "  const fileSections = document.querySelectorAll('.file-section');\n";
$html .= "  \n";
$html .= "  fileSections.forEach(section => {\n";
$html .= "    const fileName = section.querySelector('.file-header').textContent.toLowerCase();\n";
$html .= "    const functionItems = section.querySelectorAll('.function-item');\n";
$html .= "    let hasVisibleFunction = false;\n";
$html .= "    \n";
$html .= "    functionItems.forEach(item => {\n";
$html .= "      const funcName = item.querySelector('.function-name').textContent.toLowerCase();\n";
$html .= "      const funcDetails = item.querySelector('.function-details').textContent.toLowerCase();\n";
$html .= "      const match = funcName.includes(searchTerm) || funcDetails.includes(searchTerm) || fileName.includes(searchTerm);\n";
$html .= "      \n";
$html .= "      if (searchTerm === '' || match) {\n";
$html .= "        item.classList.remove('hidden-search');\n";
$html .= "        hasVisibleFunction = true;\n";
$html .= "      } else {\n";
$html .= "        item.classList.add('hidden-search');\n";
$html .= "      }\n";
$html .= "    });\n";
$html .= "    \n";
$html .= "    if (searchTerm === '' || hasVisibleFunction || fileName.includes(searchTerm)) {\n";
$html .= "      section.classList.remove('hidden-search');\n";
$html .= "      if (searchTerm !== '') {\n";
$html .= "        section.querySelector('.functions-list').classList.remove('hidden');\n";
$html .= "      } else {\n";
$html .= "        section.querySelector('.functions-list').classList.add('hidden');\n";
$html .= "      }\n";
$html .= "    } else {\n";
$html .= "      section.classList.add('hidden-search');\n";
$html .= "    }\n";
$html .= "  });\n";
$html .= "  updateToggleButton();\n";
$html .= "}\n";
$html .= "</script>\n";
$html .= "</body>\n</html>\n";

$htmlFile = $docs_path . '/js_function_registry.html';
file_put_contents($htmlFile, $html);

// Generate Markdown
$md = "# JavaScript Function Registry\n\n";
$md .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
$md .= "## Summary\n";
$md .= "- **Total Functions**: " . array_sum(array_map('count', $registry)) . "\n";
$md .= "- **Files Scanned**: " . count($registry) . "\n\n";

foreach ($registry as $jsFile => $functions) {
    $md .= "## " . $jsFile . "\n\n";
    $md .= "**" . count($functions) . " function(s)**\n\n";
    
    if (isset($phpIncludes[$jsFile])) {
        $md .= "**Included in:**\n";
        foreach ($phpIncludes[$jsFile] as $phpFile) {
            $md .= "- `" . $phpFile . "`\n";
        }
        $md .= "\n";
    }
    
    foreach ($functions as $func) {
        $usages = findJsFunctionUsages($func['name'], $jsDir, $jsFile, $func['line']);
        $md .= "### `" . $func['name'] . "()` (Line " . $func['line'] . ")\n\n";
        
        if (!empty($usages)) {
            $usagesByFile = [];
            foreach ($usages as $usage) {
                if (!isset($usagesByFile[$usage['file']])) {
                    $usagesByFile[$usage['file']] = [];
                }
                $usagesByFile[$usage['file']][] = $usage;
            }
            $md .= "**Called in " . count($usagesByFile) . " file(s) (" . count($usages) . " times):**\n";
            foreach ($usagesByFile as $fileKey => $fileUsages) {
                $md .= "- `" . $fileKey . "` (" . count($fileUsages) . "x):\n";
                foreach ($fileUsages as $usage) {
                    $md .= "  - Line " . $usage['line'] . ": `" . addslashes($usage['context']) . "`\n";
                }
            }
        } else {
            $md .= "**Not called anywhere**\n";
        }
        $md .= "\n";
    }
    
    $md .= "---\n\n";
}

$mdFile = $docs_path . '/JS_FUNCTION_REGISTRY.md';
file_put_contents($mdFile, $md);

echo "✅ JavaScript Function Registry generated!\n";
echo "   Total Functions: " . array_sum(array_map('count', $registry)) . "\n";
echo "   Files Scanned: " . count($registry) . "\n";
echo "   Output: docs/js_function_registry.html\n";
echo "           docs/JS_FUNCTION_REGISTRY.md\n";
?>
