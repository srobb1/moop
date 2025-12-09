# TOOLS CONVERSION - QUICK REFERENCE

## Answer to Main Question
**Can the BLAST and sequence retrieval tools use display-template.php?**

**YES, absolutely.** No special modifications needed. The template is flexible enough to handle form-based tools with file downloads.

---

## The 3 Tools

1. **retrieve_selected_sequences.php** (209 lines)
   - Simplest, best to start here
   - Risk: LOW
   - Effort: 0.5-1 hour

2. **retrieve_sequences.php** (463 lines)
   - Medium complexity
   - Risk: LOW
   - Effort: 1-2 hours

3. **blast.php** (710 lines)
   - Most complex, lots of JavaScript
   - Risk: MEDIUM
   - Effort: 2-3 hours

---

## Why They Work with the Template

✓ Template handles form submission (controller processes, view renders)
✓ Template supports file downloads (exit before template)
✓ Template handles JavaScript (inline_scripts + page_script)
✓ Template handles complex state (pass everything in $data array)
✓ No template modifications needed

---

## Conversion Pattern (Same for All Tools)

### Step 1: Create Content File
```
tools/pages/TOOL.php ← Extract all HTML from original
```

### Step 2: Refactor Controller
```php
tools/TOOL.php:
  - Process form BEFORE template
  - Check download BEFORE template (exit if true)
  - Build $data array with all variables
  - Set $display_config with title/content_file/page_script
  - Include display-template.php
```

### Step 3: Extract JavaScript (if significant)
```
js/TOOL-name.js ← Extract inline scripts
↓ Reference in $display_config['page_script']
```

---

## Key Implementation Details

### File Downloads BEFORE Template
```php
// In tool.php (controller) - BEFORE template
if ($download_flag && $valid_data) {
    sendFileDownload(...);
    exit;  // Never reaches template
}

// Now template can render normally
include display-template.php;
```

### Form State in Hidden Inputs
```php
// In pages/tool.php (content view)
<form method="POST">
    <!-- Hidden inputs preserve state -->
    <input type="hidden" name="query" value="<?= $data['query'] ?>">
    <input type="hidden" name="selected" value="<?= $data['selected'] ?>">
</form>
```

### Data Array for Template
```php
// In tool.php (controller)
$data = [
    'site' => $site,
    'siteTitle' => $siteTitle,
    'query' => $search_query,
    'selected' => $selected_source,
    'results' => $blast_result,
    'errors' => $error_message,
    // ... all variables needed by content file
];
```

---

## Expected Results

### retrieve_selected_sequences.php
- Original: 209 lines
- Converted: 100 (controller) + 109 (view) = 209 lines
- No functional changes, just reorganized

### retrieve_sequences.php
- Original: 463 lines
- Converted: ~150 (controller) + ~300 (view) = ~450 lines
- Extract JS if needed

### blast.php
- Original: 710 lines
- Converted: ~180 (controller) + ~450 (view) + ~400 (js) = ~1030 lines
- But much more organized and maintainable

---

## Testing Checklist

For each tool, test:
- [ ] Form submission works
- [ ] Form state is preserved (values persist)
- [ ] File downloads work
- [ ] JavaScript functions work
- [ ] Access control works
- [ ] Error handling works
- [ ] Mobile layout works

---

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Break form state | Test thoroughly with various inputs |
| Download fails | Keep download check BEFORE template |
| JS stops working | Extract carefully, test in browser |
| Access control breaks | Verify checks stay in controller |
| Performance issues | Unlikely, same code, just split |

---

## Implementation Order

1. **Start with retrieve_selected_sequences.php**
   - Simplest, lowest risk
   - Validates approach works

2. **Then retrieve_sequences.php**
   - Build confidence
   - More complex form handling

3. **Finally blast.php**
   - Most complex
   - Pattern established, easier to execute

4. **Test all together**
   - Full workflow testing
   - Cross-tool interactions

---

## Success Criteria

✓ All 3 tools use display-template.php
✓ All functionality preserved (forms, downloads, JS)
✓ Code better organized (controller/view split)
✓ No duplicate HTML/styles/navbar/footer
✓ 100% of user pages use new layout system

---

## Files to Read

1. **TOOLS_CONVERSION_PLAN.md** - Detailed plan
2. **tools/display-template.php** - Example of template
3. **tools/organism.php** - Example of converted page
4. **tools/pages/organism.php** - Example of content file
5. **tools/tool_init.php** - Common initialization

---

## Final Answer

**These are the LAST pages to convert.** After converting these 3 tools, the entire application will use the unified layout infrastructure. The conversion is straightforward because:

1. Template is already proven (used by organism, assembly, groups, registry pages)
2. Tools just need controller/view split
3. Form handling is the same, just reorganized
4. File download logic stays in controller
5. JavaScript can be extracted or stay inline

No surprises. Follow the pattern. Done. ✓
