# JBrowse2 Dynamic Configuration System

## Overview

Instead of a static `config.json`, JBrowse2 now loads assemblies dynamically based on the user's authentication status and access level.

## How It Works

### User Authentication Flow

```
User visits: http://localhost:8000/moop/jbrowse2

    ↓

Browser loads: jbrowse2-dynamic.html

    ↓

Page calls: /api/jbrowse2/get-config.php

    ↓

API checks:
  - Is user logged in? (session data)
  - What's their access level?
  - Query: $_SESSION['user_id'], $_SESSION['access_level']

    ↓

API queries: /data/moop/metadata/jbrowse2-configs/assemblies/*.json

    ↓

For EACH assembly:
  - Check assembly's defaultAccessLevel
  - Compare with user's access_level
  - Include if user can access

    ↓

Return: JSON config with only permitted assemblies

    ↓

Browser displays: List of accessible assemblies
```

## Access Level Hierarchy

```
PUBLIC
  └─ Visible to: Everyone (including anonymous)
     
COLLABORATOR
  └─ Visible to: Collaborators + Admins
  └─ Not visible to: Anonymous users
  
ALL (Admin)
  └─ Visible to: Admins only
  └─ Not visible to: Anyone else
```

## User Types & What They See

### Anonymous User (No login)
```
GET /moop/api/jbrowse2/get-config.php

Session: empty
User access level: 'Public' (default)

Sees: Only assemblies with defaultAccessLevel = 'Public'

Example:
  Anoura_caudifer (GCA_004027475.1) → ✓ Public, visible
  Human_GRCh38 → ✓ Public, visible
  Mouse_Collaborator → ✗ Collaborator, hidden
  Chimp_Admin → ✗ Admin only, hidden
```

### Logged-In Collaborator
```
GET /moop/api/jbrowse2/get-config.php

Session: 
  - user_id: 123
  - access_level: 'Collaborator'
  - is_admin: false

User access level: 'Collaborator'

Sees: Public AND Collaborator assemblies

Example:
  Anoura_caudifer (GCA_004027475.1) → ✓ Public, visible
  Human_GRCh38 → ✓ Public, visible
  Mouse_Collaborator → ✓ Collaborator, visible
  Chimp_Admin → ✗ Admin only, hidden
```

### Admin User
```
GET /moop/api/jbrowse2/get-config.php

Session:
  - user_id: 456
  - access_level: 'Collaborator'
  - is_admin: true

User access level: 'ALL' (admin override)

Sees: ALL assemblies (Public, Collaborator, Admin)

Example:
  Anoura_caudifer (GCA_004027475.1) → ✓ Visible
  Human_GRCh38 → ✓ Visible
  Mouse_Collaborator → ✓ Visible
  Chimp_Admin → ✓ Visible
```

## Implementation Details

### 1. API Endpoint

**File:** `/api/jbrowse2/get-config.php`

**Purpose:** Returns JBrowse2 config with filtered assemblies

**Input:** User session data
- `$_SESSION['user_id']` - Logged-in user ID (if present)
- `$_SESSION['access_level']` - User's access level (Collaborator, Public, etc.)
- `$_SESSION['is_admin']` - Boolean flag for admin

**Output:** JSON config with:
- `assemblies` - Array of accessible assemblies only
- `userAccessLevel` - User's current access level
- `tracks` - Empty (loaded per-assembly)

**Example Response:**
```json
{
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura_caudifer (GCA_004027475.1)",
      "aliases": ["ACA1", "GCA_004027475.1"],
      "accessLevel": "Public",
      "sequence": { ... }
    }
  ],
  "userAccessLevel": "Public",
  "tracks": [],
  "defaultSession": { "name": "New Session" }
}
```

### 2. Dynamic Loader Page

**File:** `/jbrowse2-dynamic.html`

**Purpose:** Interface to load and display accessible assemblies

**Features:**
- Calls `/api/jbrowse2/get-config.php` on page load
- Displays user's access level
- Lists all accessible assemblies
- Shows "View Genome" button for each assembly

**URL:** `http://localhost:8000/moop/jbrowse2-dynamic.html`

### 3. Session Configuration

Sessions are managed by your existing MOOP authentication system.

**Session setup example (in your login.php):**
```php
<?php
session_start();

// After user authenticates...
$_SESSION['user_id'] = $user_id;
$_SESSION['access_level'] = $user_access_level; // 'Public', 'Collaborator', etc.
$_SESSION['is_admin'] = ($user_role === 'admin');
```

