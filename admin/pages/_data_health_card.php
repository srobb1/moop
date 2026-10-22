<?php
/**
 * Data Health Issues card — shared partial.
 *
 * Included by both the admin dashboard (admin/pages/admin.php) and the manage
 * organisms page (admin/pages/manage_organisms.php) so both show the SAME warnings.
 * Expects computeDataHealthAlerts()'s result extracted into scope -- the page passes it whole
 * ($data = [...] + $_health). Its keys and their shapes are moop_data_health_empty(), which
 * also supplies the defaults here, so there is no list of them to keep in step.
 * Also $cache_stale / $cache_changed_orgs from the page.
 */
$_health_empty = moop_data_health_empty();
$health_alerts = ($health_alerts ?? []) + $_health_empty['health_alerts'];
foreach ($_health_empty as $_k => $_v) {
    if (!isset($$_k)) $$_k = $_v;
}
unset($_health_empty, $_k, $_v);
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
$_p_edb   = $health_alerts['empty_database'] > 0;
$_p_nit   = $health_alerts['not_in_tree'] > 0;
$_p_tax   = $health_alerts['taxonomy_suggestions'] > 0;
$_p_gsf   = $health_alerts['gene_set_files'] > 0;
$_p_seq   = $health_alerts['seqname_mismatch'] > 0;

