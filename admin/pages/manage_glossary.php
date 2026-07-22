<?php
/**
 * MANAGE GLOSSARY — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $terms, $flash, $file_write_error, $glossary_file.
 */
?>
<div class="container my-4">

  <div class="d-flex align-items-baseline justify-content-between mb-3">
    <h3 class="mb-0"><i class="fa fa-book me-2" style="color:#0891b2;"></i>Manage Glossary</h3>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left me-1"></i>Back to Dashboard
    </a>
  </div>

  <p class="text-muted">
    These definitions power the <strong>dashed-underline terms</strong> shown across the site —
    a reader hovers a term to see what it means. Each term is defined here <strong>once</strong>;
    editing a definition updates every place that word appears, with no code change.
  </p>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($file_write_error): ?>
    <div class="alert alert-warning">
      <i class="fa fa-triangle-exclamation me-1"></i>
      <strong>Read-only:</strong> the web server cannot write the glossary file
      (<code><?= htmlspecialchars($glossary_file) ?></code>). Editing is disabled until this is
      fixed. Run:
      <pre class="mb-0 mt-2"><?= htmlspecialchars($file_write_error['command'] ?? '') ?></pre>
    </div>
  <?php endif; ?>

  <!-- Add a term -->
  <div class="card adm-card mb-4">
    <div class="card-header adm-head"><h5 class="mb-0">Add a term</h5></div>
    <div class="card-body">
      <form method="post" class="row g-2 align-items-start">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="add">
        <div class="col-md-3">
          <label class="form-label small fw-semibold mb-1">Term</label>
          <input type="text" name="term" class="form-control" placeholder="e.g. synteny"
                 required <?= $file_write_error ? 'disabled' : '' ?>>
        </div>
        <div class="col-md-8">
          <label class="form-label small fw-semibold mb-1">Definition</label>
          <textarea name="definition" class="form-control" rows="2"
                    placeholder="A short, plain-language definition."
                    required <?= $file_write_error ? 'disabled' : '' ?>></textarea>
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100" <?= $file_write_error ? 'disabled' : '' ?>>
            Add
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Existing terms -->
  <div class="card adm-card">
    <div class="card-header adm-head d-flex align-items-center justify-content-between">
      <h5 class="mb-0">Terms</h5>
      <span class="badge rounded-pill" style="background-color:#e0f2f7; color:#0e7490;">
        <?= count($terms) ?>
      </span>
    </div>
    <div class="card-body">
      <?php if (empty($terms)): ?>
        <p class="text-muted mb-0">No terms yet. Add one above.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th style="width:18%;">Term</th>
                <th>Definition</th>
                <th style="width:1%;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($terms as $term => $definition): ?>
              <tr>
                <td class="fw-semibold">
                  <span class="gloss"
                        data-bs-toggle="popover" data-bs-trigger="hover focus"
                        data-bs-placement="top"
                        data-bs-title="<?= htmlspecialchars($term, ENT_QUOTES) ?>"
                        data-bs-content="<?= htmlspecialchars($definition, ENT_QUOTES) ?>"
                        tabindex="0"><?= htmlspecialchars($term) ?></span>
                </td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-start mb-0">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="_action" value="update">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($term, ENT_QUOTES) ?>">
                    <textarea name="definition" class="form-control form-control-sm" rows="2"
                              <?= $file_write_error ? 'disabled' : '' ?>><?= htmlspecialchars($definition) ?></textarea>
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-shrink-0"
                            <?= $file_write_error ? 'disabled' : '' ?>>Save</button>
                  </form>
                </td>
                <td>
                  <form method="post" class="mb-0"
                        onsubmit="return confirm('Delete the term &quot;<?= htmlspecialchars($term, ENT_QUOTES) ?>&quot;? Any dashed &quot;<?= htmlspecialchars($term, ENT_QUOTES) ?>&quot; on the site becomes a plain word.');">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="term" value="<?= htmlspecialchars($term, ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            title="Delete term" <?= $file_write_error ? 'disabled' : '' ?>>
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted small mb-0">
          To rename a term, delete it and add the new spelling — the term name is what code
          refers to, so a rename is a deliberate change. Definitions can be edited freely.
        </p>
      <?php endif; ?>
    </div>
  </div>

</div>
