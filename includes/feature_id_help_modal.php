<?php
/**
 * Help for the gene ID lookup — the (i) beside the index page's ID box AND the navbar box.
 *
 * Rendered once per page from includes/toolbar.php (every page has the navbar box), so
 * there is one copy of this text; it used to live in tools/pages/index.php, which would
 * have meant a second, drifting copy for the navbar.
 */
$site = $site ?? ConfigManager::getInstance()->getString('site');

echo help_modal(
    'fs-help',
    'Finding a gene by its ID',
    [
        ['heading' => 'What this does', 'cards' => [
            ['label' => 'Exact IDs only', 'text' => 'Paste the accession of a gene, transcript, CDS or protein. It must match exactly — this is a lookup, not a search.'],
            ['label' => 'Everywhere at once', 'text' => 'Every gene set you have access to is checked, so you need not know which organism the ID belongs to.'],
            ['label' => 'Lands on the gene', 'text' => 'A transcript, CDS or protein ID all open the gene page they belong to.'],
        ]],
        // Two routes, because they answer different questions and the choice is the
        // thing a newcomer cannot guess. Naming only the annotation search would send
        // someone hunting one organism across all 85.
        ['heading' => 'Looking for a word, not an ID?', 'cards' => [
            ['label' => 'In one organism, or one group',
             'text' => 'On the home page, use the Organisms tab to open that organism or group, then search from its page — results stay scoped to what you picked.'],
            ...(moop_tool_on('annotation_search') ? [   // switched off: no card pointing at it
            ['label' => 'Across everything, or your own list', 'html' => true,
             'text' => 'Use the <a href="/' . htmlspecialchars($site) . '/tools/search.php">annotation search</a>, where you can search every organism at once or build a custom selection.'],
            ] : []),
            ['label' => 'Not this box',
             'text' => 'A protein name, domain or GO term — "kinase", "zinc finger" — will not match here. This field compares accessions exactly.'],
        ]],
    ],
    ['intro' => 'Go straight to one feature when you already have its accession.']
);
