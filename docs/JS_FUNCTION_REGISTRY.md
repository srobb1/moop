# JavaScript Function Registry

Generated: 2025-12-05 22:38:15

## Summary
- **Total Functions**: 78
- **Files Scanned**: 18

## js/blast-manager.js

**7 function(s)**

**Included in:**
- `tools/blast.php`

### `downloadResultsHTML()` (Line 9)

**Not called anywhere**

### `downloadResultsText()` (Line 25)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 604: `<button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" onclick=\"downloadResultsText();\">`

### `clearResults()` (Line 44)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 597: `<button type=\"button\" class=\"btn btn-sm btn-light\" onclick=\"clearResults();\" title=\"Clear results and start new search\">`

### `clearBlastSourceFilters()` (Line 52)

**Called in 1 file(s) (1 times):**
- `tools/blast.php` (1x):
  - Line 325: `<button type=\"button\" class=\"btn btn-success\" onclick=\"clearBlastSourceFilters();\">`

### `initializeBlastManager()` (Line 56)

**Called in 1 file(s) (2 times):**
- `js/blast-manager.js` (2x):
  - Line 244: `initializeBlastManager();`
  - Line 249: `initializeBlastManager();`

### `updateCurrentSelectionDisplay()` (Line 58)

**Called in 2 file(s) (8 times):**
- `js/blast-manager.js` (4x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 95: `updateCurrentSelectionDisplay();`
  - Line 139: `updateCurrentSelectionDisplay();`
- `js/sequence-retrieval.js` (4x):
  - Line 71: `window.updateCurrentSelectionDisplay(\'currentSelection\', \'fasta-source-line\', true);`
  - Line 91: `updateCurrentSelectionDisplay();`
  - Line 106: `updateCurrentSelectionDisplay();`
  - Line 155: `updateCurrentSelectionDisplay();`

### `autoScrollToResults()` (Line 231)

**Called in 2 file(s) (3 times):**
- `js/blast-manager.js` (2x):
  - Line 245: `autoScrollToResults();`
  - Line 250: `autoScrollToResults();`
- `tools/blast.php` (1x):
  - Line 703: `autoScrollToResults();`

---

## js/index.js

**2 function(s)**

**Included in:**
- `tools/pages/index.php`

### `handleToolClick()` (Line 2)

**Not called anywhere**

### `switchView()` (Line 50)

**Called in 1 file(s) (2 times):**
- `tools/pages/index.php` (2x):
  - Line 36: `<button type=\"button\" class=\"btn btn-outline-primary active\" id=\"card-view-btn\" onclick=\"switchView(\'card\')\">`
  - Line 39: `<button type=\"button\" class=\"btn btn-outline-primary\" id=\"tree-view-btn\" onclick=\"switchView(\'tree\')\">`

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

## js/modules/manage-annotations.js

**4 function(s)**

**Included in:**
- `admin/manage_annotations.php`

### `saveTypeOrder()` (Line 98)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-annotations.js` (1x):
  - Line 30: `saveTypeOrder();`

### `showSaveNotification()` (Line 145)

**Called in 1 file(s) (3 times):**
- `js/modules/manage-annotations.js` (3x):
  - Line 133: `showSaveNotification(\'Order saved successfully\');`
  - Line 136: `showSaveNotification(\'Error saving order. Please try again.\', \'danger\');`
  - Line 141: `showSaveNotification(\'Error saving order: \' + error.message, \'danger\');`

### `toggleTypeDetails()` (Line 159)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-annotations.js` (1x):
  - Line 202: `toggleTypeDetails(typeName);`

### `deleteType()` (Line 174)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_annotations.php` (1x):
  - Line 179: `<button type=\"button\" class=\"btn btn-sm btn-danger mt-2\" onclick=\"deleteType(\'<?= htmlspecialchars($type_name) ?>\')\">`

---

## js/modules/manage-groups.js

**7 function(s)**

**Included in:**
- `admin/manage_groups.php`

### `toggleGroup()` (Line 7)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_groups.php` (1x):
  - Line 350: `<div class=\"group-header\" onclick=\"toggleGroup(\'<?= htmlspecialchars($desc[\'group_name\']) ?>\')\" style=\"cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;\">`

### `addImage()` (Line 21)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_groups.php` (1x):
  - Line 387: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-3\" onclick=\"addImage(\'<?= htmlspecialchars($desc[\'group_name\']) ?>\')\" <?= $desc_file_write_error ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\' ?>>+ Add Image</button>`

### `removeImage()` (Line 42)

**Called in 2 file(s) (2 times):**
- `js/modules/manage-groups.js` (1x):
  - Line 28: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeImage(\'${groupName}\', ${newIndex})\" style=\"float: right;\" ${isDescFileWriteError ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\'}>Remove</button>`
- `admin/pages/manage_groups.php` (1x):
  - Line 375: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeImage(\'<?= htmlspecialchars($desc[\'group_name\']) ?>\', <?= $idx ?>)\" style=\"float: right;\" <?= $desc_file_write_error ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\' ?>>Remove</button>`

### `addParagraph()` (Line 52)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_groups.php` (1x):
  - Line 411: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-3\" onclick=\"addParagraph(\'<?= htmlspecialchars($desc[\'group_name\']) ?>\')\" <?= $desc_file_write_error ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\' ?>>+ Add Paragraph</button>`

### `removeParagraph()` (Line 79)

**Called in 2 file(s) (2 times):**
- `js/modules/manage-groups.js` (1x):
  - Line 59: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeParagraph(\'${groupName}\', ${newIndex})\" style=\"float: right;\" ${isDescFileWriteError ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\'}>Remove</button>`
- `admin/pages/manage_groups.php` (1x):
  - Line 393: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeParagraph(\'<?= htmlspecialchars($desc[\'group_name\']) ?>\', <?= $idx ?>)\" style=\"float: right;\" <?= $desc_file_write_error ? \'disabled data-bs-toggle=\"modal\" data-bs-target=\"#permissionModal\"\' : \'\' ?>>Remove</button>`

### `getColorForTag()` (Line 104)

**Called in 1 file(s) (7 times):**
- `js/modules/manage-groups.js` (7x):
  - Line 119: `getColorForTag(tag);`
  - Line 125: `chip.style.background = getColorForTag(tag);`
  - Line 126: `chip.style.borderColor = getColorForTag(tag);`
  - Line 132: `chip.style.background = getColorForTag(groupName);`
  - Line 133: `chip.style.borderColor = getColorForTag(groupName);`
  - Line 181: `chip.style.background = getColorForTag(tag);`
  - Line 182: `chip.style.borderColor = getColorForTag(tag);`

### `renderTags()` (Line 175)

**Called in 1 file(s) (4 times):**
- `js/modules/manage-groups.js` (4x):
  - Line 186: `renderTags();`
  - Line 200: `renderTags();`
  - Line 219: `renderTags();`
  - Line 235: `renderTags();`

---

## js/modules/manage-registry.js

**1 function(s)**

**Included in:**
- `admin/manage_registry.php`
- `admin/pages/manage_registry.php`

### `updateRegistry()` (Line 9)

**Called in 1 file(s) (2 times):**
- `admin/pages/manage_registry.php` (2x):
  - Line 94: `<button class=\"btn btn-warning btn-sm w-100\" onclick=\"updateRegistry(\'php\')\">`
  - Line 121: `<button class=\"btn btn-warning btn-sm w-100\" onclick=\"updateRegistry(\'js\')\">`

---

## js/modules/manage-site-config.js

**2 function(s)**

**Included in:**
- `admin/manage_site_config.php`

### `isHexColor()` (Line 147)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-site-config.js` (1x):
  - Line 158: `if (isHexColor(colorValue)) {`

### `updateBadgeColor()` (Line 152)

**Called in 1 file(s) (3 times):**
- `js/modules/manage-site-config.js` (3x):
  - Line 178: `updateBadgeColor(input);`
  - Line 182: `updateBadgeColor(this);`
  - Line 185: `updateBadgeColor(this);`

---

## js/modules/manage-taxonomy-tree.js

**2 function(s)**

**Included in:**
- `admin/manage_taxonomy_tree.php`

### `renderTreeNode()` (Line 31)

**Called in 1 file(s) (2 times):**
- `js/modules/manage-taxonomy-tree.js` (2x):
  - Line 46: `html += renderTreeNode(child, level + 1);`
  - Line 59: `previewElement.innerHTML = renderTreeNode(treeData.tree);`

### `renderTreePreview()` (Line 56)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-taxonomy-tree.js` (1x):
  - Line 24: `renderTreePreview(currentTree);`

---

## js/modules/manage-users.js

**6 function(s)**

**Included in:**
- `admin/manage_users.php`

### `resetForm()` (Line 17)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_users.php` (1x):
  - Line 202: `<button type=\"button\" class=\"btn btn-secondary\" id=\"cancel-btn\" onclick=\"resetForm()\">`

### `renderAssemblySelector()` (Line 33)

**Called in 1 file(s) (4 times):**
- `js/modules/manage-users.js` (4x):
  - Line 176: `renderAssemblySelector();`
  - Line 242: `renderAssemblySelector();`
  - Line 283: `renderAssemblySelector();`
  - Line 345: `renderAssemblySelector();`

### `updateHiddenInputs()` (Line 126)

**Called in 1 file(s) (5 times):**
- `js/modules/manage-users.js` (5x):
  - Line 107: `updateHiddenInputs();`
  - Line 175: `updateHiddenInputs();`
  - Line 243: `updateHiddenInputs();`
  - Line 282: `updateHiddenInputs();`
  - Line 315: `updateHiddenInputs();`

### `populateForm()` (Line 188)

**Called in 1 file(s) (2 times):**
- `js/modules/manage-users.js` (2x):
  - Line 285: `populateForm(username);`
  - Line 365: `populateForm(username);`

### `toggleAccessSection()` (Line 301)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-users.js` (1x):
  - Line 296: `toggleAccessSection();`

### `validateForm()` (Line 324)

**Called in 1 file(s) (1 times):**
- `js/modules/manage-users.js` (1x):
  - Line 438: `if (!validateForm()) {`

---

## js/modules/organism-management.js

**13 function(s)**

**Included in:**
- `admin/manage_organisms.php`
- `admin/manage_organisms_old.php`

### `escapeHtml()` (Line 9)

**Called in 3 file(s) (11 times):**
- `js/permission-manager.js` (3x):
  - Line 52: `\'<i class=\"fa fa-check-circle\"></i> \' + escapeHtml(json.message) + \' \' +`
  - Line 61: `\'<i class=\"fa fa-times-circle\"></i> <strong>Failed:</strong> \' + escapeHtml(json.message) + \'</div>\';`
  - Line 69: `\'<i class=\"fa fa-exclamation-triangle\"></i> <strong>Error:</strong> \' + escapeHtml(error.message) + \'</div>\';`
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 50: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 165: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 167: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 169: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 175: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 324: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 393: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

### `fixDatabasePermissions()` (Line 20)

**Called in 3 file(s) (4 times):**
- `admin/manage_organisms.php` (1x):
  - Line 31: `$result = fixDatabasePermissions($db_file);`
- `admin/manage_organisms_old.php` (2x):
  - Line 31: `$result = fixDatabasePermissions($db_file);`
  - Line 837: `<button class=\"btn btn-warning btn-sm\" onclick=\"fixDatabasePermissions(event, \'<?= $org_safe ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 618: `<button class=\"btn btn-warning btn-sm\" onclick=\"fixDatabasePermissions(event, \'<?= $org_safe ?>\')\">`

### `saveMetadata()` (Line 75)

**Called in 2 file(s) (2 times):**
- `admin/manage_organisms_old.php` (1x):
  - Line 1110: `<button type=\"button\" class=\"btn btn-success\" onclick=\"saveMetadata(event, \'<?= $org_safe ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 891: `<button type=\"button\" class=\"btn btn-success\" onclick=\"saveMetadata(event, \'<?= $org_safe ?>\')\">`

### `addMetadataImage()` (Line 203)

**Called in 2 file(s) (2 times):**
- `admin/manage_organisms_old.php` (1x):
  - Line 1070: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataImage(\'<?= $org_safe ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 851: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataImage(\'<?= $org_safe ?>\')\">`

### `removeMetadataImage()` (Line 225)

**Called in 3 file(s) (3 times):**
- `js/modules/organism-management.js` (1x):
  - Line 210: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataImage(\'${organism}\', ${newIndex})\" style=\"float: right;\">Remove</button>`
- `admin/manage_organisms_old.php` (1x):
  - Line 1057: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataImage(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`
- `admin/pages/manage_organisms.php` (1x):
  - Line 838: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataImage(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`

### `addMetadataParagraph()` (Line 235)

**Called in 2 file(s) (2 times):**
- `admin/manage_organisms_old.php` (1x):
  - Line 1104: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataParagraph(\'<?= $org_safe ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 885: `<button type=\"button\" class=\"btn btn-sm btn-primary mb-4\" onclick=\"addMetadataParagraph(\'<?= $org_safe ?>\')\">`

### `removeMetadataParagraph()` (Line 266)

**Called in 3 file(s) (3 times):**
- `js/modules/organism-management.js` (1x):
  - Line 242: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataParagraph(\'${organism}\', ${newIndex})\" style=\"float: right;\">Remove</button>`
- `admin/manage_organisms_old.php` (1x):
  - Line 1082: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataParagraph(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`
- `admin/pages/manage_organisms.php` (1x):
  - Line 863: `<button type=\"button\" class=\"btn btn-sm btn-danger remove-btn\" onclick=\"removeMetadataParagraph(\'<?= $org_safe ?>\', <?= $idx ?>)\" style=\"float: right;\">Remove</button>`

### `renameAssemblyDirectory()` (Line 276)

**Called in 3 file(s) (4 times):**
- `admin/manage_organisms.php` (1x):
  - Line 51: `$result = renameAssemblyDirectory($organism_dir, $old_name, $new_name);`
- `admin/manage_organisms_old.php` (2x):
  - Line 51: `$result = renameAssemblyDirectory($organism_dir, $old_name, $new_name);`
  - Line 1278: `<button class=\"btn btn-info btn-sm w-100\" onclick=\"renameAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 1059: `<button class=\"btn btn-info btn-sm w-100\" onclick=\"renameAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`

### `deleteAssemblyDirectory()` (Line 351)

**Called in 3 file(s) (4 times):**
- `admin/manage_organisms.php` (1x):
  - Line 70: `$result = deleteAssemblyDirectory($organism_dir, $dir_name);`
- `admin/manage_organisms_old.php` (2x):
  - Line 70: `$result = deleteAssemblyDirectory($organism_dir, $dir_name);`
  - Line 1311: `<button class=\"btn btn-danger btn-sm w-100\" onclick=\"deleteAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 1092: `<button class=\"btn btn-danger btn-sm w-100\" onclick=\"deleteAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`

### `deleteCurrentAssemblyDirectory()` (Line 419)

**Called in 1 file(s) (1 times):**
- `admin/pages/manage_organisms.php` (1x):
  - Line 1201: `<button type=\"button\" class=\"btn btn-danger\" onclick=\"deleteCurrentAssemblyDirectory(event, \'<?= htmlspecialchars($organism) ?>\', \'<?= htmlspecialchars($safe_asm_id) ?>\')\">`

### `addFeatureTag()` (Line 475)

**Called in 2 file(s) (4 times):**
- `admin/manage_organisms_old.php` (2x):
  - Line 1017: `<button type=\"button\" class=\"btn btn-sm btn-outline-primary mt-2\" onclick=\"addFeatureTag(\'<?= $org_safe ?>\', \'parent\')\">`
  - Line 1034: `<button type=\"button\" class=\"btn btn-sm btn-outline-info mt-2\" onclick=\"addFeatureTag(\'<?= $org_safe ?>\', \'child\')\">`
- `admin/pages/manage_organisms.php` (2x):
  - Line 798: `<button type=\"button\" class=\"btn btn-sm btn-outline-primary mt-2\" onclick=\"addFeatureTag(\'<?= $org_safe ?>\', \'parent\')\">`
  - Line 815: `<button type=\"button\" class=\"btn btn-sm btn-outline-info mt-2\" onclick=\"addFeatureTag(\'<?= $org_safe ?>\', \'child\')\">`

### `removeFeatureTag()` (Line 504)

**Called in 3 file(s) (5 times):**
- `js/modules/organism-management.js` (1x):
  - Line 499: `badge.innerHTML = `${feature} <i class=\"fa fa-times\" style=\"cursor: pointer;\" onclick=\"removeFeatureTag(this, \'${organism}\')\"></i>`;`
- `admin/manage_organisms_old.php` (2x):
  - Line 1012: `<?= htmlspecialchars($feature) ?> <i class=\"fa fa-times\" style=\"cursor: pointer;\" onclick=\"removeFeatureTag(this, \'<?= $org_safe ?>\')\"></i>`
  - Line 1029: `<?= htmlspecialchars($feature) ?> <i class=\"fa fa-times\" style=\"cursor: pointer;\" onclick=\"removeFeatureTag(this, \'<?= $org_safe ?>\')\"></i>`
- `admin/pages/manage_organisms.php` (2x):
  - Line 793: `<?= htmlspecialchars($feature) ?> <i class=\"fa fa-times\" style=\"cursor: pointer;\" onclick=\"removeFeatureTag(this, \'<?= $org_safe ?>\')\"></i>`
  - Line 810: `<?= htmlspecialchars($feature) ?> <i class=\"fa fa-times\" style=\"cursor: pointer;\" onclick=\"removeFeatureTag(this, \'<?= $org_safe ?>\')\"></i>`

### `togglePath()` (Line 577)

**Called in 2 file(s) (2 times):**
- `admin/manage_organisms_old.php` (1x):
  - Line 445: `<br><button class=\"btn btn-sm btn-outline-secondary\" type=\"button\" onclick=\"togglePath(this, \'<?= htmlspecialchars($organism_data) ?>\', \'<?= htmlspecialchars($organism) ?>\')\">`
- `admin/pages/manage_organisms.php` (1x):
  - Line 226: `<br><button class=\"btn btn-sm btn-outline-secondary\" type=\"button\" onclick=\"togglePath(this, \'<?= htmlspecialchars($data[\'path\']) ?>\', \'<?= htmlspecialchars($organism) ?>\')\">`

---

## js/modules/shared-results-table.js

**2 function(s)**

**Included in:**
- `tools/old/assembly_display.php`
- `tools/old/groups_display.php`
- `tools/old/multi_organism_search.php`

### `createOrganismResultsTable()` (Line 18)

**Called in 1 file(s) (1 times):**
- `js/modules/annotation-search.js` (1x):
  - Line 292: `let tableHtml = createOrganismResultsTable(organism, results, this.config.sitePath, \'tools/parent.php\', imageUrl, this.currentKeywords);`

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

**Called in 3 file(s) (6 times):**
- `js/blast-manager.js` (1x):
  - Line 86: `if (line && !window.isSourceVisible(line)) {`
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

**Called in 2 file(s) (8 times):**
- `js/blast-manager.js` (4x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 95: `updateCurrentSelectionDisplay();`
  - Line 139: `updateCurrentSelectionDisplay();`
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

## js/modules/taxonomy-tree.js

**1 function(s)**

**Included in:**
- `admin/manage_taxonomy_tree.php`
- `tools/pages/index.php`

### `initPhyloTree()` (Line 168)

**Called in 1 file(s) (1 times):**
- `js/index.js` (1x):
  - Line 70: `initPhyloTree(treeData, userAccess);`

---

## js/modules/utilities.js

**5 function(s)**

**Included in:**
- `admin/error_log.php`
- `admin/manage_annotations.php`
- `admin/manage_filesystem_permissions.php`
- `admin/manage_groups.php`
- `admin/manage_organisms.php`
- `admin/manage_registry.php`
- `admin/manage_site_config.php`
- `admin/manage_taxonomy_tree.php`
- `admin/manage_users.php`
- `tools/blast.php`

### `escapeHtml()` (Line 11)

**Called in 3 file(s) (11 times):**
- `js/permission-manager.js` (3x):
  - Line 52: `\'<i class=\"fa fa-check-circle\"></i> \' + escapeHtml(json.message) + \' \' +`
  - Line 61: `\'<i class=\"fa fa-times-circle\"></i> <strong>Failed:</strong> \' + escapeHtml(json.message) + \'</div>\';`
  - Line 69: `\'<i class=\"fa fa-exclamation-triangle\"></i> <strong>Error:</strong> \' + escapeHtml(error.message) + \'</div>\';`
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 50: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 165: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 167: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 169: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 175: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 324: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 393: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

### `detectSequenceType()` (Line 29)

**Called in 2 file(s) (3 times):**
- `js/blast-manager.js` (2x):
  - Line 182: `const result = detectSequenceType(this.value);`
  - Line 196: `const result = detectSequenceType(queryTextarea.value);`
- `tools/blast.php` (1x):
  - Line 684: `const result = detectSequenceType(queryField.value);`

### `filterBlastPrograms()` (Line 93)

**Called in 2 file(s) (3 times):**
- `js/blast-manager.js` (2x):
  - Line 189: `filterBlastPrograms(result.type, \'blast_program\');`
  - Line 199: `filterBlastPrograms(result.type, \'blast_program\');`
- `tools/blast.php` (1x):
  - Line 691: `filterBlastPrograms(result.type, \'blast_program\');`

### `updateSequenceTypeInfo()` (Line 139)

**Called in 2 file(s) (3 times):**
- `js/blast-manager.js` (2x):
  - Line 185: `updateSequenceTypeInfo(result.message, \'sequenceTypeInfo\', \'sequenceTypeMessage\');`
  - Line 197: `updateSequenceTypeInfo(result.message, \'sequenceTypeInfo\', \'sequenceTypeMessage\');`
- `tools/blast.php` (1x):
  - Line 687: `updateSequenceTypeInfo(result.message, \'sequenceTypeInfo\', \'sequenceTypeMessage\');`

### `updateDatabaseList()` (Line 161)

**Called in 2 file(s) (7 times):**
- `js/blast-manager.js` (4x):
  - Line 73: `updateDatabaseList();`
  - Line 96: `updateDatabaseList();`
  - Line 142: `updateDatabaseList();`
  - Line 190: `updateDatabaseList();`
- `tools/blast.php` (3x):
  - Line 300: `<select id=\"blast_program\" name=\"blast_program\" class=\"form-control\" onchange=\"updateDatabaseList();\">`
  - Line 385: `onchange=\"updateDatabaseList();\"`
  - Line 695: `updateDatabaseList();`

---

## js/permission-manager.js

**3 function(s)**

**Included in:**
- `admin/manage_organisms_old.php`
- `admin/manage_registry.php`
- `admin/pages/manage_registry.php`

### `fixFilePermissions()` (Line 16)

**Not called anywhere**

### `md5()` (Line 79)

**Not called anywhere**

### `escapeHtml()` (Line 96)

**Called in 3 file(s) (11 times):**
- `js/permission-manager.js` (3x):
  - Line 52: `\'<i class=\"fa fa-check-circle\"></i> \' + escapeHtml(json.message) + \' \' +`
  - Line 61: `\'<i class=\"fa fa-times-circle\"></i> <strong>Failed:</strong> \' + escapeHtml(json.message) + \'</div>\';`
  - Line 69: `\'<i class=\"fa fa-exclamation-triangle\"></i> <strong>Error:</strong> \' + escapeHtml(error.message) + \'</div>\';`
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 50: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 165: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 167: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 169: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 175: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 324: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 393: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

---

## js/sequence-retrieval.js

**6 function(s)**

**Included in:**
- `tools/retrieve_sequences.php`

### `escapeHtml()` (Line 6)

**Called in 3 file(s) (11 times):**
- `js/permission-manager.js` (3x):
  - Line 52: `\'<i class=\"fa fa-check-circle\"></i> \' + escapeHtml(json.message) + \' \' +`
  - Line 61: `\'<i class=\"fa fa-times-circle\"></i> <strong>Failed:</strong> \' + escapeHtml(json.message) + \'</div>\';`
  - Line 69: `\'<i class=\"fa fa-exclamation-triangle\"></i> <strong>Error:</strong> \' + escapeHtml(error.message) + \'</div>\';`
- `js/sequence-retrieval.js` (1x):
  - Line 213: `return `<div style=\"padding: 4px 0;\"><span style=\"background: ${bgColor}; padding: 2px 6px; border-radius: 3px;\">${escapeHtml(id)}</span></div>`;`
- `js/modules/organism-management.js` (7x):
  - Line 50: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 165: `html += \'<li>File owner: <code>\' + escapeHtml(response.error.owner) + \'</code></li>\';`
  - Line 167: `html += \'<li>Web server user: <code>\' + escapeHtml(response.error.web_user) + \'</code></li>\';`
  - Line 169: `html += \'<li>Web server group: <code>\' + escapeHtml(response.error.web_group) + \'</code></li>\';`
  - Line 175: `html += \'<code class=\"text-break\">\' + escapeHtml(response.error.command) + \'</code>\';`
  - Line 324: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`
  - Line 393: `html += \'<code class=\"text-break\">\' + escapeHtml(data.command) + \'</code><br>\';`

### `clearSourceFilter()` (Line 17)

**Called in 1 file(s) (1 times):**
- `tools/retrieve_sequences.php` (1x):
  - Line 293: `<button type=\"button\" class=\"btn btn-success\" onclick=\"clearSourceFilter();\">`

### `initializeSequenceRetrieval()` (Line 21)

**Called in 1 file(s) (2 times):**
- `js/sequence-retrieval.js` (2x):
  - Line 297: `initializeSequenceRetrieval({ shouldScroll: shouldScroll });`
  - Line 304: `initializeSequenceRetrieval({ shouldScroll: shouldScroll });`

### `updateCurrentSelectionDisplay()` (Line 70)

**Called in 2 file(s) (8 times):**
- `js/blast-manager.js` (4x):
  - Line 59: `window.updateCurrentSelectionDisplay();`
  - Line 74: `updateCurrentSelectionDisplay();`
  - Line 95: `updateCurrentSelectionDisplay();`
  - Line 139: `updateCurrentSelectionDisplay();`
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

