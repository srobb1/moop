<?php
/**
 * Guide for the JavaScript Function Registry (admin/pages/manage_js_registry.php). If the page changes, change this.
 */
echo help_modal('jsregistry-guide', 'How the JavaScript Function Registry works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'A map of the code', 'text' => 'Every JavaScript function in js/, with its documentation and every place it is called from. For developers; nothing here changes the site.'],
        ['label' => 'Generate/Update Registry', 'text' => 'The list is built on request, not live. Press it after code changes; the date it was last built is shown at the top.'],
        ['label' => 'Search & Filter', 'text' => 'Find a function by name or text, or narrow the list by category.'],
        ['label' => 'Unused', 'text' => 'Functions nothing calls. Usually safe to delete — but check first: a function called by building its name from a string, or only from a template, is listed as unused too.'],
    ]],
]);
