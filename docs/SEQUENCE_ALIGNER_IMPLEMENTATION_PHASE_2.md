# Sequence Aligner Tool - Phase 2 Implementation Plan

**Date**: February 4, 2026  
**Status**: Ready to Begin  
**Owner**: MOOP Development Team

---

## Overview

We are implementing a **Sequence Aligner Tool** that integrates with our existing search results pages (Group Search and Multi Search) to enable users to align selected sequences using Galaxy's MAFFT/ClustalW tools.

### Phase 1 Recap (Completed ✅)
- [x] Galaxy account created at UseGalaxy.org
- [x] API key generated and stored securely
- [x] GalaxyClient PHP wrapper built
- [x] MAFFT alignment tested successfully with 5 sequences
- [x] Alignment visualization confirmed working
- [x] Backend infrastructure ready

### Phase 2 Goal (This Sprint)
Integrate alignment into MOOP's search results UI with three specialized tools:
1. 🧬 **Align Protein Sequences** → uses `protein.aa.fa`
2. 📊 **Align CDS Sequences** → uses `cds.nt.fa`
3. 🔗 **Align mRNA Sequences** → uses `transcript.nt.fa`

---

## Implementation Checklist

### Part A: API Endpoints (Backend)

#### 1. Create `/api/galaxy/` directory structure
```
/api/galaxy/
├── align.php           # Main alignment endpoint
├── status.php          # Job status polling
├── results.php         # Download results
└── download.php        # Download aligned FASTA
```

#### 2. Implement `align.php` endpoint
**Responsibilities**:
- Receive POST request with: `sequence_ids[]`, `sequence_type` (protein|cds|mrna), `tool` (mafft|clustalw)
- Validate user has access to all selected organisms/assemblies
- Reuse `extract_selected_sequences.php` logic to get FASTA
- Upload FASTA to Galaxy via `GalaxyClient`
- Submit alignment job to Galaxy
- Return `job_id` and `history_url` to frontend

**Pseudo-code**:
```php
POST /api/galaxy/align.php
{
    "sequence_ids": ["BraPum1_000619", "BraPum1_000620", "CCA3_000001"],
    "sequence_type": "protein",  // or "cds" or "mrna"
    "tool": "mafft"              // or "clustalw"
}

Response:
{
    "success": true,
    "job_id": "abc123def456",
    "history_url": "https://usegalaxy.org/histories/view?id=...",
    "estimated_wait": "45-120 seconds"
}
```

#### 3. Implement `status.php` endpoint
**Responsibilities**:
- Poll Galaxy for job status
- Return state: pending, running, complete, error
- Once complete, fetch results

**Pseudo-code**:
```php
GET /api/galaxy/status.php?job_id=abc123def456

Response:
{
    "status": "complete",
    "progress": 100,
    "result_dataset_id": "def789ghi012",
    "visualization_url": "https://usegalaxy.org/visualizations/display?...",
    "download_url": "/api/galaxy/download.php?dataset_id=..."
}
```

#### 4. Implement `results.php` endpoint
**Responsibilities**:
- Return aligned FASTA from Galaxy
- Format for display or download

**Pseudo-code**:
```php
GET /api/galaxy/results.php?dataset_id=def789ghi012

Response:
{
    "aligned_fasta": ">seq1\nACTGACTG...\n>seq2\n...",
    "format": "fasta",
    "num_sequences": 5
}
```

---

### Part B: UI Integration (Frontend)

#### 1. Add checkboxes to search results tables
**Where**:
- `/admin/group_search_results.php`
- `/admin/multi_species_search_results.php`

**What**:
- Add checkbox column to results table
- Track selected sequence IDs in JavaScript
- Count selected sequences by type (protein/cds/mrna)

#### 2. Create `js/sequence-aligner.js`
**Responsibilities**:
```javascript
{
    selectedSequences: {
        protein: [],
        cds: [],
        mrna: []
    },
    
    // Add/remove from selection
    toggleSequence(id, type)
    
    // Show/hide alignment buttons based on selection
    updateToolbarVisibility()
    
    // Launch alignment modal
    launchAlignmentModal(sequenceType)
    
    // Submit to Galaxy
    submitAlignment(tool)
    
    // Poll for results
    pollStatus(jobId)
    
    // Display results
    displayResults(alignedFasta, visualizationUrl)
}
```

