# Access Level Issues Found During Testing - 2026-02-10

## Issue 1: IP_IN_RANGE badge not showing on JBrowse2 page ✅ (Likely working, need to verify CSS)
**Status:** Needs verification - badge mapping exists in code
**Location:** `/data/moop/tools/pages/jbrowse2.php` line 197
**Evidence:** Code shows `'IP_IN_RANGE': { text: 'Trusted Network', class: 'badge-info' }`
**Action:** Check if CSS class `badge-info` exists, or check browser console for errors

---

## Issue 2: Logout button doesn't work for IP_IN_RANGE users 🔴 CRITICAL
**Status:** Bug confirmed
**Location:** `/data/moop/logout.php` + `/data/moop/includes/access_control.php`

**Problem:**
1. User clicks logout → `logout.php` destroys session
2. Page redirects to index.php
3. `access_control.php` runs and detects whitelisted IP
4. Session is immediately recreated with IP_IN_RANGE access
5. User appears still logged in (infinite loop)

**Solution Options:**

### Option A: Hide logout for IP_IN_RANGE, show login instead
```php
// In layout.php or wherever login/logout button is rendered
if (get_access_level() === 'IP_IN_RANGE') {
    // Show "Login" button - allows admin to login over IP auth
    echo '<a href="/moop/login.php">Login</a>';
} else {
    // Show logout for actual logged-in users
    echo '<a href="/moop/logout.php">Logout</a>';
}
```

### Option B: Logout sets a flag to prevent auto-login
```php
// In logout.php
$_SESSION['prevent_auto_login'] = true;
session_destroy();

// In access_control.php
if (!isset($_SESSION['prevent_auto_login'])) {
    // Re-enable IP auto-login
}
```

**Recommended:** Option A - simpler and clearer UX

---

## Issue 3: Assembly access redirects to index instead of access_denied 🔴
**Status:** Bug confirmed  
**Location:** `/data/moop/tools/assembly.php`

**Problem:**
- Anonymous user visits: `http://localhost:8000/moop/tools/assembly.php?organism=Nematostella_vectensis`
- `setupOrganismDisplayContext()` checks organism access, redirects to index.php
- No specific check for assembly-level access
- User gets redirected to index with no explanation why

**Current Code (line 46):**
```php
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
```

This function calls `require_access('COLLABORATOR', $organism_name)` which redirects to access_denied.php if organism is not public.

**Wait, need to check:** Does setupOrganismDisplayContext actually redirect to access_denied or index?

**Action needed:**
1. Check `setupOrganismDisplayContext()` in `/data/moop/lib/functions_display.php`
2. Verify it calls `require_access()` which should redirect to access_denied.php
3. If it redirects to index.php, change redirect target

---

## Issue 4: Add access level badges to main header (FEATURE REQUEST)
**Status:** Enhancement  
**Current:** Badges only show on JBrowse2 page
**Requested:** Show username + access level badges in main header next to login/logout button

**Implementation:**
```php
// In layout.php header
<?php if (is_logged_in()): ?>
    <span class="badge badge-user"><?= htmlspecialchars(get_username()) ?></span>
    <span class="badge badge-access-<?= strtolower(get_access_level()) ?>">
        <?= get_access_level() === 'IP_IN_RANGE' ? 'Trusted Network' : 
            ucfirst(strtolower(get_access_level())) ?>
    </span>
<?php endif; ?>
```

---

## Testing Checklist for Fixes

- [ ] IP_IN_RANGE: Badge shows "Trusted Network"
- [ ] IP_IN_RANGE: Login button visible (not logout)
- [ ] IP_IN_RANGE: Can click login and authenticate as admin
- [ ] After admin login from IP range: Logout works normally
- [ ] Anonymous accessing non-public assembly: Redirects to access_denied.php
- [ ] Access level badges in header for all user types
- [ ] COLLABORATOR: Can logout normally
- [ ] ADMIN: Can logout normally

---

**Priority:**
1. 🔴 Issue 2 - IP_IN_RANGE logout (breaks admin workflow)
2. 🔴 Issue 3 - Assembly access redirect (confusing UX)
3. 🟡 Issue 4 - Header badges (nice to have)
4. 🟢 Issue 1 - Verify badge CSS (likely already working)
