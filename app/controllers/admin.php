<?php
/** Admin controller — manage staff users and event editions. */

function users(): void
{
    require_admin();
    $rows = db()->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY name')->fetchAll();
    view('admin/users', ['title' => 'Users', 'rows' => $rows]);
}

function store_user(): void
{
    require_admin();
    verify_csrf();
    $name = input('name');
    $email = strtolower(input('email'));
    $pass = input('password');
    $role = in_array(input('role'), ['admin','staff'], true) ? input('role') : 'staff';

    if ($name === '' || $email === '' || strlen($pass) < 6) {
        flash('Name, email and a password of at least 6 characters are required.', 'error');
        redirect('/admin/users');
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        flash('A user with that email already exists.', 'error');
        redirect('/admin/users');
    }
    db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)')
        ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
    flash("User \"$name\" created.");
    redirect('/admin/users');
}

function toggle_user(string $id): void
{
    require_admin();
    verify_csrf();
    $me = current_user();
    if ((int)$id === (int)$me['id']) {
        flash('You cannot deactivate your own account.', 'error');
        redirect('/admin/users');
    }
    db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([(int)$id]);
    flash('User updated.');
    redirect('/admin/users');
}

/** Load an attendee plus their registration history (for the merge preview). */
function _merge_load_attendee(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM attendees WHERE id = ?');
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) { return null; }
    $h = db()->prepare(
        "SELECT e.year, e.name AS ed_name, r.reg_number, r.category, c.name AS cong_name
         FROM registrations r JOIN editions e ON e.id = r.edition_id
         LEFT JOIN congregations c ON c.id = r.congregation_id
         WHERE r.attendee_id = ? ORDER BY e.year DESC"
    );
    $h->execute([$id]);
    $a['history'] = $h->fetchAll();
    return $a;
}

function merge_form(): void
{
    require_admin();
    $targetId = (int)($_GET['target'] ?? 0);
    $sourceId = (int)($_GET['source'] ?? 0);
    $target = $targetId ? _merge_load_attendee($targetId) : null;
    $source = $sourceId ? _merge_load_attendee($sourceId) : null;

    $preview = null;
    $sameError = ($targetId && $targetId === $sourceId);
    if ($target && $source && !$sameError) {
        $targetYears = array_column($target['history'], 'year');
        $conflicts = [];
        $moves = [];
        foreach ($source['history'] as $h) {
            if (in_array($h['year'], $targetYears, true)) { $conflicts[] = $h; }
            else { $moves[] = $h; }
        }
        $preview = ['moves' => $moves, 'conflicts' => $conflicts];
    }

    view('admin/merge', [
        'title' => 'Merge duplicates',
        'target' => $target, 'source' => $source,
        'preview' => $preview, 'sameError' => $sameError,
    ]);
}

