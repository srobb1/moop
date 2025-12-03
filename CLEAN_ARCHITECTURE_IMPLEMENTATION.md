# Clean Architecture Implementation Guide

## Phase 1: Infrastructure Setup

### Step 1a: Create includes/layout.php

This is the heart of the clean architecture system. It wraps content with HTML structure.

**File:** `/data/moop/includes/layout.php`

```php
<?php
/**
 * PAGE LAYOUT SYSTEM
 * 
 * Provides unified page rendering with automatic header/footer wrapping.
 * All display pages (organism.php, assembly.php, etc.) use this system.
 * 
 * This ensures:
 * - Consistent HTML structure across all pages
 * - Proper opening and closing of all tags
 * - Centralized control of layout changes
 * - Separation of content from structure
 * 
 * USAGE:
 *   echo render_display_page('tools/pages/organism.php', [
 *       'title' => 'Organism Name',
 *       'data' => $organism_data,
 *   ]);
 */

/**
 * Render a display page with full HTML structure
 * 
 * @param string $content_file Path to content file (relative or absolute)
 * @param array $data Data to pass to content file
 * @param string $title Page title
 * @return string Complete HTML page
 */
function render_display_page($content_file, $data = [], $title = '') {
    // Ensure config is loaded
    if (!class_exists('ConfigManager')) {
        include_once __DIR__ . '/config_init.php';
    }
    include_once __DIR__ . '/access_control.php';
    
    // Extract data to variables for use in content file
    extract($data);
    
    // Start output buffering
    ob_start();
    
    // Output HTML structure
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title><?= htmlspecialchars($title) ?></title>
        <?php include_once __DIR__ . '/head.php'; ?>
    </head>
    <body class="bg-light">
        <?php include_once __DIR__ . '/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <?php 
            // Include content file
            if (file_exists($content_file)) {
                include $content_file;
            } else {
                echo '<div class="alert alert-danger">Error: Content file not found.</div>';
            }
            ?>
        </div>
        
        <?php include_once __DIR__ . '/footer.php'; ?>
        
        <!-- Script management - all external scripts in one place -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
        
        <!-- MOOP shared modules -->
        <script src="/<?= $config->getString('site') ?>/js/modules/datatable-config.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/shared-results-table.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/annotation-search.js"></script>
        <script src="/<?= $config->getString('site') ?>/js/modules/advanced-search-filter.js"></script>
        
        <?php
        // If custom page-specific script is provided, include it
        if (isset($page_script)) {
            echo '<script src="' . htmlspecialchars($page_script) . '"></script>';
        }
        ?>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}

/**
 * Render a display page with custom footer
 * Useful for pages that need special footer content
 * 
 * @param string $content_file Path to content file
 * @param array $data Data to pass to content
 * @param string $title Page title
 * @param string $footer_file Custom footer file
 * @return string Complete HTML page
 */
function render_display_page_custom_footer($content_file, $data = [], $title = '', $footer_file = '') {
    // Similar to above but with custom footer option
    // ... implementation similar to above ...
}

/**
 * Render a display page as JSON (for AJAX requests)
 * Used when display pages need to return data instead of HTML
 * 
 * @param array $data Data to return as JSON
 * @param int $status HTTP status code
 * @return void (outputs JSON and exits)
 */
function render_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
```

### Step 1b: Create pages/ and admin/pages/ directories

```bash
mkdir -p /data/moop/tools/pages
mkdir -p /data/moop/admin/pages
```

---

## Phase 2: Create New Display Files

### File 1: tools/organism.php (REPLACES organism_display.php)

