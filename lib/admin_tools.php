<?php
/**
 * The admin tools -- ONE list, read by the Quick Links sidebar on every admin page
 * (admin/pages/_quick_links.php, drawn by includes/layout.php) and by the tool cards at the
 * foot of the dashboard. Add a tool here and it appears in both.
 *
 * 'label' is the sidebar's short name, 'title' the dashboard card's heading.
 *
 * Groups are in sidebar order: data first (the daily work), then site settings, then the
 * developer and maintenance pages.
 *
 * @return list<array{label: string, heading: string, icon: string, btn: string, tools: list<array{file: string, title: string, label: string, icon: string, desc: string, cta: string}>}>
 */
function moop_admin_tools(): array {
    return [
        ['label' => 'Data', 'heading' => 'Data Management', 'icon' => 'database', 'btn' => 'btn-success', 'tools' => [
            ['file' => 'organism_checklist.php', 'title' => 'New Organism Setup Checklist', 'label' => 'Setup Checklist', 'icon' => 'clipboard-list',
             'desc' => 'Step-by-step guide for adding a new organism, with links to each admin tool it needs.',
             'cta'  => 'Go to Setup Checklist'],
            ['file' => 'manage_organisms.php', 'title' => 'Manage Organisms', 'label' => 'Organisms', 'icon' => 'dna',
             'desc' => 'View current organisms, assemblies, and learn how to add new data.',
             'cta'  => 'Go to Manage Organisms'],
            ['file' => 'manage_groups.php', 'title' => 'Manage Groups', 'label' => 'Groups', 'icon' => 'layer-group',
             'desc' => 'Configure organism assembly groups and group descriptions.',
             'cta'  => 'Go to Manage Groups'],
            ['file' => 'manage_annotations.php', 'title' => 'Manage Annotation Sections', 'label' => 'Annotation Sections', 'icon' => 'tags',
             'desc' => 'Configure annotation section types and descriptions.',
             'cta'  => 'Go to Annotation Sections'],
            ['file' => 'manage_glossary.php', 'title' => 'Manage Glossary', 'label' => 'Glossary', 'icon' => 'book',
             'desc' => 'Define the dashed-underline help terms shown across the site.',
             'cta'  => 'Go to Manage Glossary'],
            ['file' => 'manage_jbrowse.php', 'title' => 'JBrowse Track Management', 'label' => 'JBrowse Tracks', 'icon' => 'stream',
             'desc' => 'Manage JBrowse tracks and configurations. Register Google Sheets, sync tracks, validate URLs, and monitor track access.',
             'cta'  => 'Go to JBrowse Management'],
            ['file' => 'manage_blast_linkouts.php', 'title' => 'BLAST Linkouts', 'label' => 'BLAST Linkouts', 'icon' => 'external-link-alt',
             'desc' => 'Configure which linkout buttons appear on BLAST results: gene page, JBrowse, and custom external URLs with placeholder support.',
             'cta'  => 'Manage Linkouts'],
        ]],
        ['label' => 'Site', 'heading' => 'System Configuration', 'icon' => 'cog', 'btn' => 'btn-primary', 'tools' => [
            ['file' => 'manage_site_config.php', 'title' => 'Manage Site Configuration', 'label' => 'Site Configuration', 'icon' => 'cog',
             'desc' => 'Edit site settings like title, branding, and admin contact information.',
             'cta'  => 'Go to Site Configuration'],
            ['file' => 'manage_users.php', 'title' => 'Manage Users', 'label' => 'Users', 'icon' => 'users',
             'desc' => 'Add new users to the system and manage user accounts.',
             'cta'  => 'Go to Manage Users'],
        ]],
        ['label' => 'Maintenance', 'heading' => 'Developer & Maintenance', 'icon' => 'wrench', 'btn' => 'btn-info', 'tools' => [
            ['file' => 'manage_filesystem_permissions.php', 'title' => 'Filesystem Permissions', 'label' => 'Filesystem Permissions', 'icon' => 'lock',
             'desc' => 'Complete guide to file and directory permissions. Check and fix permission issues.',
             'cta'  => 'Check Permissions'],
            ['file' => 'manage_error_log.php', 'title' => 'Error Logs', 'label' => 'Error Logs', 'icon' => 'exclamation-triangle',
             'desc' => 'View and manage application error logs for debugging and monitoring.',
             'cta'  => 'View Error Logs'],
            ['file' => 'manage_registry.php', 'title' => 'PHP Function Registry', 'label' => 'PHP Function Registry', 'icon' => 'code',
             'desc' => 'Manage and view all PHP functions. Update, search, and track usage.',
             'cta'  => 'Manage PHP Registry'],
            ['file' => 'manage_js_registry.php', 'title' => 'JavaScript Function Registry', 'label' => 'JS Function Registry', 'icon' => 'file-code',
             'desc' => 'Manage and view all JavaScript functions. Update, search, and track usage.',
             'cta'  => 'Manage JS Registry'],
        ]],
    ];
}

