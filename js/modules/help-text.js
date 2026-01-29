/**
 * Centralized Help Text
 * Used across all search pages (organism, assembly, groups, multi-organism)
 */

// Common search help sections (reused across pages)
const BASIC_SEARCH_HELP = `<strong>How to Search</strong><br><br><strong>Single Word Searches:</strong><br>Enter a single keyword to find all matching annotations. Example: "kinase"<br><br><strong>Multi-Word Searches:</strong><br>Enter multiple words separated by spaces to find records containing ALL of the terms. The terms can appear anywhere in the text. Example: "kinase domain" finds records that contain BOTH kinase AND domain<br><br><strong>Exact Phrase Searches:</strong><br>Use quotes to search for exact phrases. Example: "ABC transporter" finds only exact matches<br><br><strong>Minimum Character Requirement:</strong><br>Terms with fewer than 3 characters are automatically ignored. Example: searching for "P53 tumor" will only search for "tumor" since "P53" has fewer than 3 characters. To search for short terms like "P53", use quotes: "P53"<br><br><strong>Search Types:</strong><br>When you search, the system first checks if your term matches a feature's unique identifier. If a match is found, those results are returned. If no match is found, the search defaults to searching across all annotations. This means:<br>• <strong>Feature ID Search:</strong> Single-term searches first look for matches in feature unique identifiers<br>• <strong>Annotation Search:</strong> If no feature ID match is found, or if you use multiple terms, results are pulled from annotation descriptions and other fields`;

const RESULTS_HELP = `<strong>Using Your Results</strong><br><br><strong>View Modes:</strong><br><strong>Simple View:</strong> Shows a unique list of feature/sequence IDs that matched your search. Each feature appears only once, providing an overview of all matching features without duplication.<br><strong>Expanded View:</strong> Shows all annotations for each matching feature, with the search terms highlighted. This lets you see every annotation that matched your search terms.<br><br><strong>Filter Results:</strong><br>Use the search boxes above each column header to filter results. Type to narrow down results by specific values.<br><br><strong>Sort Results:</strong><br>Click any column header to sort ascending or descending. Click again to reverse the sort order.<br><br><strong>Expand All Matches:</strong><br>Click the "Expand All" button to expand all rows and view detailed information for each result at once. Note: Horizontal scrolling may be necessary to view the complete table when viewing expanded results.<br><br><strong>Select and Export:</strong><br>Use the checkboxes to select specific rows, then click export buttons at the bottom:<br>• <strong>Copy:</strong> Copy selected rows to clipboard<br>• <strong>CSV:</strong> Download as comma-separated values file<br>• <strong>Excel:</strong> Download as Excel spreadsheet<br>• <strong>PDF:</strong> Download as PDF document<br>• <strong>Print:</strong> Print selected results<br><br><strong>Column Visibility:</strong><br>Click the "Column Visibility" button to show or hide specific columns based on your needs.<br><br><strong>View Details:</strong><br>Click on any gene or annotation link to view detailed information on the gene/parent feature page.`;

const ASSEMBLY_SEARCH_INFO = `<br><br><strong>Search Within a Single Assembly:</strong><br>To search within a single assembly, navigate to an organism page, then select the assembly page to limit your search to that specific assembly`;

const ORGANISM_SELECTION_INFO = `<br><br><strong>Select Organisms:</strong><br>Note: This option is only available on multiple organism search pages (Multi-organism and Groups). Use the checkboxes below to select/deselect which organisms to include in your search`;

const RESULTS_LIMIT = `<br><br><strong>Results Limit:</strong><br>Results are capped at 2,500`;

const FILTERING_INFO = `<br><br><strong>Filtering:</strong><br>Click the filter button to limit search to specific annotation sources. Example: limit searches to only "Ensembl Human Homologs (ENS_homo_sapiens)"`;


const SEARCH_HELP = {
    // Basic search help (used on organism and assembly pages - no organism selection)
    basic: `${BASIC_SEARCH_HELP}${RESULTS_LIMIT} - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`,

    // Group-specific search help
    group: `${BASIC_SEARCH_HELP}${ORGANISM_SELECTION_INFO.replace('when searching from group or multi-organism pages', 'on group pages')}${RESULTS_LIMIT} per organism - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`,

    // Multi-organism search help
    multiOrganism: `${BASIC_SEARCH_HELP}${ORGANISM_SELECTION_INFO.replace('when searching from group or multi-organism pages', 'on multi-organism pages')}${RESULTS_LIMIT} per organism - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`,

    // Results help (same for all pages)
    results: RESULTS_HELP
};