#### 3. Create Alignment Modal
**Content**:
```html
<div id="alignmentModal">
    <h3>Sequence Alignment Tool</h3>
    
    <p>Aligning: 5 protein sequences from 3 organisms</p>
    
    <div class="tool-selection">
        <label>
            <input type="radio" name="alignment_tool" value="mafft" checked>
            MAFFT (Multiple Alignment with Fast Fourier Transform)
            <small>Fast, accurate for large alignments</small>
        </label>
        <label>
            <input type="radio" name="alignment_tool" value="clustalw">
            ClustalW (Clustal Omega)
            <small>Good for phylogenetic analysis</small>
        </label>
    </div>
    
    <div class="modal-buttons">
        <button onclick="submitAlignment()">Run Alignment</button>
        <button onclick="closeModal()">Cancel</button>
    </div>
</div>
```

#### 4. Add Alignment Buttons to Toolbox
**Visibility Logic**:
```javascript
// Show when 2+ sequences selected
if (selectedSequences.protein.length >= 2) {
    showButton('align-protein-btn');
}
if (selectedSequences.cds.length >= 2) {
    showButton('align-cds-btn');
}
if (selectedSequences.mrna.length >= 2) {
    showButton('align-mrna-btn');
}
```

#### 5. Create Results Display Modal
**Content**:
- Embedded Galaxy alignment viewer
- Download button for aligned FASTA
- "View in Galaxy" link
- Job details (time started, elapsed, etc.)

---

### Part C: Code Reuse Strategy

#### Leverage Existing Functions
**From `lib/extract_search_helpers.php`**:
```php
// Already handles:
// - Database queries for sequences
// - Organism/assembly access validation
// - FASTA formatting
// - Multiple sequence types

extractSequencesForAllTypes($sequence_ids)
```

**From `tools/retrieve_selected_sequences.php`**:
```php
// Reference implementation for:
// - Handling selected sequences
// - Database column mapping
// - Feature ID parsing
```

**From `lib/blast_functions.php`**:
```php
// Use for validation:
has_assembly_access($organism_id, $assembly_id)
parseFeatureIds($ids)
```

#### New Functions Needed
```php
// lib/galaxy/GalaxyClient.php (already exists, enhance as needed)
class GalaxyClient {
    public function uploadFasta($fasta_content)
    public function submitMafftJob($dataset_id)
    public function submitClustalwJob($dataset_id)
    public function getJobStatus($job_id)
    public function getResults($dataset_id)
    public function getVisualization($dataset_id)
}
```

---

## Data Flow Diagram

```
┌──────────────────────────────────────────────────────────────┐
│ MOOP Search Results Page                                     │
│ (Group Search OR Multi Search)                              │
└────────────────────────┬─────────────────────────────────────┘
                         │
                         ├─ User selects 2+ sequences via checkboxes
                         │  (can mix organisms, must be same type)
                         │
                         ├─ Toolbox shows 3 buttons:
                         │  • Align Protein Sequences (if 2+ protein)
                         │  • Align CDS Sequences (if 2+ cds)
                         │  • Align mRNA Sequences (if 2+ mrna)
                         │
                         ├─ User clicks "Align Protein Sequences"
                         │
                         ├─ Modal opens showing:
                         │  • MAFFT (recommended)
                         │  • ClustalW
                         │
                         ├─ User selects tool and clicks "Run"
                         │
    ┌────────────────────┴─────────────────────┐
    │                                          │
    ▼                                          ▼
    
POST /api/galaxy/align.php          Browser polls status
{                                   GET /api/galaxy/status.php?job_id=X
  sequence_ids: [list],             
  sequence_type: "protein",         ┌─────────────────┐
  tool: "mafft"                     │ Progress Modal: │
}                                   │ "Aligning..."   │
    │                               │ [████░░░░] 40%  │
    ▼                               └─────────────────┘
    
Backend Processing:
    ├─ Validate user access
    ├─ Extract sequences from DB
    ├─ Format as FASTA
    │
    ├─ Upload to Galaxy
    ├─ Submit MAFFT job
    │
    └─ Return job_id + history_url
            │
            ▼
       [Galaxy Server]
       - Running MAFFT
       - Processing alignment
            │
            ▼
       Job Complete
            │
            ├─ Return results
            ├─ Generate visualization
            │
            └─ Return dataset_id
                   │
                   ▼
            
Results Display Modal:
    ├─ Embedded alignment viewer
    ├─ Download aligned FASTA button
    ├─ "View in Galaxy" link
    └─ Close button
```

---

## Implementation Priority

### Sprint 1 (This Week)
1. [ ] Create `/api/galaxy/` directory
2. [ ] Implement `align.php` endpoint
3. [ ] Test with hardcoded sequences
4. [ ] Verify Galaxy upload and job submission

### Sprint 2 (Next Week)
5. [ ] Implement `status.php` polling
6. [ ] Create results display modal
7. [ ] Embed alignment viewer

