<?php
/**
 * HELP TOPIC REGISTRY — single source of truth
 *
 * Returns the list of help topics. `category` is the audience split that the
 * landing page renders:
 *   'general'   — for people USING MOOP (biologists)
 *   'technical' — for people SETTING UP and MAINTAINING it (admins)
 *
 * Extracted from dashboard.php on 2026-07-22 so the landing page and anything
 * else needing this list read ONE copy. The old dashboard.php still carries its
 * own inline copy; it is no longer linked from anywhere and is slated for
 * removal, so it was left untouched rather than edited twice.
 *
 * Each entry: id (matches tools/pages/help/<id>.php), title, description, icon.
 */

return [
    // ── For users ────────────────────────────────────────────────────────────
    [
        'id' => 'getting-started',
        'title' => 'Getting Started',
        'description' => 'Learn the basics of MOOP and how to navigate the platform.',
        'icon' => 'fa-rocket',
        'category' => 'general',
    ],
    [
        'id' => 'organism-selection',
        'title' => 'Selecting Organisms',
        'description' => 'Choose organisms by group or use the interactive taxonomy tree for custom selections.',
        'icon' => 'fa-dna',
        'category' => 'general',
    ],
    [
        'id' => 'search-and-filter',
        'title' => 'Search &amp; Filter',
        'description' => 'Use advanced search and filtering to find specific sequences and annotations.',
        'icon' => 'fa-search',
        'category' => 'general',
    ],
    [
        'id' => 'blast-tutorial',
        'title' => 'BLAST Search',
        'description' => 'Compare a DNA or protein sequence against genome assemblies to find homologs and conserved regions.',
        'icon' => 'fa-exchange-alt',
        'category' => 'general',
    ],
    [
        'id' => 'moopmart',
        'title' => 'MOOPmart — Gene List Builder',
        'description' => 'Build custom gene lists by ID, name, annotation, or location and export as TSV or FASTA.',
        'icon' => 'fa-shopping-cart',
        'category' => 'general',
    ],
    [
        'id' => 'sequence-retrieval',
        'title' => 'Sequence Retrieval',
        'description' => 'Look up sequences by feature ID and download genomic, transcript, CDS, and protein sequences.',
        'icon' => 'fa-dna',
        'category' => 'general',
    ],
    [
        'id' => 'multi-organism-analysis',
        'title' => 'Multi-Organism Analysis',
        'description' => 'Compare and analyze data across multiple organisms simultaneously.',
        'icon' => 'fa-project-diagram',
        'category' => 'general',
    ],
    [
        'id' => 'data-export',
        'title' => 'Exporting Data',
        'description' => 'Download sequences and data in various formats for external analysis.',
        'icon' => 'fa-download',
        'category' => 'general',
    ],

    // ── For administrators: setup and maintenance ────────────────────────────
    [
        'id' => 'system-requirements',
        'title' => 'System Requirements &amp; Planning',
        'description' => 'Hardware sizing, performance benchmarks, resource planning, and cost estimation based on organism scale.',
        'icon' => 'fa-server',
        'category' => 'technical',
    ],
    [
        'id' => 'organism-setup-and-searches',
        'title' => 'Setup &amp; Searches',
        'description' => 'Setting up new organisms, configuring metadata, and how search mechanics and the parent page work.',
        'icon' => 'fa-cogs',
        'category' => 'technical',
    ],
    [
        'id' => 'organism-data-organization',
        'title' => 'Data Organization',
        'description' => 'Database schema, file organization, and the structure of organism data.',
        'icon' => 'fa-database',
        'category' => 'technical',
    ],
    [
        'id' => 'generating-annotations-and-databases',
        'title' => 'Generating Annotations &amp; Databases',
        'description' => 'Generating functional annotations and creating or loading organism.sqlite databases.',
        'icon' => 'fa-flask',
        'category' => 'technical',
    ],
    [
        'id' => 'permission-management',
        'title' => 'Permission Management &amp; Alerts',
        'description' => 'Managing file permissions, fixing permission issues, and why permissions are critical to MOOP.',
        'icon' => 'fa-lock',
        'category' => 'technical',
    ],
    [
        'id' => 'site-data-backup',
        'title' => 'Site Data Backup',
        'description' => 'How automatic site-data backup works, what it includes, where it goes, and enabling git version history.',
        'icon' => 'fa-save',
        'category' => 'technical',
    ],
    [
        'id' => 'function-registry-management',
        'title' => 'Function Registry Management',
        'description' => 'The function registry system, how registries are generated, and how to use them.',
        'icon' => 'fa-list',
        'category' => 'technical',
    ],
];
