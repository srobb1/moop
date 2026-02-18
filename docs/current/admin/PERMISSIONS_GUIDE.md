# Permissions Workflow Documentation

## Overview

This system uses **assembly-based permissions** with clear separation between:
- **Permissions** - Who can access what (determined by user role and users.json)
- **Organization** - How users find things (determined by groups in metadata)

## User Access Levels

The system recognizes four types of users:

### 1. ALL Users (IP-Based)
- **Criteria**: IP address matches allowed range (configured in access_control.php)
- **Authentication**: Automatic (no login required)
- **Access to Data**: Everything - all organisms, all assemblies, all tools
- **Access to Admin Tools**: NO - Admin tools are restricted to logged-in admins only
- **Storage**: Session variable `$_SESSION['access_level'] = 'ALL'`

### 2. Admin Users (Logged-In)
- **Criteria**: User account with `"role": "admin"` in users.json
- **Authentication**: Login with username/password
- **Access to Data**: Everything - all organisms, all assemblies, all tools
- **Access to Admin Tools**: YES - Full access to admin panel and admin functions
- **Storage**: Session variables:
  - `$_SESSION['access_level'] = 'Admin'`
  - `$_SESSION['role'] = 'admin'`
  - `$_SESSION['access'] = []` (empty, not needed)

### 3. Collaborator Users (Logged-In)
- **Criteria**: User account without admin role in users.json
- **Authentication**: Login with username/password
- **Access to Data**: 
  - Specific assemblies listed in their `access` object in users.json
  - Plus: All assemblies in "Public" group (as a bonus)
- **Access to Admin Tools**: NO - Cannot access admin panel
- **Storage**: Session variables:
  - `$_SESSION['access_level'] = 'Collaborator'`
  - `$_SESSION['access'] = { "Organism": ["assembly1", "assembly2"], ... }`

### 4. Visitors (Not Logged-In)
- **Criteria**: No login, not in IP range
- **Authentication**: None
- **Access to Data**: Only assemblies designated as "Public" (in the "Public" group)
- **Access to Admin Tools**: NO - Cannot access admin panel
- **Storage**: No special session variables

## Important: Admin Tools Access Control

**Admin tools are accessible ONLY to logged-in admin users:**

```
IP-Based Users (ALL)     → Can access all data, but NOT admin tools
Collaborators            → Can access only their data, NOT admin tools
Visitors                 → Can access only public data, NOT admin tools
Admin Users (logged-in)  → Can access everything including admin tools
```

When a non-admin user (including IP-based users) tries to access `/admin/`:
1. Check: Is user logged-in with `$_SESSION['role'] === 'admin'`?
   - YES → Allow access
   - NO → Redirect to access_denied.php

This prevents IP-based users from bypassing the login requirement for admin functions.

## Data Structures

### users.json (Location: /var/www/html/users.json)

```json
{
  "username": {
    "password": "bcrypt_hashed_password",
    "access": {
      "Organism_Name": ["assembly_name_1", "assembly_name_2"],
      "Another_Organism": ["assembly_x"]
    },
    "role": "admin"  // Optional - only include for admin accounts
  },
  "admin_user": {
    "password": "bcrypt_hashed_password",
    "access": {},
    "role": "admin"  // This makes it an admin account
  }
}
```

**Key Points:**
- `access` object maps organism names to arrays of assembly names
- Collaborators can have access to some, but not all, assemblies of an organism
- Collaborators automatically get access to all "Public" group assemblies too
- Admin users have empty `access` object (they access everything anyway)
- Passwords must be bcrypt hashed
- Only admin accounts have the `"role": "admin"` field

### organism_assembly_groups.json (Location: metadata/)

```json
[
  {
    "organism": "Anoura_caudifer",
    "assembly": "GCA_004027475.1",
    "groups": ["Bats", "Cute", "Public"]
  },
  {
    "organism": "Lasiurus_cinereus",
    "assembly": "GCA_011751065.1",
    "groups": ["Bats", "Cute"]
  },
  {
    "organism": "Montipora_capitata",
    "assembly": "HIv3",
    "groups": ["Corals", "Public"]
  }
]
```

