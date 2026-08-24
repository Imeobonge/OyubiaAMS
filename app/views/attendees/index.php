<?php
/** @var array $rows */ /** @var string $q */
$brothers = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'male'));
$sisters  = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'female'));
$other    = array_values(array_filter($rows, fn($r) => !in_array($r['gender'] ?? '', ['male', 'female'])));

function _att_rows(array $group): void { ?>
    <?php foreach ($group as $r): ?>
        <tr>
            <td><?= $r['reg_number'] ? e($r['reg_number']) : '<span class="badge pending">pending</span>' ?></td>
            <td><a href="<?= url('/attendees/' . $r['id']) ?>"><?= e(display_name($r)) ?></a></td>
            <td><?= e($r['phone'] ?: '—') ?></td>
            <td><?= e($r['cong_name'] ?: '—') ?></td>
            <td><span class="badge <?= e($r['category']) ?>"><?= e($r['category']) ?></span></td>
            <td class="inline-actions">
                <a class="btn small secondary" href="<?= url('/attendees/' . $r['id'] . '/edit') ?>">Edit</a>
            </td>
        </tr>
    <?php endforeach;
}
?>
<div class="page-head">
    <h1>Attendees</h1>
    <div class="inline-actions">
        <?php $qArg = $q !== '' ? '&q=' . urlencode($q) : ''; ?>
        <a class="btn ghost" href="<?= url('/attendees/export.csv?gender=male' . $qArg) ?>">Brothers CSV</a>
        <a class="btn ghost" href="<?= url('/attendees/export.csv?gender=female' . $qArg) ?>">Sisters CSV</a>
        <a class="btn secondary" href="<?= url('/attendees/export.csv' . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>">All CSV<?= $q !== '' ? ' (filtered)' : '' ?></a>
        <a class="btn" href="<?= url('/register') ?>">+ Register attendee</a>
    </div>
</div>

<form class="toolbar" method="get" action="<?= url('/attendees') ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, phone or reg number…">
    <button class="btn secondary small" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn ghost small" href="<?= url('/attendees') ?>">Clear</a><?php endif; ?>
    <span class="muted" style="margin-left:auto">
        <?= count($rows) ?> shown<?= count($brothers) ? ' · ' . count($brothers) . ' Bros.' : '' ?><?= count($sisters) ? ' · ' . count($sisters) . ' Sis.' : '' ?>
    </span>
</form>

<?php if (!$rows): ?>
<div class="card"><p class="muted">No attendees<?= $q !== '' ? ' match your search' : ' yet' ?>.</p></div>

<?php else: ?>

<?php if ($brothers): ?>
<div class="card" style="margin-bottom:.8rem">
    <p class="section-label" style="margin-top:0">Brothers (<?= count($brothers) ?>)</p>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Congregation</th><th>Type</th><th></th></tr></thead>
        <tbody><?php _att_rows($brothers); ?></tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($sisters): ?>
<div class="card" style="margin-bottom:.8rem">
    <p class="section-label" style="margin-top:0">Sisters (<?= count($sisters) ?>)</p>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Congregation</th><th>Type</th><th></th></tr></thead>
        <tbody><?php _att_rows($sisters); ?></tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($other): ?>
<div class="card">
    <p class="section-label" style="margin-top:0">Other / gender not set (<?= count($other) ?>)</p>
    <table>
        <thead><tr><th>Reg No.</th><th>Name</th><th>Phone</th><th>Congregation</th><th>Type</th><th></th></tr></thead>
        <tbody><?php _att_rows($other); ?></tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>
