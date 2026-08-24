<?php
/** @var array $c */ /** @var array $rows */ /** @var ?array $ed */
$brothers = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'male'));
$sisters  = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'female'));
$other    = array_values(array_filter($rows, fn($r) => !in_array($r['gender'] ?? '', ['male', 'female'])));

function _cong_rows(array $group): void { ?>
    <?php foreach ($group as $r): ?>
        <tr>
            <td><?= $r['reg_number'] ? e($r['reg_number']) : '<span class="badge pending">pending</span>' ?></td>
            <td><?= e(display_name($r)) ?></td>
            <td><?= e($r['phone'] ?: '—') ?></td>
            <td><span class="badge <?= e($r['category']) ?>"><?= $r['category']==='group'?'With congregation':($r['category']==='solo'?'Came alone':'Visitor') ?></span></td>
            <td><?= $r['accommodation'] ? ($r['accommodation']==='camping'?'Camping':'Outside') : '—' ?></td>
        </tr>
    <?php endforeach;
}
?>
<div class="page-head">
    <div>
        <h1><?= e($c['name']) ?> <span class="badge group"><?= e($c['code']) ?></span></h1>
        <p class="muted" style="margin:.2rem 0 0">
            <?php if ($c['minister_name']): ?>Minister: <?= e($c['minister_name']) ?><?php endif; ?>
            <?php if ($c['minister_phone']): ?> · <?= e($c['minister_phone']) ?><?php endif; ?>
            <?php if ($c['address']): ?> · <?= e($c['address']) ?><?php endif; ?>
        </p>
    </div>
    <div class="inline-actions">
        <a class="btn secondary" href="<?= url('/congregations/' . $c['id'] . '/edit') ?>">Edit</a>
        <a class="btn ghost" href="<?= url('/congregations/' . $c['id'] . '/roster.csv') ?>">All CSV</a>
        <a class="btn" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster') ?>">Print all</a>
    </div>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;margin-top:0">
        Attendees<?= $ed ? ' · ' . e($ed['name']) : '' ?>
        (<?= count($rows) ?> total<?= count($brothers) ? ' · ' . count($brothers) . ' Bro.' : '' ?><?= count($sisters) ? ' · ' . count($sisters) . ' Sis.' : '' ?>)
    </h2>
    <?php if (!$rows): ?>
        <p class="muted">No one from this congregation has registered for the current edition yet.</p>
    <?php else: ?>

    <?php if ($brothers): ?>
    <div class="page-head" style="margin:1rem 0 .4rem">
        <p class="section-label" style="margin:0">Brothers (<?= count($brothers) ?>)</p>
        <div class="inline-actions">
            <a class="btn ghost small" href="<?= url('/congregations/' . $c['id'] . '/roster.csv?gender=male') ?>">Brothers CSV</a>
            <a class="btn secondary small" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster?gender=male') ?>">Print Brothers</a>
        </div>
    </div>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Type</th><th>Accommodation</th></tr></thead>
        <tbody><?php _cong_rows($brothers); ?></tbody>
    </table>
    <?php endif; ?>

    <?php if ($sisters): ?>
    <div class="page-head" style="margin:1.2rem 0 .4rem">
        <p class="section-label" style="margin:0">Sisters (<?= count($sisters) ?>)</p>
        <div class="inline-actions">
            <a class="btn ghost small" href="<?= url('/congregations/' . $c['id'] . '/roster.csv?gender=female') ?>">Sisters CSV</a>
            <a class="btn secondary small" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster?gender=female') ?>">Print Sisters</a>
        </div>
    </div>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Type</th><th>Accommodation</th></tr></thead>
        <tbody><?php _cong_rows($sisters); ?></tbody>
    </table>
    <?php endif; ?>

    <?php if ($other): ?>
    <p class="section-label" style="margin-top:1.2rem">Other / gender not set (<?= count($other) ?>)</p>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Type</th><th>Accommodation</th></tr></thead>
        <tbody><?php _cong_rows($other); ?></tbody>
    </table>
    <?php endif; ?>

    <?php endif; ?>
</div>
