<?php
/**
 * Guide for Manage Groups (admin/pages/manage_groups.php). If the page changes, change this.
 */
echo help_modal('groups-guide', 'How Manage Groups works', [
    ['heading' => 'Two separate questions', 'cards' => [
        ['label' => 'Is this data open to everyone?', 'html' => true, 'text' => 'The <strong>Visibility</strong> toggle on each gene set, here. <strong>Public</strong>: anyone, logged out or not. <strong>Restricted</strong>: only the people below.'],
        ['label' => 'Who else may see restricted data?', 'html' => true, 'text' => 'Set with the person, not the data: <strong>collaborators</strong> in Manage Users, <strong>trusted networks</strong> (auto-login IP ranges) in Site Configuration. Admins see everything.'],
        ['label' => 'Groups are labels, not locks', 'text' => 'A group (Bats, Planaria…) is for browsing and searching. Making a gene set public does not move it into a "Public" group; it keeps its groups and flips to Public.'],
    ]],
    ['heading' => 'What you can do here', 'cards' => [
        ['label' => 'Assign groups', 'text' => 'Give each gene set any number of groups, new or existing. Saved to the group assignments file listed on the page.'],
        ['label' => 'Suggestions', 'text' => 'A chip suggests a group the organism\'s taxonomy implies. It opens the editor already ticked but not saved; × dismisses the suggestion for good.'],
        ['label' => 'Group descriptions', 'html' => true, 'text' => 'Enter a Wikipedia article title and press <strong>Retrieve &amp; Save</strong> to fetch the group\'s description and image, then edit them if you like.'],
        ['label' => 'Stale entries', 'text' => 'Assignments for gene sets that no longer exist on disk. Listed separately so you can remove them.'],
    ]],
]);
