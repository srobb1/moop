# Galaxy Alignment Tool - Quick Start Guide
**Ready to implement - February 20, 2026**

## TL;DR - What You Need To Do

You have a **working Galaxy API** and **checkboxes already in search results**.  
You just need to connect them with 2 simple steps:

1. Add tool to `lib/tool_config.php` (copy existing tool pattern)
2. Create `js/sequence-aligner.js` (copy from download tool pattern)

**Estimated time: 2-4 hours**

---

## Step 1: Add Tool Configuration (15 minutes)

**File**: `/data/moop/lib/tool_config.php`

Add this after the existing tools (around line 40):

```php
'align_proteins' => [
    'id' => 'align_proteins',
    'name' => 'Align Proteins',
    'icon' => 'fa-align-center',
    'description' => 'Align selected protein sequences using Galaxy MAFFT',
    'btn_class' => 'btn-primary',
    'url_path' => '#',  // Will be handled by onclick
    'context_params' => [],  // Not needed - JS handles it
    'pages' => ['organism', 'multi_organism_search', 'groups', 'assembly'],
    'requires_selection' => true  // NEW - indicates needs checkboxes
],
```

---

## Step 2: Create JavaScript Module (2-3 hours)

**File**: `/data/moop/js/sequence-aligner.js` (NEW FILE)

### What It Needs To Do:

1. **Monitor checkbox selections**
   - Enable/disable "Align Proteins" button based on selections
   - Check for minimum 2 sequences selected
   
2. **Get selected sequences**
   - Copy pattern from `js/modules/datatable-config.js` lines 93-155
   - Extract feature IDs from checked rows
   
3. **Extract sequences from database**
   - Submit to `/tools/retrieve_selected_sequences.php` to get sequences
   - Parse response to get sequence data
   
4. **Submit to Galaxy**
   - Format as JSON: `{"sequences": [{"id": "...", "seq": "...", "header": "..."}]}`
   - POST to `/api/galaxy_mafft_align.php`
   
5. **Show results**
   - Display Galaxy history URL
   - Show visualization link
   - Option to open in new tab

### Pseudo-code Structure:

```javascript
// sequence-aligner.js

class SequenceAligner {
    constructor() {
        this.selectedRows = [];
        this.minSequences = 2;
    }
    
    init() {
        // Add click handler to "Align Proteins" button
        $(document).on('click', '[data-tool-id="align_proteins"]', () => {
            this.handleAlignClick();
        });
        
        // Monitor checkbox changes to enable/disable button
        $(document).on('change', '.row-select', () => {
            this.updateButtonState();
        });
    }
    
    updateButtonState() {
        const checkedCount = $('.row-select:checked').length;
        const $btn = $('[data-tool-id="align_proteins"]');
        
        if (checkedCount >= this.minSequences) {
            $btn.prop('disabled', false);
        } else {
            $btn.prop('disabled', true);
        }
    }
    
    async handleAlignClick() {
        // Step 1: Get selected feature IDs (copy from datatable-config.js)
        const featureIds = this.getSelectedFeatureIds();
        
        if (featureIds.length < this.minSequences) {
            alert(`Please select at least ${this.minSequences} sequences`);
            return;
        }
        
        // Step 2: Extract sequences from database
        const sequences = await this.extractSequences(featureIds);
        
        // Step 3: Submit to Galaxy
        const result = await this.submitToGalaxy(sequences);
        
        // Step 4: Show results
        this.showResults(result);
    }
    
    getSelectedFeatureIds() {
        // Copy from datatable-config.js exportSelectedSequences()
        const checkedRows = $('input.row-select:checked');
        const featureIds = [];
        
        checkedRows.each(function() {
            const $row = $(this).closest('tr');
            // Extract feature ID from row (it's in the 4th column link)
            const featureId = $row.find('td:eq(3) a').text().trim();
            if (featureId) featureIds.push(featureId);
        });
        
        return featureIds;
    }
    
    async extractSequences(featureIds) {
        // Get organism and assembly from first selected row
        const firstRow = $('input.row-select:checked').first().closest('tr');
        const organism = firstRow.find('td:eq(1)').text().trim().split(' ')[0];
        const assembly = firstRow.attr('data-genome-accession') || '';
        
        // Submit to retrieve_selected_sequences to get actual sequences
        const formData = new FormData();
        formData.append('uniquenames', featureIds.join(','));
        formData.append('organism', organism);
        formData.append('assembly', assembly);
        
        const response = await fetch('/tools/retrieve_selected_sequences.php', {
            method: 'POST',
            body: formData
        });
        
        // Parse response to extract sequences
        // Note: May need to adjust based on actual response format
        const text = await response.text();
        return this.parseFastaFromResponse(text);
    }
    
    parseFastaFromResponse(html) {
        // Parse FASTA sequences from HTML response
        // Look for <pre> tags or sequence display areas
        // Return array of {id, seq, header}
        // TODO: Implement based on actual HTML structure
    }
    
    async submitToGalaxy(sequences) {
        const response = await fetch('/api/galaxy_mafft_align.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sequences: sequences})
        });
        
        return await response.json();
    }
    
    showResults(result) {
        if (result.success) {
            const message = `
                Alignment submitted successfully!
                
                View in Galaxy: ${result.history_url}
                Visualization: ${result.visualization_url}
            `;
            alert(message);
            
            // Open visualization in new tab
            window.open(result.visualization_url, '_blank');
        } else {
            alert('Error: ' + result.error);
        }
    }
}

// Initialize on page load
$(document).ready(function() {
    const aligner = new SequenceAligner();
    aligner.init();
});
```

