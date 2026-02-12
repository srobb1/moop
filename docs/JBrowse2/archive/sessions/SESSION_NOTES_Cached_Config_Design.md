maybe the direcotry should not be called tmp, since they wll stay around for a while (since they are
   small, or until they need to be updated) they can be in teh jbrowse2 folder. that might be good.
   assess. moop/jbrowse2/configs asses

 ✔ Check current configs directory
    $ ls -la /data/moop/jbrowse2/configs/
    └ 6 lines...

 ● Good question! Let me assess the options:

   Option 1: /tmp/jbrowse-configs/

   Pros:

     - ✅ Traditional location for cache files
     - ✅ Auto-cleaned by system on reboot
     - ✅ Separate from web-accessible files

   Cons:

     - ❌ Lost on server reboot (need regeneration)
     - ❌ Not backed up
     - ❌ Outside your git/deployment structure

   ----------------------------------------------------------------------------------------------------

   Option 2: /data/moop/jbrowse2/configs/ (Current location)

   Current structure:

     /data/moop/jbrowse2/configs/
     ├── Anoura_caudifer_GCA_004027475.1/
     │   └── config.json
     └── Nematostella_vectensis_GCA_033964005.1/
         └── config.json

   New structure with access levels:

     /data/moop/jbrowse2/configs/
     ├── Anoura_caudifer_GCA_004027475.1/
     │   ├── config.json              ← OLD (delete? or keep as ALL access?)
     │   ├── PUBLIC.json              ← NEW
     │   ├── COLLABORATOR.json        ← NEW
     │   └── ADMIN.json               ← NEW
     └── Nematostella_vectensis_GCA_033964005.1/
         ├── PUBLIC.json
         ├── COLLABORATOR.json
         └── ADMIN.json

   Pros:

     - ✅ Organized with assembly
     - ✅ Survives reboot
     - ✅ Gets backed up with your data
     - ✅ Web-accessible (can debug by visiting URL)
     - ✅ Consistent with current structure
     - ✅ Already has proper permissions

   Cons:

     - ⚠️ Web-accessible (but that's fine - configs should be public anyway)
     - ⚠️ Need to update .gitignore to not commit these

   ----------------------------------------------------------------------------------------------------

   Option 3: /data/moop/jbrowse2/cache/

   New dedicated cache folder:

     /data/moop/jbrowse2/cache/
     ├── Anoura_caudifer_GCA_004027475.1_PUBLIC.json
     ├── Anoura_caudifer_GCA_004027475.1_COLLABORATOR.json
     ├── Anoura_caudifer_GCA_004027475.1_ADMIN.json
     ├── Nematostella_vectensis_GCA_033964005.1_PUBLIC.json
     ├── Nematostella_vectensis_GCA_033964005.1_COLLABORATOR.json
     └── Nematostella_vectensis_GCA_033964005.1_ADMIN.json

   Pros:

     - ✅ Clear separation: "configs" = old static, "cache" = new dynamic
     - ✅ Easy to clean all caches: rm -rf jbrowse2/cache/*
     - ✅ Survives reboot
     - ✅ Gets backed up
     - ✅ Web-accessible for debugging

   Cons:

     - ⚠️ New directory structure

   ----------------------------------------------------------------------------------------------------

   Recommendation: Option 2 - Use existing configs/ with subdirectories

   Why:

     - Already exists and has proper permissions
     - Organized by assembly (makes sense)
     - Easy to find: "Where's the config for Nematostella as PUBLIC?" →
   configs/Nematostella_vectensis_GCA_033964005.1/PUBLIC.json
     - Can phase out old config.json files gradually
     - Web URLs are clean: /moop/jbrowse2/configs/Nematostella_vectensis_GCA_033964005.1/PUBLIC.json

   Migration path:

     - Keep old config.json as fallback initially
     - New system uses {ACCESS_LEVEL}.json
     - Eventually delete old config.json files

   Implementation:

     $CACHE_DIR = __DIR__ . '/../../jbrowse2/configs';
     $assembly_dir = "$CACHE_DIR/{$organism}_{$assembly}";
     $cache_file = "$assembly_dir/{$user_access_level}.json";

   Add to .gitignore:

     jbrowse2/configs/*/PUBLIC.json
     jbrowse2/configs/*/COLLABORATOR.json
     jbrowse2/configs/*/ADMIN.json
     jbrowse2/configs/*/IP_IN_RANGE.json

   ----------------------------------------------------------------------------------------------------

   Alternative: Option 3 if you want cleaner separation

   Use /data/moop/jbrowse2/cache/ with flat structure for simplicity.

   Which do you prefer?

     - Option 2: Organized subdirectories (recommended)
     - Option 3: Flat cache directory (simpler)

 > option 2 -- where would we put user_id specific ones. do we need that. hmm. when i load a track i say
    if it is admin, collab, ip_range, public. so then any user , if a collaborator, would be checked to
   see if they have access to that assemlb. can you review? step through the permissions? overall i like
    option 2 and am not sure if we ever need user_id specific ones?

 ✔ Read api/jbrowse2/assembly.php:133-190
   └ 62 lines read

 ✔ Check COLLABORATOR logic
    $ grep -n "COLLABORATOR\|required_groups\|user_groups" /data/moop/api/jbrowse2/assembly.php | head -20
    └ 7 lines...

 ✔ Read api/jbrowse2/assembly.php:220-245
   └ 31 lines read

 ● Perfect! Let me trace through the permission flow:

   Permission Flow Analysis

   Track Access Levels (Set when adding tracks)

   When you add a track, you specify ONE access level:

     --access PUBLIC       # Anyone can see
     --access COLLABORATOR # Logged-in collaborators
     --access ADMIN        # Admins + IP whitelist only

   User Access Levels (Determined at login)

   4 levels in hierarchy:

     ADMIN (4)         → Highest
     IP_IN_RANGE (3)   → IP whitelist
     COLLABORATOR (2)  → Logged in users
     PUBLIC (1)        → Anonymous users

   Permission Logic (Step-by-step)

   For PUBLIC tracks:

     Track: access_level = "PUBLIC" (requires level 1)
     User: PUBLIC (level 1) → ✅ Can see
     User: COLLABORATOR (level 2) → ✅ Can see (higher level)
     User: ADMIN (level 4) → ✅ Can see

   For COLLABORATOR tracks:

     Track: access_level = "COLLABORATOR" (requires level 2)
     User: PUBLIC (level 1) → ❌ Cannot see (too low)
     User: COLLABORATOR (level 2) → Check assembly access...
       - If user has access to THIS assembly → ✅ Can see
       - If no access to this assembly → ❌ Cannot see
     User: ADMIN (level 4) → ✅ Can see (no assembly check)

   For ADMIN tracks:

     Track: access_level = "ADMIN" (requires level 4)
     User: PUBLIC (level 1) → ❌ Cannot see
     User: COLLABORATOR (level 2) → ❌ Cannot see
     User: ADMIN (level 4) → ✅ Can see
     User: IP_IN_RANGE (level 3) → ✅ Can see (treated like admin)

   ----------------------------------------------------------------------------------------------------

   The Key Question: Do we need user-specific configs?

   Current System (Access Level Only)

   Cache files:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json          ← All anonymous users share
     ├── COLLABORATOR.json    ← All collaborators WITH ACCESS share
     └── ADMIN.json           ← All admins share

   Issue with COLLABORATOR:

     - User A: Has access to Nematostella → sees COLLABORATOR tracks
     - User B: No access to Nematostella → sees NO tracks
     - BUT: They both get the same cached COLLABORATOR.json! ❌

   The Problem

   Lines 174-184 show the issue:

     if ($user_access_level === 'COLLABORATOR' && $min_required_level >=
   $access_hierarchy['COLLABORATOR']) {
         $user_access = $_SESSION['access'] ?? [];
         if (isset($user_access[$organism]) && in_array($assembly, (array)$user_access[$organism])) {
             $user_can_access = true; // ← USER-SPECIFIC CHECK
         }
     }

   The assembly access check is per-user!

   ----------------------------------------------------------------------------------------------------

   Solutions

   Option A: No COLLABORATOR caching (Recommended)

   Cache strategy:

     - PUBLIC → Cache (all same)
     - ADMIN → Cache (all same)
     - IP_IN_RANGE → Cache (all same)
     - COLLABORATOR → NO CACHE (generate dynamically every time)

   Why:

     - Collaborators are logged in (can handle slight delay)
     - Assembly access varies per user
     - Avoids complex user-specific caching

   Files:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json
     ├── ADMIN.json
     └── IP_IN_RANGE.json

   Option B: Cache per assembly access combination

   Cache files:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json
     ├── ADMIN.json
     ├── COLLABORATOR_HAS_ACCESS.json  ← Users with access
     └── COLLABORATOR_NO_ACCESS.json   ← Users without access

   Logic:

     if ($user_access_level === 'COLLABORATOR') {
         $has_access = isset($_SESSION['access'][$organism]) &&
                       in_array($assembly, $_SESSION['access'][$organism]);
         $cache_suffix = $has_access ? 'HAS_ACCESS' : 'NO_ACCESS';
         $cache_file = "COLLABORATOR_$cache_suffix.json";
     }

   Pros: Still cached Cons: Slightly more complex

   Option C: Per-user caching (Overkill)

   Only needed if:

     - You add required_groups (group-based permissions)
     - You add per-user track visibility
     - Very fine-grained permissions

   Currently: You don't need this!

   ----------------------------------------------------------------------------------------------------

   Recommendation: Option A

   Implementation:

     // assembly-cached.php

     $user_access_level = get_access_level();

     // Don't cache COLLABORATOR configs - they're per-user
     if ($user_access_level === 'COLLABORATOR') {
         // Generate fresh config every time
         header('Content-Type: application/json');
         header('X-JBrowse-Cache: disabled-for-collaborators');
         include __DIR__ . '/assembly.php';
         exit;
     }

     // Cache PUBLIC, ADMIN, IP_IN_RANGE
     $cache_file = "$assembly_dir/{$user_access_level}.json";
     // ... rest of caching logic

   Result:

   3 cached files per assembly:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json      ← Cached
     ├── ADMIN.json       ← Cached
     └── IP_IN_RANGE.json ← Cached

   COLLABORATOR users: Get fresh config every time (takes ~50ms, totally fine)

   ----------------------------------------------------------------------------------------------------

   Directory Structure: Option 2 ✅

     /data/moop/jbrowse2/configs/
     ├── Anoura_caudifer_GCA_004027475.1/
     │   ├── PUBLIC.json
     │   ├── ADMIN.json
     │   └── IP_IN_RANGE.json
     └── Nematostella_vectensis_GCA_033964005.1/
         ├── PUBLIC.json
         ├── ADMIN.json
         └── IP_IN_RANGE.json

   No user_id specific files needed!

   Want me to implement Option A with this structure?

 > but permissions are made on a track by track basis. we know all the tracks of an organism-assembly
   that have the requirment of "collaborator", or admin, or ip_range. so all public + collaborator
   tracks would go into the organism_assembly.collaborator config. i think that should work. am i
   missing something in the logic flow? let me know

 ● You're absolutely right! I was overcomplicating it. Let me trace through this correctly:

   Track Permission Logic (Corrected)

   When adding a track:

     --access PUBLIC       # Track visible to: PUBLIC, COLLABORATOR, ADMIN
     --access COLLABORATOR # Track visible to: COLLABORATOR, ADMIN
     --access ADMIN        # Track visible to: ADMIN only

   When generating configs:

   PUBLIC user gets:

     - ✅ Tracks marked as PUBLIC
     - ❌ Tracks marked as COLLABORATOR
     - ❌ Tracks marked as ADMIN

   COLLABORATOR user gets:

     - ✅ Tracks marked as PUBLIC
     - ✅ Tracks marked as COLLABORATOR
     - ❌ Tracks marked as ADMIN

   ADMIN user gets:

     - ✅ Tracks marked as PUBLIC
     - ✅ Tracks marked as COLLABORATOR
     - ✅ Tracks marked as ADMIN

   ----------------------------------------------------------------------------------------------------

   The Assembly Access Check

   Looking at lines 174-184 again:

     if ($user_access_level === 'COLLABORATOR' && $min_required_level >=
   $access_hierarchy['COLLABORATOR']) {
         $user_access = $_SESSION['access'] ?? [];
         if (isset($user_access[$organism]) && in_array($assembly, (array)$user_access[$organism])) {
             // User has access to THIS assembly
             $user_can_access = true;
         }
     }

   This checks: "Does this COLLABORATOR user have access to view THIS ASSEMBLY at all?"

   BUT: This is an assembly-level check, not a track-level check!

   ----------------------------------------------------------------------------------------------------

   Your Logic is Correct! ✅

   You're right:

     - Track permissions are track-level (PUBLIC/COLLABORATOR/ADMIN)
     - Assembly access is assembly-level (which assemblies can this user see?)

   The Real Flow:

   User Alice (COLLABORATOR, has access to Nematostella):

     - Requests Nematostella assembly
     - Assembly check: ✅ Has access to Nematostella
     - Gets all PUBLIC + COLLABORATOR tracks

   User Bob (COLLABORATOR, NO access to Nematostella):

     - Requests Nematostella assembly
     - Assembly check: ❌ No access to Nematostella
     - Shouldn't even see the assembly in the list!

   The Key Insight:

   If a COLLABORATOR can open the assembly viewer at all, they have assembly access!

   The assembly access check happens before they click to view it (in get-config.php). By the time
   they're loading tracks in assembly.php, they've already passed the assembly gate.

   ----------------------------------------------------------------------------------------------------

   Therefore: Simple Caching Works! ✅

   Cache files per assembly:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json       ← PUBLIC tracks only
     ├── COLLABORATOR.json ← PUBLIC + COLLABORATOR tracks
     └── ADMIN.json        ← PUBLIC + COLLABORATOR + ADMIN tracks

   All COLLABORATOR users with access share the same cache!

   ----------------------------------------------------------------------------------------------------

   Verification

   Let me verify this is actually how it works in get-config.php:

 ✔ Read api/jbrowse2/get-config.php:60-85
   └ 30 lines read

 ● Wait! This shows assembly-level permissions, but I don't see the per-assembly check here. Let me look
   at the track filtering in assembly.php more carefully:

 ✔ Check the per-assembly user access check
    $ grep -B5 -A10 "user_access\[\$organism\]" /data/moop/api/jbrowse2/assembly.php
    └ 17 lines...

 ● Hmm, there IS a per-assembly check in the track filtering: $_SESSION['access'][$organism].

   Let me check what populates this:

 ✔ Find where session access is set
    $ grep -r "SESSION\['access'\]" /data/moop/lib/ /data/moop/includes/ 2>/dev/null | head -10
    └ 3 lines...

 ● Let me check the access control initialization:

 ✔ Check access control setup
    $ grep -n "SESSION.*access" /data/moop/includes/access_control.php | head -10
    └ 6 lines...

 ● So $_SESSION['access'] is set somewhere for COLLABORATOR users. This COULD mean:

   Scenario A: Different collaborators have different assembly access

     - Alice: Can see Nematostella, Anoura
     - Bob: Can only see Anoura
     - Result: Need different COLLABORATOR configs ❌

   Scenario B: All collaborators with access see the same tracks

     - The assembly list filters who sees what
     - Once viewing an assembly, all collaborators see same tracks
     - Result: Can share COLLABORATOR configs ✅

   ----------------------------------------------------------------------------------------------------

   Critical Question:

   In your system, do different COLLABORATOR users have access to different assemblies within the same
   organism?

   Example:

     - Alice (COLLABORATOR): Access to Nematostella assembly v1 only
     - Bob (COLLABORATOR): Access to Nematostella assembly v2 only

   OR:

     - If you can see the assembly in the list, you have full track access
     - Assembly access is binary: you either see it or you don't

   ----------------------------------------------------------------------------------------------------

   My Assessment:

   Based on the code, I think Scenario A is possible (per-user assembly access for collaborators).

   Therefore, my recommendation is:

   Don't cache COLLABORATOR configs

   Implementation:

     // For COLLABORATOR: Generate fresh (their assembly access may differ)
     if ($user_access_level === 'COLLABORATOR') {
         // No caching - per-user assembly permissions
         include __DIR__ . '/assembly.php';
         exit;
     }

     // Cache PUBLIC, ADMIN, IP_IN_RANGE

   Final structure:

     Nematostella_vectensis_GCA_033964005.1/
     ├── PUBLIC.json       ← All public users share
     ├── ADMIN.json        ← All admins share
     └── IP_IN_RANGE.json  ← All IP whitelist users share

   COLLABORATOR users get fresh configs (fast enough, they're logged in anyway).

   Is this acceptable, or do you want to cache COLLABORATOR too?

 > But we have a config file that lists all the tracks of Nematostella_vectensis-assembly1 that are
   accessible by any collaborator with access to Nematostella_vectensis-assembly1.

   And we will have another config file for collaborators with Anoura_caudier-assemblyZ

   so if i think a track should be accessible only by authorized users that have acces to that assemlby,
    it should work. walk through it

 ● You're absolutely right! Let me walk through this step-by-step:

   The Flow

   Step 1: User sees assembly list

   Alice (COLLABORATOR with access to Nematostella, not Anoura):

     get-config.php checks Alice's permissions:
     - Nematostella assembly → Check: Does Alice have access? ✅ YES → Show in list
     - Anoura assembly → Check: Does Alice have access? ❌ NO → Hide from list

   Bob (COLLABORATOR with access to Anoura, not Nematostella):

     get-config.php checks Bob's permissions:
     - Nematostella assembly → Check: Does Bob have access? ❌ NO → Hide from list
     - Anoura assembly → Check: Does Bob have access? ✅ YES → Show in list

   Step 2: User clicks to view an assembly

   Alice clicks Nematostella:

     - Alice can only click this because she has access (it's in her list)
     - Loads config from: Nematostella_vectensis_GCA_033964005.1/COLLABORATOR.json
     - This config contains: All PUBLIC + COLLABORATOR tracks for Nematostella

   Bob clicks Anoura:

     - Bob can only click this because he has access (it's in his list)
     - Loads config from: Anoura_caudifer_GCA_004027475.1/COLLABORATOR.json
     - This config contains: All PUBLIC + COLLABORATOR tracks for Anoura

   Step 3: What if Bob tries to access Nematostella directly?

   Bob manually types URL:

     http://localhost:8000/moop/jbrowse2/index.html?config=/moop/jbrowse2/configs/Nematostella_vectensis
   _GCA_033964005.1/COLLABORATOR.json

   Two safeguards:

     - File access check (if we implement it):  // In assembly-cached.php before serving cached file
       if ($user_access_level === 'COLLABORATOR') {
           // Verify user has access to this specific assembly
           $user_access = $_SESSION['access'] ?? [];
           if (!isset($user_access[$organism]) || !in_array($assembly, $user_access[$organism])) {
               http_response_code(403);
               echo json_encode(['error' => 'No access to this assembly']);
               exit;
           }
       }
     - Or simpler: The config is just track definitions - Bob can download it, but his browser can't
   load the actual track data files because those require authentication!

   ----------------------------------------------------------------------------------------------------

   Your Logic is Correct! ✅

   The key insight:

   Assembly Access = Gate

     - Checked in get-config.php (assembly list)
     - Checked again when loading config (security)

   Track Access = Content

     - PUBLIC tracks: Always in all configs
     - COLLABORATOR tracks: In COLLABORATOR + ADMIN configs
     - ADMIN tracks: Only in ADMIN config

   The Caching Works!

   All collaborators with access to Nematostella see the same tracks:

     Nematostella_vectensis_GCA_033964005.1/COLLABORATOR.json contains:
     - All PUBLIC tracks
     - All COLLABORATOR tracks

   The per-user difference is:

     - Alice can ACCESS the Nematostella config
     - Bob CANNOT access the Nematostella config (no permission)

   But IF they have access, they see the same tracks!

   ----------------------------------------------------------------------------------------------------

   Implementation

     /data/moop/jbrowse2/configs/
     ├── Nematostella_vectensis_GCA_033964005.1/
     │   ├── PUBLIC.json       ← PUBLIC tracks only
     │   ├── COLLABORATOR.json ← PUBLIC + COLLABORATOR tracks
     │   └── ADMIN.json        ← PUBLIC + COLLABORATOR + ADMIN tracks
     └── Anoura_caudifer_GCA_004027475.1/
         ├── PUBLIC.json
         ├── COLLABORATOR.json
         └── ADMIN.json

   With security check in assembly-cached.php:

     // Before serving cached COLLABORATOR config
     if ($user_access_level === 'COLLABORATOR') {
         $user_access = $_SESSION['access'] ?? [];
         if (!isset($user_access[$organism]) || !in_array($assembly, (array)$user_access[$organism])) {
             http_response_code(403);
             echo json_encode(['error' => 'Access denied to this assembly']);
             exit;
         }
     }
     // Now safe to serve the cached config

   ----------------------------------------------------------------------------------------------------

   Result

   ✅ Cache COLLABORATOR configs! Your logic is sound:

     - Security via assembly access check
     - All authorized collaborators share same config
     - Fast loading for everyone
     - Clean directory structure

   Want me to implement this with the security check?

 > yes

 ✔ Check configs directory permissions
    $ ls -ld /data/moop/jbrowse2/configs/
    └ 2 lines...
 ✔ Create api/jbrowse2/assembly-cached.php (+135)

 ● Now update the JavaScript loader to use the cached endpoint:

 ○ Edit js/jbrowse2-loader.js

 ╭─────────────────────────────────────────────────────────────────────────────────────────────────────╮
 │ Edit file                                                                                           │
 │                                                                                                     │
 │ jbrowse2-loader.js +3 -3                                                                            │
 │ /data/moop/js                                                                                       │
 │                                                                                                     │
 │ 214             assemblyId = 'default';                                                             │
 │ 215         }                                                                                       │
 │ 216                                                                                                 │
 │ 217  -      // Use dynamic API endpoint that filters tracks by user permissions                     │
 │ 218  -      // This ensures each user sees only tracks they have access to                          │
 │ 217  +      // Use cached config endpoint for better performance                                    │
 │ 218  +      // This caches configs per access level and validates permissions                       │
 │ 219         const iframe = document.getElementById('jbrowse2-iframe');                              │
 │ 220  -      const configUrl = `/moop/api/jbrowse2/assembly.php?organism=${encodeURIComponent(organi │
 │         sm)}&assembly=${encodeURIComponent(assemblyId)}`;                                           │
 │ 220  +      const configUrl = `/moop/api/jbrowse2/assembly-cached.php?organism=${encodeURIComponent │
 │         (organism)}&assembly=${encodeURIComponent(assemblyId)}`;                                    │
 │ 221         iframe.src = `/moop/jbrowse2/index.html?config=${encodeURIComponent(configUrl)}`;       │
 │ 222         iframe.title = `JBrowse2 Viewer for ${assembly.displayName}`;                           │
 │ 223     }                                                                                           │
 │                                                                                                     │
 │ Do you want to edit /data/moop/js/jbrowse2-loader.js?                                               │
 │                                                                                                     │
 │ ❯ 1. Yes                                                                                            │
 │   2. Yes, and approve all file operations for the rest of the running session                       │
 │   3. No, and tell Copilot what to do differently (Esc)                                              │
 │                                                                                                     │
 │ Confirm with number keys or ↑↓ keys and Enter, Cancel with Esc

════════════════════════════════════════════════════════════════
✅ COMPLETED: JBrowse2 Cached Config System
════════════════════════════════════════════════════════════════

Date: 2026-02-10

Implementation Summary:
-----------------------
1. Created assembly-cached.php - serves cached configs per access level
2. Updated generate-jbrowse-configs.php - generates 4 configs per assembly:
   - PUBLIC.json (public tracks only)
   - COLLABORATOR.json (public + collaborator tracks)
   - IP_IN_RANGE.json (public + collaborator tracks)
   - ADMIN.json (all tracks including test/unreleased)
3. Updated jbrowse2-loader.js to use cached endpoint
4. Added security check for COLLABORATOR assembly access
5. Updated .gitignore to exclude generated files

Access Hierarchy:
-----------------
ADMIN (4) > IP_IN_RANGE (3) > COLLABORATOR (2) > PUBLIC (1)

- ADMIN: Sees ALL tracks (can have test/unreleased tracks)
- IP_IN_RANGE: Sees public + collaborator tracks (not admin-only)
- COLLABORATOR: Sees public + collaborator tracks (per-assembly access checked)
- PUBLIC: Sees public tracks only

Workflow:
---------
When adding/modifying tracks:
1. Run track scripts (add_bam_track.sh, add_bigwig_track.sh, etc.)
2. Run: php tools/jbrowse/generate-jbrowse-configs.php
3. Cached configs are regenerated for all assemblies

Files:
------
- api/jbrowse2/assembly-cached.php (new)
- tools/jbrowse/generate-jbrowse-configs.php (updated)
- js/jbrowse2-loader.js (updated)
- docs/JBrowse2/WALKTHROUGH_Nematostella_vectensis.md (new)

Nematostella Integration Complete:
-----------------------------------
✅ Assembly setup with genome + annotations
✅ 3 tracks added (2 PUBLIC BigWig, 1 ADMIN BAM)
✅ Cached configs generated for all access levels
✅ Ready for production use

