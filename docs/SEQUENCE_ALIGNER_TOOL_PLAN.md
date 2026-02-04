# Sequence Aligner Tool - Integration Plan

## Overview

Create **Sequence Aligner** tools that integrate with MOOP's group search and multi-search result pages. The tools use UseGalaxy.org's MAFFT/ClustalW backends for alignment.

**Status**: ✅ API Integration Tested & Working (as of 2026-02-04)  
**Next Phase**: Web UI Integration

## Features

### 1. Three Tool Variants

The toolbox will have THREE separate alignment tools to ensure correct sequence type is used:

1. **🧬 Align Protein Sequences**
   - For: Protein-coding gene products
   - Source: `protein.aa.fa` files in organism directories
   - Enabled when: 2+ protein sequences selected
   - Default tool: MAFFT (protein-optimized)

2. **📊 Align CDS Sequences**
   - For: Coding sequences (DNA)
   - Source: `cds.nt.fa` files
   - Enabled when: 2+ CDS sequences selected
   - Default tool: MAFFT (DNA mode)

3. **🔗 Align mRNA Sequences**
   - For: Transcript sequences
   - Source: `transcript.nt.fa` files
   - Enabled when: 2+ mRNA sequences selected
   - Default tool: MAFFT (DNA mode)

### 2. Selection Flexibility
- ✅ Same organism (e.g., multiple Bradypodion_pumilum sequences)
- ✅ Across organisms (e.g., Bradypodion_pumilum + Callicebus)
- ✅ Auto-detects sequence type and uses appropriate tool
- ✅ Prevents mixing protein with nucleotide sequences

### 3. Workflow

#### Simple Case: Single Tool Type

```
User selects 2+ PROTEIN sequences (from search results)
    ↓
"🧬 Align Protein Sequences" button becomes active
    ↓
User clicks button
    ↓
Modal opens with options:
  - Tool selection (MAFFT, ClustalW)
  - Parameters (optional)
  - Confirmation of selected sequences
  - "Align" button
    ↓
Submits to /api/galaxy/align endpoint with mode="protein"
    ↓
Backend:
  - Validates all sequences are protein type
  - Fetches sequence data from database
  - Builds FASTA format with proper headers
  - Submits to Galaxy using MAFFT
  - Returns job ID + history
    ↓
Frontend shows:
  - Progress indicator (status: queued/running/ok)
  - "View in Galaxy" link
  - When done: Embedded alignment viewer
    ↓
Results can be:
  - Viewed in Galaxy (opens in new tab)
  - Downloaded as FASTA
  - Viewed inline with alignment viewer
```

#### Complex Case: Mixed Organisms/Sequences

```
User selects:
  - Protein from Bradypodion_pumilum (3 sequences)
  - Protein from Callicebus (2 sequences)
  - Protein from Human reference (1 sequence)
    ↓
"🧬 Align Protein Sequences" button becomes active
    ↓
All sequences are same type (protein) → Single alignment tool
    ↓
Results show cross-species homology alignment
```

## Implementation Steps

### Phase 1: Backend API (READY)
✅ `lib/galaxy/GalaxyClient.php` - Client wrapper
✅ `config/site_config.php` - Galaxy settings & tool IDs
✅ `config/secrets.php` - API key storage
✅ Shell script backend - Already working
✅ Configuration manager integration - Working

**Status**: All backend pieces in place. Ready for API endpoints.

### Phase 2: API Endpoints
**Location**: `/data/moop/api/galaxy/`

#### Endpoint 1: POST `/api/galaxy/align`
```php
// /api/galaxy/align.php

Request:
{
  "sequence_ids": [123, 456, 789],  // from database search results
  "sequence_type": "protein",       // "protein", "cds", or "mrna"
  "tool": "mafft",                  // or "clustalw"
  "mode": "auto",                   // detect or explicit
  "name": "User-provided-name"      // optional
}

Response:
{
  "success": true,
  "job_id": "xyz123",
  "galaxy_history_id": "abc456",
  "status": "queued",
  "message": "Alignment submitted to Galaxy"
}
```

