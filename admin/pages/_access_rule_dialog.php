<?php
/**
 * "Who can see this" -- the ONE editor for an access rule (lib/access_rules.php), included once on
 * any admin page that edits rules (Manage Pages, the Access overview).
 * Opened by a button with class js-access-edit and data-kind / data-key / data-title / data-rule
 * (js/modules/access-rule-dialog.js). Posts _action=save_access, kind, key, visible_to, dataset to
 * the including page, whose controller saves it.
 *
 * kind "analysis" shows the analysis variant: no "Everyone" (an analysis can only narrow its data set),
 * and "Same as its data set" needs no choice. data-default names the setting it has with no rule.
 * The server refuses "everyone" for an analysis anyway (moop_analysis_rule_set()) -- hiding it is
 * for clarity, not the guard.
 *
 * Needs: $access_datasets (moop_access_dataset_choices()).
 */
$__ad_by_org = [];
foreach ($access_datasets as $__d) $__ad_by_org[$__d['organism']][] = $__d;
?>
<div class="modal fade" id="accessRuleModal" tabindex="-1" aria-labelledby="accessRuleTitle" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_input_field() ?>
      <input type="hidden" name="_action" value="save_access">
      <input type="hidden" name="kind" value="">
      <input type="hidden" name="key" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="accessRuleTitle"><i class="fa fa-lock me-2"></i>Who can see <span class="js-access-title"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php foreach ([
            'everyone'  => ['Everyone', 'Public — no login needed.'],
            'logged_in' => ['Logged in', 'Collaborators, the internal network and admins.'],
            'internal'  => ['Internal network + admins', 'Not outside collaborators.'],
            'admins'    => ['Admins only', 'Drafts and things not ready yet.'],
            'dataset'   => ['Same as a data set', 'Whoever may see that organism\'s assembly or gene set — it follows that data set\'s access, so there is no second list to keep in step.'],
        ] as $__v => [$__l, $__h]): ?>
          <div class="form-check mb-2" data-setting="<?= $__v ?>">
            <input class="form-check-input" type="radio" name="visible_to" id="ar_<?= $__v ?>" value="<?= $__v ?>">
            <label class="form-check-label" for="ar_<?= $__v ?>">
              <?php if ($__v === 'dataset'): ?>
                <strong class="js-ar-for-page"><?= htmlspecialchars($__l) ?></strong>
                <span class="d-block small text-muted js-ar-for-page"><?= htmlspecialchars($__h) ?></span>
                <strong class="js-ar-for-analysis d-none">Same as its data set</strong>
                <span class="d-block small text-muted js-ar-for-analysis d-none">Whoever may see the gene set it belongs to — no extra limit.</span>
              <?php else: ?>
                <strong><?= htmlspecialchars($__l) ?></strong>
                <span class="d-block small text-muted"><?= htmlspecialchars($__h) ?></span>
              <?php endif; ?>
              <span class="badge text-bg-light border ms-1 js-ar-default d-none">default</span></label>
          </div>
        <?php endforeach; ?>
        <select class="form-select form-select-sm mt-1" name="dataset" aria-label="Data set" disabled>
          <option value="">Choose a data set…</option>
          <?php foreach ($__ad_by_org as $__org => $__list): ?>
            <optgroup label="<?= htmlspecialchars(str_replace('_', ' ', $__org)) ?>">
              <?php foreach ($__list as $__d): ?><option value="<?= htmlspecialchars($__d['value']) ?>"><?= htmlspecialchars($__d['label']) ?></option><?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <p class="small text-muted mt-3 mb-0 js-ar-for-page">Anyone else gets “page not found” — the same as for a page that does not exist,
          so a private page never shows that it is there.</p>
        <p class="small text-muted mt-3 mb-0 js-ar-for-analysis d-none">An analysis can only be limited further than its data set,
          never opened wider. Anyone else sees nothing of it, as if it were not there. Choosing the default removes the stored setting.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>