function merge_submit(): void
{
    require_admin();
    verify_csrf();
    $targetId = (int)input('target_id');
    $sourceId = (int)input('source_id');

    if (!$targetId || !$sourceId || $targetId === $sourceId) {
        flash('Choose two different records to merge.', 'error');
        redirect('/admin/merge');
    }
    $pdo = db();
    foreach ([$targetId, $sourceId] as $id) {
        $s = $pdo->prepare('SELECT id FROM attendees WHERE id = ?');
        $s->execute([$id]);
        if (!$s->fetch()) { flash('One of the records no longer exists.', 'error'); redirect('/admin/merge'); }
    }

    // Count what will happen, for the confirmation message.
    $total = (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE attendee_id = $sourceId")->fetchColumn();
    $conflict = (int)$pdo->query(
        "SELECT COUNT(*) FROM registrations s
         WHERE s.attendee_id = $sourceId
           AND s.edition_id IN (SELECT edition_id FROM registrations WHERE attendee_id = $targetId)"
    )->fetchColumn();
    $moved = $total - $conflict;

    $pdo->beginTransaction();
    try {
        // 1. Drop the source's registrations for years the target already has (true duplicates).
        $pdo->prepare(
            'DELETE FROM registrations
             WHERE attendee_id = ?
               AND edition_id IN (SELECT edition_id FROM (SELECT edition_id FROM registrations WHERE attendee_id = ?) t)'
        )->execute([$sourceId, $targetId]);

        // 2. Move the remaining source registrations onto the target.
        $pdo->prepare('UPDATE registrations SET attendee_id = ? WHERE attendee_id = ?')
            ->execute([$targetId, $sourceId]);

        // 3. Backfill any details the target is missing, from the source.
        $pdo->prepare(
            'UPDATE attendees t JOIN attendees s ON s.id = ?
             SET t.phone       = COALESCE(t.phone, s.phone),
                 t.email       = COALESCE(t.email, s.email),
                 t.birth_day   = COALESCE(t.birth_day, s.birth_day),
                 t.birth_month = COALESCE(t.birth_month, s.birth_month),
                 t.home_state  = COALESCE(t.home_state, s.home_state),
                 t.home_city   = COALESCE(t.home_city, s.home_city),
                 t.gender      = COALESCE(t.gender, s.gender)
             WHERE t.id = ?'
        )->execute([$sourceId, $targetId]);

        // 4. Remove the now-empty source record.
        $pdo->prepare('DELETE FROM attendees WHERE id = ?')->execute([$sourceId]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        flash('Merge failed: ' . $e->getMessage(), 'error');
        redirect('/admin/merge');
    }

    $msg = "Merged. $moved registration(s) moved over"
        . ($conflict > 0 ? "; $conflict duplicate same-year registration(s) removed" : '')
        . '. The duplicate record was deleted.';
    flash($msg);

    // Send the admin to the surviving record (use any registration that now points to it).
    $regId = $pdo->query("SELECT id FROM registrations WHERE attendee_id = $targetId ORDER BY id DESC LIMIT 1")->fetchColumn();
    redirect($regId ? '/attendees/' . (int)$regId : '/attendees');
}

function editions(): void
{
    require_admin();
    $rows = db()->query(
        'SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.edition_id = e.id) AS reg_count
         FROM editions e ORDER BY e.year DESC'
    )->fetchAll();
    view('admin/editions', ['title' => 'Editions', 'rows' => $rows]);
}

function store_edition(): void
{
    require_admin();
    verify_csrf();
    $year = (int)input('year');
    $name = input('name') ?: ('OYCF ' . $year);
    if ($year < 2000 || $year > 2100) {
        flash('Enter a valid year.', 'error');
        redirect('/admin/editions');
    }
    $stmt = db()->prepare('SELECT id FROM editions WHERE year = ?');
    $stmt->execute([$year]);
    if ($stmt->fetch()) {
        flash('An edition for that year already exists.', 'error');
        redirect('/admin/editions');
    }
    db()->prepare('INSERT INTO editions (name, year, start_date, end_date) VALUES (?,?,?,?)')
        ->execute([$name, $year, input('start_date') ?: null, input('end_date') ?: null]);
    flash("Edition \"$name\" created. Activate it to start registering.");
    redirect('/admin/editions');
}

function activate_edition(string $id): void
{
    require_admin();
    verify_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('UPDATE editions SET is_active = 0');
    $pdo->prepare('UPDATE editions SET is_active = 1 WHERE id = ?')->execute([(int)$id]);
    $pdo->commit();
    flash('Active edition updated.');
    redirect('/admin/editions');
}

/** Populate an empty public sandbox with the OCYAMS sample dataset. */
function seed_demo(): void
{
    require_admin();
    verify_csrf();

    if (empty(config()['demo_mode'])) {
        http_response_code(404);
        exit('Not found.');
    }

    try {
        define('OYAMS_AUTHENTICATED_DEMO_SEED', true);
        ob_start();
        require __DIR__ . '/../../database/seed_demo.php';
        $summary = trim((string)ob_get_clean());
        flash($summary !== '' ? str_replace("\n", ' ', $summary) : 'Demo data loaded successfully.');
    } catch (\Throwable $e) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        flash($e->getMessage(), 'error');
    }

    redirect('/');
}
