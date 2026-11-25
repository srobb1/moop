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
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/registry_sources.php';

$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$registry = [];

// A named function, or a const/let/var bound to a function or ARROW function. The old second
// pattern accepted "= (" alone, so `const total = (a + b) * 2` was registered as a function.
const MOOP_JS_FUNC_PATTERNS = [
    '/(?:^|\s)(?:async\s+)?function\s*\*?\s*([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/m',
    '/(?:^|\s)(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s+)?(?:function\b|\([^()]*(?:\([^()]*\)[^()]*)*\)\s*=>|[a-zA-Z_$][\w$]*\s*=>)/m',
];

/**
 * End offset of a JS function starting at $start: past the matching } of its body, skipping
 * strings, template literals and comments. An arrow function with an expression body (no
 * braces) ends at the end of its statement. There used to be a 2,000-character cap, which
 * cut 28 functions short, and an arrow without braces swallowed the NEXT function's body.
 */
function moop_js_body_end(string $code, int $start): int {
    $len = strlen($code);
    // Where does the body begin? After "=>" for arrows, else at the first "{" after the ")".
    $head = substr($code, $start, 600);
    $arrow = preg_match('/^[^{;]*?=>\s*/s', $head, $am) ? $start + strlen($am[0]) : null;
    if ($arrow !== null && ($code[$arrow] ?? '') !== '{') {
        // Expression body: up to the ; or newline at depth 0.
        $depth = 0;
        for ($i = $arrow; $i < $len; $i++) {
            $c = $code[$i];
            if ($c === '(' || $c === '[' || $c === '{') $depth++;
            elseif ($c === ')' || $c === ']' || $c === '}') { if (--$depth < 0) return $i; }
            elseif (($c === ';' || $c === "\n") && $depth === 0) return $i;
        }
        return $len;
    }
    $i = $arrow ?? $start;
    $depth = 0;
    $opened = false;
    for (; $i < $len; $i++) {
        $c = $code[$i];
        if ($c === "'" || $c === '"' || $c === '`') {
            for ($i++; $i < $len && $code[$i] !== $c; $i++) {
                if ($code[$i] === '\\') $i++;
            }
            continue;
        }
        if ($c === '/' && ($code[$i + 1] ?? '') === '*') {
            $close = strpos($code, '*/', $i + 2);
            $i = $close === false ? $len : $close + 1;
            continue;
        }
        if ($c === '/' && ($code[$i + 1] ?? '') === '/') {
            $nl = strpos($code, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }
        if ($c === '{') { $depth++; $opened = true; }
        elseif ($c === '}' && --$depth === 0 && $opened) return $i + 1;
    }
    return $len;
}

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
    
    // Utilities first: escapeHtml() in manage-registry.js used to land in "admin" (filename
    // test ran first), so the Utilities filter matched nothing at all.
    if (strpos($funcName, 'escape') !== false || strpos($funcName, 'sanitize') !== false ||
        $filename === 'utilities.js' || $filename === 'utils.js') {
        return 'utilities';
    }

    // UI/DOM manipulation. Filename tested as a word: a bare 'ui' substring matched
    // "build", "guide", "quick"...
    if (strpos($filename, 'dom') !== false || preg_match('/(^|[-_.])ui([-_.]|$)/', $filename) ||
        strpos($funcName, 'render') !== false || strpos($funcName, 'display') !== false ||
        strpos($funcName, 'show') !== false || strpos($funcName, 'hide') !== false) {
        return 'ui-dom';
    }
    
    // Event handling
    if (strpos($funcName, 'event') !== false || strpos($funcName, 'listener') !== false ||
        strpos($funcName, 'handler') !== false || strpos($funcName, 'click') !== false) {
        return 'event-handling';
    }
    
    // Data processing. ('filter'/'search' names used to be caught HERE, so the Search/Filter
    // test below could only ever match on filename.)
    if (strpos($funcName, 'parse') !== false || strpos($funcName, 'extract') !== false ||
        strpos($funcName, 'transform') !== false || strpos($funcName, 'convert') !== false) {
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
    
    return 'general';
}

/**
 * Determine tags for JS function
 */
function determineJsTags($comment, $code, $funcName) {
    $tags = [];
    
    // DOM manipulation
    // Changes the page (reading it with getElementById/querySelector is not manipulation).
    if (preg_match('/\.(?:innerHTML|outerHTML|textContent|value)\s*=(?!=)|\b(?:appendChild|removeChild|insertBefore|insertAdjacentHTML|replaceChildren|setAttribute|removeAttribute)\s*\(|\.classList\.(?:add|remove|toggle)\s*\(|\.style\.\w+\s*=(?!=)|\$\([^)]*\)\.(?:html|append|prepend|empty|remove|addClass|removeClass|toggleClass|show|hide|text)\s*\(/', $code)) {
        $tags[] = 'dom-manipulation';
    }
    
    // Asynchronous
    if (preg_match('/\basync\b|\bawait\b|\.then\s*\(|\bPromise\b|\bsetTimeout\s*\(|\bsetInterval\s*\(/', $code)) {
        $tags[] = 'asynchronous';
    }
    
    // AJAX/HTTP
    // Network calls. The old /\.get/i matched every getElementById and getAttribute.
    if (preg_match('/\bfetch\s*\(|\bXMLHttpRequest\b|\$\.(?:ajax|get|post|getJSON)\s*\(|\.ajax\s*\(|\bnavigator\.sendBeacon\s*\(/', $code)) {
        $tags[] = 'ajax';
    }
    
    // Event listener
    // /on\w+\s*=/i used to match "const option =", "json =" ...
    if (preg_match('/\baddEventListener\s*\(|\.on[a-z]+\s*=(?!=)|\.on\s*\(|\.(?:click|change|submit|keyup|keydown)\s*\(\s*(?:function|\()/', $code)) {
        $tags[] = 'event-listener';
    }
    
    // State modification
    // Changes something OUTSIDE its own local variables: a property, an array element, a
    // global, or a mutating array call. (The old /\w+\s*=/ matched any assignment -- even
    // "===" -- so 480 of 587 functions were "state modifying".)
    if (preg_match('/(?:[\w$\])]\.[\w$]+|\])\s*(?:[+\-*\/|&]?=)(?!=)|\b(?:window|globalThis)\.\w+\s*=(?!=)|\.(?:push|pop|shift|unshift|splice|sort|reverse)\s*\(|\bdelete\s+[\w$]/', $code)) {
        $tags[] = 'state-modifying';
    }
    
    // Loop operations
    if (preg_match('/forEach|for\s*\(|while\s*\(/i', $code)) {
        $tags[] = 'loops';
    }
    
    // Error handling
    if (preg_match('/\btry\s*\{|\.catch\s*\(|\bthrow\b|\bonerror\b|\.fail\s*\(|\berror\s*:\s*(?:function|\()/', $code)) {
        $tags[] = 'error-handling';
    }
    
    // Validation
    // 'check' alone matched every ".checked".
    if (preg_match('/\bvalidat\w*|\bverify\w*|\bcheckValidity\s*\(|\bisValid\w*/i', $code)) {
        $tags[] = 'validation';
    }
    
    return array_values(array_unique($tags));
}

// Scan JS files and extract functions -- the SAME list the staleness check watches
// (lib/registry_sources.php; .min.js and "unused" already filtered there).
echo "🔍 Scanning JavaScript files...\n";
$files = moop_registry_source_files('js');

// First pass: collect all function names
echo "🔍 Scanning JavaScript files (pass 1: collecting function names)...\n";
$allJsFunctionNames = [];
$jsFilesContent = [];

foreach ($files as $file) {
    if (strpos($file, '.min.js') !== false || strpos($file, 'unused') !== false) continue;
    
    $content = file_get_contents($file);
    $jsFilesContent[$file] = $content;
    
    // Match function declarations and arrow functions
    $patterns = MOOP_JS_FUNC_PATTERNS;
    
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
    $patterns = MOOP_JS_FUNC_PATTERNS;
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $match) {
                $funcName = $matches[1][$idx][0];
                $startPos = $match[1];
                // Line of the NAME: the pattern's leading \s can start on the line before.
                $lineNum = substr_count($content, "\n", 0, $matches[1][$idx][1]) + 1;
                
                // The whole function, signature included (so the code shown is readable),
                // from the keyword -- not the leading whitespace the pattern may have eaten.
                $defStart = $startPos + strlen($match[0]) - strlen(ltrim($match[0]));
                $functionCode = substr($content, $defStart, moop_js_body_end($content, $defStart) - $defStart);
                
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
    
    // In file order (the two patterns used to list all named functions first, then all arrows).
    usort($functions, function ($a, $b) { return $a['line'] <=> $b['line']; });

    if (!empty($functions)) {
        $relativePath = moop_registry_relpath($file);
        $registry[$relativePath] = $functions;
    }
}

// Every PHP file, read once. Was: glob() with "admin/**/*.php", which PHP does not treat as
// recursive -- lib/, includes/ and api/ were never searched, so a JS function called only
// from includes/layout.php or lib/tool_section.php was listed UNUSED. The files were also
// re-globbed and re-read once per function.
$phpSources = [];
foreach (moop_registry_source_files('php') as $phpFile) {
    $phpSources[$phpFile] = (string) @file_get_contents($phpFile);
}
$jsSources = [];
foreach ($files as $jsFile) {
    $jsSources[$jsFile] = $jsFilesContent[$jsFile] ?? (string) @file_get_contents($jsFile);
}

// Find which PHP files include/use each JS file
echo "🔍 Scanning for PHP files using JavaScript...\n";
$phpFilesByJsFile = [];
foreach ($registry as $jsFile => $funcs) {
    $jsFileName = basename($jsFile);
    $phpFiles = [];
    
    foreach ($phpSources as $phpFile => $phpText) {
        // As a path segment ("/name.js"), not a substring: "search.js" used to match
        // "advanced-search.js".
        if (preg_match('#(?:^|[/\'"])' . preg_quote($jsFileName, '#') . '\b#', $phpText)) {
            $phpFiles[] = moop_registry_relpath($phpFile);
        }
    }
    
    if (!empty($phpFiles)) {
        $phpFilesByJsFile[$jsFile] = array_unique($phpFiles);
    }
}

// Find function usages in JS and PHP files
echo "🔍 Scanning for function usages...\n";

// ($scanDirs used to be declared here and was never read — the usage scan below builds
// its own file lists with glob(). Removed rather than left lying around: it contained the
// same `__DIR__ . '/..'` document-root entry that made the PHP generator walk organisms/
// and die on an unreadable directory. Dead code that looks authoritative is how the next
// person reintroduces the bug — they wire up the list that is already sitting there.)

foreach ($registry as $jsFile => &$functions) {
    foreach ($functions as &$func) {
        $usages = [];
        $seen = [];
        $q = preg_quote($func['name'], '/');
        // A call -- OR the function handed over as a value: addEventListener('click', fn),
        // .on('change', fn), { success: fn }, x.onclick = fn, setTimeout(fn, ...). Only calls
        // used to count, so every handler bound with a listener (as the inline-handler
        // cleanup now does throughout) would have been reported UNUSED.
        // (?<![\w$]) and "not after a lone dot" -- but a spread, ...name(, is a call.
        $searchPattern = '/(?<![\w$])(?:(?<=\.\.\.)|(?<!\.))' . $q . '\s*\(|(?<=[(,:=])\s*' . $q . '\s*(?=[,);}\n])/';

        foreach ($jsSources as $file => $content) {
            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lines = explode("\n", $content);
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;

                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    // Skip comments
                    if (preg_match('/^\s*(\/\/|\/\*|\*|#)/', $contextLine)) continue;
                    
                    // Skip the definition itself
                    if (preg_match('/(?:^|[\s(])(?:function\s*\*?|const|let|var)\s+' . $q . '\s*[=:\(]/', $contextLine)) continue;
                    
                    $relativeFile = moop_registry_relpath($file);
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
        
        // Also search PHP files (inline JS, inline_scripts, handler attributes). Calls only
        // here: a bare PHP "name" next to a comma is far too likely to be something else.
        $phpCallPattern = '/(?<![\w$.>:])' . $q . '\s*\(/';
        foreach ($phpSources as $phpFile => $content) {
            if (preg_match_all($phpCallPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lines = explode("\n", $content);
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;

                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';
                    
                    // Skip PHP comments, and a PHP function that merely shares the name
                    if (preg_match('/^\s*(\/\/|\/\*|\*|#)/', $contextLine)) continue;
                    if (preg_match('/\bfunction\s+' . $q . '\s*\(/', $contextLine)) continue;
                    
                    $relativeFile = moop_registry_relpath($phpFile);
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
// BOTH references must go. $functions was left bound to the last registry entry, so the
// next `foreach ($registry as $file => $functions)` wrote every file into it: the last file
// (js/verify-human.js) was listed with the previous file's functions.
unset($func, $functions);

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
        // Same name defined in more than one file (was hard-coded 0; there are dozens).
        'duplicates' => count(array_filter(array_count_values(array_merge(...array_map(
            function ($fe) { return array_unique(array_column($fe['functions'], 'name')); }, $fileArray ?: [[ 'functions' => [] ]]
        ))), function ($n) { return $n > 1; })),
    ],
    'files' => $fileArray,
    'unused' => $unused,
];

// Save JSON
$jsonFile = $docs_path . '/js_function_registry.json';
@mkdir(dirname($jsonFile), 0755, true);

$json = json_encode($registryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonFile, $json)) {
    // Keep group-writable so both the CLI user and the web-server user (php-fpm)
    // can regenerate it — otherwise the admin "Generate" button fails on the
    // second run. See generate_registry_json.php for the same fix.
    @chmod($jsonFile, 0664);
    echo "\n✅ JavaScript Registry generated successfully!\n";
    echo "   File: " . moop_registry_relpath($jsonFile) . "\n";
    echo "   Total Functions: " . $totalFuncs . "\n";
    echo "   Files Scanned: " . count($fileArray) . "\n";
    echo "   Unused Functions: " . count($unused) . "\n";
} else {
    echo "\n❌ Error writing JSON file\n";
    exit(1);
}
?>

