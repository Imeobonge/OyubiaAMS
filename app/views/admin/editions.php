<?php /** @var array $rows */ ?>
<div class="page-head"><h1>Event editions</h1></div>

<div class="grid cols-2">
    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">Editions</h2>
        <?php if (!$rows): ?>
            <p class="muted">No editions yet. Create this year's event to begin.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Year</th><th>Dates</th><th>Registered</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $ed): ?>
                <tr>
                    <td><?= e($ed['name']) ?></td>
                    <td><?= (int)$ed['year'] ?></td>
                    <td><?= e(trim(($ed['start_date'] ?: '') . ($ed['end_date'] ? ' – ' . $ed['end_date'] : ''))) ?: '—' ?></td>
                    <td><?= (int)$ed['reg_count'] ?></td>
                    <td><?= $ed['is_active'] ? '<span class="badge solo">Active</span>' : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?php if (!$ed['is_active']): ?>
                        <form method="post" action="<?= url('/admin/editions/' . $ed['id'] . '/activate') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn small secondary" type="submit">Make active</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="help" style="margin-top:.6rem">Only one edition is active at a time. Registrations always attach to the active edition.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">New edition</h2>
        <form method="post" action="<?= url('/admin/editions') ?>">
            <?= csrf_field() ?>
            <div class="field"><label>Year <span class="req">*</span></label><input type="number" name="year" min="2000" max="2100" value="<?= (int)date('Y') ?>" required></div>
            <div class="field"><label>Name</label><input type="text" name="name" placeholder="OYCF <?= date('Y') ?>"><div class="help">Defaults to "OYCF &lt;year&gt;".</div></div>
            <div class="row">
                <div class="field"><label>Start date</label><input type="date" name="start_date"></div>
                <div class="field"><label>End date</label><input type="date" name="end_date"></div>
            </div>
            <button class="btn" type="submit">Create edition</button>
        </form>
    </div>
</div>