### Sprint 3 (Week After)
8. [ ] Add UI checkboxes to search pages
9. [ ] Create `sequence-aligner.js`
10. [ ] Integrate modal triggers
11. [ ] Add toolbox buttons

### Sprint 4 (Testing & Polish)
12. [ ] End-to-end testing
13. [ ] Error handling
14. [ ] Documentation
15. [ ] Performance optimization

---

## Database Considerations

**NOTE**: As requested, we are NOT creating a database table to track Galaxy jobs.

Instead:
- Job tracking happens on Galaxy server only
- MOOP stores `job_id` and `history_url` in session
- Results are displayed in modal while available
- No permanent record unless user explicitly downloads/saves

**Optional Future Enhancement**: Add `galaxy_jobs` table if we want to:
- Track user alignment history
- Provide historical results archive
- Generate analytics
- Recover lost jobs

---

## Testing Strategy

### Unit Tests
- [ ] `GalaxyClient::uploadFasta()` with various sequence formats
- [ ] `GalaxyClient::submitMafftJob()` with valid/invalid inputs
- [ ] Sequence extraction logic with mixed organisms
- [ ] Permission validation for cross-organism access

### Integration Tests
- [ ] Full end-to-end alignment on Galaxy
- [ ] Status polling accuracy
- [ ] Results download and formatting
- [ ] Visualization URL generation

### UI Tests
- [ ] Checkbox selection tracking
- [ ] Button visibility logic
- [ ] Modal opening/closing
- [ ] Progress indicator accuracy
- [ ] Cross-browser compatibility

---

## Error Handling

### Expected Errors & Responses

**Invalid Input**:
```json
{
    "success": false,
    "error": "At least 2 sequences required for alignment"
}
```

**Permission Denied**:
```json
{
    "success": false,
    "error": "Access denied to organism: Organism_XYZ"
}
```

**Galaxy Connection Failed**:
```json
{
    "success": false,
    "error": "Failed to connect to Galaxy: [details]",
    "fallback": "Please try again in 5 minutes"
}
```

**Job Failed**:
```json
{
    "status": "error",
    "error": "MAFFT job failed: Invalid sequence format",
    "troubleshooting": "Ensure all sequences are protein format"
}
```

---

## Security Checklist

- [ ] API key never exposed in frontend
- [ ] All requests validated with `has_assembly_access()`
- [ ] FASTA input sanitized before sending to Galaxy
- [ ] Session validation on all API endpoints
- [ ] Rate limiting on alignment requests (prevent abuse)
- [ ] Error messages don't leak system info

---

## Performance Targets

| Operation | Target Time |
|-----------|------------|
| FASTA upload | < 2 seconds |
| MAFFT alignment (5 seqs) | 45-120 seconds |
| ClustalW alignment (5 seqs) | 30-90 seconds |
| Status check | < 500ms |
| Results download | < 2 seconds |
| Visualization load | < 3 seconds |

---

## Files to Create/Modify

### Create
- `/api/galaxy/align.php` (150-200 lines)
- `/api/galaxy/status.php` (80-100 lines)
- `/api/galaxy/results.php` (50-70 lines)
- `/api/galaxy/download.php` (50-70 lines)
- `/js/sequence-aligner.js` (300-400 lines)
- `/admin/sequence_aligner_modal.html` (modal template)

### Modify
- `/admin/group_search_results.php` (add checkboxes + toolbox button)
- `/admin/multi_species_search_results.php` (add checkboxes + toolbox button)
- `config/site_config.php` (already has Galaxy settings, verify)

### Reference (Don't modify)
- `lib/galaxy/client.php` (use as-is)
- `lib/extract_search_helpers.php` (reuse functions)
- `tools/retrieve_selected_sequences.php` (reference implementation)

---

## Next Steps

1. **Verify Phase 1 is complete** ✅
   - Galaxy account ready
   - API key stored
   - GalaxyClient working

2. **Begin Sprint 1**
   - Create `/api/galaxy/` directory
   - Start with `align.php` endpoint
   - Test with manual POST requests

3. **Weekly Reviews**
   - Verify each endpoint works
   - Test with real data
   - Adjust Galaxy tool IDs if needed

---

## Contact & References

- **Galaxy Documentation**: https://docs.galaxyproject.org/en/master/api_doc.html
- **UseGalaxy.org**: https://usegalaxy.org
- **MOOP Docs**: `/data/moop/docs/`
- **Status History**: See `GALAXY_INTEGRATION_STATUS.md`

---

**Last Updated**: February 4, 2026  
**Ready to Start**: ✅ YES
