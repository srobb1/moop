# JavaScript Function Registry

Generated: 2025-11-26 02:05:18

## Summary
- **Total Functions**: 48
- **Files Scanned**: 11

## js/blast-manager.js

**7 function(s)**

**Included in:**
- `tools/blast.php`

### `downloadResultsHTML()` (Line 9)

**Not called anywhere**

### `downloadResultsText()` (Line 25)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 563: `<button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" onclick=\"downloadResultsText();\">`

### `clearResults()` (Line 44)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 556: `<button type=\"button\" class=\"btn btn-sm btn-light\" onclick=\"clearResults();\" title=\"Clear results and start new search\">`

### `clearBlastSourceFilters()` (Line 52)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 284: `<button type=\"button\" class=\"btn btn-success\" onclick=\"clearBlastSourceFilters();\">`

### `initializeBlastManager()` (Line 56)

**Called in 1 file(s) (2 times):**
- `js/blast-manager.js` (2x):
  - Line 221: `initializeBlastManager();`
  - Line 226: `initializeBlastManager();`

### `updateCurrentSelectionDisplay()` (Line 58)

**Called in 2 file(s) (7 times):**
- `js/blast-manager.js` (3x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 116: `updateCurrentSelectionDisplay();`
- `js/sequence-retrieval.js` (4x):
  - Line 71: `window.updateCurrentSelectionDisplay(\'currentSelection\', \'fasta-source-line\', true);`
  - Line 91: `updateCurrentSelectionDisplay();`
  - Line 106: `updateCurrentSelectionDisplay();`
  - Line 155: `updateCurrentSelectionDisplay();`

### `autoScrollToResults()` (Line 208)

**Called in 2 file(s) (3 times):**
- `js/blast-manager.js` (2x):
  - Line 222: `autoScrollToResults();`
  - Line 227: `autoScrollToResults();`
- `tools/blast.php` (1x):
  - Line 617: `autoScrollToResults();`

---

## js/index.js

**2 function(s)**

**Included in:**
- `index.php`

### `handleToolClick()` (Line 2)

**Not called anywhere**

### `switchView()` (Line 50)

**Called in 1 file(s) (2 times):**
- `index.php` (2x):
  - Line 49: `<button type=\"button\" class=\"btn btn-outline-primary active\" id=\"card-view-btn\" onclick=\"switchView(\'card\')\">`
  - Line 52: `<button type=\"button\" class=\"btn btn-outline-primary\" id=\"tree-view-btn\" onclick=\"switchView(\'tree\')\">`

---

## js/modules/blast-canvas-graph.js

**4 function(s)**

### `draw_blast_graph()` (Line 2)

**Not called anywhere**

### `printRectangles()` (Line 205)

**Called in 1 file(s) (1 times):**
- `js/modules/blast-canvas-graph.js` (1x):
  - Line 198: `off_set = printRectangles(kjs_canvas,sgn_graph_array,seq_length,img_width);`

### `move_bg_rect()` (Line 437)

**Not called anywhere**

### `draw_popup()` (Line 445)

**Called in 1 file(s) (1 times):**
- `js/modules/blast-canvas-graph.js` (1x):
  - Line 381: `draw_popup(canvas_width,blastHit,popup_layer,s_description);`

---

## js/modules/copy-to-clipboard.js

**2 function(s)**

**Included in:**
- `tools/retrieve_selected_sequences.php`
- `tools/sequences_display.php`

### `initializeCopyToClipboard()` (Line 6)

**Called in 1 file(s) (1 times):**
- `js/modules/copy-to-clipboard.js` (1x):
  - Line 81: `initializeCopyToClipboard();`

### `initializeCopyTooltips()` (Line 29)

**Called in 1 file(s) (1 times):**
- `js/modules/copy-to-clipboard.js` (1x):
  - Line 26: `initializeCopyTooltips();`

---

## js/modules/download-handler.js

**3 function(s)**

### `download()` (Line 10)

**Called in 1 file(s) (1 times):**
- `js/modules/download-handler.js` (1x):
  - Line 131: `} /* end download() */`

### `d2b()` (Line 63)

**Called in 1 file(s) (1 times):**
- `js/modules/download-handler.js` (1x):
  - Line 43: `navigator.msSaveBlob(d2b(x), fn) :`

### `saver()` (Line 77)

**Called in 1 file(s) (5 times):**
- `js/modules/download-handler.js` (5x):
  - Line 44: `saver(x) ; // everyone else can save dataURLs un-processed`
  - Line 112: `saver(self.URL.createObjectURL(blob), true);`
  - Line 117: `return saver( \"data:\" +  m   + \";base64,\"  +  self.btoa(blob)  );`
  - Line 119: `return saver( \"data:\" +  m   + \",\" + encodeURIComponent(blob)  );`
  - Line 126: `saver(this.result);`

---

## js/modules/organism-management.js

**8 function(s)**

**Included in:**
- `admin/manage_organisms.php`

### `fixDatabasePermissions()` (Line 5)

**Called in 1 file(s) (2 times):**
- `admin/manage_organisms.php` (2x):
  - Line 22: `$result = fixDatabasePermissions($db_file);`
  - Line 766: `<button class=\"btn btn-warning btn-sm\" onclick=\"fixDatabasePermissions(event, \'<?= $org_safe ?>\')\">`

### `saveMetadata()` (Line 60)

**Called in 1 file(s) (1 times):**
- `admin/manage_organisms.php` (1x):
  - Line 982: `<button type=\"button\" class=\"btn btn-success\" onclick=\"saveMetadata(event, \'<?= $org_safe ?>\')\">`

### `addMetadataImage()` (Line 176)

**Called in 1 file(s) (1 times):**
- `admin/manage_organisms.php` (1x):
  - Line 942: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataImage(\'<?= $org_safe ?>\')\">`

### `removeMetadataImage()` (Line 198)

**Called in 2 file(s) (2 times):**
- `js/modules/organism-management.js` (1x):
  - Line 183: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataImage(\'${organism}\', ${newIndex})\" style=\"float: right;\">Remove</button>`
- `admin/manage_organisms.php` (1x):
  - Line 929: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataImage(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`

### `addMetadataParagraph()` (Line 208)

**Called in 1 file(s) (1 times):**
- `admin/manage_organisms.php` (1x):
  - Line 976: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataParagraph(\'<?= $org_safe ?>\')\">`

### `removeMetadataParagraph()` (Line 239)

**Called in 2 file(s) (2 times):**
- `js/modules/organism-management.js` (1x):
  - Line 215: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataParagraph(\'${organism}\', ${newIndex})\" style=\"float: right;\">Remove</button>`
- `admin/manage_organisms.php` (1x):
  - Line 954: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataParagraph(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`

### `renameAssemblyDirectory()` (Line 249)

**Called in 1 file(s) (2 times):**
- `admin/manage_organisms.php` (2x):
  - Line 44: `$result = renameAssemblyDirectory($organism_dir, $old_name, $new_name);`
  - Line 1143: `<button class=\"btn btn-info btn-sm w-100\" onclick=\"renameAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`

### `deleteAssemblyDirectory()` (Line 324)

**Called in 1 file(s) (2 times):**
- `admin/manage_organisms.php` (2x):
  - Line 65: `$result = deleteAssemblyDirectory($organism_dir, $dir_name);`
  - Line 1176: `<button class=\"btn btn-danger btn-sm w-100\" onclick=\"deleteAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`

---

## js/modules/phylo-tree.js

**1 function(s)**

**Included in:**
- `index.php`

### `initPhyloTree()` (Line 168)

**Called in 1 file(s) (1 times):**
- `js/index.js` (1x):
  - Line 70: `initPhyloTree(treeData, userAccess);`

---

## js/modules/shared-results-table.js

**2 function(s)**

**Included in:**
- `tools/groups_display.php`
- `tools/multi_organism_search.php`
- `tools/organism_display.php`

### `createOrganismResultsTable()` (Line 18)

**Called in 1 file(s) (1 times):**
- `js/modules/annotation-search.js` (1x):
  - Line 292: `let tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, \'tools/parent_display.php\', imageUrl, this.currentKeywords);`

### `initializeResultsTable()` (Line 165)

**Called in 2 file(s) (2 times):**
- `js/modules/annotation-search.js` (1x):
  - Line 308: `initializeResultsTable(tableId, selectId, true);`
- `js/modules/shared-results-table.js` (1x):
  - Line 154: `setTimeout(() => initializeResultsTable(tableId, selectId, isUniquenameSearch), 100);`

---

## js/modules/source-list-manager.js

**8 function(s)**

**Included in:**
- `tools/blast.php`
- `tools/retrieve_sequences.php`

### `isSourceVisible()` (Line 13)

**Called in 2 file(s) (5 times):**
- `js/sequence-retrieval.js` (2x):
  - Line 98: `if (line && !isSourceVisible(line)) {`
  - Line 142: `if (line && !isSourceVisible(line)) {`
- `js/modules/source-list-manager.js` (3x):
  - Line 63: `if (line && isSourceVisible(line)) {`
  - Line 114: `if (checked && isSourceVisible(checked.closest(\'.\' + sourceListClass))) {`
  - Line 204: `const isHidden = (showHiddenWarning && line && !isSourceVisible(line)) ? \' ⚠️ (HIDDEN - FILTERED OUT)\' : \'\';`

### `applySourceFilter()` (Line 27)

**Called in 1 file(s) (2 times):**
- `js/modules/source-list-manager.js` (2x):
  - Line 243: `applySourceFilter(filterId, sourceListClass);`
  - Line 257: `applySourceFilter(filterId, sourceListClass);`

### `autoSelectFirstVisibleSource()` (Line 56)

**Called in 1 file(s) (1 times):**
- `js/modules/source-list-manager.js` (1x):
  - Line 164: `selectedRadio = autoSelectFirstVisibleSource(radioName, sourceListClass, \'.fasta-source-list\');`

### `scrollSourceIntoView()` (Line 93)

**Called in 1 file(s) (1 times):**
- `js/modules/source-list-manager.js` (1x):
  - Line 79: `scrollSourceIntoView(firstVisibleRadio, sourceListClass, scrollContainerSelector);`

### `restoreSourceSelection()` (Line 111)

**Called in 1 file(s) (1 times):**
- `js/modules/source-list-manager.js` (1x):
  - Line 160: `let selectedRadio = restoreSourceSelection(radioName, sourceListClass);`

### `clearSourceFilters()` (Line 130)

**Called in 2 file(s) (2 times):**
- `js/blast-manager.js` (1x):
  - Line 54: `window.clearSourceFilters(\'sourceFilter\', \'selected_source\', \'fasta-source-line\', null, \'blastForm\');`
- `js/sequence-retrieval.js` (1x):
  - Line 19: `clearSourceFilters(\'sourceFilter\', \'selected_source\', \'fasta-source-line\', null, \'downloadForm\');`

### `updateCurrentSelectionDisplay()` (Line 188)

**Called in 2 file(s) (7 times):**
- `js/blast-manager.js` (3x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 116: `updateCurrentSelectionDisplay();`
- `js/sequence-retrieval.js` (4x):
  - Line 71: `window.updateCurrentSelectionDisplay(\'currentSelection\', \'fasta-source-line\', true);`
  - Line 91: `updateCurrentSelectionDisplay();`
  - Line 106: `updateCurrentSelectionDisplay();`
  - Line 155: `updateCurrentSelectionDisplay();`

### `initializeSourceListManager()` (Line 226)

**Called in 2 file(s) (2 times):**
- `js/blast-manager.js` (1x):
  - Line 68: `initializeSourceListManager({`
- `js/sequence-retrieval.js` (1x):
  - Line 80: `initializeSourceListManager({`

---

## js/modules/utilities.js

**5 function(s)**

**Included in:**
- `tools/blast.php`

### `escapeHtml()` (Line 11)

**Called in 2 file(s) (8 times):**
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 35: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 138: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 140: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 142: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 148: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 297: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 366: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

### `detectSequenceType()` (Line 29)

**Called in 1 file(s) (2 times):**
- `js/blast-manager.js` (2x):
  - Line 159: `const result = detectSequenceType(this.value);`
  - Line 173: `const result = detectSequenceType(queryTextarea.value);`

### `filterBlastPrograms()` (Line 93)

**Called in 1 file(s) (2 times):**
- `js/blast-manager.js` (2x):
  - Line 166: `filterBlastPrograms(result.type, \'blast_program\');`
  - Line 176: `filterBlastPrograms(result.type, \'blast_program\');`

### `updateSequenceTypeInfo()` (Line 139)

**Called in 1 file(s) (2 times):**
- `js/blast-manager.js` (2x):
  - Line 162: `updateSequenceTypeInfo(result.message, \'sequenceTypeInfo\', \'sequenceTypeMessage\');`
  - Line 174: `updateSequenceTypeInfo(result.message, \'sequenceTypeInfo\', \'sequenceTypeMessage\');`

### `updateDatabaseList()` (Line 161)

**Called in 2 file(s) (5 times):**
- `js/blast-manager.js` (3x):
  - Line 73: `updateDatabaseList();`
  - Line 119: `updateDatabaseList();`
  - Line 167: `updateDatabaseList();`
- `tools/blast.php` (2x):
  - Line 259: `<select id=\"blast_program\" name=\"blast_program\" class=\"form-control\" onchange=\"updateDatabaseList();\">`
  - Line 344: `onchange=\"updateDatabaseList();\"`

---

## js/sequence-retrieval.js

**6 function(s)**

**Included in:**
- `tools/retrieve_sequences.php`

### `escapeHtml()` (Line 6)

**Called in 2 file(s) (8 times):**
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 35: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 138: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 140: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 142: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 148: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 297: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 366: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

### `clearSourceFilter()` (Line 17)

**Called in 1 file(s) (1 times):**
- `tools/retrieve_sequences.php` (1x):
  - Line 241: `<button type=\"button\" class=\"btn btn-success\" onclick=\"clearSourceFilter();\">`

### `initializeSequenceRetrieval()` (Line 21)

**Called in 1 file(s) (2 times):**
- `js/sequence-retrieval.js` (2x):
  - Line 297: `initializeSequenceRetrieval({ shouldScroll: shouldScroll });`
  - Line 304: `initializeSequenceRetrieval({ shouldScroll: shouldScroll });`

### `updateCurrentSelectionDisplay()` (Line 70)

**Called in 2 file(s) (7 times):**
- `js/blast-manager.js` (3x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 116: `updateCurrentSelectionDisplay();`
- `js/sequence-retrieval.js` (4x):
  - Line 71: `window.updateCurrentSelectionDisplay(\'currentSelection\', \'fasta-source-line\', true);`
  - Line 91: `updateCurrentSelectionDisplay();`
  - Line 106: `updateCurrentSelectionDisplay();`
  - Line 155: `updateCurrentSelectionDisplay();`

### `updateSearchIdsDisplay()` (Line 165)

**Called in 1 file(s) (1 times):**
- `js/sequence-retrieval.js` (1x):
  - Line 225: `updateSearchIdsDisplay();`

### `initializeCopyTooltips()` (Line 245)

**Called in 1 file(s) (1 times):**
- `js/modules/copy-to-clipboard.js` (1x):
  - Line 26: `initializeCopyTooltips();`

---