```php
<?php
/**
 * ORGANISM DISPLAY PAGE
 * 
 * Shows organism information, search, and available assemblies.
 * This file contains ONLY content-specific logic.
 * HTML structure is handled by layout.php
 * 
 * USAGE: Called by organism_display.php wrapper
 */

// Only get parameters - all includes/auth/config already done by wrapper
$organism_name = $_GET['organism'] ?? '';
$organism_data = $config->getPath('organism_data');

// Validate and load organism context
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data);
if (empty($organism_context)) {
    die("Error: Organism not found.");
}

$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];
$siteTitle = $config->getString('siteTitle');

// Get accessible assemblies for this organism
$group_data = getGroupData();
$accessible_assemblies = [];

foreach ($group_data as $data) {
    if ($data['organism'] === $organism_name) {
        if (has_assembly_access($organism_name, $data['assembly'])) {
            $accessible_assemblies[] = $data['assembly'];
        }
    }
}

// Get page data for layout
$page_data = [
    'title' => htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $siteTitle,
    'organism_name' => $organism_name,
    'organism_info' => $organism_info,
    'accessible_assemblies' => $accessible_assemblies,
    'page_script' => '/moop/js/organism-display.js',
];

// Get image data
$image_data = getOrganismImageWithCaption($organism_info, $config->getPath('images_path'), $config->getPath('absolute_images_path'));
$image_src = $image_data['image_path'];
$show_image = !empty($image_src);
?>

<!-- CONTENT ONLY - NO HTML STRUCTURE -->

<!-- Search Section -->
<div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <!-- Title Card -->
            <div class="card-header bg-light border-bottom">
                <h1 class="fw-bold mb-0 text-center">
                    <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> 
                        <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
                </h1>
            </div>

            <!-- Search Section -->
            <div class="card-body bg-search-light">
                <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations</h4>
                <form id="organismSearchForm">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" class="form-control" id="searchKeywords" 
                                       placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." 
                                       required>
                                <button type="submit" class="btn btn-icon btn-search" id="searchBtn" 
                                        title="Search" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <small class="form-text text-muted-gray">
                                Use quotes for exact phrases (e.g., "ABC transporter"). Searches this organism only.
                            </small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
        <?php
        $context = createToolContext('organism', [
            'organism' => $organism_name,
            'display_name' => $organism_info['common_name'] ?? $organism_name
        ]);
        include_once TOOL_SECTION_PATH;
        ?>
    </div>
</div>

<!-- Search Results Section -->
<div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-list"></i> Search Results</h4>
        </div>
        <div class="card-body">
            <div id="searchInfo" class="alert alert-info mb-3"></div>
            <div id="searchProgress" class="mb-3"></div>
            <div id="resultsContainer"></div>
        </div>
    </div>
</div>

<!-- Organism Header Section -->
<div class="row mb-4" id="organismHeader">
    <?php if ($show_image): ?>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <img src="<?= $image_src ?>" class="card-img-top" alt="<?= htmlspecialchars($organism_name) ?>">
                <?php if (!empty($image_data['caption'])): ?>
                    <div class="card-body">
                        <p class="card-text small text-muted">
                            <?php if (!empty($image_data['link'])): ?>
                                <a href="<?= $image_data['link'] ?>" target="_blank" class="text-decoration-none">
                                    <?= $image_data['caption'] ?> <i class="fa fa-external-link-alt fa-xs"></i>
                                </a>
                            <?php else: ?>
                                <?= $image_data['caption'] ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="<?= $show_image ? 'col-md-8' : 'col-12' ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="fw-bold mb-2">
                    <?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?>
                </h1>
                <h3 class="text-muted mb-3">
                    <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> 
                        <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
                </h3>
                
                <?php if (!empty($organism_info['taxon_id'])): ?>
                    <p class="mb-3">
                        <strong>Taxon ID:</strong> 
                        <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($organism_info['taxon_id']) ?>" 
                           target="_blank" class="text-decoration-none">
                            <?= htmlspecialchars($organism_info['taxon_id']) ?>
                            <i class="fa fa-external-link-alt fa-xs"></i>
                        </a>
                    </p>
                <?php endif; ?>

                <?php if (!empty($organism_info['subclassification']['type']) && !empty($organism_info['subclassification']['value'])): ?>
                    <p class="mb-3">
                        <strong><?= htmlspecialchars($organism_info['subclassification']['type']) ?>:</strong> 
                        <?= htmlspecialchars($organism_info['subclassification']['value']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Assemblies Section -->
<?php if (!empty($accessible_assemblies)): ?>
<div class="row mb-5">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title mb-4">Available Assemblies</h3>
                <div class="row g-3">
                    <?php foreach ($accessible_assemblies as $assembly): ?>
                        <?php $fasta_files = getAssemblyFastaFiles($organism_name, $assembly); ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm organism-card">
                                <div class="card-body text-center">
                                    <a href="/<?= $config->getString('site') ?>/tools/assembly_display.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>" 
                                       target="_blank" class="text-decoration-none">
                                        <h5 class="card-title mb-3">
                                            <?= htmlspecialchars($assembly) ?> <i class="fa fa-external-link-alt"></i>
                                        </h5>
                                    </a>
                                    <?php if (!empty($fasta_files)): ?>
                                        <div class="mt-3 pt-2 border-top">
                                            <?php foreach ($fasta_files as $type => $file_info): ?>
                                                <a href="/<?= $config->getString('site') ?>/tools/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>&type=<?= urlencode($type) ?>" 
                                                   class="btn btn-sm btn-primary w-100 mb-2" download>
                                                    <i class="fa fa-download"></i> <?= htmlspecialchars($file_info['label']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Data for page-specific JavaScript
    const sitePath = '/<?= $config->getString('site') ?>';
    const organismName = '<?= $organism_name ?>';
</script>
```

