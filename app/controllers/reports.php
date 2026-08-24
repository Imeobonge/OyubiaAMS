<?php
/** Reports controller — print-optimized per-congregation roster (Print → Save as PDF). */

function congregation_roster(string $id): void
{
    require_login();
    $stmt = db()->prepare('SELECT * FROM congregations WHERE id = ?');
    $stmt->execute([(int)$id]);
    $c = $stmt->fetch();
    if (!$c) { http_response_code(404); exit('Congregation not found.'); }

    $ed = active_edition();
    $rows = [];
    $gender = in_array(input('gender'), ['male', 'female'], true) ? input('gender') : '';
    if ($ed) {
        $stmt = db()->prepare(
            "SELECT r.reg_number, r.category, r.accommodation, a.full_name, a.gender, a.is_member, a.phone, a.email
             FROM registrations r JOIN attendees a ON a.id = r.attendee_id
             WHERE r.congregation_id = ? AND r.edition_id = ?" . ($gender ? " AND a.gender = ?" : '') . "
             ORDER BY CASE a.gender WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END, a.full_name"
        );
        $params = [$c['id'], $ed['id']];
        if ($gender) { $params[] = $gender; }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
    view_raw('reports/roster', ['c' => $c, 'rows' => $rows, 'ed' => $ed, 'gender' => $gender]);
}

function congregation_roster_csv(string $id): void
{
    require_login();
    $stmt = db()->prepare('SELECT * FROM congregations WHERE id = ?');
    $stmt->execute([(int)$id]);
    $c = $stmt->fetch();
    if (!$c) { http_response_code(404); exit('Congregation not found.'); }

    $ed = active_edition();
    $rows = [];
    $gender = in_array(input('gender'), ['male', 'female'], true) ? input('gender') : '';
    if ($ed) {
        $stmt = db()->prepare(
            "SELECT r.reg_number, r.category, r.accommodation, r.accommodation_note,
                    a.full_name, a.gender, a.is_member, a.phone, a.email,
                    a.birth_day, a.birth_month, a.home_state, a.home_city
             FROM registrations r JOIN attendees a ON a.id = r.attendee_id
             WHERE r.congregation_id = ? AND r.edition_id = ?" . ($gender ? " AND a.gender = ?" : '') . "
             ORDER BY CASE a.gender WHEN 'male' THEN 1 WHEN 'female' THEN 2 ELSE 3 END, a.full_name"
        );
        $params = [$c['id'], $ed['id']];
        if ($gender) { $params[] = $gender; }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    $typeLabels = ['group' => 'With congregation', 'solo' => 'Came alone', 'visitor' => 'Visitor'];
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['reg_number'] ?: 'pending',
            attendee_title((bool)$r['is_member'], $r['gender']),
            $r['full_name'],
            $r['gender'] ? ucfirst($r['gender']) : '',
            $r['phone'],
            $r['email'],
            format_birthday($r['birth_day'] ? (int)$r['birth_day'] : null, $r['birth_month'] ? (int)$r['birth_month'] : null),
            $typeLabels[$r['category']] ?? $r['category'],
            $r['accommodation'] === 'camping' ? 'Camping' : ($r['accommodation'] === 'outside' ? 'Outside' : ''),
            $r['accommodation_note'],
            $r['home_state'],
            $r['home_city'],
        ];
    }
    $filename = preg_replace('/[^A-Za-z0-9]+/', '-', $c['code'] . '-' . $c['name'])
        . ($ed ? '-' . $ed['year'] : '')
        . ($gender === 'male' ? '-brothers' : ($gender === 'female' ? '-sisters' : '')) . '.csv';
    send_csv($filename, [
        'Reg No.', 'Title', 'Name', 'Gender', 'Phone', 'Email', 'Birthday',
        'Type', 'Accommodation', 'Accommodation Note', 'Home State', 'Home City',
    ], $data);
}
