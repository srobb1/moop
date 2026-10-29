<?php
/**
 * Guide for the PHP Function Registry (admin/pages/manage_registry.php). If the page changes, change this.
 */
echo help_modal('phpregistry-guide', 'How the PHP Function Registry works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'A map of the code', 'text' => 'Every PHP function in lib/, tools/, admin/, includes/, api/ and the top-level scripts, with its documentation and every place it is called from. For developers; nothing here changes the site.'],
        ['label' => 'Generate/Update Registry', 'text' => 'The list is built on request, not live. Press it after code changes; the date it was last built is shown at the top.'],
        ['label' => 'Search & Filter', 'text' => 'Find a function by name or text, or narrow the list by category.'],
        ['label' => 'Unused', 'text' => 'Functions nothing calls. Check before deleting: one called by a name built from a string, or only from JavaScript or a template, is listed here too.'],
    ]],
]);
