<?php
/**
 * OyubiaCYF one-time installer.
 *
 * 1. Copy app/config.sample.php to app/config.php and set your DB credentials.
 * 2. Open this file in a browser (e.g. https://yoursite/install.php).
 * 3. Create the first admin + this year's edition.
 * 4. DELETE this file afterwards.
 */
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';

$pdo = db();
$done = [];
$error = null;

// Refuse to run if users already exist (prevents re-running to create admins).
function tables_ready(PDO $pdo): bool
{
    try { $pdo->query('SELECT 1 FROM users LIMIT 1'); return true; }
    catch (\Throwable $e) { return false; }
}
function has_users(PDO $pdo): bool
{
    try { return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0; }
    catch (\Throwable $e) { return false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Run schema (idempotent — uses CREATE TABLE IF NOT EXISTS).
        $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
        // Strip "-- ..." line comments, then run each ;-terminated statement.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                try { $pdo->exec($stmt); } catch (\Throwable $e) { /* ignore blanks */ }
            }
        }
        $done[] = 'Database tables created.';

        if (has_users($pdo)) {
            throw new RuntimeException('Setup already completed (users exist). Delete install.php.');
        }

        // 2. Admin user
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
            throw new RuntimeException('Provide a name, valid email, and password of at least 6 characters.');
        }
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)')
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
        $done[] = "Admin user \"$name\" created.";

        // 3. Edition for the chosen year (active)
        $year = (int)($_POST['year'] ?? date('Y'));
        $edName = 'OYCF ' . $year;
        $pdo->exec('UPDATE editions SET is_active = 0');
        $pdo->prepare('INSERT INTO editions (name, year, is_active) VALUES (?,?,1)')->execute([$edName, $year]);
        $done[] = "Edition \"$edName\" created and activated.";

        $done[] = 'Setup complete! Now DELETE public/install.php and sign in.';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · OyubiaCYF</title>
<meta name="theme-color" content="#173b86">
<link rel="stylesheet" href="<?= url('/assets/css/styles.css') ?>">
</head><body class="form-page">
<div class="auth-wrap form-wrap" style="max-width:460px">
  <div class="card form-card">
    <h1>OyubiaCYF Setup</h1>
    <p class="muted">Create the first administrator and this year's edition.</p>

    <?php foreach ($done as $d): ?><div class="flash flash-success"><?= e($d) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

    <?php if (!empty($done) && !$error): ?>
        <a class="btn" href="<?= url('/login') ?>">Go to sign in →</a>
    <?php else: ?>
        <?php if (has_users($pdo)): ?>
            <div class="flash flash-warn">Setup already completed. Please delete <code>public/install.php</code>.</div>
            <a class="btn" href="<?= url('/login') ?>">Sign in</a>
        <?php else: ?>
        <form method="post">
            <div class="field"><label>Admin name</label><input type="text" name="name" required></div>
            <div class="field"><label>Admin email</label><input type="email" name="email" required></div>
            <div class="field"><label>Password</label><input type="password" name="password" minlength="6" required></div>
            <div class="field"><label>First edition year</label><input type="number" name="year" value="<?= (int)date('Y') ?>" min="2000" max="2100" required></div>
            <button class="btn" type="submit" style="width:100%">Run setup</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body></html>
