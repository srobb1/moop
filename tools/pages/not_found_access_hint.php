<?php
/**
 * "Expected something here?" -- shown under every not-found message (tools/pages/not_found.php,
 * tools/pages/gene_not_found.php) to a viewer who does not already see everything.
 *
 * Not-found pages answer the SAME way for a thing that does not exist and for one this viewer
 * may not open, so a hand-built link cannot be used to learn what restricted data exists. This
 * says how to see more without saying whether there is more.
 *
 * Needs: $site. Reads the viewer's level itself.
 */
$__level = normalize_access_level(get_access_level());
if ($__level === 'ADMIN' || $__level === 'IP_IN_RANGE') return;   // they already see everything

$__config      = ConfigManager::getInstance();
$__admin_email = $__config->getString('admin_email', '');
$__has_ip      = !empty($__config->getArray('auto_login_ip_ranges'));
?>
<p class="text-muted small mb-4">
    <?php if ($__level === 'COLLABORATOR'): ?>
        Expected to find something here? Not all data is public, and your account may not include
        it<?= $__admin_email !== '' ? ' &mdash; ask <a href="mailto:' . htmlspecialchars($__admin_email) . '">' . htmlspecialchars($__admin_email) . '</a> for access' : ' &mdash; ask a site administrator for access' ?>.
    <?php else: ?>
        Expected to find something here? Not all data is public.
        <a href="/<?= htmlspecialchars($site) ?>/login.php">Log in</a><?= $__has_ip ? ', or connect from the institute network,' : '' ?>
        to see more.
    <?php endif; ?>
</p>