**Actions**:
1. Validate sequence IDs from database
2. Fetch sequences from database
3. Verify all sequences match requested type (protein/CDS/mRNA)
4. Build FASTA content with proper headers
5. Call `GalaxyClient->submitMafft()`
6. Store job record in database (new table: `galaxy_jobs`)
7. Return job tracking info

#### Endpoint 2: GET `/api/galaxy/status/{job_id}`
```php
// Monitor job progress

Response:
{
  "status": "running",
  "progress": 75,
  "galaxy_dataset_id": "def789",
  "history_url": "https://usegalaxy.org/histories/view?id=abc456"
}
```

#### Endpoint 3: GET `/api/galaxy/results/{job_id}`
```php
// Get completed results

Response:
{
  "status": "ok",
  "alignment_fasta": "...",
  "visualization_url": "https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id=...",
  "download_url": "/api/galaxy/download/{job_id}",
  "history_url": "https://usegalaxy.org/histories/view?id=..."
}
```

### Phase 3: Database Schema
New table: `galaxy_jobs`
```sql
CREATE TABLE galaxy_jobs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  moop_job_id VARCHAR(255) UNIQUE,
  galaxy_history_id VARCHAR(255),
  galaxy_dataset_id VARCHAR(255),
  tool_name VARCHAR(50),
  status ENUM('queued', 'running', 'ok', 'error'),
  sequence_ids JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  error_message TEXT,
  INDEX (status),
  INDEX (moop_job_id),
  INDEX (created_at)
);
```

### Phase 4: Frontend UI

#### 4.1 Search Results Pages
**Files to modify**:
- `group_search_results.php` (or similar)
- `multi_search_results.php` (or similar)

**Changes**:
1. Add checkbox column to results table
2. Add JS to track selected sequences
3. Add button to toolbox that becomes active when 2+ selected

#### 4.2 Toolbox Integration
**HTML Structure** (search results page toolbox):
```html
<div class="toolbox-group">
  <div class="tool-button" id="protein-aligner-btn" disabled>
    <span class="tool-icon">🧬</span>
    <span class="tool-label">Align Protein Sequences</span>
    <span class="selection-count">(0 selected)</span>
  </div>
  
  <div class="tool-button" id="cds-aligner-btn" disabled>
    <span class="tool-icon">📊</span>
    <span class="tool-label">Align CDS Sequences</span>
    <span class="selection-count">(0 selected)</span>
  </div>
  
  <div class="tool-button" id="mrna-aligner-btn" disabled>
    <span class="tool-icon">🔗</span>
    <span class="tool-label">Align mRNA Sequences</span>
    <span class="selection-count">(0 selected)</span>
  </div>
</div>
```

**Button Activation Logic**:
- Track which sequence types are selected
- Enable "Align Protein Sequences" only if 2+ protein sequences selected
- Enable "Align CDS Sequences" only if 2+ CDS sequences selected
- Enable "Align mRNA Sequences" only if 2+ mRNA sequences selected
- Update selection counts in real-time

#### 4.3 Modal for Tool Options
**Content**:
- Tool selection radio buttons (MAFFT / ClustalW)
- Advanced options (collapsible):
  - Gap opening penalty
  - Gap extension penalty
  - Matrix selection
- "Align" button (submits to `/api/galaxy/align`)

#### 4.4 Results Display
**Progress Modal**:
```
[Sequence Aligner Progress]

Status: Running (3/5 complete)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━ 60%

⏱ Estimated time: 45 seconds

[View in Galaxy] [Cancel]
```

**Results Modal**:
```
[Alignment Results]

✅ Alignment Complete!

Sequences aligned: 6
Identity score: 78%

[Embedded Alignment Viewer]
(Galaxy iframe or custom viewer)

[Download FASTA] [View in Galaxy] [Close]
```

#### 4.5 JavaScript Logic
**File**: `js/sequence-aligner.js`

