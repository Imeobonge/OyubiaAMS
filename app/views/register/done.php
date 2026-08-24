<?php /** @var array $result */ /** @var array $data */ ?>
<?php
$statusMsg = 'Registration saved';
if (($result['status'] ?? '') === 'duplicate') {
    $statusMsg = 'Registration saved (already on record)';
} elseif (($result['status'] ?? '') === 'already_registered') {
    $statusMsg = 'Already registered for this edition — showing the existing record';
}
?>
<div class="card reg-success" style="max-width:560px;margin:1rem auto">
    <p class="<?= ($result['status'] ?? '')==='already_registered' ? 'flash flash-warn' : 'muted' ?>" style="margin:0"><?= e($statusMsg) ?></p>
    <div class="regno"><?= e($result['reg_number'] ?? 'PENDING') ?></div>
    <p style="margin:.2rem 0 1rem">
        <strong><?= e(trim((($data['category']??'')!=='visitor' ? attendee_title(true, $data['gender']??null) : '') . ' ' . ($data['full_name']??''))) ?></strong>
        <?php if (!empty($result['congregation'])): ?><br><span class="muted"><?= e($result['congregation']) ?></span><?php endif; ?>
    </p>
    <div class="inline-actions" style="justify-content:center">
        <a class="btn" href="<?= url('/register?category=' . e($data['category'] ?? '')) ?>">Register another</a>
        <a class="btn ghost" href="<?= url('/') ?>">Dashboard</a>
    </div>
</div>
