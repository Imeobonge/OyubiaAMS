<?php
/** @var ?array $target */ /** @var ?array $source */ /** @var ?array $preview */ /** @var bool $sameError */
$months = [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$fmtBday = function ($a) use ($months) {
    return ($a['birth_day'] && $a['birth_month']) ? ($a['birth_day'] . ' ' . ($months[(int)$a['birth_month']] ?? '')) : '—';
};
$pickerLabel = function ($a) {
    if (!$a) return '';
    $t = attendee_title((bool)$a['is_member'], $a['gender']);
    return trim($t . ' ' . $a['full_name']) . ($a['phone'] ? ' · ' . $a['phone'] : '');
};
?>
<div class="page-head">
    <h1>Merge duplicate attendees</h1>
    <a class="btn ghost" href="<?= url('/attendees') ?>">Back to attendees</a>
</div>

<p class="muted" style="max-width:680px">
    Use this to combine two records that are really the same person (e.g. someone
    registered again offline). Pick the record to <strong>keep</strong> and the
    record to <strong>merge into it</strong>. Their registrations are moved onto the
    kept record, missing details are filled in from the duplicate, and the duplicate
    is deleted.
</p>

<?php if ($sameError): ?>
    <div class="flash flash-error">You picked the same record twice. Choose two different people.</div>
<?php endif; ?>

<form method="get" action="<?= url('/admin/merge') ?>" id="mergePickForm">
<div class="grid cols-2">
    <div class="card lookup">
        <h2 style="font-size:1rem;margin-top:0">✅ Keep this record</h2>
        <input type="hidden" name="target" id="target" value="<?= $target ? (int)$target['id'] : '' ?>">
        <div id="targetChosen" <?= $target ? '' : 'hidden' ?>>
            <div class="linked-pill"><span id="targetLabel"><?= e($pickerLabel($target)) ?></span></div>
            <button type="button" class="btn small ghost" style="margin-top:.5rem" data-clear="target">Change</button>
        </div>
        <div id="targetWrap" <?= $target ? 'hidden' : '' ?>>
            <input type="text" id="targetSearch" autocomplete="off" placeholder="Search name or phone…">
            <div class="lookup-results" id="targetResults" hidden></div>
        </div>
    </div>

    <div class="card lookup">
        <h2 style="font-size:1rem;margin-top:0">➡️ Merge this one in (will be deleted)</h2>
        <input type="hidden" name="source" id="source" value="<?= $source ? (int)$source['id'] : '' ?>">
        <div id="sourceChosen" <?= $source ? '' : 'hidden' ?>>
            <div class="linked-pill"><span id="sourceLabel"><?= e($pickerLabel($source)) ?></span></div>
            <button type="button" class="btn small ghost" style="margin-top:.5rem" data-clear="source">Change</button>
        </div>
        <div id="sourceWrap" <?= $source ? 'hidden' : '' ?>>
            <input type="text" id="sourceSearch" autocomplete="off" placeholder="Search name or phone…">
            <div class="lookup-results" id="sourceResults" hidden></div>
        </div>
    </div>
</div>
<div class="inline-actions" style="margin-top:1rem">
    <button class="btn secondary" type="submit">Preview merge</button>
</div>
</form>

<?php if ($preview && $target && $source): ?>
<div class="card" style="margin-top:1.4rem">
    <h2 style="font-size:1.1rem;margin-top:0">Preview</h2>
    <div class="grid cols-2">
        <div>
            <p class="section-title">Keep</p>
            <p><strong><?= e($pickerLabel($target)) ?></strong></p>
            <p class="muted" style="font-size:.85rem">Birthday: <?= e($fmtBday($target)) ?> · <?= count($target['history']) ?> year(s) on record</p>
        </div>
        <div>
            <p class="section-title">Delete</p>
            <p><strong><?= e($pickerLabel($source)) ?></strong></p>
            <p class="muted" style="font-size:.85rem">Birthday: <?= e($fmtBday($source)) ?> · <?= count($source['history']) ?> year(s) on record</p>
        </div>
    </div>

    <?php if ($preview['moves']): ?>
        <p class="section-title">Will move onto the kept record (<?= count($preview['moves']) ?>)</p>
        <table>
            <thead><tr><th>Year</th><th>Reg No.</th><th>Type</th><th>Congregation</th></tr></thead>
            <tbody>
            <?php foreach ($preview['moves'] as $m): ?>
                <tr><td><?= (int)$m['year'] ?></td><td><?= e($m['reg_number'] ?: 'pending') ?></td>
                    <td><span class="badge <?= e($m['category']) ?>"><?= e($m['category']) ?></span></td>
                    <td><?= e($m['cong_name'] ?: '—') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($preview['conflicts']): ?>
        <div class="flash flash-warn" style="margin-top:1rem">
            Both records are registered for the year(s) below. The duplicate's registration
            for those years will be <strong>removed</strong> (the kept record's stays).
        </div>
        <table>
            <thead><tr><th>Year</th><th>Duplicate's Reg No. (removed)</th><th>Type</th></tr></thead>
            <tbody>
            <?php foreach ($preview['conflicts'] as $cf): ?>
                <tr><td><?= (int)$cf['year'] ?></td><td><?= e($cf['reg_number'] ?: 'pending') ?></td>
                    <td><span class="badge <?= e($cf['category']) ?>"><?= e($cf['category']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!$preview['moves'] && !$preview['conflicts']): ?>
        <p class="muted">The duplicate has no registrations — merging will just delete it and backfill any missing details.</p>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/merge') ?>" style="margin-top:1.2rem"
          onsubmit="return confirm('Merge these two records? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="target_id" value="<?= (int)$target['id'] ?>">
        <input type="hidden" name="source_id" value="<?= (int)$source['id'] ?>">
        <button class="btn" type="submit">Confirm merge</button>
        <a class="btn ghost" href="<?= url('/admin/merge') ?>">Cancel</a>
    </form>
</div>
<?php endif; ?>
