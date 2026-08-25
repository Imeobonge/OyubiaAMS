<?php /** @var array $stats */ /** @var array $byCongregation */ /** @var ?array $ed */ ?>
<div class="page-head">
    <h1>Dashboard</h1>
    <a class="btn" href="<?= url('/register') ?>">+ Register attendee</a>
</div>

<?php if (!$ed): ?>
    <div class="flash flash-warn">
        No active event edition yet.
        <?php if (is_admin()): ?>
            <a href="<?= url('/admin/editions') ?>">Create one →</a>
        <?php else: ?>
            Ask an administrator to set up this year's edition.
        <?php endif; ?>
    </div>
<?php else: ?>

<?php if (is_admin()): ?>
    <?php $selfOpen = (int)($ed['self_register_open'] ?? 0) === 1; ?>
    <div class="flash <?= $selfOpen ? 'flash-success' : 'flash-warn' ?>" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <span>QR self check-in is <strong><?= $selfOpen ? 'OPEN' : 'closed' ?></strong><?= $selfOpen ? ' — attendees can register themselves.' : '.' ?></span>
        <a class="btn small <?= $selfOpen ? 'secondary' : '' ?>" href="<?= url('/admin/self-register') ?>"><?= $selfOpen ? 'Manage / show QR' : 'Open & show QR' ?></a>
    </div>
<?php endif; ?>

<div class="grid cols-4">
    <div class="card stat"><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Total registered</div></div>
    <div class="card stat"><div class="num"><?= (int)$stats['congregations'] ?></div><div class="lbl">Congregations</div></div>
    <div class="card stat"><div class="num"><?= (int)$stats['today'] ?></div><div class="lbl">Registered today</div></div>
    <div class="card stat"><div class="num"><?= (int)$stats['visitor'] ?></div><div class="lbl">Visitors</div></div>
</div>

<div class="grid cols-3" style="margin-top:1rem">
    <div class="card stat"><div class="num"><?= (int)$stats['group'] ?></div><div class="lbl">Came with congregation</div></div>
    <div class="card stat"><div class="num"><?= (int)$stats['solo'] ?></div><div class="lbl">Came alone (members)</div></div>
    <div class="card stat"><div class="num"><?= (int)$stats['pending'] ?></div><div class="lbl">Pending sync / no reg no.</div></div>
</div>

<div class="card" style="margin-top:1.4rem">
    <div class="page-head" style="margin-bottom:.6rem">
        <h2 style="font-size:1.1rem;margin:0">Attendees by congregation</h2>
        <a href="<?= url('/congregations') ?>">Manage congregations →</a>
    </div>
    <?php if (!$byCongregation): ?>
        <p class="muted">No congregations registered yet.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Congregation</th><th>Code</th><th>Attendees</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($byCongregation as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><span class="badge group"><?= e($c['code']) ?></span></td>
                <td><?= (int)$c['c'] ?></td>
                <td class="inline-actions">
                    <a class="btn small secondary" href="<?= url('/congregations/' . $c['id']) ?>">View</a>
                    <a class="btn small ghost" href="<?= url('/congregations/' . $c['id'] . '/roster.csv') ?>">CSV</a>
                    <a class="btn small ghost" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster') ?>">Roster PDF</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
