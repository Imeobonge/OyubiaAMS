<?php
/** @var array $form */ /** @var array $fields */ /** @var array $responses */
?>
<div class="page-head">
    <h1>Responses</h1>
    <div class="inline-actions">
        <?php if ($responses): ?>
            <a class="btn secondary" href="<?= url('/admin/forms/' . $form['id'] . '/responses.csv') ?>">Export CSV</a>
        <?php endif; ?>
        <a class="btn ghost" href="<?= url('/admin/forms/' . $form['id'] . '/edit') ?>">Edit form</a>
        <a class="btn ghost" href="<?= url('/admin/forms') ?>">← All forms</a>
    </div>
</div>

<p class="muted" style="margin-top:-.6rem"><strong><?= e($form['title']) ?></strong> · <?= count($responses) ?> response<?= count($responses) === 1 ? '' : 's' ?></p>

<div class="card">
<?php if (!$responses): ?>
    <p class="muted">No responses yet. Share the form link and they'll show up here.</p>
<?php elseif (!$fields): ?>
    <p class="muted">This form has no questions.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Submitted</th>
                <?php foreach ($fields as $f): ?>
                    <th><?= e($f['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($responses as $r): ?>
            <tr>
                <td><?= e(date('j M Y, g:ia', strtotime($r['submitted_at']))) ?></td>
                <?php foreach ($fields as $f): $val = $r['answers'][$f['id']] ?? null; ?>
                    <td>
                        <?php if ($f['type'] === 'file_upload' && $val): ?>
                            <a href="<?= url('/admin/forms/file/' . $r['id'] . '/' . $f['id']) ?>">⬇ <?= e(format_answer($val, $f['type'])) ?></a>
                        <?php else: ?>
                            <?= e(format_answer($val, $f['type'])) ?: '<span class="muted">—</span>' ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
