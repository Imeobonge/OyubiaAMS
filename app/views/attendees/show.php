<?php /** @var array $a */
$months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
$bday = '';
if ($a['birth_day'] && $a['birth_month']) { $bday = $a['birth_day'] . ' ' . ($months[(int)$a['birth_month']] ?? ''); }
?>
<div class="page-head">
    <div>
        <h1><?= e(display_name($a)) ?></h1>
        <p class="muted" style="margin:.2rem 0 0">
            <?= $a['reg_number'] ? e($a['reg_number']) : '<span class="badge pending">pending reg number</span>' ?>
            · <span class="badge <?= e($a['category']) ?>"><?= e($a['category']) ?></span>
        </p>
    </div>
    <div class="inline-actions">
        <a class="btn secondary" href="<?= url('/attendees/' . $a['id'] . '/edit') ?>">Edit</a>
    </div>
</div>

<?php if (!empty($history) && count($history) > 1): ?>
<div class="card" style="margin-bottom:1rem">
    <h2 style="font-size:1rem;margin-top:0">Attendance history (<?= count($history) ?> years)</h2>
    <table>
        <thead><tr><th>Year</th><th>Edition</th><th>Reg No.</th><th>Type</th><th>Congregation</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td><?= (int)$h['year'] ?><?= $h['is_active'] ? ' <span class="badge solo">current</span>' : '' ?></td>
                <td><?= e($h['ed_name']) ?></td>
                <td><?= $h['reg_id'] == $a['id'] ? '<strong>' . e($h['reg_number'] ?: 'pending') . '</strong>' : e($h['reg_number'] ?: 'pending') ?></td>
                <td><span class="badge <?= e($h['category']) ?>"><?= e($h['category']) ?></span></td>
                <td><?= e($h['cong_name'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="grid cols-2">
    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">Contact &amp; details</h2>
        <table>
            <tr><th>Phone</th><td><?= e($a['phone'] ?: '—') ?></td></tr>
            <tr><th>Email</th><td><?= e($a['email'] ?: '—') ?></td></tr>
            <tr><th>Birthday</th><td><?= e($bday ?: '—') ?></td></tr>
            <tr><th>Home</th><td><?= e(trim(($a['home_city'] ? $a['home_city'].', ' : '').($a['home_state']??'')) ?: '—') ?></td></tr>
            <tr><th>Accommodation</th><td><?= $a['accommodation'] ? ($a['accommodation']==='camping'?'Camping at event':'Outside compound') : '—' ?><?= $a['accommodation_note'] ? ' — '.e($a['accommodation_note']) : '' ?></td></tr>
        </table>
    </div>

    <div class="card">
        <?php if ($a['category'] === 'visitor'): ?>
            <h2 style="font-size:1rem;margin-top:0">Visitor information</h2>
            <table>
                <tr><th>Church attended</th><td><?= e($a['church_attended'] ?: '—') ?></td></tr>
                <tr><th>Invited by</th><td><?= e($a['invited_by'] ?: '—') ?></td></tr>
                <tr><th>How they heard</th><td><?= e($a['how_heard'] ?: '—') ?></td></tr>
                <tr><th>Expectations</th><td><?= nl2br(e($a['expectations'] ?: '—')) ?></td></tr>
            </table>
        <?php else: ?>
            <h2 style="font-size:1rem;margin-top:0">Congregation</h2>
            <table>
                <tr><th>Congregation</th><td><?= e($a['cong_name'] ?: '—') ?> <?php if($a['cong_code']):?><span class="badge group"><?= e($a['cong_code']) ?></span><?php endif;?></td></tr>
                <tr><th>Attendance</th><td><?= $a['category']==='group' ? 'Came with the congregation' : 'Came alone (member)' ?></td></tr>
            </table>
            <?php if ($a['cong_name']): ?><p style="margin-top:.6rem"><a href="<?= url('/congregations/' . $a['congregation_id']) ?>">View full congregation →</a></p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>
