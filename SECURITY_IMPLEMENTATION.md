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
- Provides helper functions to read access data from `$_SESSION` (single source of truth)
- Provides `has_access()` and `require_access()` functions for access validation

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
- Redirects unauthorized users to access_denied.php

## Access Helper Functions

The `access_control.php` file provides the following helper functions to securely access session data:

```php
// Get current access level from session
get_access_level()  // Returns: 'Public', 'Collaborator', 'Admin', or 'ALL'

// Get current user's resource access list
get_user_access()   // Returns: array of organisms/resources user can access

// Check if user is logged in
is_logged_in()      // Returns: true/false

// Get currently logged-in username
get_username()      // Returns: username string
```

These functions always read from `$_SESSION` (the authoritative source) rather than relying on cached global variables, ensuring access checks are always based on current session state.

## Session-Based Flow

### Complete Access Control Flow

```
login.php
  └─> Validates username/password
  └─> Sets $_SESSION["logged_in"] = true
  └─> Sets $_SESSION["username"] = username
  └─> Sets $_SESSION["access"] = user_access_array
  └─> Sets $_SESSION["role"] = 'admin' or null
  └─> Sets $_SESSION["access_level"] = 'Admin' or 'Collaborator'
  
header.php
  └─> Includes access_control.php
      └─> Defines helper functions that read from $_SESSION:
          ├─ is_logged_in()       → reads $_SESSION["logged_in"]
          ├─ get_access_level()   → reads $_SESSION["access_level"]
          ├─ get_user_access()    → reads $_SESSION["access"]
          └─ get_username()       → reads $_SESSION["username"]
      
toolbar.php (included by header.php)
  └─> Uses is_logged_in() helper
  └─> Uses $_SESSION['role'] directly for admin check
  └─> Displays Login/Logout buttons based on fresh session state
  
Protected pages (admin/*, tools/display/*, tools/extract/*)
  └─> Include access_control.php
  └─> Call has_access() function
      └─> has_access() calls helpers for fresh reads:
          ├─ get_access_level()
          └─ get_user_access()
  └─> Validate against protected resources
  
admin_access_check.php (called by all admin pages)
  └─> Validates admin access:
      ├─ is_logged_in() → check session exists
      ├─ get_username() → get current user
      ├─ Check user role in JSON file
      └─ get_access_level() → validate is 'Admin' (not 'ALL')
  └─> Blocks if any check fails (403 Forbidden)

logout.php
  └─> Calls session_destroy()
  └─> Clears all session data
  └─> Redirects to login
```

### Key Security Properties

✅ **Single Source of Truth:** `$_SESSION` only
- All access checks read from session
- No cached global variables
- No stale data possible

✅ **Fresh Reads on Every Check**
- Helper functions call `$_SESSION` directly
- No per-page initialization cache
- Access changes take effect immediately

✅ **Defense in Depth**
- login.php: Sets access level based on user role
- admin_access_check.php: Re-validates against JSON + session
- toolbar.php: Uses current session for UI
- All layers independent and consistent

✅ **No Parameter Injection**
- Access level never read from GET/POST
- Only from `$_SESSION` (server-side secure)
- URL parameters have no effect

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

1. **Session-Based Single Source of Truth** - All access checks read directly from `$_SESSION`, never from cached globals
2. **No URL Parameter Manipulation** - Access level is stored in session, not in URLs
3. **No Stale Cache Issues** - Helper functions always read fresh from `$_SESSION`
4. **Consistent Validation** - All pages use the same access control logic
5. **IP-Based Auto-Login** - Seamless access for authorized IP users without compromising security
6. **Granular Control** - Each page can check for specific resource access
7. **Admin Protection** - Admin panel validates both user role and access level, rejecting IP-based 'ALL' access

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
