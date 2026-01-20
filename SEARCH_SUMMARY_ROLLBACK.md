# Search Summary View - Rollback Guide

## Overview
This document explains how to safely rollback the search summary view feature if needed.

## Safe Rollback Options

### Option 1: Quick Revert to Previous Commit (Safest)
```bash
# If you haven't merged to main yet (still on feature branch):
git checkout main
git branch -D feature/search-summary-view

# If you want to try again later, the branch is saved in reflog for 90 days:
git reflog
git checkout -b feature/search-summary-view [commit_sha]
```

### Option 2: Revert Last Commit (If merged to main)
```bash
# This creates a new commit that undoes the changes
git revert [commit_sha]

# The commit SHA for the summary view feature is:
git log --oneline | grep "search results summary view"
```

### Option 3: Reset to Previous State (If on main)
```bash
# Find the commit before the feature:
git log --oneline | head -20

# Reset (careful - this rewrites history):
git reset --hard [previous_commit_sha]

# Or reset just specific files:
git checkout main~1 -- js/modules/shared-results-table.js
git checkout main~1 -- js/modules/annotation-search.js
```

---

## What Was Changed

### 1. `/data/moop/js/modules/shared-results-table.js`
**Added 3 new functions at the end of the file (starting ~line 260):**
- `groupResultsByFeature(results)` - Groups results by feature uniquename
- `createSummaryResultsTable(...)` - Creates the expandable summary view
- `initializeSummaryControls(...)` - Handles expand/collapse buttons

**To revert just this file:**
```bash
# Restore to previous version
git checkout HEAD~1 -- js/modules/shared-results-table.js
git commit -m "revert: remove search summary view functions from shared-results-table.js"
```

### 2. `/data/moop/js/modules/annotation-search.js`
**Modified the `displayOrganismResults()` method (around line 286):**
- Changed to use `createSummaryResultsTable()` for keyword searches
- Kept `createOrganismResultsTable()` for uniquename searches
- Added logic to skip DataTable initialization for summary view

**To revert just this file:**
```bash
# Restore to previous version
git checkout HEAD~1 -- js/modules/annotation-search.js
git commit -m "revert: use full table view for all searches"
```

---

## Testing Rollback

### Before Rolling Back
```bash
# Get the exact commit to revert:
cd /data/moop
git log --oneline feature/search-summary-view~1..feature/search-summary-view

# Example output:
# a782532 feat: add search results summary view with expandable details
```

### After Rolling Back
```bash
# Verify files are reverted:
git status

# Test the page loads:
# - Go to a search page
# - Perform a keyword search
# - Verify results display in full table format (old behavior)

# Check console for any errors:
# Browser DevTools > Console tab - should be clean
```

---

## Emergency Rollback (If Something Breaks)

```bash
# If the page won't load after deploying:
cd /data/moop

# Immediate rollback to last working commit:
git revert HEAD --no-edit

# Or reset completely:
git reset --hard origin/main

# Clear any caches:
rm -rf logs/*.log
```

---

## Branch Management

### Current Setup
```
main (origin/main)
└── feature/search-summary-view
    └── [commits for this feature]
```

### If Feature is Approved
```bash
# Merge to main (if you want to keep in history):
git checkout main
git merge feature/search-summary-view
git push origin main

# Clean up feature branch:
git branch -d feature/search-summary-view
```

### If Feature is Rejected
```bash
# Switch back to main:
git checkout main

# Delete feature branch:
git branch -D feature/search-summary-view

# Reset to last good commit (if needed):
git reset --hard origin/main
```

---

## Verifying Rollback Success

### Check git status
```bash
git status
# Should show "On branch main" and "working tree clean"
```

### Check file contents
```bash
# Should NOT contain "createSummaryResultsTable" if rolled back:
grep -n "createSummaryResultsTable" js/modules/shared-results-table.js
# Should return: (no matches)
```

### Check specific functions exist/don't exist
```bash
# After rollback, these should NOT be in the file:
grep -n "groupResultsByFeature\|createSummaryResultsTable\|initializeSummaryControls" \
    js/modules/shared-results-table.js
# Should return: (no matches)
```

---

## Commit History Reference

All changes are in a single atomic commit for easy tracking:

```
Commit: feat: add search results summary view with expandable details
Files changed:
  - js/modules/shared-results-table.js (+200 lines)
  - js/modules/annotation-search.js (+7 lines)
Total insertions: ~207
Total deletions: 0 (only additions)
```

This makes it very easy to revert - just one commit to undo.

---

## Questions to Ask Before Rolling Back

1. **What's the specific issue?**
   - UI not rendering? → Check browser console
   - Search taking too long? → Check result set size
   - Features not working? → Identify which feature

2. **Is it affecting all searches or just some?**
   - All searches → Could be a syntax error (check console)
   - Only keyword searches → Feature-specific issue
   - Only certain organisms → Data-related issue

3. **Can we debug instead of rollback?**
   - Browser DevTools > Console for errors
   - Browser DevTools > Network for AJAX responses
   - Look at server logs for PHP errors

---

## Git Reflog for Recovery

If you accidentally delete commits, they're still in the reflog for 90 days:

```bash
# View reflog:
git reflog

# Recover a deleted branch:
git checkout -b feature/search-summary-view [commit_sha]

# Example output:
# a782532 HEAD@{0}: commit: feat: add search results summary view...
# 05ed55c HEAD@{1}: commit: WIP: registry updates...
```

---

## Notes for Team

- ✅ Feature is on isolated branch: `feature/search-summary-view`
- ✅ Main branch is untouched and safe
- ✅ All changes are backwards compatible
- ✅ Only affects keyword searches (not uniquename searches)
- ✅ JavaScript syntax validated before commit
- ✅ Rollback is a single `git revert` command

**Bottom line:** If anything goes wrong, you can undo everything with one command.
