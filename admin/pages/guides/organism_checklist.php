<?php
/**
 * Guide for the New Organism Setup Checklist (admin/pages/organism_checklist.php). If the
 * page's steps change, change this.
 */
echo help_modal('checklist-guide', 'How the Setup Checklist works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Adding an organism, in order', 'text' => 'Four steps, each checked live against the server, with a button for the easy fixes and a link to the full page for the rest.'],
        ['label' => 'Files come first', 'text' => 'Organism data is copied onto the server (normally by the pipeline), not uploaded here. This page checks what arrived and finishes the setup.'],
    ]],
    ['heading' => 'The four steps', 'cards' => [
        ['label' => '1 · Copy files', 'text' => 'The directory layout an organism needs: organism.sqlite, organism.json, and per assembly the genome and gene set FASTA files.'],
        ['label' => '2 · File permissions', 'text' => 'Whether the web server can read the files and write where it must (BLAST and FAI indexes), with the command to fix it.'],
        ['label' => '3 · Status and metadata', 'text' => 'Assemblies and FASTA found, organism.json complete (genus, species, common name, taxon ID). Generate missing organism.json from NCBI, and build missing BLAST or FAI indexes here.'],
        ['label' => '4 · Home page', 'text' => 'Whether the organism appears in the home page selector, and the Refresh Cache that puts it there.'],
    ]],
]);
