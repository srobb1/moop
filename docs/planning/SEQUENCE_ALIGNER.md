# Sequence Aligner Tool - Planning

**Status:** Backend complete ✅ | UI integration pending ⏳  
**Last Updated:** February 4, 2026 (planning docs)  
**Current Status:** Galaxy MAFFT integration working, UI not yet implemented

---

## Overview

Plan to integrate Galaxy-based sequence alignment into MOOP's search results pages, enabling users to align selected sequences using MAFFT/ClustalW.

### Backend Status ✅ COMPLETE

**Implemented:**
- ✅ Galaxy account created at UseGalaxy.org
- ✅ API key generated and stored securely in config/secrets.php
- ✅ GalaxyClient PHP wrapper (`lib/galaxy/client.php`)
- ✅ MAFFT tool integration (`lib/galaxy/mafft.php`)
- ✅ API endpoints (`api/galaxy/mafft.php`, `api/galaxy/results.php`)
- ✅ Successfully tested with 5 protein sequences
- ✅ Alignment visualization confirmed working

**Documentation:**
- See `docs/Galaxy/GALAXY_INTEGRATION.md` for complete backend docs
- See `docs/Galaxy/GALAXY_INTEGRATION_STATUS.md` for test results

### Frontend Status ⏳ NOT IMPLEMENTED

**Planned UI Integration:**

Three specialized alignment tools in search results:
1. 🧬 **Align Protein Sequences** → uses `protein.aa.fa`
2. 📊 **Align CDS Sequences** → uses `cds.nt.fa`
3. 🔗 **Align mRNA Sequences** → uses `transcript.nt.fa`

**Planned Features:**
- Checkbox selection in search results tables
- "Align Selected" button appears when sequences selected
- Modal to choose alignment tool (MAFFT/ClustalW)
- Progress tracking with polling
- Results display with:
  - Alignment visualization
  - Download options (FASTA, phylip, clustal)
  - Link to Galaxy history
  - Option to save to MOOP results

**Target Pages:**
- Multi-organism search results (`tools/pages/search_results.php`)
- Group search results (if applicable)
- Gene details page (align orthologs)

---

## Implementation Phases

### Phase 1: Galaxy Backend ✅ COMPLETE
- GalaxyClient PHP wrapper
- API key management
- MAFFT tool integration
- Testing with sample sequences

### Phase 2: UI Integration (NOT STARTED)
**Checklist:**
- [ ] Add sequence selection checkboxes to search results
- [ ] Create "Align Selected" button with dropdown
- [ ] Build alignment modal UI
- [ ] Implement job submission to Galaxy API
- [ ] Add progress polling (check job status every 5s)
- [ ] Display results in modal or new page
- [ ] Add download options
- [ ] Handle errors gracefully

### Phase 3: Advanced Features (FUTURE)
- [ ] Per-user Galaxy accounts (currently uses shared account)
- [ ] Results caching in MOOP database
- [ ] Alignment history per user
- [ ] Additional tools (ClustalW, MUSCLE, etc.)
- [ ] Batch alignment (>100 sequences)
- [ ] Integration with phylogenetic tree viewer

---

## Technical Details

### Galaxy API Integration

**Submit MAFFT Job:**
```php
$galaxyMafft = new GalaxyMAFFT($galaxyClient);
$result = $galaxyMafft->runAlignment($sequences, [
    'order' => 'aligned',
    'reorder' => true
]);
```

**Check Job Status:**
```php
$status = $galaxyClient->getJobStatus($jobId);
// Returns: 'new', 'queued', 'running', 'ok', 'error'
```

**Get Results:**
```php
$alignment = $galaxyClient->downloadDataset($datasetId);
// Returns FASTA alignment
```

### Sequence Format Requirements

**Input:** FASTA format
```fasta
>Gene1_Species1
MKHILWVLGLAALATVMAGNHAKVLTIDGDGFVDLTQAAKALGEMDEADRAGIINP
>Gene2_Species2
MKHILVVLGLAFGLATVMAGNHVKVLTLDEKGFIDLTQAAQALGEVDPADRAGIINP
```

**Output:** Aligned FASTA
```fasta
>Gene1_Species1
MKHILWVLGLAALATVMAGNHAKVLTIDGDGFVDLTQAAKALGEMDEADRAGIINP--
>Gene2_Species2
MKHILVVLGLAFGLATVMAGNHVKVLTLDEKGFIDLTQAAQALGEVDPADRAGIINPTT
```

---

## Next Steps

When ready to implement UI:

1. Review backend code in `lib/galaxy/` and `api/galaxy/`
2. Study Galaxy integration docs in `docs/Galaxy/`
3. Add selection UI to search results pages
4. Create alignment modal component
5. Test with various sequence types and sizes
6. Handle edge cases (no sequences, too many sequences, errors)
7. Document user-facing workflow

---

**Note:** This is a planning document. Backend is working. UI implementation is pending based on priorities.

**References:**
- Backend docs: `docs/Galaxy/`
- Implementation: `lib/galaxy/`, `api/galaxy/`
- Config: `config/site_config.php`, `config/secrets.php`
