<?php
/** Congregations controller. */

function _find_congregation(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM congregations WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Congregation not found.');
    }
    return $row;
}

function index(): void
{
    require_login();
    $ed   = active_edition();
    $edId = $ed ? (int)$ed['id'] : 0;
    $stmt = db()->prepare(
        "SELECT c.*,
                COALESCE(ca.brothers_done, 0) AS brothers_done,
                COALESCE(ca.sisters_done,  0) AS sisters_done,
                COALESCE((SELECT COUNT(*) FROM registrations r
                           JOIN attendees a ON a.id = r.attendee_id
                           WHERE r.congregation_id = c.id AND r.edition_id = ?
                             AND a.gender = 'male'), 0) AS brothers_count,
                COALESCE((SELECT COUNT(*) FROM registrations r
                           JOIN attendees a ON a.id = r.attendee_id
                           WHERE r.congregation_id = c.id AND r.edition_id = ?
                             AND a.gender = 'female'), 0) AS sisters_count,
                COALESCE((SELECT COUNT(*) FROM registrations r WHERE r.congregation_id = c.id AND r.edition_id = ?), 0) AS attendee_count
         FROM congregations c
         LEFT JOIN congregation_accommodation ca
               ON ca.congregation_id = c.id AND ca.edition_id = ?
         ORDER BY c.name"
    );
    $stmt->execute([$edId, $edId, $edId, $edId]);
    view('congregations/index', ['title' => 'Congregations', 'rows' => $stmt->fetchAll(), 'ed' => $ed]);
}

function create_form(): void
{
    require_login();
    view('congregations/form', [
        'title' => 'Add congregation',
        'c' => ['name'=>'','code'=>'','minister_name'=>'','minister_phone'=>'','address'=>'','home_state'=>'','home_city'=>''],
        'action' => url('/congregations'),
    ]);
}

function store(): void
{
    require_login();
    verify_csrf();
    $name = input('name');
    if ($name === '') {
        flash('Congregation name is required.', 'error');
        redirect('/congregations/new');
    }
    $code = input('code');
    $code = $code !== '' ? unique_congregation_code($code) : unique_congregation_code(suggest_congregation_code($name));

    $stmt = db()->prepare(
        'INSERT INTO congregations (name, code, minister_name, minister_phone, address, home_state, home_city)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $name, $code, input('minister_name') ?: null, input('minister_phone') ?: null,
        input('address') ?: null, input('home_state') ?: null, input('home_city') ?: null,
    ]);
    flash("Congregation \"$name\" added with code $code.");
    redirect('/congregations/' . db()->lastInsertId());
}

function edit_form(string $id): void
{
    require_login();
    $c = _find_congregation((int)$id);
    view('congregations/form', [
        'title' => 'Edit congregation',
        'c' => $c,
        'action' => url('/congregations/' . $c['id']),
    ]);
}

function update(string $id): void
{
    require_login();
    verify_csrf();
    $c = _find_congregation((int)$id);
    $name = input('name') ?: $c['name'];
    $code = input('code');
    $code = $code !== '' ? unique_congregation_code($code, (int)$c['id']) : $c['code'];

    $stmt = db()->prepare(
        'UPDATE congregations SET name=?, code=?, minister_name=?, minister_phone=?, address=?, home_state=?, home_city=? WHERE id=?'
    );
    $stmt->execute([
        $name, $code, input('minister_name') ?: null, input('minister_phone') ?: null,
        input('address') ?: null, input('home_state') ?: null, input('home_city') ?: null, $c['id'],
    ]);
    flash('Congregation updated.');
    redirect('/congregations/' . $c['id']);
}

function toggle_accommodation(string $id): void
{
    require_login();
    verify_csrf();
    $c   = _find_congregation((int)$id);
    $ed  = active_edition();
    if (!$ed) {
        flash('No active edition.', 'error');
        redirect('/congregations');
    }
    $which = input('which');
    if (!in_array($which, ['brothers', 'sisters'], true)) {
        redirect('/congregations');
    }
    $col = $which === 'brothers' ? 'brothers_done' : 'sisters_done';

    $stmt = db()->prepare(
        'SELECT brothers_done, sisters_done FROM congregation_accommodation WHERE edition_id = ? AND congregation_id = ?'
    );
    $stmt->execute([(int)$ed['id'], (int)$c['id']]);
    $curr = $stmt->fetch();

    if ($curr) {
        $newVal = (int)$curr[$col] === 1 ? 0 : 1;
        db()->prepare("UPDATE congregation_accommodation SET $col = ? WHERE edition_id = ? AND congregation_id = ?")
            ->execute([$newVal, (int)$ed['id'], (int)$c['id']]);
    } else {
        db()->prepare(
            'INSERT INTO congregation_accommodation (edition_id, congregation_id, brothers_done, sisters_done) VALUES (?,?,?,?)'
        )->execute([
            (int)$ed['id'], (int)$c['id'],
            $which === 'brothers' ? 1 : 0,
            $which === 'sisters'  ? 1 : 0,
        ]);
    }
    redirect('/congregations');
}

function show(string $id): void
{
    require_login();
    $c = _find_congregation((int)$id);
    $ed = active_edition();
    $rows = [];
    if ($ed) {
        $stmt = db()->prepare(
            "SELECT r.reg_number, r.category, r.accommodation, a.full_name, a.gender, a.is_member, a.phone, a.email
             FROM registrations r JOIN attendees a ON a.id = r.attendee_id
             WHERE r.congregation_id = ? AND r.edition_id = ?
             ORDER BY CASE a.gender WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END, a.full_name"
        );
        $stmt->execute([$c['id'], $ed['id']]);
        $rows = $stmt->fetchAll();
    }
    view('congregations/show', ['title' => $c['name'], 'c' => $c, 'rows' => $rows, 'ed' => $ed]);
}
