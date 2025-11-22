/**
 * Organism Display Page Logic
 * Handles search functionality for a single organism's annotations
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - sitePath: the site path prefix
 * - organismName: the organism name
 */

let allResults = [];
let searchedOrganisms = 0;
const totalOrganisms = 1; // Single organism search
let organismImagePaths = {}; // Store image paths by organism

$('#organismSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide organism content on first search
    if ($('#searchResults').is(':hidden')) {
        $('#organismHeader').slideUp();
        $('#organismContent').slideUp();
    }
    
    // Reset and show results section
    allResults = [];
    searchedOrganisms = 0;
    $('#searchResults').show();
    $('#resultsContainer').html('');
    $('#searchInfo').html(`Searching for: <strong>${keywords}</strong> in ${organismName}`);
    
    // Show progress bar
    $('#searchProgress').html(`
        <div class="search-progress-bar">
            <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
        </div>
        <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
    `);
    
    // Search the single organism
    searchOrganism(organismName, keywords, quotedSearch);
});

function searchOrganism(organism, keywords, quotedSearch) {
    $('#progressText').html(`Searching ${organism}...`);
    
    $.ajax({
        url: sitePath + '/tools/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: '', // No group context
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(response) {
            searchedOrganisms++;
            
            if (response.results && response.results.length > 0) {
                allResults = allResults.concat(response.results);
                // Store organism image path
                if (response.organism_image_path) {
                    organismImagePaths[response.organism] = response.organism_image_path;
                }
            }
            
            // Update progress
            const progress = (searchedOrganisms / totalOrganisms) * 100;
            $('#progressFill').css('width', progress + '%').text(Math.round(progress) + '%');
            
            // Display final results
            displayResults();
        },
        error: function(xhr, status, error) {
            console.error('Search error for ' + organism + ':', error);
            searchedOrganisms++;
            const progress = (searchedOrganisms / totalOrganisms) * 100;
            $('#progressFill').css('width', progress + '%').text(Math.round(progress) + '%');
            displayResults();
        }
    });
}

function displayResults() {
    if (allResults.length === 0) {
        $('#searchProgress').html('<div class="alert alert-warning">No results found. Try different search terms.</div>');
    } else {
        $('#searchProgress').html(`
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${allResults.length} result${allResults.length !== 1 ? 's' : ''}.
                <hr class="my-2">
                <small>
                    <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                    <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                    <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print) to download selected data.<br>
                    <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                </small>
            </div>
        `);
        
        // Group results by organism (will be single organism)
        const resultsByOrganism = {};
        allResults.forEach(result => {
            if (!resultsByOrganism[result.organism]) {
                resultsByOrganism[result.organism] = [];
            }
            resultsByOrganism[result.organism].push(result);
        });
        
        // Display results for the organism using shared function
        Object.keys(resultsByOrganism).forEach(organism => {
            const results = resultsByOrganism[organism];
            const imageUrl = organismImagePaths[organism] || '';
            $('#resultsContainer').append(createOrganismResultsTable(organism, results, sitePath, 'tools/parent_display.php', imageUrl));
        });
    }
}
