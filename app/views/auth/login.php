<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(config()['app_name']) ?></title>
    <meta name="theme-color" content="#173b86">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="login-page">
<section class="login-showcase" aria-hidden="true">
    <div class="showcase-mark"><span></span><span></span></div>
    <p class="showcase-kicker">Oyubia Christian Youth Forum</p>
    <h1>Simple attendance.<br>Better events.</h1>
    <p class="showcase-copy">Register attendees, manage congregations and see your event numbers in one calm workspace.</p>
    <div class="showcase-orbit orbit-one"></div>
    <div class="showcase-orbit orbit-two"></div>
</section>
<main class="login-card">
    <div class="login-brand">
        <div class="login-logo" aria-hidden="true">OYCF</div>
        <h1>Welcome to OYAMS</h1>
        <p class="login-sub"><?= e(config()['org_name']) ?></p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php
    $isDemo = !empty(config()['demo_mode']);
    $demoEmail = config()['demo_email'] ?? 'demo@oyubiacyf.com';
    $demoPass  = config()['demo_password'] ?? 'demo1234';
    ?>
    <?php if ($isDemo): ?>
        <div class="demo-note">
            <strong>Demo site — try it out</strong>
            This is a sandbox with sample data. Sign in with:<br>
            Email <code><?= e($demoEmail) ?></code> · Password <code><?= e($demoPass) ?></code>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('/login') ?>" class="login-form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= $isDemo ? e($demoEmail) : '' ?>" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" value="<?= $isDemo ? e($demoPass) : '' ?>" required>
        </div>
        <button class="btn btn-block" type="submit">Sign in</button>
    </form>

    <p class="login-foot">Attendance Management System</p>
</main>
</body>
</html>
