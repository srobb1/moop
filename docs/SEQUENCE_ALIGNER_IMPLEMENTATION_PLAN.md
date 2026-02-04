# Sequence Aligner Tool - Implementation Plan (Phase 3)

**Status**: Ready for Implementation  
**Last Updated**: February 4, 2026  
**Database**: NO SCHEMA CHANGES - Use file-based tracking only

---

## Overview

Create **Sequence Aligner** tools that integrate with MOOP's group search and multi-search result pages. The tools use UseGalaxy.org's MAFFT/ClustalW for alignment WITHOUT adding database tables.

## Design Decision: No Database Schema

Instead of a `galaxy_jobs` table, we will:
1. **Generate unique Galaxy history names** with timestamp and user ID
2. **Return Galaxy history URLs** to the frontend (Galaxy is the job tracker)
3. **Use Galaxy's built-in job history** as the source of truth
4. **No MOOP database tracking** - keeps system simple and requires no migrations

This approach:
- ✅ Eliminates database dependencies
- ✅ Leverages Galaxy's robust job history
- ✅ Reduces code complexity
- ✅ Users can see all their jobs in Galaxy (transparent, auditable)
- ✅ No need for job polling/status endpoints in MOOP

---

## Features

### 1. Three Tool Variants

The toolbox will have THREE separate alignment tools to ensure correct sequence type is used:

| Tool | Sequences | Source File | Enabled When |
|------|-----------|------------|--------------|
| **🧬 Align Protein Sequences** | Proteins | `protein.aa.fa` | 2+ protein sequences selected |
| **📊 Align CDS Sequences** | DNA coding | `cds.nt.fa` | 2+ CDS sequences selected |
| **🔗 Align mRNA Sequences** | RNA transcripts | `transcript.nt.fa` | 2+ mRNA sequences selected |

Each tool:
- Validates sequence type before submission
- Prevents mixing protein with nucleotide sequences
- Works across multiple organisms
- Shows progress indicator during alignment
- Links directly to Galaxy results

### 2. Selection Flexibility
- ✅ Same organism (e.g., multiple Bradypodion_pumilum sequences)
- ✅ Across organisms (e.g., Bradypodion_pumilum + Callicebus)
- ✅ Auto-detects sequence type and uses appropriate tool
- ✅ Prevents mixing protein with nucleotide sequences

---

## Workflow: Simple & Direct

```
User selects 2+ PROTEIN sequences from search results
    ↓
"🧬 Align Protein Sequences" button becomes active
    ↓
User clicks button
    ↓
Modal opens:
  - Tool selection (MAFFT or ClustalW)
  - Optional parameters
  - Confirmation of selected sequences
    ↓
User clicks "Align"
    ↓
Submits to /api/galaxy/align.php
    ↓
Backend:
  1. Validates user has access to organism/assembly
  2. Fetches sequences from FASTA files
  3. Builds FASTA content
  4. Uploads to Galaxy
  5. Submits alignment job
  6. Returns Galaxy history URL
    ↓
Frontend shows:
  - ✅ Galaxy history link (job is tracking there)
  - 🔗 "View in Galaxy" button (opens Galaxy in new tab)
  - Download option (from Galaxy's export)
    ↓
User can:
  - View job status in Galaxy (they're taken there)
  - Check results in Galaxy alignment viewer
  - Download aligned FASTA from Galaxy
```

---

## Implementation: Three Phases

### Phase 1: API Endpoint (Single Entry Point)

**File**: `/api/galaxy/align.php`

```php
<?php
// POST endpoint
// Integrates with existing sequence retrieval functions

// Input:
{
  "organism": "Bradypodion_pumilum",
  "assembly": "BraPum1",
  "uniquenames": "id1,id2,id3",
  "sequence_type": "protein",      // protein, cds, or mrna
  "tool": "mafft"                  // mafft or clustalw
}

// Output:
{
  "success": true,
  "galaxy_history_id": "abc123xyz",
  "galaxy_url": "https://usegalaxy.org/histories/view?id=abc123xyz",
  "status": "submitted",
  "message": "Alignment job submitted to Galaxy"
}

// Error Output:
{
  "success": false,
  "error": "Detailed error message"
}
?>
```

