<?php
/**
 * Guide for JBrowse Track Management (admin/pages/manage_jbrowse.php). If the page changes,
 * change this. The index rules are docs/JBrowse2/GENE_TRACK_INDEXES.md.
 */
echo help_modal('jbrowse-guide', 'How JBrowse Track Management works', [
    ['heading' => 'The usual order', 'cards' => [
        ['label' => '1 · Register', 'text' => 'Puts an assembly in the genome browser: prepares the genome files, the gene track from genes.gff, and the gene-name search index. A gene set without genes.gff gets no gene track.'],
        ['label' => '2 · Add sheet', 'text' => 'For other tracks (RNA-seq, peaks…): press Add sheet on the assembly\'s row and paste the Google Sheet link. Saving creates the tracks.'],
        ['label' => '3 · Sync', 'text' => 'After editing the sheet, press Sync on the row. Names, categories and descriptions update; the gene track is never touched by a sync.'],
    ]],
    ['heading' => 'Badges and repairs', 'cards' => [
        ['label' => 'Gene track / search index', 'text' => 'Each gene set is checked live against its genes.gff. A warning means the track or search index is missing or older than the GFF; Rebuild fixes it (housekeeping also does this in the background).'],
        ['label' => 'Full rebuild', 'text' => 'Re-reads every track file — slow on a big sheet. Only needed when a row\'s file, colour or track type changed.'],
        ['label' => 'Dry run', 'text' => 'Shows what a sync or clean-up would change, without changing anything.'],
        ['label' => 'Unregister', 'text' => 'Removes only what registration created, so the assembly leaves the genome browser. Its data, BLAST and MOOP pages are untouched.'],
    ]],
]);
