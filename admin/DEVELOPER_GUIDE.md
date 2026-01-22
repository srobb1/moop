# Admin Pages Developer Guide

## Overview

Admin pages follow a **controller + view template** architecture:
- **Controller** (root admin/*.php): Handles logic, validation, AJAX
- **View Template** (admin/pages/*.php): Renders HTML UI

---

## Quick Start: Creating an Admin Page

### Step 1: Create Controller

Create `/data/moop/admin/my_admin_page.php`:

```php
<?php
// 1. Start output buffering for AJAX handling
ob_start();

// 2. Initialize admin environment
include_once __DIR__ . '/admin_init.php';

// 3. Load page-specific libraries
include_once __DIR__ . '/../lib/functions_data.php';

// 4. Get config values
$metadata_path = $config->getPath('metadata_path');

// 5. Load page data
$data = loadJsonFile("$metadata_path/my_data.json", []);

// 6. Handle AJAX requests
handleAdminAjax(function($action) use ($data) {
    if ($action === 'save_changes') {
        // Process request
        echo json_encode(['success' => true]);
        return true;
    }
    return false;
});

// 7. Clear buffer and render page
ob_end_clean();
include_once __DIR__ . '/../includes/layout.php';

// 8. Prepare data for view
$title = "My Admin Page";
$page_data = ['items' => $data];

// 9. Render with layout
render_display_page(
    __DIR__ . '/pages/my_admin_page.php',
    $page_data,
    $title
);
?>
```

### Step 2: Create View Template

Create `/data/moop/admin/pages/my_admin_page.php`:

```php
<div class="container mt-5">
    <h2>My Admin Page</h2>
    
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editItem('<?php echo htmlspecialchars($item['id']); ?>')">
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editItem(id) {
    // Send AJAX request to controller
    fetch('?', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save_changes', id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) alert('Saved!');
        else alert('Error: ' + data.message);
    });
}
</script>
```

---

## admin_init.php: What It Provides

**admin_init.php** handles all initialization:

```php
include_once __DIR__ . '/admin_init.php';

// Available after include:
// 1. Session started (if not already)
// 2. ConfigManager initialized ($config available)
// 3. Admin access verified (non-admins redirected)
// 4. Common functions loaded (in lib/)
// 5. Variables set: $header_img, $images_path, $site
```

### What It Does

1. **Session Management**
   - Starts PHP session (if not already started)
   - Preserves existing session data

2. **Config Loading**
   - Initializes ConfigManager singleton
   - Loads site_config.php (defaults)
   - Loads config_editable.json (overrides)
   - Makes $config available globally

3. **Access Control**
   - Calls admin_access_check.php
   - Verifies user is logged in
   - Verifies user has Admin role
   - Redirects if not authorized

4. **Common Libraries**
   - Loads lib/moop_functions.php (general utilities)
   - Loads lib/functions_display.php (display helpers)
   - Loads lib/functions_filesystem.php (file operations)

5. **Header Setup**
   - Gets header_img from config
   - Gets images_path from config
   - Gets site from config
   - Ready for navbar inclusion

---

## Page Structure

### Pattern: Output Buffering for AJAX

Admin pages use output buffering to handle AJAX requests:

```php
<?php
// 1. Start capturing output
ob_start();

// 2. Setup admin environment
include_once __DIR__ . '/admin_init.php';

// 3. Check for AJAX requests FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Handle request and exit
    // Output is captured, only JSON goes out
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;  // Stop here, don't render HTML
}

// 4. If not AJAX, clear captured output
ob_end_clean();

// 5. Continue with page rendering
include_once __DIR__ . '/../includes/layout.php';
render_display_page(...);
?>
```

**Why?** 
- Errors/warnings are captured but discarded
- AJAX responses are pure JSON (no HTML)
- No mixing of HTML and JSON in response

### Pattern: handleAdminAjax() Helper

Use the built-in helper for cleaner code:

```php
<?php
ob_start();
include_once __DIR__ . '/admin_init.php';

// Define handlers
handleAdminAjax(function($action) {
    if ($action === 'save') {
        // Process request
        echo json_encode(['success' => true]);
        return true;  // Handled
    }
    return false;  // Not handled
});

// Continue with normal page rendering
ob_end_clean();
?>
```

---

## Available Resources

### ConfigManager Methods

```php
$config = ConfigManager::getInstance();

// Path getters (filesystem safe)
$root = $config->getPath('root_path');
$organism_data = $config->getPath('organism_data');
$metadata = $config->getPath('metadata_path');
$logs = $config->getPath('logs_path');

// String getters
$title = $config->getString('siteTitle');
$email = $config->getString('admin_email');
$site = $config->getString('site');

// Array getters
$types = $config->getArray('sequence_types');
$tools = $config->getArray('tools');

// Special getters
$all_tools = $config->getAllTools();
$tool = $config->getTool('tool_name');
```

### Loaded Libraries

After `admin_init.php`, these libraries are available:

**lib/moop_functions.php:**
- `loadJsonFile($path, $default)` - Load and decode JSON
- `saveJsonFile($path, $data)` - Save and encode JSON
- `getDirectorySize($path)` - Get directory size
- `getFileWriteError($path)` - Check file write ability

**lib/functions_display.php:**
- Display and formatting helpers
- Table rendering helpers
- Card/alert components

**lib/functions_filesystem.php:**
- File permission checking
- File operation helpers
- Path validation

### Session Variables

After access control, these $_SESSION variables are set:

```php
$_SESSION['user'] = [
    'username' => 'admin_user',
    'role' => 'admin',
    'access_level' => 'admin'
];

$_SESSION['access'] = [
    'organisms' => [...],     // Accessible organisms
    'groups' => [...],        // Accessible groups
    'tools' => [...]          // Accessible tools
];
```

---

## Controller vs View Separation

### Controller Responsibilities (admin/*.php)

✓ Load configuration  
✓ Validate user access  
✓ Process form submissions  
✓ Handle AJAX requests  
✓ Query databases  
✓ Perform operations (save, delete, etc.)  
✓ Prepare data for view  
✓ Call render_display_page()  

❌ Should NOT output HTML directly  
❌ Should NOT include page tags  
❌ Should NOT mix logic and display  

### View Responsibilities (admin/pages/*.php)

✓ Display data received  
✓ Render HTML forms  
✓ Create UI elements  
✓ Show success/error messages  
✓ Include JavaScript for interactivity  

❌ Should NOT perform queries  
❌ Should NOT handle POST/GET directly  
❌ Should NOT output page structure tags  
❌ Should NOT start sessions or load config  

---

## Best Practices

### 1. Always Use Output Buffering on AJAX Pages

```php
<?php
ob_start();
include_once __DIR__ . '/admin_init.php';

// Handle AJAX FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... process
    exit;
}

// Then render HTML
ob_end_clean();
```

### 2. Use ConfigManager, Never Hardcode Paths

❌ **Wrong:**
```php
$file = '/data/moop/metadata/organisms.json';
```

✅ **Right:**
```php
$metadata = $config->getPath('metadata_path');
$file = "$metadata/organisms.json";
```

### 3. Validate Admin Access Early

Access is checked by admin_init.php, but verify specific permissions:

```php
if (!isset($_SESSION['access']['organisms'][$organism])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
```

### 4. Load Page-Specific Libraries After admin_init.php

```php
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../lib/blast_functions.php';  // ✓ AFTER admin_init
```

### 5. Use render_display_page() for Consistency

```php
ob_end_clean();
include_once __DIR__ . '/../includes/layout.php';
render_display_page(
    __DIR__ . '/pages/my_page.php',
    ['data' => $data],
    'Page Title'
);
```

### 6. Escape Output in Views

```php
<!-- In view template -->
<td><?php echo htmlspecialchars($item['name']); ?></td>  // ✓ Safe
<td><?php echo $item['name']; ?></td>                    // ✗ XSS risk
```

### 7. Validate Input Everywhere

```php
$organism = $_POST['organism'] ?? null;
if (!$organism || !preg_match('/^[a-zA-Z0-9_-]+$/', $organism)) {
    echo json_encode(['success' => false, 'message' => 'Invalid organism']);
    exit;
}
```

---

## Common Patterns

### Form + AJAX

```php
<!-- admin/pages/my_admin_page.php -->
<form id="myForm">
    <input type="text" name="title" required>
    <button type="button" onclick="submitForm()">Save</button>
</form>

<script>
function submitForm() {
    const data = new FormData(document.getElementById('myForm'));
    const json = Object.fromEntries(data);
    json.action = 'save_item';
    
    fetch('<?php echo $_SERVER['SCRIPT_NAME']; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(json)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Saved!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
```

### DataTables with AJAX

```php
<!-- admin/pages/my_table.php -->
<table id="myTable" class="table">
    <thead>
        <tr><th>Name</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td>
                    <button class="btn-edit" onclick="editItem(<?php echo $item['id']; ?>)">Edit</button>
                    <button class="btn-delete" onclick="deleteItem(<?php echo $item['id']; ?>)">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// DataTables initialization
$(document).ready(function() {
    $('#myTable').DataTable();
});

function deleteItem(id) {
    if (!confirm('Delete?')) return;
    
    fetch('?', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_item', id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + data.message);
    });
}
</script>
```

---

## Error Handling

### Server Errors

Errors are logged to `/data/moop/logs/error.log` by access_control.php

View via admin interface: "Manage > View Error Log"

### AJAX Error Responses

Always return consistent JSON:

```php
// Success
echo json_encode([
    'success' => true,
    'message' => 'Operation completed',
    'data' => [...]
]);

// Error
echo json_encode([
    'success' => false,
    'message' => 'Operation failed: invalid input',
    'error_code' => 'INVALID_INPUT'
]);
```

### Permission Denied

Access control automatically handles, but for specific checks:

```php
if (!isset($_SESSION['access']['organisms'][$organism])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}
```

---

## Admin Pages Reference

Current admin pages (15 total):

| Page | Purpose | Type |
|------|---------|------|
| admin.php | Dashboard | Controller |
| manage_organisms.php | Manage organisms | Controller + AJAX |
| manage_users.php | Manage users | Controller + AJAX |
| manage_site_config.php | Site settings | Controller + AJAX |
| manage_annotations.php | Manage annotations | Controller + AJAX |
| manage_taxonomy_tree.php | Phylogenetic tree | Controller |
| manage_groups.php | Manage groups | Controller + AJAX |
| manage_error_log.php | View error log | Controller |
| manage_filesystem_permissions.php | File permissions | Controller |
| manage_registry.php | Function registry | Controller |
| manage_js_registry.php | JS registry | Controller |
| organism_checklist.php | Setup checklist | Controller |
| admin_init.php | Initialization | Support |
| admin_access_check.php | Access validation | Support |
| registry-template.php | Template helper | Support |

---

## File References

```
admin/
├── DEVELOPER_GUIDE.md                 # This file
├── admin.php                          # Dashboard controller
├── admin_init.php                     # Initialization (for all pages)
├── admin_access_check.php             # Access verification
├── manage_*.php                       # Admin controllers (15 total)
├── registry-template.php              # Template helper
├── pages/                             # View templates
│   ├── admin.php                      # Dashboard UI
│   ├── manage_*.php                   # Admin page UIs
│   └── [12 view templates]
├── api/                               # AJAX endpoints
│   ├── generate_registry.php          # Registry generation
│   └── [other API endpoints]
└── backups/                           # Auto-created backup directory
```

---

## Related Documentation

- **Configuration System:** See `/config/README.md`
- **Page Architecture:** See `MOOP_COMPREHENSIVE_OVERVIEW.md`
- **Includes System:** See `includes/README.md`
- **Tools Development:** See `tools/DEVELOPER_GUIDE.md`

---

**Last Updated:** January 2026
