<?php /** @var array $results */ /** @var array $errors */ /** @var array $congregation */ ?>
<?php $created = array_values(array_filter($results, fn($r) => ($r['status'] ?? '') === 'created')); ?>
<div class="card reg-success" style="max-width:820px;margin:1rem auto">
    <h1><?= count($created) ?> <?= count($created) === 1 ? 'person' : 'people' ?> registered</h1>
    <p class="muted"><?= e($congregation['name']) ?></p>
    <?php foreach ($errors as $message): ?><div class="flash flash-error"><?= e($message) ?></div><?php endforeach; ?>
    <?php if ($results): ?>
    <table>
        <thead><tr><th>Name</th><th>Gender</th><th>Registration number</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= e(trim(attendee_title(true, $r['gender'] ?? null) . ' ' . ($r['full_name'] ?? ''))) ?></td>
                <td><?= ($r['gender'] ?? '') === 'male' ? 'Brother' : 'Sister' ?></td>
                <td><strong><?= e($r['reg_number'] ?? '—') ?></strong></td>
                <td><?= ($r['status'] ?? '') === 'already_registered' ? 'Already registered' : 'Registered' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <div class="inline-actions" style="justify-content:center;margin-top:1rem">
        <a class="btn" href="<?= url('/register?category=group') ?>">Register another congregation group</a>
        <a class="btn secondary" href="<?= url('/congregations/' . $congregation['id']) ?>">View congregation</a>
        <a class="btn ghost" href="<?= url('/') ?>">Dashboard</a>
    </div>
</div>
