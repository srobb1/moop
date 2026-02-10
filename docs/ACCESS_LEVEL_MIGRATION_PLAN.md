# Access Level Migration Plan: ALL → ADMIN & IP_IN_RANGE

## Overview
This document outlines all changes needed to migrate from the confusing `ALL` keyword to the clearer `ADMIN` and `IP_IN_RANGE` system.

---

## Current System Analysis

### Access Level Values in Use:
1. **`'Public'`** - Anonymous/unauthenticated users (default)
2. **`'Collaborator'`** - Logged-in users with organism-specific permissions
3. **`'Admin'`** - Logged-in admin users (set in login.php line 35)
4. **`'ALL'`** - Whitelisted IP users (set in access_control.php line 37)

### Key Finding:
- **Whitelisted IPs get `'ALL'`** (access_control.php:37)
- **Admin users get `'Admin'`** (login.php:35)
- **Most code checks BOTH**: `=== 'ALL' || === 'Admin'`

---

## New System Design

### Proposed Access Level Keywords:
1. **`'Public'`** - Anonymous users (unchanged)
2. **`'Collaborator'`** - Logged-in collaborators (unchanged)
3. **`'ADMIN'`** - Logged-in admin users (was 'Admin')
4. **`'IP_IN_RANGE'`** - Whitelisted IP addresses (was 'ALL')

### Hierarchical Access (auto-inherit):
```
Public           → Everyone
Collaborator     → ADMIN, IP_IN_RANGE, Collaborator (no anonymous)
IP_IN_RANGE      → ADMIN, IP_IN_RANGE (no collaborators, no anonymous)
ADMIN            → ADMIN only
```

---

## Files Requiring Changes

### 1. SESSION SETUP FILES

#### `/includes/access_control.php`
**Line 37:** Set whitelisted IP users
```php
// CURRENT:
$_SESSION["access_level"] = 'ALL';

// CHANGE TO:
$_SESSION["access_level"] = 'IP_IN_RANGE';
```

**Line 176:** `has_access()` function
```php
// CURRENT:
if ($access_level === 'ALL' || $access_level === 'Admin') {

// CHANGE TO:
if ($access_level === 'ADMIN' || $access_level === 'IP_IN_RANGE') {
```

#### `/login.php`
**Line 35:** Set admin users
```php
// CURRENT:
$_SESSION["access_level"] = 'Admin';

// CHANGE TO:
$_SESSION["access_level"] = 'ADMIN';
```

---

### 2. JBROWSE2 API FILES

#### `/api/jbrowse2/assembly.php`
**Line 50:** Auto-detect access level
```php
// CURRENT:
$user_access_level = isset($_SESSION['username']) && has_access('ALL') ? 'ALL' : 'Public';

// CHANGE TO:
$user_access_level = get_access_level();  // Use helper function instead
```

**Line 148:** Track filtering - REPLACE ENTIRE SECTION
```php
// CURRENT:
if ($user_access_level === 'ALL') {
    // Admin sees everything
    $user_can_access = true;
} elseif (in_array('Public', $track_access_levels)) {
    // Public tracks visible to everyone
    $user_can_access = true;
} elseif ($user_access_level === 'Collaborator' && in_array('Collaborator', $track_access_levels)) {
    // Check if collaborator has access to this specific assembly
    $user_access = $_SESSION['access'] ?? [];
    if (isset($user_access[$organism]) && in_array($assembly, (array)$user_access[$organism])) {
        $user_can_access = true;
    }
}

// CHANGE TO HIERARCHICAL LOGIC (see section 3 below)
```

#### `/api/jbrowse2/get-config.php`
**Line 33:** Check admin
```php
// CURRENT:
if ($_SESSION['is_admin'] ?? false) {
    $user_access_level = 'ALL';
}

// CHANGE TO:
// Remove this block - rely on session already being set to 'ADMIN'
$user_access_level = $_SESSION['access_level'] ?? 'Public';
```

