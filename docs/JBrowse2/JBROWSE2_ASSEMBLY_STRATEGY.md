# JBrowse2 Assembly Strategy: Static vs Dynamic Config

## The Question

When you have multiple assemblies with different access levels (Public, Collaborator, Admin), how should they appear in JBrowse2?

**Option A: Static config.json (Current Approach)**
- Only add PUBLIC assemblies to `/var/www/html/moop/jbrowse2/config.json`
- Private/Admin assemblies are NOT accessible through JBrowse2 UI
- Simple, secure, no permission logic needed in UI

**Option B: Dynamic config (Future Enhancement)**
- All assemblies listed in config.json regardless of access level
- Permission checking happens at the API level
- Users only see tracks/features they're allowed to access
- More flexible but requires API integration

## Current Architecture: Option A (Static Config)

### How It Works

```
User → Browser → JBrowse2 UI
                   ↓
              config.json
                   ↓
    "Show these assemblies:"
    - Anoura_caudifer (Public)
                   ↓
              User clicks assembly
                   ↓
           API (/api/jbrowse2/assembly.php)
                   ↓
    Check user permissions
    Return tracks user can access
```

### Benefits
✅ Simple to manage - just list public assemblies
✅ Secure by default - private assemblies not even listed
✅ Fast - no permission logic in UI layer
✅ Clear separation - metadata defines permissions

### Limitations
❌ Can't show "you don't have access to this assembly" message
❌ Different users see different assembly lists (requires custom UI)
❌ Admin can't easily access all assemblies from standard UI

## Recommended: Hybrid Approach

### Best Practice

```
config.json contains:
  - ALL assemblies (public, private, admin)
  - But UI only shows based on user access level

API provides:
  - Assembly metadata with access level
  - Permission-aware track lists
  - User sees "You don't have access" or gets empty track list
```

### Implementation

#### 1. Update config.json to include all assemblies:

```json
{
  "assemblies": [
    {
      "name": "Anoura_caudifer_GCA_004027475.1",
      "displayName": "Anoura_caudifer (GCA_004027475.1)",
      "accessLevel": "Public",
      "sequence": { ... }
    },
    {
      "name": "Private_Organism_GCA_123456.1",
      "displayName": "Private Organism (GCA_123456.1)",
      "accessLevel": "Collaborator",
      "sequence": { ... }
    }
  ]
}
```

#### 2. JBrowse2 custom session/UI checks:

The UI can:
- Load config.json
- Filter assemblies by user access level
- Show unavailable assemblies as "locked" or disabled

#### 3. API provides access control:

```bash
GET /api/jbrowse2/assembly.php?organism=X&assembly=Y
  ↓
Check user permissions
  ↓
Return config with only permitted tracks
OR
Return 403 Forbidden if user can't access
```

## What We Should Do Now

### Short Term (Recommended)
**Use Option A: Static config.json with only PUBLIC assemblies**

**Why:**
- JBrowse2 doesn't have built-in permission UI
- Simpler to manage during bulk loading
- Permissions enforced by API anyway
- Can upgrade later

**How:**
```bash
# When adding an assembly, only add to config.json if:
./tools/jbrowse/add_assembly_to_jbrowse.sh Org Asm --access-level Public

# If access level is Public, add to config.json
# If not, skip config.json (keep in metadata only)
```

### Long Term (Enhancement)
**Implement Option B: Custom JBrowse2 UI with permission checking**

**What's needed:**
- Query API to get user's accessible assemblies
- Filter config.json dynamically
- Show custom message for restricted assemblies
- Could be a JBrowse2 plugin

## Decision Matrix

| Scenario | Approach | Reason |
|----------|----------|--------|
| Single-user/single-org system | Add all to config.json | Simple, everyone sees everything |
| Public genome browser | Only public in config.json | Security by default |
| Multi-user, permission-aware | Add all, filter by API | Most flexible, requires custom UI |
| Collaborative research group | Only collaborator+ in config.json | Balance of security and convenience |

## Current Setup

**Current:**
- 1 Public assembly in config.json
- 0 Private/Admin assemblies
- Permission checking at API level

**Recommended for bulk loading:**
```bash
# For each assembly:
./tools/jbrowse/add_assembly_to_jbrowse.sh Org Assembly --access-level Public

# Then ONLY if access-level is "Public":
# Add to /var/www/html/moop/jbrowse2/config.json
```

## Implementation Checklist

### For Public Assemblies
- [ ] Run setup_jbrowse_assembly.sh
- [ ] Run add_assembly_to_jbrowse.sh
- [ ] Add to jbrowse2/config.json (accessible to all users)

### For Private/Collaborator Assemblies
- [ ] Run setup_jbrowse_assembly.sh
- [ ] Run add_assembly_to_jbrowse.sh
- [ ] DO NOT add to config.json (not accessible via UI)
- [ ] Metadata definition enforces permissions
- [ ] API enforces access control

### For Admin-only Assemblies
- [ ] Run setup_jbrowse_assembly.sh
- [ ] Run add_assembly_to_jbrowse.sh
- [ ] DO NOT add to config.json (not visible to regular users)
- [ ] Admin can access via direct API calls

## Future Enhancement: Proposal

Create a script that auto-generates config.json:

```bash
./tools/jbrowse/generate_jbrowse_config.sh --min-access-level Public

# Reads all assembly definitions from metadata
# Filters by access level
# Generates config.json
# Could run on deployment
```

## Summary

**For now:** Keep config.json with only PUBLIC assemblies
**As you grow:** Consider adding all assemblies with filtering at UI/API level
**Key principle:** Metadata definitions are the source of truth, config.json is a convenience layer

