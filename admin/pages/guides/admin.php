<?php
/**
 * Guide for the Admin Dashboard (admin/pages/admin.php). If the page changes, change this.
 * No list of the tools on purpose: Quick Links and the tool cards ARE that list
 * (lib/admin_tools.php); a copy here drifted before (it lacked Glossary, JBrowse and BLAST).
 */
echo help_modal('admin-guide', 'How the Admin Dashboard works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Two questions', 'html' => true, 'text' => '<strong>Is the site healthy?</strong> (System Status) and <strong>where do I go to change something?</strong> (Quick Links and the tool cards).'],
        ['label' => 'Quick Links', 'html' => true, 'text' => 'On every admin page, at the left. A number beside a link means something there needs attention — <span class="badge rounded-pill bg-danger">red</span> critical, <span class="badge rounded-pill bg-warning">amber</span> to fix. On Dashboard it counts OS security updates and library advisories.'],
        ['label' => 'System Status', 'text' => 'One card per check, grouped Data, Security & software, Server and Maintenance. The header states the result; click it for the detail and what to do.'],
        ['label' => 'Up to an hour old', 'html' => true, 'text' => 'Most figures come from housekeeping, which runs in the background at most once an hour. <strong>Run housekeeping now</strong> (Housekeeping card) refreshes them; the page a card links to always re-checks live.'],
    ]],
    ['heading' => 'Common jobs', 'cards' => [
        ['label' => 'Add an organism', 'text' => 'Setup Checklist walks through every step, from copying files to seeing it on the home page.'],
        ['label' => 'Make data public', 'html' => true, 'text' => 'Groups → the <strong>Visibility</strong> toggle on each gene set. To give one person access instead, use Users.'],
        ['label' => 'An organism\'s About text or image', 'html' => true, 'text' => 'Organisms → the <i class="fa fa-file-alt"></i> icon in its row. Text written there replaces the Wikipedia text; leave it empty to keep Wikipedia. Same for the image.'],
        ['label' => 'Something broke', 'text' => 'Error Logs lists crashes and problems MOOP noticed; System Monitor shows slow and failed pages.'],
    ]],
]);
