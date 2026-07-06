<?php
/**
 * Data Health Issues card — shared partial.
 *
 * Included by both the admin dashboard (admin/pages/admin.php) and the manage
 * organisms page (admin/pages/manage_organisms.php) so both show the SAME warnings.
 * Expects two variables in scope (extracted from the page's $data):
 *   $health_alerts            — ['ungrouped','not_in_tree','stale_groups','new_gene_sets','orphaned_gene_sets']
 *   $orphaned_gene_set_tuples — list of ['organism','assembly','gene_set']
 */
$health_alerts = ($health_alerts ?? []) + [
    'ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0, 'new_gene_sets' => 0, 'orphaned_gene_sets' => 0,
];
$orphaned_gene_set_tuples = $orphaned_gene_set_tuples ?? [];

$has_health_issues = ($health_alerts['ungrouped'] > 0 || $health_alerts['not_in_tree'] > 0
                    || $health_alerts['stale_groups'] > 0 || $health_alerts['new_gene_sets'] > 0
                    || $health_alerts['orphaned_gene_sets'] > 0);
if ($has_health_issues):
    $_after_ungrouped   = $health_alerts['new_gene_sets'] > 0 || $health_alerts['stale_groups'] > 0 || $health_alerts['orphaned_gene_sets'] > 0 || $health_alerts['not_in_tree'] > 0;
    $_after_new_gs      = $health_alerts['stale_groups'] > 0 || $health_alerts['orphaned_gene_sets'] > 0 || $health_alerts['not_in_tree'] > 0;
    $_after_stale       = $health_alerts['orphaned_gene_sets'] > 0 || $health_alerts['not_in_tree'] > 0;
    $_after_orphaned_gs = $health_alerts['not_in_tree'] > 0;
?>
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning bg-opacity-10">
    <h5 class="mb-0"><i class="fa fa-exclamation-triangle text-warning"></i> Data Health Issues</h5>
  </div>
  <div class="card-body p-0">
    <?php if ($health_alerts['ungrouped'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 <?= $_after_ungrouped ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-layer-group me-2"></i>
        <strong><?= $health_alerts['ungrouped'] ?> organism<?= $health_alerts['ungrouped'] > 1 ? 's' : '' ?></strong>
        not assigned to any group — invisible to users.
        Use "Add to Group" on the Manage Organisms page or fix in Manage Groups.
      </div>
      <div class="d-flex gap-2 flex-shrink-0">
        <a href="manage_organisms.php" class="btn btn-sm btn-warning">Manage Organisms</a>
        <a href="manage_groups.php" class="btn btn-sm btn-outline-warning">Manage Groups</a>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['new_gene_sets'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 <?= $_after_new_gs ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-dna me-2"></i>
        <strong><?= $health_alerts['new_gene_sets'] ?> gene set<?= $health_alerts['new_gene_sets'] > 1 ? 's' : '' ?></strong>
        on disk with no group assignment yet — invisible to every user, including you, until access is granted.
        This is checked per gene set, not just per assembly, so adding a gene set to an
        already-grouped assembly won't hide it here.
      </div>
      <a href="manage_groups.php#new-assemblies" class="btn btn-sm btn-warning flex-shrink-0">Grant Access</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['stale_groups'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 <?= $_after_stale ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-trash-alt me-2"></i>
        <strong><?= $health_alerts['stale_groups'] ?> stale group entr<?= $health_alerts['stale_groups'] > 1 ? 'ies' : 'y' ?></strong>
        reference organisms or assemblies no longer on disk.
      </div>
      <a href="manage_groups.php" class="btn btn-sm btn-warning flex-shrink-0">Clean Up in Groups</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['orphaned_gene_sets'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 <?= $_after_orphaned_gs ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-unlink me-2"></i>
        <strong><?= $health_alerts['orphaned_gene_sets'] ?> gene set<?= $health_alerts['orphaned_gene_sets'] > 1 ? 's' : '' ?></strong>
        still <?= $health_alerts['orphaned_gene_sets'] > 1 ? 'have files' : 'has files' ?> on disk but no longer exist in that organism's database —
        likely removed in a database rebuild without cleaning up here. Still counted toward
        BLAST indexes, JBrowse tracks, and group access even though queries can no longer find them.
        <?php foreach ($orphaned_gene_set_tuples as $t): ?>
          <br><small class="text-muted"><?= htmlspecialchars($t['organism']) ?> / <?= htmlspecialchars($t['assembly']) ?> / <?= htmlspecialchars($t['gene_set']) ?></small>
        <?php endforeach; ?>
      </div>
      <a href="manage_groups.php#db-orphaned" class="btn btn-sm btn-danger flex-shrink-0">Review &amp; Archive</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['not_in_tree'] > 0): ?>
    <div class="alert alert-info mb-0 border-0 rounded-0 d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-sitemap me-2"></i>
        <strong><?= $health_alerts['not_in_tree'] ?> organism<?= $health_alerts['not_in_tree'] > 1 ? 's' : '' ?></strong>
        not in the taxonomy tree — won't appear in the homepage organism selector.
        Check that <code>taxon_id</code> is set in <code>organism.json</code> and run Refresh Cache.
      </div>
      <a href="manage_organisms.php" class="btn btn-sm btn-info flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
