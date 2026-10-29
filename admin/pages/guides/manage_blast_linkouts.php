<?php
/**
 * Guide for Manage BLAST Linkouts (admin/pages/manage_blast_linkouts.php). If the page changes,
 * change this.
 */
echo help_modal('linkouts-guide', 'How BLAST Linkouts work', [
    ['heading' => 'Buttons on every BLAST hit', 'cards' => [
        ['label' => 'Gene Page', 'text' => 'For hits in an mRNA, CDS or protein database: opens the gene\'s page, when the organism has a database.'],
        ['label' => 'Genome Browser', 'text' => 'Opens JBrowse at the hit, with the matching pieces drawn as coloured blocks. A genome hit can zoom to each piece; a feature hit goes to the gene.'],
        ['label' => 'Global linkouts', 'text' => 'Your own buttons, shown on every hit in every database — a URL with placeholders such as {fasta_id}.'],
        ['label' => 'Per-database linkouts', 'text' => 'Your own buttons for one organism, assembly and database only.'],
    ]],
    ['heading' => 'Feature coordinates', 'cards' => [
        ['label' => 'feature_coords.tsv', 'text' => 'Genome Browser buttons on feature hits need each gene set\'s coordinate file. It is built when the gene set is registered in JBrowse; the section at the foot shows which have one.'],
    ]],
]);
