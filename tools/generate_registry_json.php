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
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../includes/config_init.php';
require_once __DIR__ . '/../lib/registry_sources.php';   // moop_registry_relpath()
$config = ConfigManager::getInstance();
$docs_path = $config->getPath('docs_path');

$registry = [];

// ── What to scan ────────────────────────────────────────────────────────────────────
//
// Two DIFFERENT things, kept separate because conflating them caused three bugs:
//
//   $scanDirs      — directories of MOOP code, scanned RECURSIVELY. lib/jbrowse/,
//                    admin/api/, admin/pages/ and tools/pages/ hold 104 PHP files that
//                    were never scanned, because the old code globbed '$dir/*.php'
//                    (top level only). Every function in them — the JBrowse layer, every
//                    admin API endpoint — was absent from the registry.
//
//   $rootPhpFiles  — the top-level scripts (login.php, index.php, setup.php...). The old
//                    list said `__DIR__ . '/..'`, meaning "also the root". For the
//                    definition pass that behaved as top-level-only (glob), but the usage
//                    pass walked it RECURSIVELY — through organisms/ (thousands of files,
//                    hundreds of GB), vendor/ and data/, hunting PHP call sites that
//                    cannot be there. It died on data/tracks/bed, which is labelled
//                    user_home_t and unreadable to httpd_t, and the admin saw only
//                    "Failed to generate php registry". Naming the intent removes both
//                    the fatal and the pointless walk — no prune list to maintain.
//
// includes/ was missing entirely until 2026-07-16, hiding 23 functions including the whole
// access-control layer (is_logged_in, has_access, get_access_level, has_assembly_access,
// csrf_input_field) and genes_gff_filename() — the functions CLAUDE.md tells every
// developer to use. A registry that silently omits a directory is worse than none:
// "not in the registry" reads as "does not exist", which is how a helper gets rewritten.
$scanDirs = [
    __DIR__ . '/../lib',
    __DIR__ . '/../tools',
    __DIR__ . '/../admin',
    __DIR__ . '/../includes',
    // api/ was missing until 2026-10-29: every public endpoint (api/, api/jbrowse2/) went
    // unscanned, so a function used only by an endpoint was listed as UNUSED — an invitation
    // to delete something live. Keep in step with lib/registry_sources.php.
    __DIR__ . '/../api',
    // scripts/ added 2026-11-25: functions only the command-line scripts call (the JBrowse
    // track-sheet loader, the build_* scripts) were listed UNUSED -- deleting them would have
    // broken those scripts silently.
    __DIR__ . '/../scripts',
];
// Searched for USES only (their own functions are not documented): the tests, and the
// config files that name functions as strings ('available_if' => 'moop_crosswalk_available').
$usageOnlyFiles = array_merge(
    moop_registry_php_files([__DIR__ . '/../tests'], []),
    glob(__DIR__ . '/../config/*.php') ?: []
);
$rootPhpFiles = glob(__DIR__ . '/../*.php') ?: [];


/**
 * Every PHP file the registry considers: $scanDirs recursively, plus the top-level
 * scripts. CATCH_GET_CHILD skips a directory that cannot be opened rather than letting
 * one unreadable path abort the whole run.
 *
 * @return list<string> absolute paths
 */
