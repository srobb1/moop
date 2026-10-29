<?php
/**
 * Guide for Manage Organisms (admin/pages/manage_organisms.php). If the page changes, change this.
 * The list of checks is built from moop_organism_check_defs() — the same list the Health column
 * uses — so it cannot drift. The old About said "10 requirements" while the code had 12.
 */
$checks = array_column(moop_organism_check_defs(), 'label');
echo help_modal('organisms-guide', 'How Manage Organisms works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Every organism, one row', 'text' => 'Its assemblies and gene sets, whether its data is complete, and the groups it is in. Start here when an organism looks wrong on the site.'],
        ['label' => 'Health', 'text' => 'A tick when every check passes; otherwise the icons say which area is failing. The filter bar above the table shows only organisms with a given problem.'],
        ['label' => 'Metadata, About text, image', 'html' => true, 'text' => 'The <i class="fa fa-file-alt"></i> icon in a row edits organism.json and the About page. Text or an image you add replaces the Wikipedia one; leave them empty to keep Wikipedia.'],
        ['label' => 'Data Health', 'text' => 'The card at the top (also on the Dashboard) lists problems inside the databases themselves — such as gene sets with no annotations.'],
    ]],
    ['heading' => 'The ' . count($checks) . ' checks behind Health', 'cards' => [
        ['label' => 'Checked for every organism', 'html' => true, 'text' => '<ul class="mb-0 ps-3">' . implode('', array_map(fn($l) => '<li>' . htmlspecialchars($l) . '</li>', $checks)) . '</ul>'],
    ]],
    ['heading' => 'The buttons', 'cards' => [
        ['label' => 'Refresh Cache', 'text' => 'The page loads from a saved scan, so it is instant. Refresh rescans only the organisms whose files changed; the page reloads when it finishes.'],
        ['label' => 'Force Full Rescan', 'text' => 'Rescans everything. Use it after rsync, cp -p, tar or a restore — they keep old file dates — if a row still looks out of date.'],
        ['label' => 'Sync NCBI Taxonomy', 'text' => 'Downloads the NCBI taxonomy once, so lineages are looked up locally instead of one request per organism.'],
        ['label' => 'Retire', 'html' => true, 'text' => 'The <i class="fa fa-archive"></i> button in a row takes an organism off the site: its data moves to organisms/.retired/, its genome-browser registration and groups are removed, its cache cleared. Nothing is deleted, and <strong>Restore</strong> in the Retired list puts it back. The box shows exactly what it will do before you confirm.'],
    ]],
]);
