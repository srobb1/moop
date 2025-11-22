/**
 * Groups Display Page Logic
 * Handles search functionality for searching annotations across all organisms in a group
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - groupOrganisms: array of organism names in this group
 * - groupName: the group name
 * - sitePath: the site path prefix
 */

let allResults = [];
let searchedOrganisms = 0;
let totalOrganisms = groupOrganisms.length;

$('#groupSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide group description and organisms sections on first search
    if ($('#searchResults').is(':hidden')) {
        $('#groupDescription').slideUp();
        $('#organismsSection').slideUp();
    }
    
    // Reset and show results section
    allResults = [];
    searchedOrganisms = 0;
    $('#searchResults').show();
    $('#resultsContainer').html('');
    $('#searchInfo').html(`Searching for: <strong>${keywords}</strong> across ${totalOrganisms} organisms in ${groupName}`);
    
    // Show progress bar
    $('#searchProgress').html(`
        <div class="search-progress-bar">
            <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
        </div>
        <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
    `);
    
    // Disable search button
    $('#searchBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Searching...');
    
    // Search each organism sequentially
    searchNextOrganism(keywords, quotedSearch, 0);
});

function searchNextOrganism(keywords, quotedSearch, index) {
    if (index >= totalOrganisms) {
        // All searches complete
        finishSearch();
        return;
    }
    
    const organism = groupOrganisms[index];
    $('#progressText').html(`Searching ${organism}... (${index + 1}/${totalOrganisms})`);
    
    $.ajax({
        url: sitePath + '/tools/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: groupName,
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(data) {
            if (data.results && data.results.length > 0) {
                allResults = allResults.concat(data.results);
                displayOrganismResults(data);
            }
            
            searchedOrganisms++;
            const progress = Math.round((searchedOrganisms / totalOrganisms) * 100);
            $('#progressFill').css('width', progress + '%').text(progress + '%');
            
            // Search next organism
            searchNextOrganism(keywords, quotedSearch, index + 1);
        },
        error: function(xhr, status, error) {
            console.error('Search error for ' + organism + ':', error);
            console.error('Response text:', xhr.responseText.substring(0, 500));
            searchedOrganisms++;
            searchNextOrganism(keywords, quotedSearch, index + 1);
        }
    });
}

function displayOrganismResults(data) {
    const organism = data.organism;
    const results = data.results;
    const imageUrl = data.organism_image_path || '';
    
    // Add "Read More" button after organism name by wrapping the shared function
    const tableHtml = createOrganismResultsTable(organism, results, sitePath, 'tools/parent_display.php', imageUrl);
    
    // Insert the "Read More" button into the generated HTML
    const safeOrganism = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const organismUrl = sitePath + '/tools/organism_display.php?organism=' + encodeURIComponent(organism) + '';
    const readMoreBtn = `<a href="${organismUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary ms-2 font-size-small">
                    <i class="fa fa-info-circle"></i> Read More
                </a>`;
    
    // Insert button after the badge in the h5 tag
    const modifiedHtml = tableHtml.replace(/(<span class="badge bg-primary">.*?<\/span>)/, `$1\n                ${readMoreBtn}`);
    
    $('#resultsContainer').append(modifiedHtml);
}

function finishSearch() {
    $('#searchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Search');
    
    if (allResults.length === 0) {
        $('#searchProgress').html('<div class="alert alert-warning">No results found. Try different search terms.</div>');
    } else {
        // Build jump-to navigation
        let jumpToHtml = '<div class="alert alert-info mb-3"><strong>Jump to results for:</strong> ';
        const organismCounts = {};
        allResults.forEach(r => {
            if (!organismCounts[r.organism]) {
                organismCounts[r.organism] = 0;
            }
            organismCounts[r.organism]++;
        });
        
        Object.keys(organismCounts).forEach((org, idx) => {
            const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
            const genus = allResults.find(r => r.organism === org)?.genus || '';
            const species = allResults.find(r => r.organism === org)?.species || '';
            if (idx > 0) jumpToHtml += ' | ';
            jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
        });
        jumpToHtml += '</div>';
        
        $('#searchProgress').html(`
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${allResults.length} total result${allResults.length !== 1 ? 's' : ''} across ${searchedOrganisms} organisms.
                <hr class="my-2">
                <small>
                    <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                    <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                    <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print) to download selected data.<br>
                    <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                </small>
            </div>
            ${jumpToHtml}
        `);
    }
}
