/**
 * Groups Display Page Logic
 * Handles search functionality for searching annotations across all organisms in a group
 * 
 * Expects these variables to be defined in the HTML page (from PHP):
 * - groupOrganisms: array of organism names in this group
 * - groupName: the group name
 * - sitePath: the site path prefix
 */

const searchManager = new AnnotationSearch({
    formSelector: '#groupSearchForm',
    organismsVar: groupOrganisms,
    totalVar: groupOrganisms.length,
    hideSections: ['#groupDescription', '#organismsSection'],
    scrollToResults: false,
    extraAjaxParams: {group: groupName},
    noReadMoreButton: false
});

searchManager.init();

