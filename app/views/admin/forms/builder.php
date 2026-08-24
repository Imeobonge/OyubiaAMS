<?php
/** @var array $form */ /** @var array $fields */ /** @var string|null $error */
$isEdit = !empty($form['id']);
$action = $isEdit ? url('/admin/forms/' . $form['id']) : url('/admin/forms');

// Seed the builder JS with the current fields (normalized for the client).
$seed = [];
foreach ($fields as $f) {
    $seed[] = [
        'label'       => $f['label'] ?? '',
        'help_text'   => $f['help_text'] ?? '',
        'type'        => $f['type'] ?? 'short_text',
        'options'     => array_values($f['options'] ?? []),
        'is_required' => !empty($f['is_required']),
    ];
}
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Edit form' : 'New form' ?></h1>
    <a class="btn ghost" href="<?= url('/admin/forms') ?>">← All forms</a>
</div>

<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" id="formBuilder">
    <?= csrf_field() ?>

    <div class="card">
        <div class="field">
            <label>Form title <span class="req">*</span></label>
            <input type="text" name="title" value="<?= e($form['title'] ?? '') ?>" placeholder="e.g. Volunteer Sign-up" required>
        </div>
        <div class="field">
            <label>Description</label>
            <textarea name="description" placeholder="Optional intro shown at the top of the form"><?= e($form['description'] ?? '') ?></textarea>
        </div>
        <div class="field" style="max-width:240px">
            <label>Status</label>
            <select name="status">
                <?php foreach (['draft' => 'Draft (hidden)', 'open' => 'Open (accepting responses)', 'closed' => 'Closed'] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($form['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h2 style="font-size:1rem;margin:1.4rem 0 .6rem">Questions</h2>
    <div id="fieldList"></div>

    <div class="inline-actions" style="margin-top:1rem">
        <button type="button" class="btn secondary" id="addField">+ Add question</button>
    </div>

    <div class="toolbar" style="margin-top:1.4rem">
        <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Create form' ?></button>
        <a class="btn ghost" href="<?= url('/admin/forms') ?>">Cancel</a>
    </div>
</form>

<script type="application/json" id="seedFields"><?= json_encode($seed) ?></script>
<script src="<?= asset('/assets/js/form-builder.js') ?>" defer></script>
