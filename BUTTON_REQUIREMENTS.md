# Download Button Requirements

## Critical Discovery
The download buttons require a **hybrid approach** combining OLD and NEW libraries:
- **NEW buttons 2.3.6 JS** = Functionality (copy, csv, excel, print, colvis)
- **OLD buttons 1.6.4 CSS** = Beautiful styling and appearance
- Using ONLY 2.3.6 results in invisible/plain buttons
- Using ONLY 1.6.4 results in non-functional buttons

---

## What's Actually Required

### 1. jQuery (REQUIRED)
- `https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js`
- **Purpose**: jQuery library needed for DataTables plugin
- **Location**: header.php (line 42)
- **Status**: ✅ Correct version

### 2. DataTables Core 1.13.4 (REQUIRED)
- **JS**: `https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js`
- **CSS**: `https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css`
- **Purpose**: Core DataTables library and Bootstrap5 theme
- **Location**: header.php (lines 48, 36)
- **Status**: ✅ Correct version

### 3. DataTables Bootstrap5 Theme 1.13.4 (REQUIRED)
- **JS**: `https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js`
- **Purpose**: Bootstrap5 integration for DataTables
- **Location**: header.php (line 49)
- **Status**: ✅ Correct version

### 4. DataTables Buttons Extension 2.3.6 (REQUIRED - for functionality)
- **JS Core**: `https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js`
- **JS Bootstrap5**: `https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js`
- **JS HTML5**: `https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js`
- **JS Print**: `https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js`
- **JS ColVis**: `https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js`
- **CSS**: `https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css`
- **Purpose**: 
  - Core buttons functionality
  - CSV/Excel export (via html5 module)
  - Print functionality
  - Column visibility toggle
- **Location**: header.php (lines 50-52, 69)
- **Status**: ✅ Correct version

### 5. DataTables Buttons 1.6.4 CSS (TESTED - NOT NECESSARY)
- **CSS Only**: `https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css`
- **Purpose**: ~~Makes buttons visually appealing with icons, proper sizing, and styling~~
- **Location**: header.php, groups_display.php, organism_display.php (all COMMENTED OUT)
- **Status**: ❌ DISABLED - Not needed
- **Test Result**: Buttons 2.3.6 CSS provides all necessary styling
- **Action**: SAFE TO REMOVE

### 6. Export Helper Libraries (REQUIRED for Excel export)
- **jszip**: `https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js`
- **Purpose**: Enable Excel export functionality
- **Location**: header.php, organism_display.php, groups_display.php
- **Status**: ✅ Correct version
- **Note**: pdfmake removed - users use browser's print-to-PDF instead

### 7. Font Awesome 5.7.0 (TESTED - REQUIRED)
- **CSS**: `https://use.fontawesome.com/releases/v5.7.0/css/all.css`
- **Purpose**: Provides icons for download buttons (copy, CSV, Excel, print, column visibility)
- **Location**: header.php (line 74)
- **Status**: ✅ REQUIRED - Icons disappear without it
- **Test Result**: Button icons are from Font Awesome; removing it breaks icon display
- **Action**: MUST KEEP

### 8. Button Configuration (REQUIRED for initialization)
- **File**: `/js/datatable-config.js`
- **Functions**:
  - `getAnnotationButtons()` - Returns button config for parent.php (Copy, CSV, Excel, Print/PDF)
  - `getSearchResultsButtons()` - Returns button config for search pages (Copy, CSV, Excel, Print/PDF, Column Visibility)
- **Location**: Loaded in parent.php, organism_display.php, groups_display.php
- **Status**: ✅ Created and functional

### 9. Page Initialization Scripts (REQUIRED)
- **parent.php**: `/js/parent.js` - Calls `DataTableExportConfig.reinitialize()` for annotation tables
- **search pages**: `shared_results_table.js` - Calls `DataTableExportConfig.getSearchResultsButtons()` for search results
- **Status**: ✅ Functional

---

## What Can Be Removed

### ✅ DataTables 1.10.24 (lines 59-60 in header.php) - REQUIRED
```
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
```
- **Reason**: Core functionality for buttons and tables
- **Status**: TESTED - Buttons disappear without it
- **Action**: MUST KEEP

### ❌ colReorder 1.5.5 (line 62 in header.php) - NOT NEEDED
```
<script src="https://cdn.datatables.net/colreorder/1.5.5/js/dataTables.colReorder.min.js"></script>
```
- **Reason**: Column reordering functionality (not essential)
- **Status**: TESTED - Sort works fine without it (parent.php, groups_display.php)
- **Action**: SAFE TO REMOVE

### ❌ DataTables Buttons 1.6.4 CSS (line 43 in header.php) - NOT NEEDED
```
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.dataTables.min.css">
```
- **Reason**: Buttons 2.3.6 CSS provides all necessary styling
- **Status**: TESTED - Buttons display correctly without it
- **Action**: SAFE TO REMOVE (already commented out in all files)

