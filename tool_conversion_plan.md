# TOOLS CONVERSION PLAN - BLAST & SEQUENCE RETRIEVAL TOOLS

## Overview
Convert the remaining 3 tool pages (BLAST, Retrieve Sequences, Retrieve Selected Sequences) to use the new layout infrastructure following the display-template.php pattern established for organism/assembly/groups display pages.

## Current State Analysis

### Tools to Convert
1. **blast.php** (710 lines)
   - Handles BLAST search submissions
   - Displays form with database/sequence selection
   - Shows BLAST results
   - Heavy form interaction, complex state management

2. **retrieve_sequences.php** (463 lines)
   - Manual sequence search/download tool
   - Multi-page workflow (search → results → download)
   - Uses unique organism/assembly context

3. **retrieve_selected_sequences.php** (209 lines)
   - Simplified download tool
   - Takes pre-selected feature IDs
   - Shows multiple sequence types
   - Currently simplest of the three

### Current HTML Structure
- All 3 include custom `<html>`, `<head>`, `<body>` tags
- Manual navbar and footer inclusion
- No use of layout.php rendering system
- Inline styles and custom CSS
- Some inline JavaScript (especially blast.php)

### Key Differences from Display Pages
| Aspect | Display Pages | Tools |
|--------|---------------|-------|
| Purpose | Show data from database | Process forms, handle uploads/downloads |
| State | Single page, static | Multi-step, form-based |
| Output | HTML display | HTML display + file downloads |
| Complexity | Moderate | High (esp. BLAST with advanced options) |
| JavaScript | Moderate | Heavy (form validation, AJAX) |

## Conversion Strategy

### Option 1: Full Template Conversion (Recommended)
**Complexity: Medium | Effort: 3 conversions × 2-3 hours each**

**Pattern:**
```
tool.php (controller)
  ├─ Load tool_init.php
  ├─ Process form submissions / file downloads
  ├─ Build $data array with:
  │  ├─ Processed results
  │  ├─ Form state
  │  ├─ Error messages
  │  └─ Page-specific JS/CSS
  ├─ Set $display_config (title, content_file, etc)
  └─ include display-template.php

pages/blast.php (content view)
  └─ Render UI using $data
```

### Challenges & Solutions

#### Challenge 1: Form Submissions
**Problem:** These tools handle POST requests and maintain state across page loads.

**Solution:**
- Controller (tool.php) processes POST/GET before calling template
- All state variables passed in $data array
- Content file (pages/blast.php) renders forms with hidden inputs to preserve state
- Works same as current, just split across files

**Example:**
```php
// In blast.php (controller)
if ($_POST) {
    $blast_result = executeBlastSearch(...);
    $data['blast_result'] = $blast_result;
    $data['search_query'] = $search_query;
    $data['selected_source'] = $selected_source;
}

// In pages/blast.php (content)
<?php if (isset($data['blast_result'])): ?>
    <!-- Show BLAST results -->
<?php endif; ?>
<form method="POST">
    <input type="hidden" name="selected_source" value="<?= $data['selected_source'] ?>">
    ...
</form>
```

#### Challenge 2: File Downloads
**Problem:** BLAST results and sequence files need to be downloadable.

**Solution:**
- Check for download flag BEFORE calling template
- If download_file=1, send headers and exit (current behavior)
- If not, proceed with normal template rendering
- Keep download logic in controller (tool.php)

**Example:**
```php
// In retrieve_selected_sequences.php (controller)
if ($download_file_flag && !empty($sequence_type)) {
    sendFileDownload(...);
    exit; // Never reaches template
}

// Normal display path continues to template
```

#### Challenge 3: Complex JavaScript
**Problem:** blast.php has heavy client-side logic (500+ lines of JS).

**Solution:**
- Extract to pages/js/blast-tool.js
- Reference in $display_config['page_script']
- Existing inline JS becomes initial state in $display_config['inline_scripts']
- Zero changes to actual JS code, just relocated

## Detailed Conversion Steps

### Step 1: Create Content Files (pages/)
- **pages/blast.php** - Contains all HTML/form from current blast.php (lines 220-705)
- **pages/retrieve_sequences.php** - Contains all HTML/form
- **pages/retrieve_selected_sequences.php** - Contains all HTML/form

