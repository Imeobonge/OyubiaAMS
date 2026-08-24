<?php
/** Attendees controller. */

function index(): void
{
    require_login();
    $ed = active_edition();
    $q = input('q');
    $params = [$ed ? (int)$ed['id'] : 0];
    $where = 'r.edition_id = ?';
    if ($q !== '') {
        $where .= ' AND (a.full_name LIKE ? OR a.phone LIKE ? OR r.reg_number LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    $sql = "SELECT r.id, r.reg_number, r.category, a.full_name, a.gender, a.is_member, a.phone,
                   c.name AS cong_name, c.code AS cong_code
            FROM registrations r
            JOIN attendees a ON a.id = r.attendee_id
            LEFT JOIN congregations c ON c.id = r.congregation_id
            WHERE $where
            ORDER BY CASE a.gender WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END, a.full_name LIMIT 500";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    view('attendees/index', ['title' => 'Attendees', 'rows' => $stmt->fetchAll(), 'q' => $q, 'ed' => $ed]);
}

function export_csv(): void
{
    require_login();
    $ed = active_edition();
    $q = input('q');
    $params = [$ed ? (int)$ed['id'] : 0];
    $where = 'r.edition_id = ?';
    $gender = in_array(input('gender'), ['male', 'female'], true) ? input('gender') : '';
    if ($gender !== '') {
        $where .= ' AND a.gender = ?';
        $params[] = $gender;
    }
    if ($q !== '') {
        $where .= ' AND (a.full_name LIKE ? OR a.phone LIKE ? OR r.reg_number LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    $sql = "SELECT r.reg_number, r.category, r.accommodation, r.accommodation_note,
                   a.full_name, a.gender, a.is_member, a.phone, a.email,
                   a.birth_day, a.birth_month, a.home_state, a.home_city,
                   c.name AS cong_name, c.code AS cong_code,
                   v.church_attended, v.invited_by, v.how_heard, v.expectations
            FROM registrations r
            JOIN attendees a ON a.id = r.attendee_id
            LEFT JOIN congregations c ON c.id = r.congregation_id
            LEFT JOIN visitor_details v ON v.registration_id = r.id
            WHERE $where
            ORDER BY c.name, a.full_name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $typeLabels = ['group' => 'With congregation', 'solo' => 'Came alone', 'visitor' => 'Visitor'];
    $data = [];
    foreach ($stmt as $r) {
        $data[] = [
            $r['reg_number'] ?: 'pending',
            attendee_title((bool)$r['is_member'], $r['gender']),
            $r['full_name'],
            $r['gender'] ? ucfirst($r['gender']) : '',
            $r['phone'],
            $r['email'],
            format_birthday($r['birth_day'] ? (int)$r['birth_day'] : null, $r['birth_month'] ? (int)$r['birth_month'] : null),
            $typeLabels[$r['category']] ?? $r['category'],
            $r['cong_name'],
            $r['cong_code'],
            $r['accommodation'] === 'camping' ? 'Camping' : ($r['accommodation'] === 'outside' ? 'Outside' : ''),
            $r['accommodation_note'],
            $r['home_state'],
            $r['home_city'],
            $r['church_attended'],
            $r['invited_by'],
            $r['how_heard'],
            $r['expectations'],
        ];
    }
    $filename = 'attendees' . ($ed ? '-' . $ed['year'] : '')
        . ($gender === 'male' ? '-brothers' : ($gender === 'female' ? '-sisters' : ''))
        . ($q !== '' ? '-filtered' : '') . '.csv';
    send_csv($filename, [
        'Reg No.', 'Title', 'Name', 'Gender', 'Phone', 'Email', 'Birthday', 'Type',
        'Congregation', 'Cong. Code', 'Accommodation', 'Accommodation Note',
        'Home State', 'Home City', 'Church Attended (visitor)', 'Invited By (visitor)',
        'How Heard (visitor)', 'Expectations (visitor)',
    ], $data);
}

function _find(int $id): array
{
    $stmt = db()->prepare(
        "SELECT r.*, a.full_name, a.gender, a.is_member, a.phone, a.email, a.birth_day, a.birth_month,
                a.home_state, a.home_city, c.name AS cong_name, c.code AS cong_code,
                v.church_attended, v.invited_by, v.how_heard, v.expectations
         FROM registrations r
         JOIN attendees a ON a.id = r.attendee_id
         LEFT JOIN congregations c ON c.id = r.congregation_id
         LEFT JOIN visitor_details v ON v.registration_id = r.id
         WHERE r.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Attendee not found.'); }
    return $row;
}

function show(string $id): void
{
    require_login();
    $a = _find((int)$id);
    $stmt = db()->prepare(
        "SELECT e.name AS ed_name, e.year, e.is_active, r.id AS reg_id, r.reg_number, r.category, c.name AS cong_name
         FROM registrations r
         JOIN editions e ON e.id = r.edition_id
         LEFT JOIN congregations c ON c.id = r.congregation_id
         WHERE r.attendee_id = ?
         ORDER BY e.year DESC"
    );
    $stmt->execute([$a['attendee_id']]);
    view('attendees/show', ['title' => 'Attendee', 'a' => $a, 'history' => $stmt->fetchAll()]);
}

function edit_form(string $id): void
{
    require_login();
    view('attendees/edit', ['title' => 'Edit attendee', 'a' => _find((int)$id)]);
}

function update(string $id): void
{
    require_login();
    verify_csrf();
    $a = _find((int)$id);
    db()->prepare(
        'UPDATE attendees SET full_name=?, gender=?, phone=?, email=?, birth_day=?, birth_month=?, home_state=?, home_city=? WHERE id=?'
    )->execute([
        input('full_name') ?: $a['full_name'],
        in_array(input('gender'), ['male','female'], true) ? input('gender') : null,
        input('phone') ?: null, input('email') ?: null,
        input('birth_day') !== '' ? (int)input('birth_day') : null,
        input('birth_month') !== '' ? (int)input('birth_month') : null,
        input('home_state') ?: null, input('home_city') ?: null,
        $a['attendee_id'],
    ]);
    db()->prepare('UPDATE registrations SET accommodation=?, accommodation_note=? WHERE id=?')->execute([
        in_array(input('accommodation'), ['camping','outside'], true) ? input('accommodation') : null,
        input('accommodation_note') ?: null, $a['id'],
    ]);
    flash('Attendee updated.');
    redirect('/attendees/' . $a['id']);
}
