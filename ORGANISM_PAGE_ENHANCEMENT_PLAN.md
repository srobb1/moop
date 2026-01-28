# Organism Page Enhancement Plan

## Overview
Enhance the organism information display page to show taxonomic hierarchy and group memberships, with links to dynamically generated taxonomy pages and group pages.

---

## 1. Taxonomic Breadcrumb Navigation

### Current State
- Shows Taxon ID linked to NCBI
- Shows Genus/Species
- Shows optional subclassification

### Enhancement: Taxonomic Hierarchy Display
**Goal:** Display taxonomic path (Kingdom → Phylum → Class → Order → Family → Genus → Species)

**Implementation:**
- Parse NCBI Taxonomy data for this organism
- Extract full taxonomic path from taxon_id
- Display as breadcrumb trail or hierarchical list
- Each level clickable to show dynamic taxonomy page
- Format: `Animalia > Chordata > Mammalia > Carnivora > Felidae > Felis > catus`

**Data Source:** 
- NCBI Taxonomy API or cached taxonomy data
- Stored in `metadata/taxonomy_cache/` or similar

**Example Visual:**
```
Taxonomy: Animalia > Chordata > Mammalia > Primates > Hominidae > Homo > sapiens
```

---

## 2. Dynamic Taxonomy Pages

### New Feature: Browse by Taxonomy Level

**Concept:**
- Generate pages for each major taxonomy level
- Show all organisms in system at that level
- Allows browsing like "All Mammals" or "All Vertebrates"

**URLs:**
- `/tools/taxonomy.php?rank=kingdom&name=Animalia`
- `/tools/taxonomy.php?rank=phylum&name=Chordata`
- `/tools/taxonomy.php?rank=class&name=Mammalia`
- etc.

**Content:**
- Title: "{rank}: {name}"
- Count of organisms at that level
- Organism cards (similar to groups page)
- Filter/search by organism name
- Links back up to parent level
- Links down to child levels

**Reuse Groups Page Structure:**
- Use same `organism-selector-card` styling
- Display organism cards in grid
- Include organism image, scientific name, common name
- Link to individual organism pages

**Implementation Priority:** Medium (Phase 2)

---

## 3. Manual Group Membership Cards

### Current State
- Groups exist but organism pages don't show which groups they belong to

### Enhancement: Display Group Cards
**Goal:** Show all manually-created groups this organism belongs to

**Location:** Below taxonomy breadcrumb on organism page

**Visual Design:**
- Small cards showing group name, description snippet, icon
- Link to `/tools/groups.php?group={group_name}`
- Shows count of organisms in that group
- "View Group" button/link

**Example:**
```
Organism Belongs to These Groups:
[Primates Card] - 15 organisms
[Mammals Card] - 245 organisms
[Vertebrates Card] - 8000 organisms
```

**Data Query:**
```php
// Pseudo-code
$groups = getGroupsForOrganism($organism_name);
// Returns: [
//   ['group_name' => 'Primates', 'count' => 15, ...],
//   ['group_name' => 'Mammals', 'count' => 245, ...],
// ]
```

**Implementation Priority:** High (Phase 1) - Easy to implement

---

## 4. Using Groups Page for Dynamic Taxonomy

### Concept: Dual-Purpose Groups
**Extend groups system to handle both:**
1. Manually curated groups (current)
2. Auto-generated taxonomy groups (new)

**Implementation Options:**

**Option A: Add taxonomy_rank flag to groups**
- Modify groups config to include optional `taxonomy_rank` and `taxonomy_name`
- Groups page checks if it's a taxonomy group
- If yes: dynamically loads organisms at that taxonomy level
- Reuse same groups.php template

**Option B: Separate pages**
- Keep groups.php for manual groups (current)
- Create taxonomy.php for taxonomy-based viewing
- Share same UI components and search logic

**Recommendation:** Option A (unified) - cleaner, less duplication

**Example Config:**
```json
{
  "group_name": "Primates",
  "in_use": true,
  "type": "taxonomy",
  "taxonomy_rank": "order",
  "taxonomy_name": "Primates",
  "description": "All primate species in the system..."
}
```

---

## 5. Implementation Phases

### Phase 1: Manual Group Cards (Easy, Quick Win)
**Effort:** Low | **Value:** High
- Add function `getGroupsForOrganism($organism_name)`
- Display group cards on organism page
- Link to existing group pages
- **Timeline:** 1-2 hours
- **Files:** 
  - `lib/database_queries.php` (new function)
  - `tools/pages/organism.php` (new section)
  - `css/organism.css` (new card styles)

### Phase 2: Taxonomy Breadcrumb (Medium)
**Effort:** Medium | **Value:** High
- Parse taxon_id and fetch full taxonomy path
- Display as breadcrumb navigation
- Each level links to new taxonomy.php page
- **Timeline:** 2-3 hours
- **Files:**
  - `lib/taxonomy.php` (new, taxonomy parsing functions)
  - `tools/pages/organism.php` (new section)
  - `css/organism.css` (breadcrumb styling)

### Phase 3: Dynamic Taxonomy Pages (Medium)
**Effort:** Medium | **Value:** Medium
- Create `tools/taxonomy.php` controller
- Create `tools/pages/taxonomy.php` template
- Integrate search/filter logic
- Reuse groups page search components
- **Timeline:** 3-4 hours
- **Files:**
  - `tools/taxonomy.php` (new)
  - `tools/pages/taxonomy.php` (new)
  - `lib/taxonomy.php` (expanded)
  - `css/taxonomy.css` or use `display.css`

### Phase 4: Unified Groups/Taxonomy System (Optional)
**Effort:** High | **Value:** Medium (architectural improvement)
- Extend groups config to support taxonomy groups
- Update groups.php to handle both types
- Build UI to distinguish manual vs auto groups
- **Timeline:** 4-5 hours
- **Files:** Multiple across groups system

---

## 6. Technical Considerations

### Taxonomy Data Source
- **Option 1:** NCBI Taxonomy API (requires network calls)
- **Option 2:** Cached taxonomy file (`metadata/taxonomy_cache.json`)
- **Option 3:** Parse from organism.json files
- **Recommendation:** Combination of cached + fallback to API

### Performance
- Cache taxonomy paths for frequently viewed organisms
- Consider index on `taxon_id` for quick lookups
- Lazy-load child organisms on taxonomy pages

### Error Handling
- If taxon_id invalid/missing: show limited taxonomy info
- Graceful fallback if NCBI API unavailable
- Skip taxonomy breadcrumb if data missing

---

## 7. Example User Flow

1. User visits organism page (e.g., Human)
2. Sees:
   - **Taxonomy Breadcrumb:** Animalia > Chordata > Mammalia > Primates > Hominidae > Homo > sapiens
   - **Manual Groups:** Cards showing "Primates (15 organisms)", "Mammals (245 organisms)"
3. User clicks "Primates" in breadcrumb
   - → Goes to dynamic taxonomy page showing all primates in system
4. User clicks group card
   - → Goes to manually curated Primates group page

---

## 8. Next Steps

**Recommend starting with Phase 1 (Group Cards):**
- Quick implementation
- Immediate value to users
- Foundation for later phases
- Low risk

**Then Phase 2 (Taxonomy Breadcrumb):**
- Requires taxonomy data infrastructure
- High value for organism discovery
- Sets up Phase 3

Would you like me to:
1. ✅ Start with Phase 1 (Group Cards)?
2. Create detailed specs for Phase 2 (Taxonomy)?
3. Design database schema for taxonomy caching?