**Key Actions**:
1. Validate organism/assembly access (reuse `has_assembly_access()`)
2. Parse feature IDs (reuse `parseFeatureIds()`)
3. Extract sequences from FASTA (reuse `extractSequencesForAllTypes()`)
4. Build FASTA content
5. Upload to Galaxy
6. Submit alignment job
7. **Return Galaxy URL** (no database tracking)

---

### Phase 2: Frontend UI Components

#### 2.1 Search Results Page Modifications

**File**: `group_search_results.php` (or similar)

**Add to results table**:
- Checkbox column for sequence selection
- Track selected sequences by ID and type

**Add to toolbox**:
- Three buttons (initially disabled):
  - 🧬 Align Protein Sequences
  - 📊 Align CDS Sequences  
  - 🔗 Align mRNA Sequences

**Update states**:
- Buttons enable when 2+ sequences of that type are selected
- Show selection count on button: "Align Protein Sequences (3 selected)"

#### 2.2 Alignment Modal

**Triggered by**: Clicking any alignment button

**Content**:
```
┌─────────────────────────────────────────┐
│  Multiple Sequence Alignment              │
├─────────────────────────────────────────┤
│                                           │
│  Sequences: 3 protein sequences selected │
│  Organisms: Bradypodion_pumilum (2)     │
│             Callicebus (1)              │
│                                           │
│  Tool Selection:                          │
│  ○ MAFFT (default, faster)              │
│  ○ ClustalW (alternative)               │
│                                           │
│  Advanced Options (optional):             │
│  [ ] Configure parameters               │
│                                           │
│              [Cancel] [Align →]          │
└─────────────────────────────────────────┘
```

**Actions**:
- Display summary of selected sequences
- Let user choose alignment tool
- Submit to `/api/galaxy/align.php`
- On success: Show Galaxy URL with "View in Galaxy" button

#### 2.3 JavaScript Logic

**File**: `js/sequence-aligner.js`

```javascript
class SequenceAligner {
  // Track selected sequences by type
  selectedSequences = { protein: [], cds: [], mrna: [] };
  
  // Update selections when checkboxes change
  updateSelection(sequenceId, sequenceType, isSelected);
  
  // Enable/disable buttons based on selection counts
  updateButtonStates();
  
  // Open modal for selected type
  openAlignerModal(sequenceType);
  
  // Submit alignment request
  submitAlignment(sequenceType, tool);
  
  // Handle success - show Galaxy URL
  handleSuccess(galaxyUrl);
  
  // Handle errors gracefully
  handleError(error);
}
```

---

### Phase 3: Backend Implementation

#### 3.1 Core API Endpoint

**File**: `/api/galaxy/align.php`

**Reuses existing functions**:
- `has_assembly_access($organism, $assembly)` - Access control
- `parseFeatureIds($uniquenames)` - Parse selected sequence IDs
- `extractSequencesForAllTypes()` - Get sequences from FASTA files

**New code**:
- Build FASTA from extracted sequences
- Upload to Galaxy using `GalaxyClient`
- Submit alignment job
- Return Galaxy history URL

**No database table needed** - Galaxy tracks the job!

#### 3.2 Configuration

**In** `config/site_config.php` (already present):
```php
'galaxy_settings' => [
    'enabled' => true,
    'url' => 'https://usegalaxy.org',
    'api_key' => $secrets['galaxy']['api_key'],
    'tools' => [
        'mafft' => 'toolshed.g2.bx.psu.edu/repos/rnateam/mafft/rbc_mafft/7.221.3',
        'clustalw' => 'toolshed.g2.bx.psu.edu/repos/devteam/clustalw/clustalw/2.1+galaxy1',
    ]
]
```

**In** `config/secrets.php` (NOT committed):
```php
return [
    'galaxy' => [
        'api_key' => 'YOUR_API_KEY_HERE',
    ],
];
```

---

## File Structure (Minimal)