**Line 81:** Assembly filtering
```php
// CURRENT:
if ($user_access_level === 'ALL') {

// CHANGE TO:
if ($user_access_level === 'ADMIN' || $user_access_level === 'IP_IN_RANGE') {
```

#### `/api/jbrowse2/test-assembly.php`
**Line 103:**
```php
// CURRENT:
if ($access_level === 'ALL') {

// CHANGE TO:
if ($access_level === 'ADMIN' || $access_level === 'IP_IN_RANGE') {
```

---

### 3. LIBRARY FUNCTIONS

#### `/lib/functions_access.php`
**Line 238:** `getTaxonomyTreeUserAccess()` function
```php
// CURRENT:
if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {

// CHANGE TO:
if (get_access_level() === 'ADMIN' || get_access_level() === 'IP_IN_RANGE') {
```

#### `/lib/functions_data.php`
**Line 286:** `getIndexDisplayCards()` function
```php
// CURRENT:
if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {

// CHANGE TO:
if (get_access_level() === 'ADMIN' || get_access_level() === 'IP_IN_RANGE') {
```

---

### 4. ADMIN PANEL

#### `/admin/admin_access_check.php`
**Line 16:** Admin verification
```php
// CURRENT:
if (!$is_admin || get_access_level() !== 'Admin') {

// CHANGE TO:
if (!$is_admin || get_access_level() !== 'ADMIN') {
```

---

### 5. MISCELLANEOUS PHP FILES

#### `/jbrowse2.php`
**Line 24:** Uses `get_access_level()` - no change needed (already uses helper)

#### `/tools/pages/jbrowse2-view.php`
**Line 8:** Check if reads from session - verify after changes

#### `/tools/annotation_search_ajax.php`
**Line 46:** Uses `$_SESSION['role']` not `access_level` - no change needed

---

## 3. NEW HIERARCHICAL TRACK FILTERING LOGIC

### Implementation for `/api/jbrowse2/assembly.php` (lines 138-165)

```php
// 4. FILTER TRACKS BY USER ACCESS LEVEL
$is_whitelisted = isWhitelistedIP();

// Define access hierarchy
$access_hierarchy = [
    'ADMIN' => 4,
    'IP_IN_RANGE' => 3,
    'Collaborator' => 2,
    'Public' => 1
];

$user_level_value = $access_hierarchy[$user_access_level] ?? 0;

foreach ($available_tracks as $track) {
    // Get track access levels
    $track_access_levels = $track['access_levels'] ?? ['Public'];
    
    // Determine minimum required level for this track
    $min_required_level = 0;
    foreach ($track_access_levels as $level) {
        $level_value = $access_hierarchy[$level] ?? 0;
        if ($level_value > $min_required_level) {
            $min_required_level = $level_value;
        }
    }
    
    // Check if user meets minimum requirement
    $user_can_access = false;
    
    if ($user_level_value >= $min_required_level) {
        // User has sufficient access level
        
        // Special check for Collaborator: must have access to THIS assembly
        if ($user_access_level === 'Collaborator' && $min_required_level >= $access_hierarchy['Collaborator']) {
            $user_access = $_SESSION['access'] ?? [];
            if (isset($user_access[$organism]) && in_array($assembly, (array)$user_access[$organism])) {
                // Check for required_groups if specified
                if (!empty($track['required_groups'])) {
                    $user_groups = $_SESSION['groups'] ?? [];
                    $user_can_access = !empty(array_intersect($track['required_groups'], $user_groups));
                } else {
                    $user_can_access = true;
                }
            }
        } else {
            // ADMIN, IP_IN_RANGE, or Public access - no assembly check needed
            $user_can_access = true;
        }
    }
    
    // Skip if user cannot access this track
    if (!$user_can_access) {
        continue;
    }
    
    // Continue with track URL generation...
}
```

---

## 4. TRACK METADATA EXAMPLES

### Example Track Configurations:

