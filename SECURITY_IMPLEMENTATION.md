# Access Control Security Implementation

## Overview
This document describes the secure access control system implemented to prevent unauthorized access escalation.

## Security Vulnerability Fixed

### Previous Issue
- `access_group` was only a display variable, not validated on protected pages
- Users could potentially manipulate URL parameters or session data to gain unauthorized access
- No centralized access control logic

### Solution Implemented
- Centralized access control in `access_control.php`
- Session-based authentication with `access_level` stored securely in session
- Automatic login for IP-based users with proper session management
- Access validation on all protected pages

## Access Levels

1. **Public** - Default access, no authentication required
2. **Collaborator** - Logged-in users with specific organism access
3. **Admin** - Logged-in users with admin role
4. **ALL** - IP-based users from authorized IP range (127.0.0.11)

## How It Works

### 1. Centralized Access Control (`access_control.php`)
All pages now include this file which:
- Checks visitor IP and auto-logs in authorized IP users
- Sets `$access_level` from session (never from GET/POST parameters)
- Provides `has_access()` and `require_access()` functions

### 2. IP-Based Auto-Login
Users from IP range 127.0.0.11 are automatically:
- Logged in with username "IP_USER_{ip}"
- Assigned `access_level` = 'ALL'
- Given access to all pages and data
- Protected from URL parameter manipulation

### 3. Regular User Login
Through `login.php`:
- Validates username/password
- Sets `access_level` = 'Admin' or 'Collaborator' based on role
- Stores user-specific access permissions in session

### 4. Page Protection
Each protected page:
- Includes `access_control.php` at the top
- Calls `has_access()` or `require_access()` to validate access
- Redirects unauthorized users to index.php

## Usage Examples

### Protect a Page
```php
<?php
include_once __DIR__ . '/access_control.php';

// Require collaborator access
require_access('Collaborator');

// Or check for specific organism access
if (!has_access('Collaborator', $organism_name)) {
    header("Location: /index.php");
    exit;
}
?>
```

### Check Access in Code
```php
if (has_access('ALL')) {
    // Show all data
} elseif (has_access('Admin')) {
    // Show admin features
} elseif (has_access('Collaborator', 'organism_name')) {
    // Show specific organism data
}
```

## Security Benefits

1. **No URL Parameter Manipulation** - Access level is stored in session, not in URLs
2. **Consistent Validation** - All pages use the same access control logic
3. **Session-Based Security** - Proper session management with server-side validation
4. **IP-Based Auto-Login** - Seamless access for authorized IP users without compromising security
5. **Granular Control** - Each page can check for specific resource access

## Configuration

To modify the authorized IP range, edit `access_control.php`:

```php
$all_access_start_ip = ip2long("127.0.0.11");
$all_access_end_ip   = ip2long("127.0.0.11");
```

For a range like 10.0.0.1 to 10.0.0.255:
```php
$all_access_start_ip = ip2long("10.0.0.1");
$all_access_end_ip   = ip2long("10.0.0.255");
```

## Files Modified

- `/moop/access_control.php` - NEW centralized access control
- `/moop/index.php` - Uses access_control.php
- `/moop/login.php` - Sets access_level in session
- `/moop/tools/display/groups_display.php` - Protected with access validation
- `/moop/tools/display/organism_display.php` - Protected with access validation
- `/moop/admin/admin_header.php` - Enhanced admin protection

## Testing

1. **Test IP-based access**: Access from 127.0.0.11 should auto-login with ALL access
2. **Test regular login**: Login with credentials should work normally
3. **Test URL manipulation**: Adding `?access_group=ALL` should have no effect
4. **Test protected pages**: Accessing organisms without permission should redirect
5. **Test admin panel**: Only admin users should access admin pages (not IP-based ALL users)
