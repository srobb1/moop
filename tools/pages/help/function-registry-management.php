<?php
/**
 * FUNCTION REGISTRY MANAGEMENT - Admin Help Tutorial
 * 
 * Available variables:
 * - $config (ConfigManager instance)
 * - $siteTitle (Site title)
 */
?>

<div class="container mt-5">
  <!-- Back to Help Link -->
  <div class="mb-4">
    <a href="help.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Help
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <h1 class="fw-bold mb-4"><i class="fa fa-list-check"></i> Function Registry Management</h1>

      <!-- Overview Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">What is the Function Registry?</h3>
          <p class="text-muted mb-3">
            The Function Registry is a comprehensive catalog of all PHP and JavaScript functions used throughout the MOOP codebase. It provides developers and administrators with:
          </p>
          <ul class="text-muted mb-0">
            <li><strong>Complete function inventory:</strong> Every function defined in the PHP and JavaScript code</li>
            <li><strong>Function dependencies:</strong> Which functions call which other functions</li>
            <li><strong>Usage tracking:</strong> Where each function is used throughout the codebase</li>
            <li><strong>Documentation:</strong> Parameter types, return types, and PHPDoc/JSDoc comments</li>
            <li><strong>Code quality insights:</strong> Identify unused functions, duplicates, and potential issues</li>
          </ul>
        </div>
      </div>

      <!-- Why It Matters Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Why Function Registries Matter</h3>
          
          <h5 class="fw-semibold text-dark mt-3 mb-2">For Developers:</h5>
          <ul class="text-muted">
            <li>Quickly understand the codebase structure and organization</li>
            <li>Find where functions are defined and used</li>
            <li>Understand function relationships and dependencies</li>
            <li>Refactor with confidence by seeing all usages</li>
            <li>Identify code that can be reused or consolidated</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">For Administrators:</h5>
          <ul class="text-muted">
            <li>Monitor code quality and identify maintenance issues</li>
            <li>Track down unused or redundant code</li>
            <li>Understand system capabilities</li>
            <li>Plan refactoring efforts</li>
            <li>Ensure coding standards are being followed</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">For the System:</h5>
          <ul class="text-muted mb-0">
            <li>Detects duplicate function definitions (code smell)</li>
            <li>Identifies functions with no callers (dead code)</li>
            <li>Provides metadata for code analysis and optimization</li>
            <li>Supports documentation generation</li>
          </ul>
        </div>
      </div>

      <!-- PHP Registry Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">PHP Function Registry</h3>
          <p class="text-muted mb-3">
            The PHP registry catalogs all custom functions defined in the PHP codebase across the following directories:
          </p>

          <div class="bg-light p-3 rounded mb-3">
            <p class="mb-2"><strong>Scanned Directories:</strong></p>
            <ul class="mb-0 text-muted" style="font-family: monospace;">
              <li>lib/ - Shared library functions</li>
              <li>tools/ - Tool implementations</li>
              <li>admin/ - Administrator interface functions</li>
              <li>Root level - Site configuration and main entry points</li>
            </ul>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Accessing the PHP Registry:</h5>
          <ol class="text-muted">
            <li>Navigate to the Admin panel</li>
            <li>Select "Manage Function Registry"</li>
            <li>Browse the interactive PHP function catalog</li>
            <li>View staleness indicators (registry age)</li>
            <li>Click on individual functions to view details</li>
          </ol>

          <h5 class="fw-semibold text-dark mt-3 mb-2">What You'll See for Each Function:</h5>
          <ul class="text-muted mb-0">
            <li><strong>Name:</strong> The function identifier</li>
            <li><strong>File and line:</strong> Where the function is defined</li>
            <li><strong>Comment:</strong> PHPDoc documentation block</li>
            <li><strong>Parameters:</strong> Input parameters with types and descriptions</li>
            <li><strong>Return type:</strong> What the function returns</li>
            <li><strong>Category:</strong> Database, filesystem, security, UI, etc.</li>
            <li><strong>Tags:</strong> mutation, readonly, error-handling, database-dependent, etc.</li>
            <li><strong>Internal calls:</strong> Other functions this function calls</li>
            <li><strong>Usage count:</strong> How many times this function is called</li>
            <li><strong>Usages:</strong> Files and line numbers where it's used</li>
          </ul>
        </div>
      </div>

      <!-- JavaScript Registry Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">JavaScript Function Registry</h3>
          <p class="text-muted mb-3">
            The JavaScript registry catalogs all custom functions defined in the JavaScript codebase. It's particularly useful for understanding UI interactions and event handling.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">JavaScript Function Types:</h5>
          <ul class="text-muted">
            <li><strong>Function declarations:</strong> <code>function myFunction() { }</code></li>
            <li><strong>Arrow functions:</strong> <code>const myFunc = () => { }</code></li>
            <li><strong>Variable assignments:</strong> <code>const myFunc = function() { }</code></li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Accessing the JavaScript Registry:</h5>
          <ol class="text-muted">
            <li>Navigate to the Admin panel</li>
            <li>Select "Manage JavaScript Registry"</li>
            <li>Browse the interactive JavaScript function catalog</li>
            <li>Click on individual functions to view details</li>
          </ol>

          <h5 class="fw-semibold text-dark mt-3 mb-2">JavaScript-Specific Information:</h5>
          <ul class="text-muted mb-0">
            <li><strong>JS-to-JS calls:</strong> Which JavaScript functions call each other</li>
            <li><strong>PHP inclusion:</strong> Which PHP files include/reference the JavaScript file</li>
            <li><strong>Tags:</strong> dom-manipulation, asynchronous, ajax, event-listener, state-modifying, etc.</li>
            <li><strong>Categories:</strong> UI/DOM, event-handling, data-processing, search-filter, admin tools, etc.</li>
          </ul>
        </div>
      </div>

      <!-- Generating Registries Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Generating and Updating Registries</h3>
          <p class="text-muted mb-3">
            Registries are generated by running scripts that scan your codebase and extract function metadata. They should be regenerated whenever code is modified.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">PHP Registry Generation:</h5>
          <div class="bg-light p-3 rounded mb-3">
            <p class="text-muted mb-2"><strong>From command line:</strong></p>
            <pre class="text-muted mb-0" style="font-size: 0.9em; overflow-x: auto;">php tools/generate_registry_json.php</pre>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">JavaScript Registry Generation:</h5>
          <div class="bg-light p-3 rounded mb-3">
            <p class="text-muted mb-2"><strong>From command line:</strong></p>
            <pre class="text-muted mb-0" style="font-size: 0.9em; overflow-x: auto;">php tools/generate_js_registry_json.php</pre>
          </div>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Output Files:</h5>
          <ul class="text-muted mb-0">
            <li><strong>PHP:</strong> <code>docs/function_registry.json</code></li>
            <li><strong>JavaScript:</strong> <code>docs/js_function_registry.json</code></li>
          </ul>
        </div>
      </div>

      <!-- Registry Staleness Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Registry Staleness Indicators</h3>
          <p class="text-muted mb-3">
            The registry management pages show when the registry was last generated and whether it's considered current:
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Status Indicators:</h5>
          <ul class="text-muted">
            <li><strong>Fresh (green):</strong> Registry generated within the last 7 days</li>
            <li><strong>Stale (yellow):</strong> Registry is older than 7 days but still available</li>
            <li><strong>No registry:</strong> Registry has never been generated or files are missing</li>
          </ul>

          <div class="bg-info bg-opacity-10 p-3 rounded mt-3 mb-0">
            <i class="fa fa-info-circle text-info"></i> <strong>Recommendation:</strong> Regenerate registries regularly, especially after major code changes or new feature additions. Consider automating registry generation as part of your deployment process.
          </div>
        </div>
      </div>

      <!-- Understanding Function Metadata Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Understanding Function Metadata</h3>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Categories</h5>
          <p class="text-muted mb-3">
            Functions are automatically categorized based on filename and function name patterns:
          </p>
          <ul class="text-muted">
            <li><strong>database:</strong> Database query and operations functions</li>
            <li><strong>filesystem:</strong> File and directory manipulation</li>
            <li><strong>validation:</strong> Input validation and sanitization</li>
            <li><strong>security:</strong> Access control and authentication</li>
            <li><strong>configuration:</strong> System configuration functions</li>
            <li><strong>data-processing:</strong> Parse, extract, transform, convert functions</li>
            <li><strong>organisms:</strong> Organism and genome-related functions</li>
            <li><strong>tools-blast:</strong> BLAST functionality</li>
            <li><strong>search:</strong> Search and indexing functions</li>
            <li><strong>ui:</strong> Display, rendering, and UI functions</li>
            <li><strong>utility:</strong> General utility functions</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Tags (PHP)</h5>
          <p class="text-muted mb-3">
            Tags provide additional metadata about function behavior:
          </p>
          <ul class="text-muted">
            <li><strong>mutation:</strong> Function modifies data (files, database, etc.)</li>
            <li><strong>readonly:</strong> Function only reads, doesn't modify</li>
            <li><strong>error-handling:</strong> Function includes try/catch or error management</li>
            <li><strong>database-dependent:</strong> Function uses database operations</li>
            <li><strong>file-io:</strong> Function performs file input/output</li>
            <li><strong>helper:</strong> Utility or helper function</li>
            <li><strong>security-related:</strong> Function handles passwords, auth, permissions</li>
            <li><strong>loops:</strong> Function contains loops (performance consideration)</li>
            <li><strong>recursive:</strong> Function calls itself</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Tags (JavaScript)</h5>
          <ul class="text-muted mb-0">
            <li><strong>dom-manipulation:</strong> Modifies the DOM</li>
            <li><strong>asynchronous:</strong> Uses async/await or Promises</li>
            <li><strong>ajax:</strong> Makes HTTP requests</li>
            <li><strong>event-listener:</strong> Adds or manages event listeners</li>
            <li><strong>state-modifying:</strong> Changes application state</li>
            <li><strong>loops:</strong> Contains loop operations</li>
            <li><strong>error-handling:</strong> Has try/catch blocks</li>
            <li><strong>validation:</strong> Validates input or data</li>
          </ul>
        </div>
      </div>

      <!-- Finding Unused Functions Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Finding and Managing Unused Functions</h3>
          <p class="text-muted mb-3">
            The registry tracks functions that have zero usages throughout the codebase. These are candidates for cleanup:
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">How to Find Unused Functions:</h5>
          <ol class="text-muted">
            <li>Generate the registry (PHP or JavaScript)</li>
            <li>Scroll to the "Unused Functions" section in the manage page</li>
            <li>Review the list of functions with no callers</li>
          </ol>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Important Considerations Before Deleting:</h5>
          <ul class="text-muted">
            <li><strong>Public APIs:</strong> Functions may be called by external code or plugins</li>
            <li><strong>Event handlers:</strong> JavaScript functions may be called via HTML event attributes</li>
            <li><strong>Hooks:</strong> Functions may be called dynamically via do_action hooks</li>
            <li><strong>Callbacks:</strong> Functions may be registered as callbacks</li>
            <li><strong>Backwards compatibility:</strong> Removing functions breaks code that depends on them</li>
          </ul>

          <div class="bg-warning bg-opacity-10 p-3 rounded mt-3 mb-0">
            <i class="fa fa-exclamation-triangle text-warning"></i> <strong>Caution:</strong> Don't delete unused functions without careful review. Consider leaving them if they're part of a public API, documented interface, or important for backwards compatibility.
          </div>
        </div>
      </div>

      <!-- Detecting Duplicates Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Detecting Duplicate Functions</h3>
          <p class="text-muted mb-3">
            The registry can identify functions with the same name defined in multiple files (usually an error):
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Why Duplicates Matter:</h5>
          <ul class="text-muted">
            <li>Indicates accidental code duplication</li>
            <li>Can cause unexpected behavior if wrong version is loaded</li>
            <li>Makes maintenance harder (bugs must be fixed in multiple places)</li>
            <li>Wastes memory and slows performance</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Viewing Duplicates:</h5>
          <ol class="text-muted mb-0">
            <li>Generate the registry</li>
            <li>The manage page shows a duplicate count at the top</li>
            <li>In the metadata section, see which functions are duplicated</li>
            <li>Consolidate duplicate functions into a single, shared location</li>
          </ol>
        </div>
      </div>

      <!-- Best Practices Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Best Practices for Function Registry</h3>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Documentation Standards</h5>
          <ul class="text-muted">
            <li>Always include PHPDoc comments for PHP functions</li>
            <li>Always include JSDoc comments for JavaScript functions</li>
            <li>Document parameter types, return types, and descriptions</li>
            <li>Explain complex logic in function comments</li>
            <li>Use standard @param and @return tags</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Code Organization</h5>
          <ul class="text-muted">
            <li>Keep related functions together in the same file</li>
            <li>Use consistent naming conventions across the codebase</li>
            <li>Avoid duplicate functionality; refactor shared code into utilities</li>
            <li>Place shared functions in the lib/ directory</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Registry Maintenance</h5>
          <ul class="text-muted">
            <li>Regenerate registries regularly (weekly at minimum for active projects)</li>
            <li>Review the registry metadata when refactoring code</li>
            <li>Use unused function detection to guide cleanup efforts</li>
            <li>Monitor for duplicate definitions</li>
            <li>Commit registry files to version control for history tracking</li>
          </ul>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Development Workflow</h5>
          <ul class="text-muted mb-0">
            <li>Before refactoring, check registry to understand function dependencies</li>
            <li>After adding new functions, regenerate registry</li>
            <li>After major changes, run registry generation and review results</li>
            <li>Treat registry updates as part of the deployment process</li>
          </ul>
        </div>
      </div>

      <!-- Troubleshooting Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Troubleshooting</h3>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Registry not loading in admin?</h5>
          <p class="text-muted mb-3">
            Verify the registry JSON files exist at <code>docs/function_registry.json</code> and <code>docs/js_function_registry.json</code>. If not, run the generation scripts from the command line.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Registry generation fails?</h5>
          <p class="text-muted mb-3">
            Check that the docs/ directory is writable. Ensure PHP has permission to create and modify files. The scripts require at least 512MB of PHP memory for large codebases.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Functions not showing up?</h5>
          <p class="text-muted mb-3">
            Functions must have proper documentation comments (/** */ blocks) to be extracted. Ensure your functions follow standard syntax. Class methods may not be captured in the current implementation.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Registry shows old data?</h5>
          <p class="text-muted mb-3">
            The registry is static and only updates when generation scripts are run. Regenerate the registry after any code changes.
          </p>

          <h5 class="fw-semibold text-dark mt-3 mb-2">Can't see JavaScript functions?</h5>
          <p class="text-muted mb-0">
            The JavaScript registry scans js/ and js/modules/ directories. Ensure your JS files are in the correct location and use standard function declaration syntax (function keyword, const/let/var assignments, or arrow functions).
          </p>
        </div>
      </div>

      <!-- Summary Section -->
      <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-4">
          <h3 class="fw-bold text-dark mb-3">Key Takeaways</h3>
          <ul class="text-muted mb-0">
            <li>Function registries provide a complete inventory of your codebase</li>
            <li>They track function dependencies, usages, and relationships</li>
            <li>Both PHP and JavaScript functions are cataloged separately</li>
            <li>Registries help identify unused functions and code duplicates</li>
            <li>They support code refactoring, documentation, and maintenance</li>
            <li>Registries should be regenerated regularly as code changes</li>
            <li>Proper function documentation makes registries more useful</li>
            <li>Use registry insights to improve code quality and organization</li>
          </ul>
        </div>
      </div>

      <div class="mb-4">
        <a href="help.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Back to Help
        </a>
      </div>
    </div>
  </div>
</div>