### ✅ Font Awesome 5.7.0 (header.php line 74) - REQUIRED
```
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css">
```
- **Reason**: Provides icons for download buttons (copy, CSV, Excel, print, column visibility)
- **Status**: TESTED - Button icons disappear without it
- **Action**: MUST KEEP

### ✅ DataTables Buttons 1.6.4 JS Files (lines 62-66 in header.php) - REQUIRED
<script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.print.min.js"></script>
```
- **Reason**: Provides core button functionality (not just styling)
- **Note**: Paired with 2.3.6 JS for modern features + 1.6.4 styling
- **Status**: TESTED - CANNOT be removed without buttons disappearing
- **Action**: MUST KEEP

---

## Complete Button Stack by Page

### parent.php (Annotation Tables)
**Required includes (from header.php):**
1. jQuery
2. Bootstrap 5.3.2
3. DataTables 1.13.4 (JS + CSS)
4. Buttons 2.3.6 (JS + CSS)
5. Buttons 1.6.4 JS (functionality)
6. DataTables 1.10.24 (compatibility)
7. Font Awesome 5.7.0 (icons)
8. jszip 3.10.1 (Excel export)
9. datatable-config.js
10. parent.js

**Buttons provided:** Copy, CSV, Excel, Print/PDF (no Column Visibility)

---

### organism_display.php (Search Results)
**Required includes:**
1. Bootstrap 5.3.2
2. DataTables 1.13.4 (JS + CSS)
3. Buttons 2.3.6 (JS + CSS)
4. Buttons 1.6.4 JS (functionality)
5. DataTables 1.10.24 (compatibility)
6. Font Awesome 5.7.0 (icons)
7. jszip 3.10.1 (Excel export)
8. datatable-config.js
9. shared_results_table.js

**Buttons provided:** Copy, CSV, Excel, Print/PDF, Column Visibility

---

### groups_display.php (Search Results)
**Required includes:**
1. Bootstrap 5.3.2
2. DataTables 1.13.4 (JS + CSS)
3. Buttons 2.3.6 (JS + CSS)
4. Buttons 1.6.4 JS (functionality)
5. DataTables 1.10.24 (compatibility)
6. Font Awesome 5.7.0 (icons)
7. jszip 3.10.1 (Excel export)
8. datatable-config.js
9. shared_results_table.js

**Buttons provided:** Copy, CSV, Excel, Print/PDF, Column Visibility

---

## Summary

### Modern Stack (Working - Hybrid Approach)
- DataTables 1.13.4 (JS + CSS)
- Buttons 2.3.6 JS (modern functionality)
- Buttons 1.6.4 JS (core button functionality)
- Font Awesome 5.7.0 (button icons)
- jszip 3.10.1 (for Excel export)
- pdfmake REMOVED - users use browser's print-to-PDF

### Legacy Stack (Tested & Confirmed Required)
- DataTables 1.10.24 (TESTED - REQUIRED for button functionality)
- ColReorder 1.5.5 CSS (TESTED - CSS kept for table styling, JS removed)

### Tested & Removed
- ✅ Buttons 1.6.4 CSS - REMOVED (Buttons 2.3.6 CSS sufficient)
- ✅ colReorder 1.5.5 JS - REMOVED (Sorting works without it)
- ✅ pdfmake - REMOVED (Users use browser's print-to-PDF)

### CONFIRMED REQUIRED (DO NOT REMOVE)
- ✅ Buttons 1.6.4 JS - Tested: buttons disappear without it
- ✅ DataTables 1.10.24 - Tested: buttons disappear without it
- ✅ Font Awesome 5.7.0 - Tested: button icons disappear without it

### CONFIRMED SAFE TO REMOVE
- ✅ Buttons 1.6.4 CSS - Tested: buttons display correctly with 2.3.6 CSS only
- ✅ colReorder 1.5.5 JS - Tested: sort works fine without it

### Why This Works
1. **DataTables 1.13.4** = Modern, actively maintained, Bootstrap5 compatible
2. **Buttons 2.3.6 JS** = Provides all modern button features
3. **Buttons 1.6.4 CSS** = Only CSS file that makes buttons look good with 2.3.6 JS
4. **Export Libraries** = Required for CSV/Excel export functionality
5. **Configuration** = Centralized button styling in `datatable-config.js`
6. **Initialization** = Page-specific scripts call configuration

---

## Last Updated
2025-11-05 (Comprehensive testing completed)

## Notes
- ✅ **Buttons 1.6.4 CSS** - TESTED & REMOVED (2.3.6 CSS sufficient)
- ✅ **Font Awesome 5.7.0** - TESTED & REQUIRED (icons disappear without it)
- Do NOT remove Buttons 1.6.4 JS - buttons disappear without it
- Do NOT use only 1.6.4 JS - missing modern features
- The hybrid approach (2.3.6 JS + 1.6.4 JS + Font Awesome) is the working solution
- **jszip REQUIRED** for Excel export
- **pdfmake REMOVED** - Users now print-to-PDF via browser (cleaner, fewer dependencies)
- CSV export uses native browser functionality (no extra library needed)
- Print button opens browser print dialog where users can save as PDF
- Column visibility (colvis) is only enabled on search results pages
