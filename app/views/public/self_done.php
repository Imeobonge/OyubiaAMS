<?php
/**
 * Self-registration done page.
 * $batch === true  → group submission: $results[], $congregation, $errors[]
 * $batch === false → single submission: $result[], $data[]
 */
/** @var bool $batch */ /** @var array $data */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registered · <?= e(config()['org_name']) ?></title>
    <meta name="theme-color" content="#f5c400">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap<?= ($batch ?? false) ? ' form-wrap-wide' : '' ?>">
    <div class="form-brand"><div class="login-logo" aria-hidden="true">OYCF</div></div>

<?php if ($batch ?? false): ?>
    <?php
    /** @var array $results */ /** @var string $congregation */ /** @var array $errors */
    $registered   = array_filter($results, fn($r) => ($r['status'] ?? '') !== 'already_registered');
    $alreadyOnList= array_filter($results, fn($r) => ($r['status'] ?? '') === 'already_registered');
    $total = count($results);
    $newCount = count($registered);
    ?>

    <div class="card form-card form-done">
        <div class="form-done-mark">✓</div>
        <h1><?= $newCount > 0 ? e($newCount) . ' ' . ($newCount === 1 ? 'person' : 'people') . ' registered!' : 'Check complete' ?></h1>
        <p class="muted" style="margin-bottom:.2rem"><?= e($congregation) ?></p>
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error" style="text-align:left;margin:.4rem 0"><?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="card" style="margin-top:.8rem;padding:0;overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--surface-alt)">
                    <th style="padding:.55rem .8rem;text-align:left;font-size:.8rem;color:var(--muted)">Name</th>
                    <th style="padding:.55rem .8rem;text-align:left;font-size:.8rem;color:var(--muted)">Reg #</th>
                    <th style="padding:.55rem .8rem;text-align:left;font-size:.8rem;color:var(--muted)">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r):
                $isNew = ($r['status'] ?? '') !== 'already_registered';
                $title = attendee_title(true, $r['gender'] ?? null);
                $name  = trim($title . ' ' . ($r['full_name'] ?? ''));
            ?>
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:.6rem .8rem"><?= e($name) ?></td>
                    <td style="padding:.6rem .8rem;font-weight:700;font-family:monospace"><?= e($r['reg_number'] ?? '—') ?></td>
                    <td style="padding:.6rem .8rem">
                        <?php if ($isNew): ?>
                            <span style="color:var(--success-fg);font-weight:600">✓ Registered</span>
                        <?php else: ?>
                            <span style="color:var(--muted)">Already on list</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1.2rem">
        <a class="btn secondary" href="<?= url('/join') ?>">Register another group</a>
    </p>

<?php else: ?>
    <?php
    /** @var array $result */
    $alreadyReg = ($result['status'] ?? '') === 'already_registered';
    $title = ($data['category'] ?? '') !== 'visitor' ? attendee_title(true, $data['gender'] ?? null) : '';
    $name  = trim($title . ' ' . ($data['full_name'] ?? ''));
    ?>

    <div class="card form-card form-done">
        <div class="form-done-mark">✓</div>
        <?php if ($alreadyReg): ?>
            <h1>You're already registered</h1>
            <p class="muted">This phone number is already on the list. Here's your number:</p>
        <?php else: ?>
            <h1>You're registered!</h1>
            <p class="muted">Welcome<?= $name ? ', ' . e($name) : '' ?>. Please keep your registration number:</p>
        <?php endif; ?>

        <div class="regno" style="font-size:2.2rem"><?= e($result['reg_number'] ?? 'PENDING') ?></div>

        <?php if (!empty($result['congregation'])): ?>
            <p class="muted"><?= e($result['congregation']) ?></p>
        <?php endif; ?>

        <p style="margin-top:1.2rem"><a class="btn secondary" href="<?= url('/join') ?>">Register someone else</a></p>
    </div>

<?php endif; ?>

    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
</body>
</html>
