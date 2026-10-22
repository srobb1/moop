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
    // Only what has no page of its own to live on: where to go, the concepts that span
    // pages, and the site-wide facts. Help about a control lives beside that control.
    [
        'id'          => 'finding-the-right-page',
        'title'       => 'What do you want to do?',
        'description' => 'Common tasks and the page for each, plus every page in one sentence — start '
                       . 'here if you know what you want to do but not where to do it.',
        'icon'        => 'fa-map-signs',
        'category'    => 'general',
    ],
    [
        'id'          => 'data-organization',
        'title'       => 'How the data is organized',
        'description' => 'Organism, assembly, gene set, gene, transcript, protein — what each one is '
                       . 'and how they fit together.',
        'icon'        => 'fa-sitemap',
        'category'    => 'general',
    ],
    [
        'id'          => 'data-sources',
        'title'       => 'Where the data comes from',
        'description' => 'Where the gene sets and annotations come from, and what to cite when you use them.',
        'icon'        => 'fa-database',
        'category'    => 'general',
    ],
    [
        'id'          => 'access',
        'title'       => 'Who can see what',
        'description' => 'Why an organism may be missing for you, and how to get access to more.',
        'icon'        => 'fa-lock',
        'category'    => 'general',
    ],
    [
        'id'          => 'glossary',
        'title'       => 'Glossary',
        'description' => 'Every term the site defines, in one list — the same definitions you see when '
                       . 'you point at a dashed-underlined word.',
        'icon'        => 'fa-book-open',
        'category'    => 'general',
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