function moop_registry_php_files(array $scanDirs, array $rootPhpFiles): array {
    $out = $rootPhpFiles;
    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
    }
    return array_values(array_unique($out));
}

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
    // SQL verbs are matched case-SENSITIVELY as words: the old /i matched "$updated",
    // "delete_button" etc. Filesystem writes are matched as calls.
    if (preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|REPLACE\s+INTO|CREATE\s+TABLE|DROP\s+TABLE)\b/', $code) ||
        preg_match('/CURLOPT_CUSTOMREQUEST[^;]*[\'"](?:DELETE|PUT|PATCH)[\'"]/', $code) ||
        preg_match('/\b(?:file_put_contents|fwrite|fputcsv|unlink|rename|mkdir|rmdir|chmod|chown|chgrp|copy|touch|symlink|move_uploaded_file|tempnam)\s*\(/i', $code)) {
        $tags[] = 'mutation';
    } else {
        $tags[] = 'readonly';
    }
    
    // Check if it handles errors
    // Real handling only -- bare "error" matched any variable or message containing the word.
    if (preg_match('/\btry\s*\{|\bcatch\s*\(|\bthrow\b|set_error_handler/i', $code)) {
        $tags[] = 'error-handling';
    }
    
    // Check if it uses database
    // Calls, not substrings: "query"/"fetch" used to match $query_string, prefetch, etc.
    if (preg_match('/\bnew\s+PDO\b|->(?:prepare|query|exec|execute|fetch|fetchAll|fetchColumn)\s*\(|\b(?:fetchData|fetch_one|fetch_all)\w*\s*\(/', $code)) {
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
    
    // Recursion: look in the BODY only. $code begins with "function NAME(", so searching all
    // of it found the definition itself and tagged all 647 functions "recursive".
    $bodyStart = strpos($code, '{');
    $body = $bodyStart === false ? '' : substr($code, $bodyStart);
    if (preg_match('/(?<![\w$>:])' . preg_quote($funcName, '/') . '\s*\(|(?:\$this->|self::|static::)' . preg_quote($funcName, '/') . '\s*\(/', $body)) {
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
    
    // Non-PHP parts (HTML, the tags) are replaced by blanks of the SAME length, keeping their
    // newlines, so an offset in $phpContent is the same offset in the file. They used to be
    // dropped, and the line number was then guessed from strpos($content, $funcName) -- the
    // first place the name appeared ANYWHERE (a call, a longer name, a comment): 482 of 647
    // functions pointed at the wrong line.
    $blank = function ($t) { return preg_replace('/[^\n]/', ' ', $t); };
    foreach ($parts as $part) {
        if ($part === $phpTag) {
            $inPhp = true;
            $phpContent .= $blank($part);
        } elseif ($part === $closeTag) {
            $inPhp = false;
            $phpContent .= $blank($part);
        } else {
            $phpContent .= $inPhp ? $part : $blank($part);
        }
    }
    $phpLen = strlen($phpContent);
    
    // Match PHP function definitions.
    //
    // The `(?::\s*[?\w\\\\|]+\s*)?` before the brace matches a RETURN TYPE. Without it the
    // regex required `)` to be followed straight by `{`, so every function declared
    // `function f(): string {` was invisible — 152 of them as of 2026-07-16, including
    // genes_gff_filename(). The count the registry printed was confidently wrong.
    //
    // The parameter list is `[^)]*`, which cannot match a default value containing `)`.
    // Left as-is deliberately: a real fix needs a parser, not a bigger regex, and this is
    // documentation tooling — but it is why a rare function may still go missing.
    if (preg_match_all(
        '/^\s*(?:public\s+|private\s+|protected\s+)?(?:static\s+)?function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*(?::\s*[?\w\\\\|]+\s*)?\{/m',
        $phpContent, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $idx => $match) {
            $funcName = $matches[1][$idx][0];
            $startPos = $match[1];
            $namePos = $matches[1][$idx][1];
            $lineNum = substr_count($phpContent, "\n", 0, $namePos) + 1;
            // A method (inside a class) may share its name with methods of other classes;
            // only top-level functions can be true duplicates.
            $isMethod = (bool) preg_match('/^\s*(?:public|private|protected|static|abstract|final)\b/', $match[0])
                || moop_registry_in_class($phpContent, $startPos);
            
            // Extract comment
            $comment = extractCommentBlock($phpContent, $startPos);
            
            // Extract function code
            $end = moop_registry_body_end($phpContent, $startPos);
            $functionCode = substr($phpContent, $startPos, $end - $startPos);
            
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
                'tags' => $tags,
                'isMethod' => $isMethod,
            ];
        }
    }
    
    return $functions;
}

/**
 * Offset just past the closing brace of the function starting at $start. Braces inside
 * strings and comments are skipped: counting them raw ran a body on into the NEXT function
 * whenever a regex or message held a "{" (it made determineJsTags() look recursive).
 */