### Step 1c: Create NEW organism_display.php wrapper

This is the NEW entry point. It replaces the old one.

```php
<?php
/**
 * ORGANISM DISPLAY WRAPPER
 * 
 * Entry point for organism display.
 * Handles loading, validation, and rendering.
 * Content is in tools/pages/organism.php
 */

include_once __DIR__ . '/tools/tool_init.php';
include_once __DIR__ . '/includes/layout.php';

// Get parameters
$organism_name = $_GET['organism'] ?? '';
$organism_data = $config->getPath('organism_data');
$siteTitle = $config->getString('siteTitle');

// Validate organism exists
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data);
if (empty($organism_context)) {
    die("Error: Organism not found.");
}

$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];

// Render page using layout system
echo render_display_page(
    __DIR__ . '/tools/pages/organism.php',
    [
        'organism_name' => $organism_name,
        'organism_info' => $organism_info,
        'config' => $config,
    ],
    htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) . ' - ' . $siteTitle
);
?>
```

---

## Implementation Steps (DETAILED)

### Phase 1: Setup Infrastructure
- [ ] Create `includes/layout.php` with render functions
- [ ] Create `tools/pages/` directory  
- [ ] Update footer.php to include closing tags
- [ ] Test layout.php in isolation

### Phase 2: Convert organism_display
- [ ] Create `tools/pages/organism.php` with content
- [ ] Create NEW `tools/organism_display.php` wrapper
- [ ] Test organism.php in browser
- [ ] Compare with original (functionality identical?)
- [ ] Test links, downloads, tools
- [ ] Verify HTML structure in dev tools

### Phase 3: Convert assembly, groups, multi_organism
- [ ] Repeat for each file (similar process)
- [ ] Test each thoroughly
- [ ] Verify all AJAX works
- [ ] Test access control

### Phase 4: Convert parent (the complex one)
- [ ] Special handling for annotation display
- [ ] Keep complex logic in wrapper or separate functions
- [ ] Test thoroughly (most critical)

### Phase 5: Cleanup
- [ ] Verify all new pages work
- [ ] Backup old files
- [ ] Delete old `*_display.php` files (keep for safety for 1 week)
- [ ] Commit "Complete clean architecture migration"

---

## File Mapping

| Old File | New Wrapper | New Content |
|----------|-------------|------------|
| organism_display.php | tools/organism_display.php | tools/pages/organism.php |
| assembly_display.php | tools/assembly_display.php | tools/pages/assembly.php |
| groups_display.php | tools/groups_display.php | tools/pages/groups.php |
| multi_organism_search.php | tools/multi_organism_search.php | tools/pages/multi_organism.php |
| parent_display.php | tools/parent_display.php | tools/pages/parent.php |

---

## Expected Benefits

**Before (Old Files):**
- organism_display.php: 294 lines (30% content, 70% structure)
- assembly_display.php: 274 lines (25% content, 75% structure)
- Total: 1000+ lines with duplication

**After (New Files):**
- organism.php: ~150 lines (ONLY content, no structure)
- organism_display.php: ~30 lines (wrapper only)
- Total: ~500 lines, no duplication
- **50% reduction in code**
- **Bug-proof HTML structure**
- **Single point to change layout**

---

## Testing Checklist for Each Page

```
ORGANISM TEST:
[ ] Opens without errors
[ ] HTML valid (dev tools)
[ ] Search works
[ ] Assemblies show
[ ] Downloads work
[ ] Tools appear
[ ] Access control works
[ ] Links work

ASSEMBLY TEST:
[ ] Opens without errors
[ ] $assembly_accession bug fixed
[ ] HTML valid
[ ] Search works
[ ] Stats show
[ ] Downloads work

GROUPS TEST:
[ ] Opens without errors
[ ] Access control consistent
[ ] Search works
[ ] Organisms show
[ ] Links work

MULTI_ORGANISM TEST:
[ ] Opens without errors
[ ] Multiple organisms show
[ ] Search across all works

PARENT TEST:
[ ] Opens without errors
[ ] Annotations display
[ ] Complex features work
[ ] (Most critical - test thoroughly)
```

---

## Risk Mitigation

1. **Keep old files** for 1 week (fallback if issues)
2. **Test each page** before moving to next
3. **Small commits** after each file works
4. **No breaking changes** to API/URLs
5. **Separate content** from logic in pages/

---

Ready to start? I recommend beginning with **Step 1a & 1b** (infrastructure), then **Phase 2** (organism), then rolling out the others.

Should I create the layout.php file now?