$_any_data_issue = (bool)array_filter($health_alerts);   // any alert with a count -- every key above has its alert below
$has_health_issues = $cache_stale || $_any_data_issue;
if ($has_health_issues):
?>
<div class="card mb-4 border-warning">
  <div class="card-header adm-head-warn">
    <h5 class="mb-0"><i class="fa fa-exclamation-triangle text-warning"></i> Data Health Issues</h5>
  </div>
  <?php // A rule between stacked alerts, none under the last: css/admin-cards.css .dh-stack.
        // It was nine hand-kept $_after_* flags, each listing every alert below it, so
        // adding one alert meant editing all nine. ?>
  <div class="card-body p-0 dh-stack">
    <?php // ORDER AND COLOR = SEVERITY, the rule Manage Organisms uses
          // (moop_organism_issue_defs()): red first -- the site cannot serve the data --
          // then amber -- something is missing or unconfigured -- then gray suggestions.
          // These alerts were red, amber and blue with no rule behind it, and a red
          // database failure sat below amber housekeeping. JBrowse is red only when EVERY
          // registration fails at once (the data share itself is gone). ?>
    <?php if ($health_alerts['no_database'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
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
    <?php if ($health_alerts['empty_database'] > 0): ?>
    <div class="alert alert-danger mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-database me-2"></i>
        <strong><?= $health_alerts['empty_database'] ?> organism<?= $health_alerts['empty_database'] > 1 ? 's' : '' ?></strong>
        <?= $health_alerts['empty_database'] > 1 ? 'have' : 'has' ?> a database that loaded but holds
        nothing usable. The organism looks completely normal on every other check — it is listed,
        grouped and searchable — but returns <strong>no results</strong>, with no error shown to the user.
        Reload the organism's database.
        <?php foreach ($empty_database_organisms as $_edb): ?>
          <br><small class="text-muted">
            <strong><?= htmlspecialchars($_edb['organism']) ?></strong>
            — <?= htmlspecialchars(implode('; ', $_edb['issues'])) ?>
          </small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php?filter=db-content" class="btn btn-sm btn-danger flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['orphaned_jbrowse'] > 0): ?>
    <div class="alert alert-<?= $orphaned_jbrowse_systemic ? 'danger' : 'warning' ?> mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <?php if ($orphaned_jbrowse_systemic): ?>
      <div>
        <i class="fa fa-plug me-2"></i>
        <strong>Organism data appears to be unavailable.</strong>
        All <?= $health_alerts['orphaned_jbrowse'] ?> JBrowse registrations report missing source
        data at once, which points at the <code>organisms/</code> directory itself rather than at
        <?= $health_alerts['orphaned_jbrowse'] ?> separate assemblies — typically an unmounted share
        or a wrong <code>organism_data</code> path. Check that the data directory is mounted and
        readable <strong>before</strong> unregistering anything: the registrations are probably fine,
        and removing them would mean rebuilding all of them once the data is back.
      </div>
      <a href="manage_filesystem_permissions.php" class="btn btn-sm btn-<?= $orphaned_jbrowse_systemic ? 'danger' : 'warning' ?> flex-shrink-0">Check Filesystem</a>
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
      <a href="manage_jbrowse.php#orphaned-registrations" class="btn btn-sm btn-<?= $orphaned_jbrowse_systemic ? 'danger' : 'warning' ?> flex-shrink-0">Review &amp; Unregister</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($cache_stale):
      $_n = count($cache_changed_orgs);
      $_preview = '';
      if ($_n > 0) {
          $_preview = htmlspecialchars(implode(', ', array_slice($cache_changed_orgs, 0, 4)));
          if ($_n > 4) $_preview .= ' +' . ($_n - 4) . ' more';
      }
    ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
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
    <?php if ($_p_gsf): ?>
    <?php // Amber: Incomplete, not Critical -- the gene set still serves what it has
          // (moop_organism_issue_defs()['gene-set-files']). ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-stream me-2"></i>
        <strong><?= $health_alerts['gene_set_files'] ?> gene set<?= $health_alerts['gene_set_files'] > 1 ? 's are' : ' is' ?></strong>
        missing a FASTA file. A missing type cannot be BLASTed or downloaded for that gene set.
        <?php foreach ($gene_set_file_gaps as $g): ?>
          <br><small class="text-muted"><?= htmlspecialchars(str_replace('/', ' / ', $g['path'])) ?> — no <?= htmlspecialchars(implode(', ', $g['missing'])) ?></small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php?filter=gene-set-files" class="btn btn-sm btn-warning flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($_p_seq): ?>
    <?php // Amber: search and BLAST still work; everything placed by coordinates does not
          // (moop_organism_issue_defs()['seqnames']). ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-random me-2"></i>
        <strong><?= $health_alerts['seqname_mismatch'] ?> gene set<?= $health_alerts['seqname_mismatch'] > 1 ? 's name' : ' names' ?></strong>
        chromosomes differently from <?= $health_alerts['seqname_mismatch'] > 1 ? 'their genomes' : 'its genome' ?>. Genomic
        sequence, exon export and the genome browser find nothing for those genes; search and BLAST still work.
        Fix the data: a genome and annotation that use the same names, or a name-translation table.
        <?php foreach ($gene_set_seqname_gaps as $g): ?>
          <br><small class="text-muted"><?= htmlspecialchars(str_replace('/', ' / ', $g['path'])) ?> —
            <?= number_format($g['missing']) ?> of <?= number_format($g['total']) ?> names not in the genome
            (e.g. <?= htmlspecialchars(implode(', ', $g['examples'])) ?>)</small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php?filter=seqnames" class="btn btn-sm btn-warning flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['orphaned_assemblies'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-folder-minus me-2"></i>
        <strong><?= $health_alerts['orphaned_assemblies'] ?> assembly director<?= $health_alerts['orphaned_assemblies'] > 1 ? 'ies' : 'y' ?></strong>
        on disk with no matching genome row in the database — a leftover from a rename or
        reload, or data not loaded yet. Invisible to the database, but still scanned for BLAST
        indexes and FASTA files. Remove the directory on disk, or load the assembly if it should
        exist.
        <?php foreach ($orphaned_assembly_tuples as $t): ?>
          <br><small class="text-muted"><?= htmlspecialchars($t['organism']) ?> / <?= htmlspecialchars($t['assembly']) ?></small>
        <?php endforeach; ?>
      </div>
      <a href="manage_organisms.php?filter=dir-mismatch" class="btn btn-sm btn-warning flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['orphaned_gene_sets'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
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
      <a href="manage_groups.php#db-orphaned" class="btn btn-sm btn-warning flex-shrink-0">Review &amp; Archive</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['ungrouped'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-layer-group me-2"></i>
        <strong><?= $health_alerts['ungrouped'] ?> organism<?= $health_alerts['ungrouped'] > 1 ? 's' : '' ?></strong>
        not assigned to any group — invisible to users. Add to a group in Manage Groups.
      </div>
      <a href="manage_groups.php#new-assemblies" class="btn btn-sm btn-warning flex-shrink-0">Manage Groups</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['new_gene_sets'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
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
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-trash-alt me-2"></i>
        <strong><?= $health_alerts['stale_groups'] ?> stale group entr<?= $health_alerts['stale_groups'] > 1 ? 'ies' : 'y' ?></strong>
        reference organisms or assemblies no longer on disk.
      </div>
      <a href="manage_groups.php" class="btn btn-sm btn-warning flex-shrink-0">Clean Up in Groups</a>
    </div>
    <?php endif; ?>
    <?php if ($health_alerts['not_in_tree'] > 0): ?>
    <div class="alert alert-warning mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-sitemap me-2"></i>
        <strong><?= $health_alerts['not_in_tree'] ?> organism<?= $health_alerts['not_in_tree'] > 1 ? 's' : '' ?></strong>
        not in the taxonomy tree — won't appear in the homepage organism selector.
        Check that <code>taxon_id</code> is set in <code>organism.json</code> and run Refresh Cache.
      </div>
      <a href="manage_organisms.php" class="btn btn-sm btn-warning flex-shrink-0">View Organisms</a>
    </div>
    <?php endif; ?>
    <?php if ($_p_tax):
      // Group the suggestions by the group they point at, so 49 untagged bats read as
      // "Bats: 49" rather than 49 separate lines.
      $_by_group = [];
      foreach ($taxonomy_suggestions as $_s) { $_by_group[$_s['group']][] = $_s; }
      ksort($_by_group);
      $_n_groups = count($_by_group);
    ?>
    <div class="alert alert-secondary mb-0 border-0 rounded-0 border-bottom d-flex align-items-center justify-content-between gap-3">
      <div>
        <i class="fa fa-lightbulb me-2"></i>
        <strong><?= $health_alerts['taxonomy_suggestions'] ?> gene set<?= $health_alerts['taxonomy_suggestions'] > 1 ? 's' : '' ?></strong>
        sit<?= $health_alerts['taxonomy_suggestions'] > 1 ? '' : 's' ?> under a taxonomy rank that matches
        <?= $_n_groups > 1 ? 'curated groups they are' : 'a curated group it is' ?> not in — usually an
        oversight when the organism was added. <strong>Suggestions only:</strong> nothing changes until you accept one,
        and you can mark any of them as deliberate.
        <?php foreach ($_by_group as $_g => $_items):
          $_names = array_values(array_unique(array_map(function ($x) { return $x['organism']; }, $_items)));
          $_shown = array_slice($_names, 0, 4);
        ?>
          <br><small class="text-muted">
            <strong><?= htmlspecialchars($_g) ?></strong><?php
              // For a name-basis group the rank IS the group name; printing it again
              // reads as "Cnidaria (Cnidaria)".
              if ($_items[0]['rank'] !== $_g): ?> (<?= htmlspecialchars($_items[0]['rank']) ?>)<?php endif; ?>
            — <?= htmlspecialchars(implode(', ', $_shown)) ?><?= count($_names) > 4 ? ' +' . (count($_names) - 4) . ' more' : '' ?>
          </small>
        <?php endforeach; ?>
      </div>
      <a href="manage_groups.php#taxonomy-suggestions" class="btn btn-sm btn-secondary flex-shrink-0">Review Suggestions</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
