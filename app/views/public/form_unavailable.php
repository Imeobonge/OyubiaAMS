<?php
/** @var string $reason */  // 'closed' | 'missing'
$closed = ($reason ?? 'missing') === 'closed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form unavailable</title>
    <meta name="theme-color" content="#0f4c81">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap">
    <div class="form-brand"><div class="login-logo" aria-hidden="true">OYCF</div></div>
    <div class="card form-card" style="text-align:center">
        <h1><?= $closed ? 'This form is closed' : 'Form not found' ?></h1>
        <p class="muted">
            <?= $closed
                ? 'This form is not accepting responses right now. Please check back later or contact the organisers.'
                : 'The link may be incorrect or the form may have been removed.' ?>
        </p>
    </div>
    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
</body>
</html>
