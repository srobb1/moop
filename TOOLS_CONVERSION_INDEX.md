# TOOLS CONVERSION - DOCUMENTATION INDEX

## Quick Answer
**Can the BLAST and sequence retrieval tools use display-template.php?**

**YES.** These are the last 3 pages needing conversion. The template is already proven and flexible enough to handle form-based tools with file downloads. No template modifications needed.

---

## Documents in This Analysis

### 1. **TOOLS_CONVERSION_QUICK_REFERENCE.md** (Start Here!)
   - **Read Time:** 5 minutes
   - **What it covers:**
     - Direct answer to the main question
     - The 3 tools and their complexity
     - Why the template works
     - Key implementation patterns
     - Testing checklist
   - **Best for:** Getting the overview and understanding feasibility

### 2. **TOOLS_CONVERSION_PLAN.md** (Detailed Plan)
   - **Read Time:** 15-20 minutes
   - **What it covers:**
     - Current state analysis
     - Conversion strategy
     - Detailed challenges and solutions
     - Step-by-step implementation
     - Risks and mitigations
     - Timeline and priorities
   - **Best for:** Understanding the full scope and planning

### 3. **TOOLS_CONVERSION_INDEX.md** (This File)
   - Navigation and reference

---

## The 3 Tools Being Analyzed

| Tool | Lines | Complexity | Effort | Start? |
|------|-------|-----------|--------|--------|
| retrieve_selected_sequences.php | 209 | ⭐ Easiest | 0.5-1h | ✓ YES |
| retrieve_sequences.php | 463 | ⭐⭐ Medium | 1-2h | ⭐ Next |
| blast.php | 710 | ⭐⭐⭐ Hardest | 2-3h | ⭐⭐ Last |

**Total Effort:** 5-8 hours (including testing)

---

## Key Findings Summary

### Can They Use the Template?
✓ YES - No modifications needed  
✓ Template already handles form-based pages  
✓ Template supports file downloads (exit before render)  
✓ Template supports complex JavaScript  
✓ Pattern proven on organism/assembly/groups/registry pages

### What Makes Them Different?
These are **form-based tools** not display-only pages:
- Handle POST submissions (versus GET display)
- Support file downloads (versus HTML display)
- Have complex JavaScript (versus UI display)
- Manage state across requests (versus static content)

### But It All Works!
The template is flexible:
- Process forms in controller → add data to $data array
- Check download before template → exit if true
- Extract JavaScript → reference in config
- Pass state via hidden form inputs

---

## Conversion Pattern (Same for All 3)

```
Tool.php (Controller)
├─ Load config
├─ Process form/download
├─ IF download flag → sendFile() + exit
├─ Build $data array
├─ Set $display_config
└─ include display-template.php

pages/Tool.php (Content View)
└─ Use $data array, render form/results

js/Tool-name.js (If Needed)
└─ Extract significant JavaScript
```

---

## Implementation Phases

### Phase 1: retrieve_selected_sequences.php (Start Here)
- Smallest (209 lines)
- Simplest form (3 inputs)
- Validates template approach with forms
- **Effort:** 0.5-1 hour
- **Outcome:** Pattern confirmed

### Phase 2: retrieve_sequences.php (Then This)
- Medium (463 lines)
- More form complexity
- Build confidence
- **Effort:** 1-2 hours
- **Outcome:** More complex handling proven

### Phase 3: blast.php (Finally This)
- Largest (710 lines)
- Most JavaScript (500 lines)
- Most complex form logic
- **Effort:** 2-3 hours
- **Outcome:** Pattern handles complexity

### Phase 4: Testing (All Together)
- Form submissions
- File downloads
- State preservation
- Access control
- **Effort:** 1-2 hours
- **Outcome:** Everything works together

---

## What You'll Achieve

After converting these 3 tools:

✓ **100% of user-facing pages** use new layout infrastructure  
✓ **No duplicate code** for HTML/navbar/footer/styles  
✓ **Single source of truth** for layout  
✓ **Better organization** with controller/view split  
✓ **Easier maintenance** - changes affect all pages  
✓ **Completed migration** - layout project done  

---

## Key Risks & Mitigations

| Risk | Severity | Mitigation |
|------|----------|-----------|
| Form state breaks | LOW | Test thoroughly, use $data array |
| Downloads fail | LOW | Keep download check before template |
| JS stops working | MEDIUM | Extract carefully, test in browser |
| Access control breaks | LOW | Verify checks stay in controller |
| Performance issues | LOW | Same code, just split differently |

**Overall Assessment:** LOW RISK (pattern proven)

---

## Files to Reference During Conversion

1. **tools/display-template.php** - The template you're using
2. **tools/organism.php** - Example converted page (controller)
3. **tools/pages/organism.php** - Example content file (view)
4. **tools/tool_init.php** - Common initialization
5. **includes/layout.php** - Layout system

---

## Quick Checklist for Each Tool

- [ ] Create tools/pages/TOOL.php with extracted HTML
- [ ] Refactor tools/TOOL.php as controller
- [ ] Extract JavaScript to js/TOOL-name.js if needed
- [ ] Build $display_config array
- [ ] Build $data array with all variables
- [ ] Test form submissions
- [ ] Test file downloads
- [ ] Test state preservation
- [ ] Test with different user access levels
- [ ] Verify mobile layout works

---

## Next Steps

1. **Read TOOLS_CONVERSION_QUICK_REFERENCE.md** (5 min)
   - Get the overview
   - Understand why it works

2. **Read TOOLS_CONVERSION_PLAN.md** (15 min)
   - Deep dive into strategy
   - Understand challenges

3. **Pick retrieve_selected_sequences.php** (First conversion)
   - Simplest tool
   - Lowest risk
   - Validates approach

4. **Follow the pattern**
   - Create content file
   - Refactor controller
   - Test thoroughly
   - Commit changes

5. **Repeat for other 2 tools**
   - Build confidence
   - Handle complexity
   - Complete migration

---

## Expected Outcomes

### After retrieve_selected_sequences.php
✓ You'll know if the template approach works  
✓ You'll understand the controller/view split  
✓ You'll have confidence for the harder tools

### After retrieve_sequences.php
✓ You'll handle complex form states  
✓ You'll manage more complex logic  
✓ You'll be ready for the hardest tool

### After blast.php
✓ All 3 tools converted  
✓ Heavy JavaScript extracted  
✓ Complex logic organized  
✓ Migration complete

---

## Success Criteria

By the end:
- [ ] All 3 tools use display-template.php
- [ ] All functionality preserved (forms, downloads, JS)
- [ ] Code better organized (controller/view)
- [ ] No manual HTML/navbar/footer/styles
- [ ] Full test coverage of workflows
- [ ] 100% of pages use new layout system

---

## Bottom Line

These are the **final 3 pages** to convert. The conversion is **straightforward** because:

1. The template is proven (used by 4+ pages)
2. The pattern is established (follow it)
3. Challenges are solvable (solutions documented)
4. Risk is low (pattern already works)
5. Effort is reasonable (5-8 hours total)

**No surprises. Follow the plan. Done.** ✓

---

## Questions?

Refer to:
- **TOOLS_CONVERSION_QUICK_REFERENCE.md** - Answers quick questions
- **TOOLS_CONVERSION_PLAN.md** - Answers detailed questions
- **tools/organism.php** - Real working example
- **tools/display-template.php** - How the template works