```javascript
class SequenceAligner {
  constructor() {
    this.selectedSequences = {
      protein: [],
      cds: [],
      mrna: []
    };
    this.toolButtons = {
      protein: document.getElementById('protein-aligner-btn'),
      cds: document.getElementById('cds-aligner-btn'),
      mrna: document.getElementById('mrna-aligner-btn')
    };
  }
  
  // Track selected checkboxes by type
  updateSelectionByType(sequenceId, sequenceType, isSelected)
  
  // Update button states based on selected sequences
  updateButtonStates()
  
  // Open modal for tool options
  openAlignerModal(sequenceType)
  
  // Submit alignment request
  submitAlignment(sequenceType, selectedIds)
  
  // Poll job status
  pollJobStatus(jobId)
  
  // Display results with alignment viewer
  displayResults(jobData)
  
  // Handle errors gracefully
  handleError(error)
}
```

**Features**:
- Tracks selections separately by type
- Buttons enable/disable independently
- One modal per sequence type
- Type validation before submission

## Database Queries Needed

### Get Sequences by ID
```php
// lib/Galaxy/SequenceRepository.php
public function getSequencesById($ids) {
  // Query database for sequences
  // Return array: [['id' => ..., 'name' => ..., 'sequence' => ...], ...]
}

// Check if sequence is protein or nucleotide
public function getSequenceType($sequence) {
  // Simple heuristic: count ACGT vs MVHD amino acids
  // Return 'protein' or 'nucleotide'
}
```

## Configuration Requirements

### site_config.php additions (already present)
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

### secrets.php (NOT COMMITTED)
```php
return [
    'galaxy' => [
        'api_key' => 'YOUR_API_KEY_HERE',
    ],
];
```

## Testing Plan

### Unit Tests
- GalaxyClient FASTA format builder
- Sequence type detection (protein vs nucleotide)
- API endpoint request validation

### Integration Tests
- Submit actual alignment job to Galaxy
- Monitor job status through completion
- Download and validate results

### UI Tests
- Checkbox selection works
- Button enable/disable toggles correctly
- Modal opens with correct selected sequences
- Results display properly

## File Structure

```
/data/moop/
├── api/
│   └── galaxy/
│       ├── align.php           [NEW] POST endpoint
│       ├── status.php          [NEW] GET job status
│       ├── results.php         [NEW] GET results
│       └── download.php        [NEW] Download FASTA
├── lib/
│   └── galaxy/
│       ├── GalaxyClient.php    [EXIST] Client wrapper
│       └── SequenceRepository.php [NEW] DB queries
├── js/
│   └── sequence-aligner.js     [NEW] UI logic
├── galaxy_testing/
│   └── *.php (test files - for reference only)
└── config/
    ├── site_config.php         [EXIST] Galaxy settings
    └── secrets.php             [EXIST] API key
```

## Success Criteria

✅ **Phase 1**: Backend API endpoints working  
✅ **Phase 2**: UI elements render correctly  
✅ **Phase 3**: End-to-end alignment submission works  
✅ **Phase 4**: Results display properly in modal  
✅ **Phase 5**: Error handling graceful

## Next Immediate Steps

1. ✅ Verify secrets.php has correct API key stored
2. Create `/api/galaxy/` directory structure
3. Implement `align.php` endpoint
4. Create sequence repository class
5. Add UI components to search result pages
6. Test end-to-end workflow

## Notes

- **Shared Galaxy Account**: All users will use the same Galaxy account for simplicity
- **Job Tracking**: Store Galaxy history/dataset IDs in MOOP database for record keeping
- **Result Retention**: Galaxy keeps results for 30 days - link to them, don't replicate
- **Multiple Tools**: Architecture supports easy addition of ClustalW, RAxML, etc. in future

## References

- Galaxy API: https://docs.galaxyproject.org/en/master/api_doc.html
- Tool IDs: Available via `https://usegalaxy.org/api/tools` (requires authentication)
- Previous tests: `/data/moop/docs/GALAXY_INTEGRATION.md`
