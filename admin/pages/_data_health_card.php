<?php
/**
 * Data Health Issues card — shared partial.
 *
 * Included by both the admin dashboard (admin/pages/admin.php) and the manage
 * organisms page (admin/pages/manage_organisms.php) so both show the SAME warnings.
 * Expects these variables in scope (extracted from the page's $data):
 *   $health_alerts            — ['ungrouped','not_in_tree','stale_groups','new_gene_sets','orphaned_gene_sets','orphaned_assemblies','orphaned_jbrowse','no_database']
 *   $orphaned_gene_set_tuples — list of ['organism','assembly','gene_set']
 *   $orphaned_assembly_tuples — list of ['organism','assembly']
 *   $orphaned_jbrowse_registrations — list of ['organism','assembly','reason','detail']
 *   $no_database_organisms    — list of organism-name strings
 */
$health_alerts = ($health_alerts ?? []) + [
    'ungrouped' => 0, 'not_in_tree' => 0, 'stale_groups' => 0, 'new_gene_sets' => 0,
    'orphaned_gene_sets' => 0, 'orphaned_assemblies' => 0, 'orphaned_jbrowse' => 0,
    'no_database' => 0,
];
$orphaned_gene_set_tuples = $orphaned_gene_set_tuples ?? [];
$orphaned_assembly_tuples = $orphaned_assembly_tuples ?? [];
$orphaned_jbrowse_registrations = $orphaned_jbrowse_registrations ?? [];
$orphaned_jbrowse_systemic      = $orphaned_jbrowse_systemic ?? false;
$no_database_organisms    = $no_database_organisms ?? [];
$cache_stale        = $cache_stale ?? false;
$cache_changed_orgs = $cache_changed_orgs ?? [];

// Presence of each alert, in the order they render top-to-bottom. Each alert draws a
// bottom border when any alert after it is present, so the stack reads as one panel.
$_p_ung   = $health_alerts['ungrouped'] > 0;
$_p_ngs   = $health_alerts['new_gene_sets'] > 0;
$_p_sg    = $health_alerts['stale_groups'] > 0;
$_p_ogs   = $health_alerts['orphaned_gene_sets'] > 0;
$_p_oa    = $health_alerts['orphaned_assemblies'] > 0;
$_p_ojb   = $health_alerts['orphaned_jbrowse'] > 0;
$_p_nodb  = $health_alerts['no_database'] > 0;
$_p_nit   = $health_alerts['not_in_tree'] > 0;

$_any_data_issue = ($_p_ung || $_p_ngs || $_p_sg || $_p_ogs || $_p_oa || $_p_ojb || $_p_nodb || $_p_nit);
$has_health_issues = $cache_stale || $_any_data_issue;
if ($has_health_issues):
    $_after_ungrouped    = $_p_ngs || $_p_sg || $_p_ogs || $_p_oa || $_p_ojb || $_p_nodb || $_p_nit;
    $_after_new_gs       = $_p_sg || $_p_ogs || $_p_oa || $_p_ojb || $_p_nodb || $_p_nit;
    $_after_stale        = $_p_ogs || $_p_oa || $_p_ojb || $_p_nodb || $_p_nit;
    $_after_orphaned_gs  = $_p_oa || $_p_ojb || $_p_nodb || $_p_nit;
    $_after_orphaned_asm = $_p_ojb || $_p_nodb || $_p_nit;
    $_after_orphaned_jb  = $_p_nodb || $_p_nit;
    $_after_no_db        = $_p_nit;
