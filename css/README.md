# CSS Organization & Guidelines

## File Structure

**CSS Directory Organization:**
```
css/
├── README.md                              # This file
├── moop.css                               # Base/global styles (ALWAYS included)
├── bootstrap.min.css                      # Bootstrap 5.3.2 framework
├── datatables.css & datatables.min.css    # DataTables table styling
├── datatables/                            # DataTables plugin CSS
│
├── Page-Specific Stylesheets (loaded conditionally):
│   ├── advanced-search-filter.css         # Advanced search filter UI
│   ├── display.css                        # Organism/assembly display page
│   ├── manage-*.css                       # Admin management pages
│   ├── parent.css                         # Feature detail page
│   ├── registry.css                       # Function registry UI
│   ├── retrieve-selected-sequences.css    # Sequence download tool
│   └── search-controls.css                # Search controls
│
└── Third-Party Libraries (CDN):
    └── Bootstrap, Font Awesome, DataTables (loaded from CDN in head-resources.php)
```

## When to Create CSS Files

**Create a new CSS file when:**

1. **Page-specific styling** - Page has unique layout or component styles
   - Example: `manage-groups.css` for the group management admin page
   - Only loaded when that page is displayed

2. **Feature module styling** - Shared UI component used across multiple pages
   - Example: `advanced-search-filter.css` for search UI components
   - Loaded whenever feature is needed

3. **Complex styling** - Styles exceed ~200 lines in `moop.css`
   - For readability and organization
   - Easier to maintain separate logical units

**Do NOT create CSS file for:**
- Single-element fixes (use inline styles - see below)
- Temporary styling during development (use inline first, move to file if permanent)
- Animation-only changes (keep in appropriate page CSS)

## How to Include CSS Files

### In Controllers (Recommended)

Use `page_styles` array in `display_config`:

```php
$display_config = [
    'title' => 'Manage Groups',
    'content_file' => __DIR__ . '/pages/manage_groups.php',
    'page_styles' => [
        '/' . $site . '/css/manage-groups.css'     // Page-specific
    ],
    'page_script' => [
        '/' . $site . '/js/modules/manage-groups.js'
    ]
];
```

**How it works:**
1. Controller sets `page_styles` array
2. `layout.php` renders `<link>` tags in `<head>`
3. CSS loads **after** Bootstrap (so you can override Bootstrap)
4. CSS loads **before** page scripts (so JS can manipulate styled elements)

### Global Styles

**Always loaded on every page:**
- `bootstrap.min.css` - Bootstrap framework (from CDN)
- `moop.css` - Base MOOP styles
- `datatables.css` - DataTables table styling (when using tables)

Located in `head-resources.php` - should **not** be changed per-page

## Inline CSS vs CSS Files

### When to Use Inline Styles (✅ Acceptable)

**Single element, simple styling:**
```html
<div style="margin-top: 10px; padding: 5px;">Content</div>
```

**Use inline when:**
1. Style applies to **one element only**
2. Style is **very simple** (1-3 properties)
3. Style is **temporary** (dev/test) or will never be reused
4. Dynamic styling from PHP variables:
   ```html
   <div style="color: <?php echo $dynamicColor; ?>;">Content</div>
   ```

**Example: Acceptable inline styles**
```html
<span style="font-weight: bold;">Important</span>
<div style="display: none;" id="loadingSpinner">Loading...</div>
<p style="margin-bottom: 20px;">Introductory text</p>
```

### When to Use CSS Files (✅ Preferred)

**Any styling that:**
1. Affects **multiple elements**
2. Is **reusable** across pages
3. Involves **media queries** or complex selectors
4. Uses **class names** (for reusability)
5. Is **more than 3 CSS properties**

**Example: Should be in CSS file**
```php
// DON'T do this in HTML:
<div style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">

// DO this in CSS file instead:
// manage-groups.css:
.group-card {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 5px;
}

// In HTML:
<div class="group-card">
```

## Inline JavaScript vs JS Files

### When to Use Inline JavaScript (⚠️ Limited & Cautious)

**ONLY for configuration that MUST be set in HTML context:**

```php
// In controller:
'inline_scripts' => [
    "const sitePath = '/" . $site . "';",        // Config variable
    "const isAdmin = " . ($is_admin ? 'true' : 'false') . ";",  // Auth flag
    "const userId = " . json_encode($user_id) . ";"  // User ID
]

// In HTML page:
<script>
    const configValue = <?php echo json_encode($config); ?>;
    const isMobile = window.innerWidth < 768;
</script>
```

**Use inline scripts ONLY when:**
1. **PHP variable needed** - Can't access PHP in external JS file
2. **Runtime detection needed** - Client-side device/browser detection
3. **Very short** - 1-2 lines maximum
4. **Page-specific** - Not used elsewhere
5. **Must execute immediately** - Before modules load

**Examples of acceptable inline scripts:**
```php
// Pass PHP config to JavaScript
'inline_scripts' => [
    "const API_ENDPOINT = '/" . $site . "/api/';",
    "const currentUserId = " . $user_id . ";"
]

// Detect device type
<script>
    const isMobile = /iPhone|iPad|Android/i.test(navigator.userAgent);
</script>

// Set feature flag based on config
<script>
    const hasBlastTools = <?php echo $config->hasBlastTools() ? 'true' : 'false'; ?>;
</script>
```

