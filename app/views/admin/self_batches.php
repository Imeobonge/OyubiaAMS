<?php
/** @var array|null $edition */ /** @var array $batchList */
?>
<div class="page-head">
    <h1>Group arrivals</h1>
    <a class="btn ghost" href="<?= url('/admin/self-register') ?>">← Self check-in</a>
</div>

<?php if (!$edition): ?>
    <div class="card"><p class="muted">No active edition. <a href="<?= url('/admin/editions') ?>">Create one</a> first.</p></div>
<?php elseif (empty($batchList)): ?>
    <div class="card">
        <p class="muted">No group arrivals recorded yet for <strong><?= e($edition['name']) ?></strong>.</p>
        <p class="muted">When a congregation leader scans the QR code and registers their group, it will appear here.</p>
    </div>
<?php else: ?>

<p class="muted" style="margin-bottom:1rem">
    <?= count($batchList) ?> group<?= count($batchList) === 1 ? '' : 's' ?> arrived for
    <strong><?= e($edition['name']) ?></strong>, most recent first.
</p>

<?php foreach ($batchList as $b): ?>
<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.4rem">
        <div>
            <strong style="font-size:1.05rem"><?= e($b['congregation_name'] ?? 'Unknown congregation') ?></strong>
            <span class="badge group" style="margin-left:.4rem"><?= (int)$b['headcount'] ?> <?= (int)$b['headcount'] === 1 ? 'person' : 'people' ?></span>
        </div>
        <span class="muted" style="font-size:.85rem"><?= e(date('D d M Y, g:ia', strtotime($b['arrived_at']))) ?></span>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-top:.8rem">
        <thead>
            <tr style="background:var(--surface-alt)">
                <th style="padding:.45rem .7rem;text-align:left;font-size:.8rem;color:var(--muted)">#</th>
                <th style="padding:.45rem .7rem;text-align:left;font-size:.8rem;color:var(--muted)">Name</th>
                <th style="padding:.45rem .7rem;text-align:left;font-size:.8rem;color:var(--muted)">Reg number</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($b['members'] as $i => $m):
            $title = attendee_title(true, $m['gender'] ?? null);
            $name  = trim($title . ' ' . $m['full_name']);
        ?>
            <tr style="border-top:1px solid var(--border)">
                <td style="padding:.5rem .7rem;color:var(--muted);font-size:.85rem"><?= $i + 1 ?></td>
                <td style="padding:.5rem .7rem"><?= e($name) ?></td>
                <td style="padding:.5rem .7rem;font-family:monospace;font-weight:700"><?= e($m['reg_number']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php endif; ?>
