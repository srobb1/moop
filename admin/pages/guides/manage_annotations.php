<?php
/**
 * Guide for Manage Annotation Sections (admin/pages/manage_annotations.php). If the page
 * changes, change this. The old About promised "enable/disable annotation types per
 * organism" — no such feature exists (on the user's to-do list, 2026-10-29).
 */
echo help_modal('annotations-guide', 'How Annotation Sections work', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'The sections of a gene page', 'text' => 'Each annotation type in the databases (Orthologs, Domains, Gene Ontology…) is one section on a gene page. Here you decide their order, names and descriptions.'],
        ['label' => 'Found automatically', 'text' => 'Loading this page re-reads only the databases that changed since the last look. A new type is added at the end with default settings.'],
    ]],
    ['heading' => 'What you can change', 'cards' => [
        ['label' => 'Order', 'text' => 'Drag a row to move it. Gene pages show their sections in this order, for every organism.'],
        ['label' => 'Display label', 'text' => 'The name readers see, e.g. "Homologous Proteins" instead of the database\'s own name for the type.'],
        ['label' => 'Description', 'text' => 'A sentence or two shown with the section, saying what the annotations are and where they come from.'],
        ['label' => 'Synonyms', 'text' => 'Other names a database may use for the same type, so they are shown as one section.'],
        ['label' => 'Delete', 'text' => 'Only offered for a type no database has any data for any more.'],
        ['label' => 'Not here (yet)', 'text' => 'Sections apply to every organism; there is no per-organism on/off.'],
    ]],
]);