### Step 2: Refactor Controllers (tools/)
- Extract business logic (BLAST execution, sequence extraction) → stays in tool.php
- Extract HTML/form rendering → move to pages/
- Extract JavaScript → extract to js/blast-tool.js, etc
- Create $display_config and $data arrays
- Add `include display-template.php;` at end

### Step 3: Update Tool Pages to Use Template
- **blast.php** → processes forms, calls pages/blast.php
- **retrieve_sequences.php** → processes forms, calls pages/retrieve_sequences.php
- **retrieve_selected_sequences.php** → processes forms, calls pages/retrieve_selected_sequences.php

### Step 4: Extract JavaScript & CSS
- Move inline JS from blast.php → js/blast-tool.js
- Move inline styles → css/ (if significant)
- Reference in $display_config['page_script'] and $display_config['page_styles']

### Step 5: Test & Validate
- Test form submissions work correctly
- Test pre-population of form state
- Test file downloads bypass template
- Test access control still enforced
- Test with different user access levels

## Implementation Details

### Required Configuration (display-template.php style)

```php
// Example for blast.php
$display_config = [
    'title' => 'BLAST Search',
    'content_file' => __DIR__ . '/pages/blast.php',
    'page_script' => '/moop/js/blast-tool.js',
    'inline_scripts' => [
        "const sourcesByGroup = " . json_encode($sources_by_group) . ";",
        "const isLoggedIn = " . ($is_logged_in ? 'true' : 'false') . ";"
    ]
];

$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'accessible_sources' => $accessible_sources,
    'search_query' => $search_query,
    'selected_source' => $selected_source,
    'blast_program' => $blast_program,
    'search_error' => $search_error ?? '',
    'blast_result' => $blast_result ?? null,
    'blast_programs' => $blast_programs,
    'databases' => $databases,
    'page_styles' => [
        '/moop/css/display.css',
        '/moop/css/fasta.css' // if needed
    ]
];
```

### Expected File Structure After Conversion
```
tools/
├─ blast.php                      (refactored controller)
├─ retrieve_sequences.php         (refactored controller)
├─ retrieve_selected_sequences.php (refactored controller)
├─ display-template.php           (already exists)
├─ tool_init.php                  (already exists)
├─ sequences_display.php          (component, unchanged)
└─ pages/
   ├─ blast.php                   (new content file)
   ├─ retrieve_sequences.php      (new content file)
   └─ retrieve_selected_sequences.php (new content file)

js/
├─ blast-tool.js                  (extracted from blast.php)
├─ retrieve-sequences-tool.js     (extracted if needed)
└─ retrieve-selected-sequences.js (extracted if needed)

css/
├─ display.css                    (already exists)
├─ fasta.css                      (if consolidating FASTA styles)
└─ blast.css                      (if needed)
```

## Benefits

✓ **Consistency** - All tools/pages use same infrastructure
✓ **Maintainability** - Changes to layout affect all pages automatically
✓ **Reduced Duplication** - navbar, footer, styles loaded once
✓ **Easier Testing** - Controllers separated from HTML
✓ **Cleaner Code** - Less line-of-code per file, clearer responsibilities
✓ **Better Workflow** - Complex tools decomposed into logical sections

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Breaking form state preservation | Test all form submissions thoroughly |
| Download functionality breaks | Keep download check BEFORE template |
| JavaScript stops working | Extract carefully, preserve context |
| Access control bypassed | Verify access checks still in controller |
| Long refactor disrupts dev | Convert one tool at a time, test thoroughly |

## Estimated Effort

- **blast.php conversion**: 2-3 hours (largest, complex JS)
- **retrieve_sequences.php conversion**: 1-2 hours (moderate)
- **retrieve_selected_sequences.php conversion**: 0.5-1 hour (simplest)
- **Testing**: 1-2 hours (all workflows)

**Total: ~5-8 hours for all three**

## Next Steps

1. Start with **retrieve_selected_sequences.php** (smallest, lowest risk)
2. Verify pattern works with test submission/download
3. Move to **retrieve_sequences.php** (medium complexity)
4. Finally tackle **blast.php** (most complex)
5. Comprehensive testing of all tool workflows

