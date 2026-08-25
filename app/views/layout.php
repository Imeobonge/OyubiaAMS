<?php /** @var string $content */ /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'OyubiaCYF') ?> · <?= e(config()['app_name']) ?></title>
    <link rel="manifest" href="<?= url('/manifest.webmanifest') ?>">
    <meta name="theme-color" content="#173b86">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="app-shell">
<?php
$u  = current_user();
$ed = active_edition();
$base    = rtrim(config()['base_url'] ?? '', '/');
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
function nav_active(string $href, string $base, string $reqPath): string {
    $full = $base . $href;
    $match = ($href === '/') ? ($reqPath === $full || $reqPath === $base . '')
                             : (str_starts_with($reqPath, $full));
    return $match ? ' class="active"' : '';
}
?>
<header class="topbar">
    <div class="brand">
        <a href="<?= url('/') ?>" aria-label="OYAMS dashboard">OYAMS</a>
        <?php if ($ed): ?><span class="edition-pill"><?= e($ed['name']) ?></span><?php endif; ?>
    </div>
    <?php if ($u): ?>
    <button type="button" class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false" aria-controls="mainnav">
        <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>
    <nav class="mainnav" id="mainnav">
        <a href="<?= url('/') ?>"<?= nav_active('/', $base, $reqPath) ?>>Dashboard</a>
        <a href="<?= url('/register') ?>"<?= nav_active('/register', $base, $reqPath) ?>>Register</a>
        <a href="<?= url('/congregations') ?>"<?= nav_active('/congregations', $base, $reqPath) ?>>Congregations</a>
        <a href="<?= url('/attendees') ?>"<?= nav_active('/attendees', $base, $reqPath) ?>>Attendees</a>
        <?php if (is_admin()): ?>
            <a href="<?= url('/admin/self-register') ?>"<?= nav_active('/admin/self-register', $base, $reqPath) ?>>Self check-in</a>
            <a href="<?= url('/admin/forms') ?>"<?= nav_active('/admin/forms', $base, $reqPath) ?>>Forms</a>
            <a href="<?= url('/admin/editions') ?>"<?= nav_active('/admin/editions', $base, $reqPath) ?>>Editions</a>
            <a href="<?= url('/admin/merge') ?>"<?= nav_active('/admin/merge', $base, $reqPath) ?>>Merge</a>
            <a href="<?= url('/admin/users') ?>"<?= nav_active('/admin/users', $base, $reqPath) ?>>Users</a>
        <?php endif; ?>
    </nav>
    <div class="userbox">
        <span class="sync-status" id="syncStatus" title="Offline queue">●</span>
        <span class="uname"><?= e($u['name']) ?></span>
        <a class="nav-signout" href="<?= url('/logout') ?>">Sign out</a>
    </div>
    <?php endif; ?>
</header>

<main class="container">
    <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <div class="offline-banner" id="offlineBanner" hidden>
        You are offline — registrations are saved on this device and will sync automatically.
    </div>
    <?= $content ?>
</main>

<script>window.OyubiaCYF_BASE = <?= json_encode(rtrim(config()['base_url'] ?? '', '/')) ?>;</script>
<script src="<?= asset('/assets/js/app.js') ?>" defer></script>
</body>
</html>