**Key Points:**
- Each entry represents ONE organism + assembly combination
- `groups` array lists all groups this assembly belongs to
- **"Public" group** = accessible to visitors without login
- Other groups are purely for UI organization (don't affect permissions)
- An assembly can be in multiple groups (e.g., "Bats", "Cute", and "Public")

## Permission Check Logic

### The Permission Hierarchy for Data Access

When a user tries to access an assembly, the system checks (in order):

```
1. Is user ALL (IP-based)?           → YES = ALLOW
                                      → NO = continue to 2

2. Is user Admin (role: admin)?       → YES = ALLOW
                                      → NO = continue to 3

3. Is assembly in "Public" group?     → YES = ALLOW
                                      → NO = continue to 4

4. Is user Collaborator with assembly in their access list?
   (assembly in $_SESSION['access'][$organism][]?)
                                      → YES = ALLOW
                                      → NO = DENY
```

### The Permission Hierarchy for Admin Tools

When a user tries to access admin tools (`/admin/`), the system checks:

```
Is user logged-in AND $_SESSION['role'] === 'admin'?
                                      → YES = ALLOW
                                      → NO = DENY (redirect to access_denied.php)
```

**NOTE:** IP-based users (ALL access level) cannot access admin tools even though they have universal data access. They must be logged in as an admin account.

### Permission Check Functions

**`has_assembly_access($organism, $assembly)`** - Main permission function for data
```php
// Returns TRUE if user can access the assembly, FALSE otherwise
// Called by: download tools, display tools, extract tools

if (has_assembly_access('Lasiurus_cinereus', 'GCA_011751065.1')) {
    // User can access this assembly
} else {
    // User cannot access - redirect appropriately
}
```

**`is_public_assembly($organism, $assembly)`** - Check if assembly is public
```php
// Returns TRUE if assembly has "Public" group, FALSE otherwise

if (is_public_assembly('Montipora_capitata', 'HIv3')) {
    // This assembly is in Public group - visitors can access
}
```

**Admin Tool Check** - Done in admin pages
```php
// In /admin/index.php and other admin pages:
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /$site/access_denied.php");
    exit;
}
```

## Session Variables After Login

When a user logs in, the session is populated:

```php
$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'username';
$_SESSION['access_level'] = 'Admin' or 'Collaborator';
$_SESSION['access'] = array of organisms and assemblies;
$_SESSION['role'] = 'admin' or null;  // 'admin' for admin users, null for collaborators
```

## Common Workflows

### Workflow 1: Visitor Downloads from Public Assembly

```
1. Visitor (not logged in) accesses download_fasta.php
   ↓
2. getAccessibleAssemblies() is called
   - Loops through all assemblies in metadata
   - For each: checks is_public_assembly()
   - Only "Public" assemblies returned
   ↓
3. Visitor sees only Public assemblies
   ↓
4. Visitor selects assembly and enters feature IDs
   ↓
5. Download request submitted
   ↓
6. System checks: has_assembly_access()?
   - Assembly is Public? YES → ALLOW DOWNLOAD
   ↓
7. FASTA file returned to visitor
```

### Workflow 2: Collaborator with Partial Access

**Example: User "test10"**
- Permission: `Anoura_caudifer: ["assembly_v1"]`
- Permission: `Lasiurus_cinereus: ["GCA_011751065.1", "assembly_v1"]`

```
1. test10 logs in
   ↓
2. $_SESSION['access'] = {
     "Anoura_caudifer": ["assembly_v1"],
     "Lasiurus_cinereus": ["GCA_011751065.1", "assembly_v1"]
   }
   $_SESSION['role'] = null  (not an admin)
   ↓
3. Visits download_fasta.php
   ↓
4. getAccessibleAssemblies() is called
   - Checks Anoura/GCA_004027475.1: is_public? YES → INCLUDE
   - Checks Anoura/assembly_v1: in $_SESSION['access']? YES → INCLUDE
   - Checks Lasiurus/GCA_011751065.1: in $_SESSION['access']? YES → INCLUDE
   - Checks Lasiurus/GCA_011751095.1: 
     * in $_SESSION['access']? NO
     * is_public? NO
     * EXCLUDE
   - Checks Lasiurus/assembly_v1: in $_SESSION['access']? YES → INCLUDE
   - Checks Montipora/HIv3: is_public? YES → INCLUDE
   ↓
5. test10 sees 5 accessible assemblies
   ↓
6. test10 tries to visit /admin/
   ↓
7. Admin check: $_SESSION['role'] === 'admin'? NO
   → DENIED - redirected to access_denied.php
   ↓
8. test10 tries to download from Lasiurus/GCA_011751095.1
   ↓
9. System checks: has_assembly_access()?
   - Assembly is Public? NO
   - In $_SESSION['access']? NO
   - → DENY - redirect to access_denied.php
```

### Workflow 3: Admin User

```
1. Admin logs in (role: admin)
   ↓
2. $_SESSION['access_level'] = 'Admin'
   $_SESSION['role'] = 'admin'
   ↓
3. Can visit /admin/ and access admin tools
   ↓
4. Visits download_fasta.php
   ↓
5. getAccessibleAssemblies() is called
   - For EVERY assembly: has_access('ALL') → TRUE
   - All assemblies included (regardless of Public group or users.json)
   ↓
6. Admin sees ALL assemblies
   ↓
7. Admin can download from ANY assembly
```

### Workflow 4: IP-Based User (ALL Access)

```
1. User from allowed IP range accesses the site
   ↓
2. Auto-authenticated by access_control.php
   $_SESSION['access_level'] = 'ALL'
   $_SESSION['logged_in'] = true
   $_SESSION['role'] = null  (NOT admin)
   ↓
3. Can download sequences from ALL assemblies (data access)
   ↓
4. Tries to visit /admin/index.php
   ↓
5. Admin check: $_SESSION['role'] === 'admin'? NO
   → DENIED - redirected to access_denied.php
   ↓
6. IP-based users cannot access admin tools
   (even though they have universal data access)
```

## Creating and Managing Users

### Add a Collaborator with Specific Access

Edit `/var/www/html/users.json`:

```json
{
  "maria": {
    "password": "$2y$10$...",  // bcrypt hash of password
    "access": {
      "Anoura_caudifer": ["assembly_v1"],
      "Lasiurus_cinereus": ["GCA_011751065.1"]
    }
  }
}
```

Result: Maria can:
- Download from Anoura_caudifer/assembly_v1 (explicit permission)
- Download from Lasiurus_cinereus/GCA_011751065.1 (explicit permission)
- Download from any assembly in "Public" group (automatic bonus)
- Cannot access /admin/ tools

### Add an Admin User

Edit `/var/www/html/users.json`:

```json
{
  "manager": {
    "password": "$2y$10$...",  // bcrypt hash of password
    "access": {},
    "role": "admin"
  }
}
```

Result: Manager:
- Has access to EVERYTHING (all organisms, all assemblies)
- Can access and use admin tools at `/admin/`
- Must log in with username/password (cannot access via IP range)

### Add Users with Multiple Organisms

Edit `/var/www/html/users.json`:

```json
{
  "researcher": {
    "password": "$2y$10$...",
    "access": {
      "Anoura_caudifer": ["assembly_v1", "GCA_004027475.1"],
      "Lasiurus_cinereus": ["assembly_v1"],
      "Montipora_capitata": ["HIv3"]
    }
  }
}
```

Result: Researcher can download from:
- Anoura_caudifer/assembly_v1
- Anoura_caudifer/GCA_004027475.1
- Lasiurus_cinereus/assembly_v1
- Montipora_capitata/HIv3
- Any additional "Public" group assemblies
- Cannot access admin tools

## Managing Groups (UI Organization)

### What Groups Are

Groups are **purely for user interface organization**. They help users find and browse assemblies.

### What Groups Are NOT

- Groups do NOT grant permissions
- Group membership does NOT affect who can access what
- You can be in the "Bats" group and still not have access (if not admin, not public, and not in users.json)

### How to Add Groups

Edit `/data/moop/metadata/organism_assembly_groups.json`:

```json
[
  {
    "organism": "Anoura_caudifer",
    "assembly": "assembly_v1",
    "groups": ["Bats", "Lab_JohnSmith", "2024_Project", "Public"]
  }
]
```

Result: Assembly appears in:
- Bats group (in UI)
- Lab_JohnSmith group (in UI)
- 2024_Project group (in UI)
- Public group (in UI AND accessible without login)

### Example: Multiple Groups, Different Permissions

```json
[
  {
    "organism": "Lasiurus_cinereus",
    "assembly": "GCA_011751065.1",
    "groups": ["Bats", "Species_Study_2024"]
  },
  {
    "organism": "Lasiurus_cinereus",
    "assembly": "GCA_011751095.1",
    "groups": ["Bats", "High_Quality_Assemblies", "Public"]
  }
]
```

With users.json:
```json
{
  "john": {
    "access": {
      "Lasiurus_cinereus": ["GCA_011751065.1"]
    }
  }
}
```

Results:
- John CAN access GCA_011751065.1 (has explicit permission)
- John CAN access GCA_011751095.1 (it's Public)
- Both are in "Bats" group but grouping doesn't matter for access
- GCA_011751065.1 is NOT public, but John has it anyway due to explicit permission

## Understanding "Public"

The "Public" group is special - it's the ONLY group that affects permissions:

- If an assembly has "Public" in its groups array → Visitors can access it
- If an assembly does NOT have "Public" → Only users in users.json and admins can access it
- All other groups are ignored for permission purposes

## Tools That Use These Permissions

### Download Tools
- **download_fasta.php** - Search and download FASTA sequences
- **fasta_extract.php** - Extract and download sequences for features

Both tools:
1. Call `getAccessibleAssemblies()` to show only accessible assemblies
2. Validate permissions with `has_assembly_access()` before allowing download
3. Redirect to login if not logged in and assembly is private
4. Redirect to access_denied if logged in but don't have permission

### Display Tools
- **organism_display.php** - Display organism information
- **assembly_display.php** - Display assembly information
- **groups_display.php** - Display groups and assemblies

These also use permission checks to show/hide data.

### Admin Tools
- **admin/index.php** and other admin pages

Check: `$_SESSION['role'] === 'admin'` before allowing access

## Troubleshooting Common Issues

### Issue: User can't see an assembly

**Check:**
1. Is the assembly listed in `organism_assembly_groups.json`? (Must be added there first)
2. For visitors: Does it have "Public" in groups?
3. For collaborators: Is it in their `access` object in users.json?
4. Is the assembly directory actually present in the data directory?

### Issue: User can see assembly but can't download

**Check:**
1. Does the assembly have FASTA files? (.fa, .fasta, .faa, .nt.fa, .aa.fa)
2. Are the files in the correct directory?
3. Does blastdbcmd have access to the files?

### Issue: Collaborator sees too many/too few assemblies

**Check:**
1. Verify their `access` object in users.json (correct organism and assembly names)
2. Check if they're accidentally seeing "Public" assemblies (this is intentional)
3. Verify assembly names match exactly in both users.json and organism_assembly_groups.json

### Issue: Admin can't access everything

**Check:**
1. Verify `"role": "admin"` is set in users.json
2. Verify it's spelled exactly as "admin" (lowercase)
3. Clear browser cookies and log in again

### Issue: IP-Based User (ALL) Can't Access Admin Tools

**This is correct behavior.** IP-based users have universal data access but cannot access admin tools.

**Solution:** Create an admin account in users.json for users who need admin access. They must log in with username/password.

## Summary Table

| User Type | How to Create | Data Access | Admin Tools | Notes |
|-----------|---------------|-------------|-------------|-------|
| ALL (IP) | Configure IP range in access_control.php | Everything | NO | Auto-authenticated, no login |
| Admin | Add to users.json with `"role": "admin"` | Everything | YES | Must login |
| Collaborator | Add to users.json with `access` object | Specific + Public | NO | Must login |
| Visitor | No action needed | "Public" only | NO | No login |

## Files Involved

- **Login/Auth**: `login.php`, `access_control.php`
- **Permissions**: `includes/access_control.php`, `tools/moop_functions.php`
- **Download Tools**: `tools/extract/download_fasta.php`, `tools/extract/fasta_extract.php`
- **Admin Tools**: `admin/index.php` (checks for `$_SESSION['role'] === 'admin'`)
- **Configuration**: `/var/www/html/users.json`, `metadata/organism_assembly_groups.json`
- **Documentation**: `notes/PERMISSIONS_WORKFLOW.md` (this file)