## Assembly Definition Format

Each assembly definition file at `/metadata/jbrowse2-configs/assemblies/{Organism}_{AssemblyId}.json` must include `defaultAccessLevel`:

```json
{
  "name": "Anoura_caudifer_GCA_004027475.1",
  "displayName": "Anoura_caudifer (GCA_004027475.1)",
  "organism": "Anoura_caudifer",
  "assemblyId": "GCA_004027475.1",
  "aliases": ["ACA1", "GCA_004027475.1"],
  "defaultAccessLevel": "Public",
  "sequence": { ... }
}
```

Allowed values for `defaultAccessLevel`:
- `"Public"` - Visible to everyone
- `"Collaborator"` - Visible to collaborators and admins
- `"ALL"` - Visible to admins only

## Setting Up Session Data

Your authentication system should set session variables on login.

**Example in your login.php:**
```php
<?php
session_start();

if (authenticate_user($username, $password)) {
    $user = get_user_data($username);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['access_level'] = get_user_access_level($user['id']); 
    $_SESSION['is_admin'] = $user['is_admin'] ?? false;
    
    // Now when user visits /jbrowse2-dynamic.html
    // They'll see their permitted assemblies
}
```

## Workflow for Multiple Assemblies

### Loading Many Assemblies

1. **For each assembly:**
   ```bash
   ./tools/jbrowse/setup_jbrowse_assembly.sh /path/to/organism/assembly
   ./tools/jbrowse/add_assembly_to_jbrowse.sh Organism AssemblyId \
     --access-level [Public|Collaborator|ALL]
   ```

2. **No need to edit config.json**
   - Assembly definition includes access level
   - API reads from metadata
   - UI shows based on user authentication

3. **Users automatically see:**
   - Public assemblies: Always visible
   - Collaborator assemblies: If logged in as collaborator+
   - Admin assemblies: If logged in as admin

### Adding a Private/Collaborator Assembly

```bash
# Setup and register assembly
./tools/jbrowse/setup_jbrowse_assembly.sh /organisms/Mouse/GRCm39
./tools/jbrowse/add_assembly_to_jbrowse.sh Mouse GRCm39 \
  --access-level Collaborator

# That's it! No manual config.json editing needed
# Collaborators will see it automatically
# Anonymous users won't see it
```

## API Security

The dynamic config system maintains security by:

1. **Session-based authentication**
   - API checks `$_SESSION` for user data
   - Unauthorized users get 'Public' access level
   - Admin flag required for ALL access

2. **Access level filtering**
   - API compares user level with assembly level
   - Only returns assemblies user can access
   - Prevents unauthorized assemblies from appearing

3. **Track-level filtering**
   - Each track also has access levels
   - `/api/jbrowse2/assembly.php` filters tracks per user
   - Even if assembly is accessible, tracks might not be

## Future Enhancements

### 1. Full JBrowse2 Integration
Replace `/jbrowse2-dynamic.html` with actual JBrowse2 that:
- Loads config from API
- Passes session token to track servers
- Enforces track-level permissions

### 2. Custom Session Storage
Store user assembly permissions in database:
```sql
CREATE TABLE user_assembly_permissions (
  user_id INT,
  assembly_id VARCHAR(255),
  access_level ENUM('Public', 'Collaborator', 'Admin'),
  PRIMARY KEY (user_id, assembly_id)
);
```

### 3. Group-Based Access
Extend to support:
- Research groups
- Project teams
- Custom permission sets

## Troubleshooting

### "No Assemblies Available"
**Cause:** User doesn't have access to any defined assemblies
**Check:**
- User is logged in: `echo $_SESSION`
- User's access level: Check database
- Assembly access levels: Check metadata files

### Seeing wrong assemblies
**Cause:** Session data not set correctly
**Check:**
- Login system sets `$_SESSION['access_level']`
- Assembly `defaultAccessLevel` is correct
- Clear browser cache and cookies

### Assembly not in list
**Cause:** Access level mismatch
**Check:**
- Assembly `defaultAccessLevel` in definition
- User's actual permission level
- Admin status flag

## Files Created

- `/api/jbrowse2/get-config.php` - Dynamic config API
- `/jbrowse2-dynamic.html` - Assembly selection page
- `/docs/JBrowse2/JBROWSE2_DYNAMIC_CONFIG.md` - This file

## Next Steps

1. Test the dynamic config system
2. Integrate with your actual login system
3. Set session variables on login
4. Test with different user roles
5. Deploy to production