---

## Step 3: Test (30 minutes)

1. Go to organism or multi-organism search page
2. Search for "NTNG1" or any protein
3. Select 2-3 protein sequences (checkboxes)
4. Click "Align Proteins" button (should appear in tool section)
5. Wait for Galaxy submission
6. Check Galaxy web interface for results

---

## Files You'll Reference

1. **Checkbox selection pattern**:
   - `js/modules/datatable-config.js` lines 93-155 (exportSelectedSequences function)

2. **Tool configuration pattern**:
   - `lib/tool_config.php` lines 8-42 (existing tools)

3. **Tool rendering**:
   - `lib/tool_section.php` (automatically renders tools - no changes needed)

4. **API endpoint**:
   - `/api/galaxy_mafft_align.php` (working and tested!)

5. **Sequence extraction**:
   - `/tools/retrieve_selected_sequences.php` (working controller)

---

## Troubleshooting

### Button doesn't appear
- Check `lib/tool_config.php` has correct page names
- Verify tool_section.php is included on the page
- Check browser console for JS errors

### Can't get sequences
- Check retrieve_selected_sequences.php returns data
- Verify organism and assembly are correct
- Check access control permissions

### Galaxy API fails
- Verify config/secrets.php has API key
- Check Galaxy is up: https://usegalaxy.org/
- Test API directly in browser dev tools

### Sequences format wrong
- API needs: `{"sequences": [{"id": "abc", "seq": "MKHIL...", "header": "..."}]}`
- Check JSON format matches exactly
- Verify seq field has amino acid sequence (not empty)

---

## Alternative: Quick Hack for Testing

If you want to test Galaxy API first without full UI:

1. Open browser console on search results page
2. Run this quick test:

```javascript
// Test alignment with hardcoded sequences
fetch('/api/galaxy_mafft_align.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        sequences: [
            {id: "test1", seq: "MKHILWVLGLAALATVMAGNHAKVLTIDGDGFVDLTQAAKALGEMDEADRAGIINP", header: "Test sequence 1"},
            {id: "test2", seq: "MKHILVVLGLAFGLATVMAGNHVKVLTLDEKGFIDLTQAAQALGEVDPADRAGIINP", header: "Test sequence 2"}
        ]
    })
})
.then(r => r.json())
.then(d => console.log(d));
```

3. Check console for response with history_url and visualization_url
4. Open URLs in new tab to see results

---

## Success Criteria

When done, you should have:
- ✅ "Align Proteins" button appears in tool section on search pages
- ✅ Button enabled/disabled based on checkbox selections
- ✅ Clicking button submits to Galaxy API
- ✅ Galaxy returns history_url and visualization_url
- ✅ Can view alignment results in Galaxy

---

## Next Steps After MVP Works

1. Add proper modal UI (Bootstrap) for progress/results
2. Add CDS and mRNA alignment tools (same pattern)
3. Add sequence type validation
4. Implement polling for job status
5. Add database tracking (optional)

---

**Questions?** Check `/data/moop/docs/Galaxy/GALAXY_INTEGRATION_STATUS.md` for full details.