function moop_registry_body_end(string $code, int $start): int {
    $len = strlen($code);
    $depth = 0;
    $opened = false;
    for ($i = $start; $i < $len; $i++) {
        $c = $code[$i];
        if ($c === "'" || $c === '"') {                       // skip a quoted string
            for ($i++; $i < $len && $code[$i] !== $c; $i++) {
                if ($code[$i] === '\\') $i++;
            }
            continue;
        }
        if ($c === '/' && ($code[$i + 1] ?? '') === '*') {    // skip /* ... */
            $close = strpos($code, '*/', $i + 2);
            $i = $close === false ? $len : $close + 1;
            continue;
        }
        if (($c === '/' && ($code[$i + 1] ?? '') === '/') || $c === '#') {   // skip // or # line
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
 * Is $pos inside a class/trait/interface body? Finds the last class opener before $pos and
 * checks its braces are still open. (Plain brace depth would also call a function inside an
 * `if (!function_exists(...)) {` guard a method.) Braces in strings can skew it; good enough
 * for documentation tooling.
 */
function moop_registry_in_class(string $code, int $pos): bool {
    $head = substr($code, 0, $pos);
    if (!preg_match_all('/^\s*(?:abstract\s+|final\s+)?(?:class|trait|interface)\s+\w+[^{;]*\{/m', $head, $m, PREG_OFFSET_CAPTURE)) {
        return false;
    }
    $last = end($m[0]);
    $inside = substr($head, $last[1] + strlen($last[0]));
    return substr_count($inside, '{') - substr_count($inside, '}') >= 0;
}

/**
 * Find function usages in codebase
 */
function findFunctionUsages($funcName, $scanDirs, $definitionFile) {
    global $rootPhpFiles;
    $usages = [];
    $seen = [];
    $q = preg_quote($funcName, '/');
    // A call, OR the name passed as a string callable ('usort($a, 'cmp_fn')',
    // array_map('fn', ...), register_shutdown_function('fn')) -- those used to count as
    // no use at all, so a live callback was listed UNUSED.
    $searchPattern = '/\b' . $q . '\s*\(|([\'"])' . $q . '\1/';
    $excludeDirs = ['docs', 'logs', 'notes', 'not_used', '.git'];

    // Cached: the same file list serves every function, and there are hundreds.
    static $allFiles = null;
    if ($allFiles === null) {
        global $usageOnlyFiles;
        $allFiles = array_values(array_unique(array_merge(
            moop_registry_php_files($scanDirs, $rootPhpFiles ?: []), $usageOnlyFiles ?? [])));
    }

    {
        $files = $allFiles;

        foreach ($files as $filePath) {
            // $filePath is an absolute path string (moop_registry_php_files already
            // filtered to real .php files), not an SplFileInfo.
            $filePath = realpath($filePath) ?: $filePath;

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
            
            static $contentCache = [];
            $content = $contentCache[$filePath] ??= (string) @file_get_contents($filePath);

            if (preg_match_all($searchPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lines = explode("\n", $content);   // once per file, not once per match
                foreach ($matches[0] as $match) {
                    $lineNum = substr_count($content, "\n", 0, $match[1]) + 1;
                    $contextLine = isset($lines[$lineNum - 1]) ? trim($lines[$lineNum - 1]) : '';

                    // Comment lines only. (A line merely CONTAINING /* ... */ used to be
                    // skipped whole, real call included.)
                    if (preg_match('/^(\/\/|#|\*|\/\*)/', $contextLine)) {
                        continue;
                    }
                    // The definition itself, whatever its modifiers. The old pattern allowed
                    // only public/static, so every private/protected method counted its own
                    // definition as a use and could never be reported unused.
                    if (preg_match('/\bfunction\s+&?' . $q . '\s*\(/', $contextLine)) {
                        continue;
                    }
                    // A function_exists('name') guard is not a use -- but only THIS match is
                    // skipped: `function_exists('f') ? f() : null` still counts its real call.
                    if (preg_match('/function_exists\s*\(\s*$/', substr($content, max(0, $match[1] - 40), min(40, $match[1])))) {
                        continue;
                    }

                    $relativeFilePath = moop_registry_relpath($filePath);
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

// Recursive over the code dirs + the top-level scripts. The old form globbed
// '$dir/*.php', which silently skipped lib/jbrowse/, admin/api/, admin/pages/ and
// tools/pages/ — 104 PHP files whose functions were absent from the registry.
foreach ([null] as $_) {
    $files = moop_registry_php_files($scanDirs, $rootPhpFiles);
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
        
        $relativePath = moop_registry_relpath($file);
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

// Recursive over the code dirs + the top-level scripts. The old form globbed
// '$dir/*.php', which silently skipped lib/jbrowse/, admin/api/, admin/pages/ and
// tools/pages/ — 104 PHP files whose functions were absent from the registry.
foreach ([null] as $_) {
    $files = moop_registry_php_files($scanDirs, $rootPhpFiles);
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
        
        $relativePath = moop_registry_relpath($file);
        $functions = extractFunctions($file, $allFunctionNames);  // Second pass WITH dependencies
        
        if (!empty($functions)) {
            $registry[$relativePath] = $functions;
        }
    }
}

// A function that calls a writer writes too (moop_feedback_set_status() saves through
// moop_feedback_write()). Propagated to a fixpoint, through top-level functions with a
// UNIQUE name only -- a call to "get" or "delete" could be any of several methods.
$nameCount = [];
foreach ($registry as $functions) {
    foreach ($functions as $func) {
        if (empty($func['isMethod'])) $nameCount[$func['name']] = ($nameCount[$func['name']] ?? 0) + 1;
    }
}
$writers = [];
foreach ($registry as $functions) {
    foreach ($functions as $func) {
        if (in_array('mutation', $func['tags'], true) && ($nameCount[$func['name']] ?? 0) === 1) $writers[$func['name']] = true;
    }
}
do {
    $changed = false;
    foreach ($registry as &$functions) {
        foreach ($functions as &$func) {
            if (in_array('mutation', $func['tags'], true)) continue;
            foreach ($func['internalCalls'] as $called) {
                if (isset($writers[$called]) && $called !== $func['name']) {
                    $func['tags'] = array_values(array_diff($func['tags'], ['readonly']));
                    array_unshift($func['tags'], 'mutation');
                    if (empty($func['isMethod']) && ($nameCount[$func['name']] ?? 0) === 1) $writers[$func['name']] = true;
                    $changed = true;
                    break;
                }
            }
        }
        unset($func);
    }
    unset($functions);
} while ($changed);

// Find duplicates and check for unused functions
$funcMap = [];
$duplicates = [];
$totalFuncs = 0;

foreach ($registry as $file => $functions) {
    foreach ($functions as $func) {
        $totalFuncs++;
        if (!empty($func['isMethod'])) continue;   // Foo::get and Bar::get are not duplicates
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
    // Keep the file group-writable so it can be regenerated by either the CLI
    // user or the web-server user (php-fpm) — otherwise whoever wrote it last
    // owns it read-only to the other and the admin "Generate" button fails.
    @chmod($jsonFile, 0664);
    echo "\n✅ Registry generated successfully!\n";
    echo "   File: " . moop_registry_relpath($jsonFile) . "\n";
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