### When to Use JavaScript Files (✅ Preferred)

**Any logic or event handling:**

```php
// DON'T do this inline:
'inline_scripts' => [
    "
    jQuery(document).ready(function() {
        $('button').click(function() {
            $.ajax({url: '/api/action', success: function(data) {...}});
        });
    });
    "
]

// DO this in js/modules/ or js/page-name.js:
// js/modules/button-handler.js:
jQuery(document).ready(function() {
    $('button').click(function() {
        $.ajax({url: '/api/action', success: function(data) {...}});
    });
});

// In controller:
'page_script' => [
    '/' . $site . '/js/modules/button-handler.js'
]
```

**Use external JS files for:**
1. **Any event handlers** - Click, submit, change, etc.
2. **AJAX requests** - Data fetching and updates
3. **DOM manipulation** - Adding/removing/modifying elements
4. **Reusable logic** - Functions used across pages
5. **More than 2 lines** - Better readability in dedicated file
6. **Complex logic** - Easier to debug and maintain

## Best Practices

### CSS Organization

1. **Group related styles** - Keep similar selectors together
   ```css
   /* Table styling */
   table { ... }
   thead { ... }
   tbody { ... }
   
   /* Button styling */
   .btn { ... }
   .btn-primary { ... }
   .btn-secondary { ... }
   ```

2. **Use class names** - Not element selectors (more reusable)
   ```css
   /* ✅ Good - Reusable */
   .form-error { color: red; }
   
   /* ❌ Avoid - Too specific */
   .manage-groups-form input[type="text"] { color: red; }
   ```

3. **Follow Bootstrap naming** - Use Bootstrap classes first
   ```html
   <!-- ✅ Use Bootstrap classes -->
   <button class="btn btn-primary">Save</button>
   
   <!-- ❌ Create custom when Bootstrap doesn't fit -->
   <button style="custom-button: blue;">Save</button>
   ```

4. **Avoid !important** - Use specificity instead
   ```css
   /* ❌ Avoid */
   .element { color: red !important; }
   
   /* ✅ Preferred - Better specificity */
   .form-group .element { color: red; }
   ```

5. **Order properties logically:**
   - Positioning (position, top, left, z-index)
   - Box model (margin, padding, width, height)
   - Border & background
   - Typography (font, color, text-align)
   - Visual effects (opacity, transform, box-shadow)

### JavaScript Best Practices

1. **Use modules for reusable code** - Not inline scripts
2. **Pass config via inline only** - Not logic
3. **Document complex functions** - JSDoc comments
4. **Use event delegation** - For dynamic elements
5. **Avoid global variables** - Namespace with module pattern

## CSS Load Order (In Head)

```
1. Meta tags (viewport, charset)
2. Bootstrap 5.3.2 (CDN)
3. moop.css (base styles)
4. Page-specific CSS (manage-groups.css, etc.)
5. DataTables CSS
6. Font Awesome (icons)
```

**Why this order:**
- Bootstrap loads first (baseline)
- moop.css overrides Bootstrap defaults
- Page-specific CSS overrides both
- DataTables and icons load last (don't need overriding)

## JavaScript Load Order (In Body)

```
1. jQuery (CDN) - Required by DataTables/legacy code
2. Bootstrap JS (CDN) - Modals, dropdowns, etc.
3. DataTables JS & plugins (CDN)
4. Inline scripts (config variables)
5. Page module scripts (shared utilities first)
6. Page-specific scripts (page functionality last)
```

**Why this order:**
- jQuery loads first (dependency for others)
- Bootstrap JS loads after jQuery
- Shared modules load before page-specific
- Inline config loads before scripts that need it
- Page scripts load last (can call all dependencies)

## Troubleshooting

**Styles not applying?**
1. Check CSS file is in `page_styles` array
2. Verify file path is correct
3. Check browser developer tools for file 404
4. Ensure CSS loads **after** Bootstrap (higher specificity needed)
5. Check for typos in class names

**JavaScript not working?**
1. Check JS file is in `page_script` array
2. Verify jQuery is loaded (check console for $ errors)
3. Check for JavaScript errors in console
4. Verify modules load **before** page-specific script
5. Check inline scripts have correct variable names

**Inline vs File Conflict?**
- Keep configuration inline (PHP variables)
- Keep logic in files (reusable, testable)
- Inline scripts should only set variables

## Examples

### Add a new admin page with custom styling

```php
// admin/manage_something.php
$display_config = [
    'title' => 'Manage Something',
    'content_file' => __DIR__ . '/pages/manage_something.php',
    'page_styles' => [
        '/' . $site . '/css/manage-something.css'  // New CSS file
    ],
    'page_script' => [
        '/' . $site . '/js/modules/manage-something.js'  // New JS module
    ],
    'inline_scripts' => [
        "const sitePath = '/" . $site . "';"       // Config only
    ]
];
```

```css
/* css/manage-something.css */
.item-card {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
}

.item-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
```

```js
// js/modules/manage-something.js
jQuery(document).ready(function() {
    // Initialize management UI
    initializeItemManager();
});

function initializeItemManager() {
    // Logic here
}
```

## See Also

- `/MOOP_COMPREHENSIVE_OVERVIEW.md` - Styling section
- `/includes/head-resources.php` - Global CSS/JS includes
- `/includes/layout.php` - How page_styles are rendered
- `/admin/manage_groups.php` - Example implementation
