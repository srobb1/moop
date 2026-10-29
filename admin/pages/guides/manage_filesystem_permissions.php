<?php
/**
 * Guide for Filesystem Permissions (admin/pages/manage_filesystem_permissions.php). If the
 * page changes, change this. The rules are lib/permission_check.php and CLAUDE.md §11.
 */
echo help_modal('permissions-guide', 'How Filesystem Permissions works', [
    ['heading' => 'What this page is for', 'cards' => [
        ['label' => 'Exactly the access needed', 'text' => 'Whether the web server can read and write the files it must — and nothing more — and the command that fixes each problem.'],
        ['label' => 'Silent failures', 'text' => 'A writable directory set wrong does not show an error: uploads, saves and index builds just stop working. This page is where that shows up.'],
        ['label' => 'Fixing', 'text' => 'Copy one path\'s command, or a whole section\'s in one paste, and run it on the server. Then reload the page — it checks live.'],
    ]],
    ['heading' => 'How the checks judge', 'cards' => [
        ['label' => 'By impact, not exact mode', 'text' => '640 and 660 pass; they are not wrong for not being 644. World-writable never passes, and data files should never be executable.'],
        ['label' => 'Counted by problem', 'text' => 'Findings are grouped by category, so a count means how many things are wrong, not how many files exist.'],
        ['label' => 'SELinux decides', 'text' => 'Under SELinux the label, not the mode, is the real gate. A directory can look writable at 2775 and still be blocked — chmod will not fix that; the page gives the restorecon/semanage command.'],
        ['label' => 'The s in drwxrwsr-x', 'text' => 'SGID: new files inherit the directory\'s group, so writable areas stay writable as content is added.'],
    ]],
]);
