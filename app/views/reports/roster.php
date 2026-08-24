<?php
/** @var array $c */ /** @var array $rows */ /** @var ?array $ed */ /** @var string $gender */
$brothers = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'male'));
$sisters  = array_values(array_filter($rows, fn($r) => ($r['gender'] ?? '') === 'female'));
$other    = array_values(array_filter($rows, fn($r) => !in_array($r['gender'] ?? '', ['male', 'female'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= $gender === 'male' ? 'Brothers' : ($gender === 'female' ? 'Sisters' : 'Roster') ?> — <?= e($c['name']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font: 13px/1.45 "Segoe UI", Arial, sans-serif; color: #1f2d3d; margin: 0; padding: 24px; }
        .toolbar { margin-bottom: 16px; }
        .btn { background:#0f4c81; color:#fff; border:0; padding:.5rem 1rem; border-radius:6px; font:inherit; cursor:pointer; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .sub { color:#555; margin: 0 0 2px; }
        .meta { color:#555; font-size: 12px; margin: 0 0 14px; }
        h2 { font-size: 13px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .04em; color: #0f4c81; border-bottom: 2px solid #0f4c81; padding-bottom: 3px; }
        h2.sisters { color:#c2185b; border-color:#c2185b; }
        table { width:100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border:1px solid #cdd6e2; padding:6px 8px; text-align:left; }
        th { background:#eef2f8; font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
        td.idx { width: 34px; text-align:center; color:#777; }
        .count { margin-top: 10px; font-size: 12px; color:#555; }
        @media print { .toolbar { display:none; } body { padding: 0; } @page { margin: 14mm; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    <?php if ($gender): ?><a href="<?= url('/congregations/' . $c['id'] . '/roster') ?>" style="margin-left:10px">View full roster</a><?php endif; ?>
</div>

<h1><?= e($c['name']) ?> <span style="font-weight:normal;color:#0f4c81">(<?= e($c['code']) ?>)</span></h1>
<p class="sub"><?= e(config()['org_name']) ?><?= $ed ? ' — ' . e($ed['name']) : '' ?></p>
<?php if ($gender): ?><p class="sub"><strong><?= $gender === 'male' ? 'BROTHERS' : 'SISTERS' ?> ROSTER</strong></p><?php endif; ?>
<p class="meta">
    <?php if ($c['minister_name']): ?>Minister: <strong><?= e($c['minister_name']) ?></strong><?php endif; ?>
    <?php if ($c['minister_phone']): ?> · <?= e($c['minister_phone']) ?><?php endif; ?>
    <?php if ($c['address']): ?> · <?= e($c['address']) ?><?php endif; ?>
</p>

<?php if (!$rows): ?>
    <p style="color:#777">No attendees registered yet.</p>
<?php else: ?>

<?php if ($brothers): ?>
<h2>Brothers (<?= count($brothers) ?>)</h2>
<table>
    <thead><tr><th class="idx">#</th><th>Reg No.</th><th>Name</th><th>Phone</th><th>Accommodation</th></tr></thead>
    <tbody>
    <?php foreach ($brothers as $i => $r): ?>
        <tr>
            <td class="idx"><?= $i + 1 ?></td>
            <td><?= e($r['reg_number'] ?: 'pending') ?></td>
            <td><?= e(display_name($r)) ?></td>
            <td><?= e($r['phone'] ?: '—') ?></td>
            <td><?= $r['accommodation'] ? ($r['accommodation']==='camping'?'Camping':'Outside') : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($sisters): ?>
<h2 class="sisters">Sisters (<?= count($sisters) ?>)</h2>
<table>
    <thead><tr><th class="idx">#</th><th>Reg No.</th><th>Name</th><th>Phone</th><th>Accommodation</th></tr></thead>
    <tbody>
    <?php foreach ($sisters as $i => $r): ?>
        <tr>
            <td class="idx"><?= $i + 1 ?></td>
            <td><?= e($r['reg_number'] ?: 'pending') ?></td>
            <td><?= e(display_name($r)) ?></td>
            <td><?= e($r['phone'] ?: '—') ?></td>
            <td><?= $r['accommodation'] ? ($r['accommodation']==='camping'?'Camping':'Outside') : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($other): ?>
<h2>Other / gender not set (<?= count($other) ?>)</h2>
<table>
    <thead><tr><th class="idx">#</th><th>Reg No.</th><th>Name</th><th>Phone</th><th>Accommodation</th></tr></thead>
    <tbody>
    <?php foreach ($other as $i => $r): ?>
        <tr>
            <td class="idx"><?= $i + 1 ?></td>
            <td><?= e($r['reg_number'] ?: 'pending') ?></td>
            <td><?= e(display_name($r)) ?></td>
            <td><?= e($r['phone'] ?: '—') ?></td>
            <td><?= $r['accommodation'] ? ($r['accommodation']==='camping'?'Camping':'Outside') : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p class="count">
    Total: <strong><?= count($rows) ?></strong> attendee(s)
    <?= $brothers ? '· Brothers: <strong>' . count($brothers) . '</strong>' : '' ?>
    <?= $sisters  ? '· Sisters: <strong>'  . count($sisters)  . '</strong>' : '' ?>
    · Printed <?= date('j M Y, g:i a') ?>
</p>
<?php endif; ?>
</body>
</html>