?>
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning bg-opacity-10">
    <h5 class="mb-0"><i class="fa fa-exclamation-triangle text-warning"></i> Data Health Issues</h5>
  </div>
  <div class="card-body p-0">
    <?php if ($cache_stale):
      $_n = count($cache_changed_orgs);
      $_preview = '';
      if ($_n > 0) {
          $_preview = htmlspecialchars(implode(', ', array_slice($cache_changed_orgs, 0, 4)));
          if ($_n > 4) $_preview .= ' +' . ($_n - 4) . ' more';
      }
    ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 <?= $_any_data_issue ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-sync-alt me-2"></i>
        <strong>Organism cache out of date</strong> —
        <?php if ($_n > 0): ?>
          <?= $_n ?> organism<?= $_n === 1 ? '' : 's' ?> changed since it was built (<?= $_preview ?>).
        <?php else: ?>
          groups or taxonomy config changed since it was built.
        <?php endif; ?>
        Re-cache recommended — the orphan/drift checks below read the cache, so they can be out of date until you refresh.
      </div>
      <button class="btn btn-sm btn-warning flex-shrink-0"
              onclick="var f=window.startOrganismCacheRefresh||window.rescanOrganisms; if(f) f(this);">
        <i class="fa fa-sync-alt"></i> Update Cache
      </button>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['ungrouped'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 <?= $_after_ungrouped ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-layer-group me-2"></i>
        <strong><?= $health_alerts['ungrouped'] ?> organism<?= $health_alerts['ungrouped'] > 1 ? 's' : '' ?></strong>
        not assigned to any group — invisible to users. Add to a group in Manage Groups.
      </div>
      <a href="manage_groups.php#new-assemblies" class="btn btn-sm btn-warning flex-shrink-0">Manage Groups</a>
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
    <?php if ($health_alerts['orphaned_assemblies'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 <?= $_after_orphaned_asm ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-folder-minus me-2"></i>
        <strong><?= $health_alerts['orphaned_assemblies'] ?> assembly director<?= $health_alerts['orphaned_assemblies'] > 1 ? 'ies' : 'y' ?></strong>
        on disk with a <code>genome.json</code> but no matching genome row in the database —
        likely a stale leftover from a rename or reload. Invisible to the database, but still
        scanned for BLAST indexes and FASTA files. Remove the directory on disk, or reload the
        assembly if it should exist.
        <?php foreach ($orphaned_assembly_tuples as $t): ?>
          <br><small class="text-muted"><?= htmlspecialchars($t['organism']) ?> / <?= htmlspecialchars($t['assembly']) ?></small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php" class="btn btn-sm btn-danger flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['orphaned_jbrowse'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 <?= $_after_orphaned_jb ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <?php if ($orphaned_jbrowse_systemic): ?>
      <div>
        <i class="fa fa-plug-circle-exclamation me-2"></i>
        <strong>Organism data appears to be unavailable.</strong>
        All <?= $health_alerts['orphaned_jbrowse'] ?> JBrowse registrations report missing source
        data at once, which points at the <code>organisms/</code> directory itself rather than at
        <?= $health_alerts['orphaned_jbrowse'] ?> separate assemblies — typically an unmounted share
        or a wrong <code>organism_data</code> path. Check that the data directory is mounted and
        readable <strong>before</strong> unregistering anything: the registrations are probably fine,
        and removing them would mean rebuilding all of them once the data is back.
      </div>
      <a href="manage_filesystem_permissions.php" class="btn btn-sm btn-danger flex-shrink-0">Check Filesystem</a>
      <?php else: ?>
      <div>
        <i class="fa fa-unlink me-2"></i>
        <strong><?= $health_alerts['orphaned_jbrowse'] ?> JBrowse registration<?= $health_alerts['orphaned_jbrowse'] > 1 ? 's' : '' ?></strong>
        point<?= $health_alerts['orphaned_jbrowse'] > 1 ? '' : 's' ?> at source data that no longer
        exists. The genome browser still lists <?= $health_alerts['orphaned_jbrowse'] > 1 ? 'these assemblies' : 'this assembly' ?>,
        but the reference sequence returns <strong>404</strong> for every user who opens
        <?= $health_alerts['orphaned_jbrowse'] > 1 ? 'them' : 'it' ?>. MOOP builds these links itself
        during registration, so a rename or delete of the source data leaves them behind silently.
        <?php foreach ($orphaned_jbrowse_registrations as $r): ?>
          <br><small class="text-muted">
            <?= htmlspecialchars($r['organism']) ?> / <?= htmlspecialchars($r['assembly']) ?>
            — <?= htmlspecialchars($r['detail']) ?>
          </small>
        <?php endforeach; ?>
      </div>
      <a href="manage_jbrowse.php#orphaned-registrations" class="btn btn-sm btn-danger flex-shrink-0">Review &amp; Unregister</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['no_database'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 <?= $_after_no_db ? 'border-bottom' : '' ?> d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-database me-2"></i>
        <strong><?= $health_alerts['no_database'] ?> organism<?= $health_alerts['no_database'] > 1 ? 's' : '' ?></strong>
        <?= $health_alerts['no_database'] > 1 ? 'have' : 'has' ?> assembly data on disk but no
        <code>organism.sqlite</code> — never loaded, or the database was removed. Completely
        invisible to the site. Load the organism's database, or remove the directory if it's abandoned.
        <?php foreach ($no_database_organisms as $org_name): ?>
          <br><small class="text-muted"><?= htmlspecialchars($org_name) ?></small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php" class="btn btn-sm btn-danger flex-shrink-0">View Organisms</a>
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