/**
 * "Needs attention" counts for the Quick Links, keyed by tool file ('admin.php' = the dashboard,
 * for checks that have no page of their own). Each: {n, tone: 'danger'|'warning', title}.
 *
 * Every figure is one the dashboard already shows, read the same way, so the sidebar can never
 * disagree with it:
 *   - Data Health: the card itself is rendered (off-screen) and counted, because that card
 *     counts its own severities from what it renders -- there is no second list to read.
 *     ~15 ms: computeDataHealthAlerts() reads the organism cache, opens no database.
 *   - permissions, JBrowse indexes, security updates, library advisories: the housekeeping
 *     results admin_init.php has already put in $_SESSION (at most an hour old, see §10).
 */
function moop_admin_tool_badges(): array {
    $badges = [];

    [$critical, $to_fix] = moop_admin_data_health_counts();
    if ($critical + $to_fix > 0) {
        $badges['manage_organisms.php'] = [
            'n'     => $critical ?: $to_fix,
            'tone'  => $critical ? 'danger' : 'warning',
            'title' => $critical ? "$critical critical data health issue" . ($critical === 1 ? '' : 's')
                                 : "$to_fix data health issue" . ($to_fix === 1 ? '' : 's') . ' to fix',
        ];
    }

    $perm = $_SESSION['perm_summary'] ?? [];
    if (!empty($perm['finding_count'])) {
        $n = (int)$perm['finding_count'];
        $badges['manage_filesystem_permissions.php'] = [
            'n' => $n, 'tone' => !empty($perm['high']) ? 'danger' : 'warning',
            'title' => "$n permission finding" . ($n === 1 ? '' : 's'),
        ];
    }

    $jb = $_SESSION['jbrowse_index_summary'] ?? [];
    if (!empty($jb['need'])) {
        $n = (int)$jb['need'];
        $badges['manage_jbrowse.php'] = [
            'n' => $n, 'tone' => !empty($jb['broken']) ? 'danger' : 'warning',
            'title' => "$n gene set" . ($n === 1 ? '' : 's') . ' whose JBrowse indexes need rebuilding',
        ];
    }

    // No page of their own: the dashboard card is where they are read.
    // The report itself, read live like the card: the saved housekeeping result can be an hour behind it
    $sec  = (function_exists('moop_read_security_report') ? moop_read_security_report() : null)
         ?? ($_SESSION['security_updates'] ?? []);
    $libs = $_SESSION['library_advisories']['packages'] ?? [];
    $vuln = count(array_filter($libs, fn($p) => !empty($p['vulns'])));
    $osn  = (int)($sec['packages'] ?? 0);
    if ($osn + $vuln > 0 || !empty($sec['error'])) {
        $parts = array_filter([
            $osn  ? "$osn OS security update" . ($osn === 1 ? '' : 's') : '',
            $vuln ? "$vuln librar" . ($vuln === 1 ? 'y' : 'ies') . ' with advisories' : '',
            !empty($sec['error']) ? 'security update check failed' : '',
        ]);
        $badges['admin.php'] = ['n' => ($osn + $vuln) ?: '!', 'tone' => 'warning', 'title' => implode('; ', $parts)];
    }
    return $badges;
}

/**
 * [critical, to fix] from the Data Health card, counted the way the card counts its own header.
 * The card is rendered in this function's scope so none of its variables leak into the page.
 */
function moop_admin_data_health_counts(): array {
    $config        = ConfigManager::getInstance();
    $organism_data = $config->getPath('organism_data');
    extract(computeDataHealthAlerts($organism_data));

    ['cache_stale' => $cache_stale, 'cache_changed_orgs' => $cache_changed_orgs, 'cache_stale_reasons' => $cache_stale_reasons] =
        moop_organism_cache_stale_summary(loadJsonFile(moop_organism_cache_file(), []));
    $health_card_collapsible = true;

    ob_start();
    include __DIR__ . '/../admin/pages/_data_health_card.php';
    $html = ob_get_clean();
    // The card's own rule ($_dh_counts there, which it unsets once drawn): one alert per issue,
    // its colour class is its severity.
    return [substr_count($html, 'class="alert alert-danger'), substr_count($html, 'class="alert alert-warning')];
}
