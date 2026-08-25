<?php
/** @var string $reason */  // 'closed' | 'no_edition'
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration · <?= e(config()['org_name']) ?></title>
    <meta name="theme-color" content="#f5c400">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap">
    <div class="form-brand"><div class="login-logo" aria-hidden="true">OYCF</div></div>
    <div class="card form-card" style="text-align:center">
        <h1>Self check-in isn't open</h1>
        <p class="muted">
            Self-registration isn't accepting entries right now. Please see a registration
            desk at the event, or check back shortly.
        </p>
    </div>
    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
</body>
</html>
