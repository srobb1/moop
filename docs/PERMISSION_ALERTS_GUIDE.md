# Permission Alerts System - Developer Guide

A centralized, reusable permission alert system for admin pages that provides both automated fixes and manual instructions.

## Overview

The permission alert system provides:
- **Automated Detection**: Checks read/write permissions on files and directories
- **Smart Fixes**: Provides either a button for automatic fix OR manual instructions
- **Consistent UI**: Yellow alerts with clear status and instructions
- **User-Friendly**: Dismissible alerts, clear error messages, refresh on success

## Components

### 1. PHP Functions (lib/functions_display.php)

#### `generatePermissionAlert()`
Generates complete HTML for permission alert with auto-fix or manual instructions.

```php
$html = generatePermissionAlert(
    $file_path,              // Path to file/directory
    'Metadata File Issue',   // Alert title
    'File is not writable',  // Problem description
    'file',                  // 'file' or 'directory'
    'Organism_name'          // Optional organism name
);

echo $html;
```

**Returns**: HTML string (empty if no permission issues)

#### `getWebServerUserInfo()`
Gets current web server user and group.

```php
$info = getWebServerUserInfo();
// Returns: ['user' => 'www-data', 'group' => 'www-data']
```

### 2. PHP Functions (lib/functions_system.php)

#### `fixFilePermissions()`
Attempts to fix permissions on a file or directory.

```php
$result = fixFilePermissions('/path/to/file.json', 'file');
// Returns: ['success' => bool, 'message' => string]
```

#### `handleFixFilePermissionsAjax()`
AJAX handler wrapper - processes POST data and returns JSON.

```php
// In your admin script:
if (isset($_POST['action']) && $_POST['action'] === 'fix_file_permissions') {
    header('Content-Type: application/json');
    echo json_encode(handleFixFilePermissionsAjax());
    exit;
}
```

### 3. JavaScript (js/permission-manager.js)

#### `fixFilePermissions()`
Handles the button click and AJAX communication.

```javascript
fixFilePermissions(event, filePath, fileType, organism);
```

**Parameters**:
- `event` - Click event object
- `filePath` - Path to file/directory
- `fileType` - 'file' or 'directory'
- `organism` - Optional organism name

## Usage Examples

### Example 1: Metadata File Alert (manage_organisms.php)

```php
<?php
// At top of admin script
require_once __DIR__ . '/../lib/functions_display.php';
require_once __DIR__ . '/../lib/functions_system.php';

// Handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'fix_file_permissions') {
    header('Content-Type: application/json');
    echo json_encode(handleFixFilePermissionsAjax());
    exit;
}

// In your validation/display code
$organism_json = "/path/to/organism.json";
$alert = generatePermissionAlert(
    $organism_json,
    'Metadata File Not Writable',
    'Cannot edit metadata. File permissions must be fixed.',
    'file',
    'Organism_name'
);
?>

<!-- Display alert if issues found -->
<?php echo $alert; ?>
```

### Example 2: Multiple Files (Database + Config)

```php
<?php
// Check multiple files
$files_to_check = [
    [
        'path' => $db_file,
        'title' => 'Database File Permission Issue',
        'problem' => 'Database is not readable',
        'type' => 'file'
    ],
    [
        'path' => $config_file,
        'title' => 'Configuration File Permission Issue', 
        'problem' => 'Cannot write to configuration',
        'type' => 'file'
    ]
];

foreach ($files_to_check as $file_info) {
    $alert = generatePermissionAlert(
        $file_info['path'],
        $file_info['title'],
        $file_info['problem'],
        $file_info['type']
    );
    echo $alert;
}
?>
```

## Alert Behavior

### When Web Server CAN Fix Permissions

If the web server has write access to the parent directory:
1. Shows "Fix Permissions" button
2. User clicks button
3. AJAX sends request to fix permissions
4. Shows success/failure message
5. Auto-refreshes page on success

### When Web Server CANNOT Fix Permissions

If web server lacks permissions:
1. Shows manual instructions
2. Displays exact `chown` and `chmod` commands
3. User runs commands on server
4. User refreshes page to verify

## HTML Output

### With Auto-Fix Button

```html
<div class="alert alert-warning alert-dismissible fade show mb-3">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <h6><i class="fa fa-exclamation-circle"></i> File Not Writable</h6>
  <p class="mb-2"><strong>Problem:</strong> Cannot edit file...</p>
  
  <p class="mb-3"><strong>Current Status:</strong></p>
  <ul class="mb-3 small">
    <li>Path: <code>/path/to/file.json</code></li>
    <li>Owner: <code>ubuntu</code></li>
    <li>Permissions: <code>644</code></li>
    <li>Readable: <span class="badge bg-danger">✗ No</span></li>
    <li>Writable: <span class="badge bg-danger">✗ No</span></li>
    <li>Web server user: <code>www-data</code></li>
  </ul>
  
  <p class="mb-2"><strong>Quick Fix:</strong> Click the button below:</p>
  <button class="btn btn-warning btn-sm" onclick="fixFilePermissions(...)">
    <i class="fa fa-wrench"></i> Fix Permissions
  </button>
  <div id="fixResult-..."></div>
  
  <p class="small text-muted mb-0">After fixing, page will refresh.</p>
</div>
```

### With Manual Instructions

```html
<div class="alert alert-warning alert-dismissible fade show mb-3">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <h6><i class="fa fa-exclamation-circle"></i> File Not Writable</h6>
  
  <!-- Status, etc... -->
  
  <p class="mb-2"><strong>To Fix:</strong> Run this command on the server:</p>
  <div style="background: #f0f0f0; padding: 10px; border-radius: 4px;">
    <code>sudo chown www-data:www-data /path/to/file.json<br>
    sudo chmod 664 /path/to/file.json</code>
  </div>
  
  <p class="small text-muted mb-0">After fixing, refresh this page.</p>
</div>
```

## Integration Checklist

For each admin page:

- [ ] Include `lib/functions_display.php`
- [ ] Include `lib/functions_system.php`
- [ ] Add AJAX handler in POST section:
  ```php
  if (isset($_POST['action']) && $_POST['action'] === 'fix_file_permissions') {
      header('Content-Type: application/json');
      echo json_encode(handleFixFilePermissionsAjax());
      exit;
  }
  ```
- [ ] Include JS in page footer: `<script src="/moop/js/permission-manager.js"></script>`
- [ ] Call `generatePermissionAlert()` for each file/directory to check
- [ ] Display alerts in appropriate location

## Files

- **lib/functions_display.php** - Permission alert HTML generation
- **lib/functions_system.php** - Permission fixing logic
- **js/permission-manager.js** - AJAX and UI handling
- **docs/PERMISSION_ALERTS_GUIDE.md** - This guide

## Security Notes

1. **Path Validation**: `handleFixFilePermissionsAjax()` uses `realpath()` to prevent directory traversal
2. **XSS Prevention**: All user-visible strings escaped with `htmlspecialchars()`
3. **Output Buffering**: JavaScript uses `escapeHtml()` to prevent XSS in AJAX responses
4. **Safe Permissions**: Uses `0644` for files (rw-r--r--) and `0755` for dirs (rwxr-xr-x)

## Troubleshooting

### Alert Not Showing
- Check that `generatePermissionAlert()` returns non-empty string
- Verify `file_exists()` returns true
- Check that file is actually unreadable/unwritable

### Fix Button Not Working
- Verify AJAX handler is included in POST section
- Check browser console for JavaScript errors
- Ensure `js/permission-manager.js` is included

### Manual Commands Don't Work
- Run as `sudo` if needed
- Check that user/group exist on system
- Verify paths are correct