```json
{
  "name": "Public Coverage Track",
  "access_levels": ["Public"],
  "comment": "Visible to: Everyone (ADMIN, IP_IN_RANGE, Collaborator, Anonymous)"
}

{
  "name": "Collaborator Alignment",
  "access_levels": ["Collaborator"],
  "required_groups": ["worm_xyz_special"],
  "comment": "Visible to: ADMIN, IP_IN_RANGE, Collaborators in 'worm_xyz_special' group"
}

{
  "name": "Internal Research Data",
  "access_levels": ["IP_IN_RANGE"],
  "comment": "Visible to: ADMIN, IP_IN_RANGE only (no external collaborators)"
}

{
  "name": "Admin-Only Preliminary Data",
  "access_levels": ["ADMIN"],
  "comment": "Visible to: ADMIN users only"
}
```

---

## 5. DOCUMENTATION UPDATES NEEDED

### Files to Update:
- `/docs/JBrowse2/dynamic-config-and-jwt-security.md`
- `/docs/JBrowse2/SECURITY.md`
- `/docs/JBrowse2/ADMIN_GUIDE.md`
- `/docs/JBrowse2/DEVELOPER_GUIDE.md`
- `/docs/SECURITY_IMPLEMENTATION.md`
- `/docs/PERMISSIONS_WORKFLOW.md`
- All files in `/docs/JBrowse2/archive/` (for reference)

### Changes:
- Replace all mentions of `'ALL'` with `'ADMIN'` or `'IP_IN_RANGE'`
- Update access level hierarchy diagrams
- Add examples of granular track permissions

---

## 6. TESTING CHECKLIST

After making changes, test:

### Session Setup
- [ ] Whitelisted IP gets `$_SESSION['access_level'] = 'IP_IN_RANGE'`
- [ ] Admin login gets `$_SESSION['access_level'] = 'ADMIN'`
- [ ] Collaborator login gets `$_SESSION['access_level'] = 'Collaborator'`
- [ ] Anonymous gets `get_access_level() = 'Public'`

### JBrowse2 Access
- [ ] ADMIN sees all assemblies and all tracks
- [ ] IP_IN_RANGE sees all assemblies and IP_IN_RANGE+ tracks
- [ ] Collaborator sees permitted assemblies and Collaborator+ tracks
- [ ] Public sees public assemblies and Public tracks only

### Track Filtering
- [ ] Public assembly with `["ADMIN"]` track → only ADMIN sees it
- [ ] Public assembly with `["IP_IN_RANGE"]` track → ADMIN + IP_IN_RANGE see it
- [ ] Public assembly with `["Collaborator"]` track → ADMIN + IP_IN_RANGE + Collaborator see it
- [ ] Collaborator with `required_groups` → only matching group members see it

### Admin Panel
- [ ] Admin users can access `/admin/`
- [ ] IP_IN_RANGE users CANNOT access `/admin/` (correct behavior)
- [ ] Collaborators CANNOT access `/admin/`

### Site-Wide Access
- [ ] Index page shows correct organisms for each access level
- [ ] Taxonomy tree filters correctly
- [ ] Organism pages respect permissions

---

## 7. MIGRATION STEPS

1. **Backup database and config files**
2. **Update session setup files** (access_control.php, login.php)
3. **Update JBrowse2 API files** (assembly.php, get-config.php)
4. **Update library functions** (functions_access.php, functions_data.php)
5. **Update admin panel** (admin_access_check.php)
6. **Update track metadata** (change access_levels in JSON files)
7. **Update documentation**
8. **Clear all active sessions** (force re-login)
9. **Run test checklist**

---

## 8. BACKWARD COMPATIBILITY

**NOTE:** No backward compatibility needed per user request.
- All old `'ALL'` references will be removed
- All old `'Admin'` references will be changed to `'ADMIN'`
- Users will need to re-login after deployment

---

**Last Updated:** 2026-02-10
