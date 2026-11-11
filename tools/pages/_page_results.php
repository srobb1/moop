<?php
/**
 * Anatomical Expression Search results table -- the frame only. Rows come from
 * api/anatomy_records.php (DataTables server-side: paged, sorted and filtered over EVERY match);
 * js/anatomy-search.js wires it. Column order = that API's (Transcript, Gene, Expressed in,
 * Evidence, Life stage, Specimen, Paper, Curated). Variables: $records_url.
 */
?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle" id="pageResults" style="width:100%"
               data-url="<?= htmlspecialchars($records_url) ?>">
          <thead><tr>
            <th><?= t('Transcript') ?></th><th><?= t('Gene') ?></th><th><?= t('Expressed in') ?></th><th><?= t('Evidence') ?></th>
            <th><?= t('Life stage') ?></th><th><?= t('Specimen') ?></th><th><?= t('Paper') ?></th><th><?= t('Curated') ?></th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
