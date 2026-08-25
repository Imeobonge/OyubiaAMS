<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · <?= e(config()['org_name']) ?></title>
    <meta name="theme-color" content="#173b86">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap">
    <div class="form-brand">
        <div class="login-logo" aria-hidden="true">OYCF</div>
        <?php if ($ed = active_edition()): ?><h1 style="margin:.6rem 0 0;font-size:1.3rem"><?= e($ed['name']) ?></h1><?php endif; ?>
    </div>

    <div class="card form-card">
        <h1>Welcome — register yourself</h1>
        <p class="form-desc">How are you attending? Tap the option that fits you.</p>

        <div class="cat-picker" style="grid-template-columns:1fr">
            <a class="cat-card" href="<?= url('/join/group') ?>">
                <h3>I came with my congregation</h3>
                <p>You travelled with a group from a Church of Christ congregation.</p>
            </a>
            <a class="cat-card" href="<?= url('/join/solo') ?>">
                <h3>I came on my own (member)</h3>
                <p>You're a Church of Christ member who travelled by yourself.</p>
            </a>
            <a class="cat-card" href="<?= url('/join/visitor') ?>">
                <h3>I'm a visitor</h3>
                <p>You're not a member of the Church of Christ. Welcome!</p>
            </a>
        </div>
    </div>

    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
</body>
</html>