```
/data/moop/
├── api/
│   └── galaxy/
│       └── align.php              [NEW] POST alignment request
├── lib/
│   └── galaxy/
│       ├── GalaxyClient.php       [EXISTS] API wrapper
│       └── index.php              [EXISTS] Galaxy config loader
├── js/
│   └── sequence-aligner.js        [NEW] UI logic
├── config/
│   ├── site_config.php            [EXISTS] Galaxy settings
│   └── secrets.php                [EXISTS] API key (not committed)
└── galaxy_testing/
    └── (test files - for reference)
```

---

## Integration with Existing Code

### Reuse Pattern

Both the **download** and **align** workflows follow the same access control pattern:

```php
// Step 1: Validate request
has_assembly_access($organism, $assembly);

// Step 2: Parse IDs
parseFeatureIds($uniquenames);

// Step 3: Extract sequences
extractSequencesForAllTypes($assembly_dir, $ids, $types);

// Step 4: Then diverge:
// Download: sendFile() to browser
// Align: uploadToGalaxy() and return job ID
```

**Files to reference**:
- `/data/moop/tools/retrieve_selected_sequences.php` - Download pattern
- `/data/moop/lib/extract_search_helpers.php` - Sequence extraction
- `/data/moop/lib/blast_functions.php` - Access validation

---

## No Database Changes Required

**Why this is better**:

1. **Galaxy is the job tracker**: History names include timestamp + user context
2. **Simpler code**: No MOOP database operations needed
3. **No migrations**: Works in any environment without schema changes
4. **Transparent**: Users can see all jobs in Galaxy (full audit trail)
5. **Scalable**: Galaxy handles the data, not MOOP

**Galaxy history naming convention**:
```
MOOP_Align_{sequence_type}_{user_id}_{timestamp}
Example: MOOP_Align_protein_42_20260204_141523
```

---

## Implementation Checklist

### Backend
- [ ] Create `/api/galaxy/` directory
- [ ] Implement `align.php` endpoint
- [ ] Test with hardcoded sequences
- [ ] Test with real database sequences
- [ ] Verify Galaxy history creation
- [ ] Verify job submission

### Frontend  
- [ ] Add checkboxes to search results table
- [ ] Create `sequence-aligner.js` with selection tracking
- [ ] Implement button enable/disable logic
- [ ] Create alignment modal
- [ ] Add modal form submission
- [ ] Display Galaxy URL on success
- [ ] Handle errors gracefully

### Integration
- [ ] Test protein alignment across organisms
- [ ] Test CDS alignment within organism
- [ ] Test mRNA alignment with mixed selection
- [ ] Verify access control works
- [ ] Test download from Galaxy

### Testing
- [ ] Unit: Sequence extraction logic
- [ ] Unit: FASTA builder
- [ ] Integration: Full alignment workflow
- [ ] UI: Selection tracking and button states
- [ ] UI: Modal open/close/submit
- [ ] Error: Invalid selections
- [ ] Error: Access denied

---

## Success Criteria

✅ User can select 2+ sequences from search results  
✅ Alignment button becomes active  
✅ Modal shows selected sequences  
✅ Job submits to Galaxy successfully  
✅ User sees Galaxy history URL  
✅ User can click "View in Galaxy" to see results  
✅ Works across multiple organisms  
✅ Works with all three sequence types  
✅ Proper error messages shown  

---

## Next Steps

1. **Create `/api/galaxy/align.php`** endpoint
2. **Create `/js/sequence-aligner.js`** UI class
3. **Modify search results pages** to add checkboxes and buttons
4. **Test with actual sequences** from database
5. **Validate end-to-end workflow**

---

## References

- **Galaxy API**: https://docs.galaxyproject.org/en/master/api_doc.html
- **Existing Galaxy Integration**: `/data/moop/docs/GALAXY_INTEGRATION_STATUS.md`
- **Working Test**: `/data/moop/docs/GALAXY_INTEGRATION_WORKING_TEST.sh`
- **Test Reference**: `/data/moop/galaxy_testing/`
