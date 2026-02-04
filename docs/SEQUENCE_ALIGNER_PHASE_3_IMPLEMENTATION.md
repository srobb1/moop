# Sequence Aligner Tool - Phase 3 Implementation Plan

**Date Created:** 2026-02-04  
**Status:** Planning  
**Previous Phases:** Phase 1 (Galaxy setup), Phase 2 (MAFFT backend)

---

## Overview

Integrate Galaxy MAFFT alignment into MOOP's search results interface. Users will be able to select sequences from group or multi-search results and launch alignment analyses directly from the UI.

---

## Phase 1 Summary (Completed ✓)

- **Galaxy Account Setup:** Created account on usegalaxy.org
- **API Key Generation:** Stored securely in `config/site_config.php`
- **Test Status:** ✓ Successfully ran hardcoded MAFFT alignment via shell script
- **Result:** Alignment completed, Galaxy history created, visualization available

---

## Phase 2 Summary (Completed ✓)

- **GalaxyClient PHP Class:** Built in `lib/galaxy/GalaxyClient.php`
- **MAFFT Endpoint:** Created `api/galaxy_mafft.php` 
- **Integration Test:** Validated config-based variable usage
- **Test File:** `galaxy_testing/integration_test_with_config.php`
- **Status:** ✓ Integration working with stored config variables

---

## Phase 3: Search Results Integration (Next)

### Goals

1. Add "Sequence Aligner" tool to search results toolbar
2. Tool appears/becomes clickable when 1+ sequences selected
3. Three alignment options (protein, CDS, mRNA) based on sequence type
4. Reuse existing sequence retrieval logic from `tools/retrieve_selected_sequences.php`
5. Send sequences to Galaxy and embed visualization in modal

### Architecture

```
Search Results Page (group_search.php or multi_search.php)
    ↓
User selects sequences → Tool becomes active
    ↓
Click "Sequence Aligner" → Modal appears
    ↓
Modal: "Select alignment type: Protein | CDS | mRNA"
    ↓
User selects type → Call retrieve_selected_sequences logic
    ↓
Extract sequences of selected type → Send to Galaxy
    ↓
Galaxy processes MAFFT → Return job/dataset IDs
    ↓
Embed alignment viewer in modal (iframe or embed)
    ↓
User can download/view results in Galaxy
```

### Implementation Steps

#### Step 1: Create Tool Definition
- **File:** `tools/tool_definitions.json` (or similar registry)
- **Tool Name:** "Sequence Aligner"
- **Icon:** Align icon (or generic tool icon)
- **Visibility:** Show only when 1+ sequences selected
- **Actions:** 
  - Protein Sequences → Align Protein
  - CDS Sequences → Align CDS
  - mRNA Sequences → Align Transcript
- **Context:** Group search, Multi-search

#### Step 2: UI Components
- **Modal Dialog:** For sequence type selection
- **Button/Icon:** In search results toolbar
- **Status Indicator:** Show when Galaxy job is running
- **Results Display:** Embed Galaxy alignment viewer

#### Step 3: Reuse Sequence Retrieval
- **Source:** `tools/retrieve_selected_sequences.php` functions
- **Functions to extract:**
  - Load sequences from BLAST databases by uniquename
  - Support protein, CDS, mRNA types
  - Return FASTA format for Galaxy submission
- **Endpoint:** Create new `api/align_sequences.php` that:
  1. Accepts: selected IDs, sequence type, organism, assembly
  2. Calls retrieve logic to get FASTA data
  3. Submits to Galaxy via GalaxyClient
  4. Returns job status/visualization URL

#### Step 4: API Endpoint (`api/align_sequences.php`)

```php
// Accepts POST request:
// - uniquenames: array of sequence IDs
// - sequence_type: 'protein' | 'cds' | 'mrna'
// - organism: organism name
// - assembly: assembly name

// Returns:
// - success: boolean
// - galaxy_history_id: string
// - galaxy_dataset_id: string
// - visualization_url: string
// - message: string
```

#### Step 5: Frontend Integration
- **JavaScript handler** for tool click
- **Modal for sequence type selection**
- **AJAX call to api/align_sequences.php**
- **Embed Galaxy visualization iframe** in modal

