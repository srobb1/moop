/**
 * Centralized Help Text
 * Used across all search pages (organism, assembly, groups, multi-organism)
 */

/**
 * The per-organism result cap, as a formatted string for help text.
 *
 * Comes from window.MOOP_SEARCH_RESULTS_LIMIT, emitted by includes/layout.php from
 * the site configuration — help must never carry its own copy of the number, or it
 * goes on quoting 2,500 after an admin changes the setting, and help that states a
 * wrong number is worse than help that states none.
 */
const RESULTS_CAP = (window.MOOP_SEARCH_RESULTS_LIMIT || 2500).toLocaleString();

// Common search help sections (reused across pages)
const BASIC_SEARCH_HELP = `<strong>How to Search</strong><br><br><strong>Single Word Searches:</strong><br>Enter a single keyword to find all matching annotations. Example: "kinase"<br><br><strong>Multi-Word Searches:</strong><br>Enter multiple words separated by spaces to find records containing ALL of the terms. The terms can appear anywhere in the text. Example: "kinase domain" finds records that contain BOTH kinase AND domain<br><br><strong>Quoted Searches:</strong><br>Use quotes to search for exact phrases. Example: "ABC transporter" finds only exact matches<br><br><strong>Minimum Character Requirement:</strong><br>Terms with fewer than 3 characters are automatically ignored. Example: searching for "P53 tumor" will only search for "tumor" since "P53" has fewer than 3 characters. To search for short terms like "P53", use quotes: "P53"<br><br><strong>Search Types:</strong><br>When you search, the system first checks if your term matches a feature's unique identifier. If a match is found, those results are returned. If no match is found, the search defaults to searching across all annotations. This means:<br>• <strong>Feature ID Search:</strong> Single-term searches first look for matches in feature unique identifiers<br>• <strong>Annotation Search:</strong> If no feature ID match is found, or if you use multiple terms, results are pulled from annotation descriptions and other fields`;


const ASSEMBLY_SEARCH_INFO = `<br><br><strong>Search Within a Single Assembly:</strong><br>To search within a single assembly, navigate to an organism page, then select the assembly page to limit your search to that specific assembly`;

const ORGANISM_SELECTION_INFO = `<br><br><strong>Select Organisms:</strong><br>Note: This option is only available on multiple organism search pages (Multi-organism and Groups). Use the checkboxes below to select/deselect which organisms to include in your search`;

const RESULTS_LIMIT = `<br><br><strong>Results Limit:</strong><br>Results are capped at ${RESULTS_CAP}`;

const FILTERING_INFO = `<br><br><strong>Filtering:</strong><br>Click the filter button to limit search to specific annotation sources. Example: limit searches to only "Ensembl Human Homologs (ENS_homo_sapiens)"`;


const SEARCH_HELP = {
    // Basic search help (used on organism and assembly pages - no organism selection)
    basic: `${BASIC_SEARCH_HELP}${RESULTS_LIMIT} - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`,

    // Group-specific search help
    group: `${BASIC_SEARCH_HELP}${ORGANISM_SELECTION_INFO.replace('when searching from group or multi-organism pages', 'on group pages')}${RESULTS_LIMIT} per organism - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`,

    // Multi-organism search help
    multiOrganism: `${BASIC_SEARCH_HELP}${ORGANISM_SELECTION_INFO.replace('when searching from group or multi-organism pages', 'on multi-organism pages')}${RESULTS_LIMIT} per organism - use more specific terms to refine your search${ASSEMBLY_SEARCH_INFO}${FILTERING_INFO}`
};
