<?php
/**
 * Guide for Manage Users (admin/pages/manage_users.php). If the page changes, change this.
 */
echo help_modal('users-guide', 'How Manage Users works', [
    ['heading' => 'Who needs an account', 'cards' => [
        ['label' => 'Nobody, for public data', 'text' => 'A public gene set needs no account at all. Make data public with the Visibility toggle in Manage Groups.'],
        ['label' => 'Collaborators', 'text' => 'A user account with access to the restricted assemblies you tick. They see those, plus everything public.'],
        ['label' => 'Admins', 'html' => true, 'text' => 'Tick <strong>Admin User</strong>: every organism, plus the admin pages. No assemblies need ticking.'],
        ['label' => 'On a trusted network', 'text' => 'Anyone in the auto-login IP ranges (Site Configuration) gets full data access without an account — but not the admin pages.'],
    ]],
    ['heading' => 'Creating and editing', 'cards' => [
        ['label' => 'Create New User', 'text' => 'Username and password are required; a non-admin needs at least one assembly. Copy from user starts from another account\'s assemblies.'],
        ['label' => 'Editing', 'text' => 'Changes take effect when saved. The username cannot change. Leave both password fields blank to keep the current password.'],
        ['label' => 'Stale assemblies', 'text' => 'Access to assemblies that no longer exist on disk. Remove them per user, from every user at once, or all of them in one press.'],
        ['label' => 'Where accounts live', 'text' => 'In the users file outside the website\'s folders, with passwords stored hashed. If the page says it cannot save, that file is not writable by the web server.'],
    ]],
]);