#### Step 6: Testing
- Test with protein sequences (multiple organisms)
- Test with CDS sequences
- Test with mRNA/transcript sequences
- Verify visualization embedding works
- Check mobile/responsive behavior

---

## Database Changes

**NONE** - This phase uses only existing sequence data and Galaxy APIs.

---

## Configuration

- **Galaxy URL:** Already in `config/site_config.php`
- **Galaxy API Key:** Already in `config/site_config.php`
- **No new config needed:** Uses existing setup from Phase 1

---

## Files to Create/Modify

### Create
- `api/align_sequences.php` - Main alignment API endpoint
- `js/sequence_aligner_tool.js` - Frontend handler and modal logic
- `templates/components/sequence_aligner_modal.html` - Modal template

### Modify
- Search results page (group_search.php or multi_search.php) - Add tool button
- Tool registry/configuration - Add alignment tool definition
- Possibly extract functions from `tools/retrieve_selected_sequences.php` if not directly callable

### Reference (Don't modify)
- `lib/galaxy/GalaxyClient.php` - Already built
- `api/galaxy_mafft.php` - Already built
- `tools/retrieve_selected_sequences.php` - Study for sequence retrieval logic

---

## Key Integration Points

### 1. Sequence Retrieval
The `retrieve_selected_sequences.php` tool already handles:
- Loading sequences from BLAST databases by uniquename
- Supporting protein/CDS/mRNA extraction
- FASTA formatting

We need to extract this logic into a reusable function or create a wrapper API.

### 2. Galaxy Submission
Use existing `GalaxyClient::submitMAFFT()` method with:
- FASTA sequences from step 1
- Selected alignment type (protein/CDS/mRNA)

### 3. Result Visualization
Galaxy provides alignment viewers:
- URL format: `https://usegalaxy.org/visualizations/display?visualization=alignmentviewer&dataset_id=<ID>`
- Embed in iframe or open in new tab

---

## Testing Command Line

Before integrating into UI:
```bash
# Test sequence retrieval
curl -X POST http://localhost/moop/api/align_sequences.php \
  -d 'uniquenames[]=ID1&uniquenames[]=ID2&sequence_type=protein&organism=OrgName&assembly=AsmName'

# Verify Galaxy submission works with real sequences
```

---

## Rollout Plan

1. **Implement API endpoint** (`api/align_sequences.php`)
2. **Test sequence retrieval logic** - Verify FASTA output
3. **Test Galaxy submission** - Verify job creation
4. **Add frontend components** - Modal, button, handlers
5. **Test UI integration** - Click to alignment flow
6. **Verify visualization** - Embedding in modal
7. **User acceptance testing** - Different sequence types, organisms

---

## Success Criteria

- ✓ Tool appears in search results when 1+ sequences selected
- ✓ Modal allows sequence type selection (protein/CDS/mRNA)
- ✓ Sequences sent to Galaxy correctly
- ✓ MAFFT alignment completes successfully
- ✓ Alignment viewer embedded in modal
- ✓ Users can download results from Galaxy
- ✓ Works with sequences from same organism and across organisms

---

## Notes for Tomorrow

**When you return, provide/confirm:**

1. **Search results page details:**
   - Which files handle group search results? (`group_search.php`?)
   - Which files handle multi-search results?
   - How are tools/buttons currently added to toolbar?

2. **Tool registry:**
   - Is there a central tool registry or config file?
   - How are tools conditionally shown based on selection state?

3. **Sequence type indicators:**
   - How do we identify protein vs CDS vs mRNA sequences in results?
   - Are there data attributes or flags in the UI?

4. **Existing functions:**
   - Can we directly call functions from `retrieve_selected_sequences.php`?
   - Or should we create a new shared library for sequence retrieval?

5. **Galaxy visualization:**
   - Should we embed in iframe or open in new tab?
   - Do we need additional embedding parameters?

6. **Access control:**
   - Should all users be able to submit to Galaxy?
   - Or only certain roles (e.g., admins)?

---

## Related Documentation

- `GALAXY_INTEGRATION_WORKING_TEST.sh` - Successful test reference
- `lib/galaxy/GalaxyClient.php` - API client implementation
- `api/galaxy_mafft.php` - Existing MAFFT endpoint
- `tools/retrieve_selected_sequences.php` - Sequence retrieval logic
