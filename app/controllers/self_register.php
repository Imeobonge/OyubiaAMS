<?php
/**
 * Self-registration controller — public QR check-in at /join (no login),
 * plus admin pages for the QR toggle and the group-arrivals log.
 *
 * Group self-registration: one congregation leader registers the whole bus at
 * once. All rows share a batch_id (UUID) so admins can view them as a unit.
 *
 * Solo / visitor: single-person form, unchanged from before.
 *
 * registered_by = NULL on all self-reg rows.
 */

require_once __DIR__ . '/../services/registration.php';

const SELF_REG_CATEGORIES = ['group', 'solo', 'visitor'];

/** Active edition only if self-registration is currently open; else null. */
function _self_gate(): ?array
{
    $ed = active_edition();
    if (!$ed || (int)($ed['self_register_open'] ?? 0) !== 1) {
        return null;
    }
    return $ed;
}

/** All congregations for the searchable select. */
function _self_congregations(): array
{
    return db()->query('SELECT id, name, code FROM congregations ORDER BY name')->fetchAll();
}

/** Category picker (or the "closed" page). */
function pick(): void
{
    if (!_self_gate()) {
        view_raw('public/self_closed', ['reason' => active_edition() ? 'closed' : 'no_edition']);
        return;
    }
    view_raw('public/self_pick', []);
}

/** The self-registration form for a category. */
function form(string $category): void
{
    if (!_self_gate()) {
        view_raw('public/self_closed', ['reason' => active_edition() ? 'closed' : 'no_edition']);
        return;
    }
    if (!in_array($category, SELF_REG_CATEGORIES, true)) {
        redirect('/join');
    }
    view_raw('public/self_form', [
        'category'      => $category,
        'congregations' => _self_congregations(),
        'old'           => [],
        'error'         => null,
    ]);
}

/** Handle a public self-registration submission. */
function submit(): void
{
    $ed = _self_gate();
    if (!$ed) {
        view_raw('public/self_closed', ['reason' => active_edition() ? 'closed' : 'no_edition']);
        return;
    }
    verify_csrf();

    $category = input('category');
    if (!in_array($category, SELF_REG_CATEGORIES, true)) {
        redirect('/join');
    }

    // Honeypot
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        view_raw('public/self_done', [
            'batch'   => false,
            'result'  => ['reg_number' => 'PENDING'],
            'data'    => ['category' => $category],
        ]);
        return;
    }

    if ($category === 'group') {
        _submit_group($ed);
    } else {
        _submit_single($ed, $category);
    }
}

/** Register one person (solo or visitor). */
function _submit_single(array $ed, string $category): void
{
    $data = [
        'category'          => $category,
        'full_name'         => input('full_name'),
        'gender'            => input('gender'),
        'phone'             => input('phone'),
        'email'             => input('email'),
        'birth_day'         => input('birth_day'),
        'birth_month'       => input('birth_month'),
        'accommodation'     => input('accommodation'),
        'congregation_id'   => input('congregation_id'),
        'congregation_name' => input('congregation_name'),
        'church_attended'   => input('church_attended'),
        'invited_by'        => input('invited_by'),
        'how_heard'         => input('how_heard'),
        'expectations'      => input('expectations'),
    ];

    // Phone duplicate guard
    $phone = trim($data['phone']);
    if ($phone !== '') {
        $stmt = db()->prepare(
            'SELECT r.reg_number FROM registrations r
             JOIN attendees a ON a.id = r.attendee_id
             WHERE r.edition_id = ? AND a.phone = ? LIMIT 1'
        );
        $stmt->execute([(int)$ed['id'], $phone]);
        if ($existing = $stmt->fetchColumn()) {
            view_raw('public/self_done', [
                'batch'  => false,
                'result' => ['status' => 'already_registered', 'reg_number' => $existing],
                'data'   => $data,
            ]);
            return;
        }
    }

    try {
        $result = create_registration($data, null);
    } catch (RegistrationError $ex) {
        http_response_code(422);
        view_raw('public/self_form', [
            'category'      => $category,
            'congregations' => _self_congregations(),
            'old'           => $data,
            'error'         => $ex->getMessage(),
        ]);
        return;
    }

    view_raw('public/self_done', ['batch' => false, 'result' => $result, 'data' => $data]);
}

/** Register a whole congregation group at once. */
function _submit_group(array $ed): void
{
    $congData = [
        'category'          => 'group',
        'congregation_id'   => input('congregation_id'),
        'congregation_name' => input('congregation_name'),
        'minister_name'     => input('minister_name'),
        'minister_phone'    => input('minister_phone'),
        'address'           => input('address'),
    ];

    // When adding a new congregation (no existing one selected), all three
    // detail fields are required so the records are complete from day one.
    $isNewCong = empty($congData['congregation_id']);
    if ($isNewCong) {
        $missing = [];
        if (trim($congData['congregation_name']) === '') { $missing[] = 'congregation name'; }
        if (trim($congData['minister_name'])     === '') { $missing[] = 'minister\'s name'; }
        if (trim($congData['minister_phone'])    === '') { $missing[] = 'minister\'s phone'; }
        if (trim($congData['address'])           === '') { $missing[] = 'congregation address'; }
        if ($missing) {
            http_response_code(422);
            view_raw('public/self_form', [
                'category'      => 'group',
                'congregations' => _self_congregations(),
                'old'           => $congData,
                'error'         => 'Please fill in: ' . implode(', ', $missing) . '.',
            ]);
            return;
        }
    }

    // Resolve congregation — fail fast before touching any person rows.
    try {
        $congregation = find_or_create_congregation($congData);
    } catch (\Throwable $ex) {
        $congregation = null;
    }
    if (!$congregation) {
        http_response_code(422);
        view_raw('public/self_form', [
            'category'      => 'group',
            'congregations' => _self_congregations(),
            'old'           => $congData,
            'error'         => 'Please select or enter your congregation name.',
        ]);
        return;
    }

    $attendeesRaw = $_POST['attendees'] ?? [];
    if (!is_array($attendeesRaw) || count($attendeesRaw) === 0) {
        http_response_code(422);
        view_raw('public/self_form', [
            'category'      => 'group',
            'congregations' => _self_congregations(),
            'old'           => $congData,
            'error'         => 'Add at least one person before submitting.',
        ]);
        return;
    }

    $batchId = generate_uuid();
    $results = [];
    $errors  = [];

    foreach (array_values($attendeesRaw) as $idx => $person) {
        $phone = trim($person['phone'] ?? '');

        // Phone duplicate guard per person
        if ($phone !== '') {
            $stmt = db()->prepare(
                'SELECT r.reg_number FROM registrations r
                 JOIN attendees a ON a.id = r.attendee_id
                 WHERE r.edition_id = ? AND a.phone = ? LIMIT 1'
            );
            $stmt->execute([(int)$ed['id'], $phone]);
            if ($existing = $stmt->fetchColumn()) {
                $results[] = [
                    'status'     => 'already_registered',
                    'reg_number' => $existing,
                    'full_name'  => trim($person['full_name'] ?? ''),
                    'gender'     => $person['gender'] ?? '',
                ];
                continue;
            }
        }

        $data = array_merge($congData, [
            'full_name'     => trim($person['full_name'] ?? ''),
            'gender'        => $person['gender'] ?? '',
            'phone'         => $phone,
            'email'         => trim($person['email'] ?? ''),
            'birth_day'     => $person['birth_day'] ?? '',
            'birth_month'   => $person['birth_month'] ?? '',
            'accommodation' => 'camping',
            'batch_id'      => $batchId,
        ]);

        try {
            $reg = create_registration($data, null);
            $reg['full_name'] = $data['full_name'];
            $reg['gender']    = $data['gender'];
            $results[] = $reg;
        } catch (RegistrationError $ex) {
            $errors[] = 'Person ' . ($idx + 1) . ' (' . e($data['full_name']) . '): ' . $ex->getMessage();
        }
    }

    view_raw('public/self_done', [
        'batch'        => true,
        'results'      => $results,
        'congregation' => $congregation['name'],
        'errors'       => $errors,
        'data'         => ['category' => 'group'],
    ]);
}

/* ------------------------------- Admin ------------------------------- */

/** Admin page: QR code, public link, open/close toggle. */
function admin_page(): void
{
    require_admin();
    $ed = active_edition();
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $joinUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('/join');
    view('admin/self_register', [
        'title'   => 'Self check-in',
        'edition' => $ed,
        'joinUrl' => $joinUrl,
        'isOpen'  => $ed ? (int)($ed['self_register_open'] ?? 0) === 1 : false,
    ]);
}

/** Flip self-registration on/off for the active edition. */
function toggle(): void
{
    require_admin();
    verify_csrf();
    $ed = active_edition();
    if (!$ed) {
        flash('Set up and activate an edition first.', 'error');
        redirect('/admin/self-register');
    }
    $new = (int)($ed['self_register_open'] ?? 0) === 1 ? 0 : 1;
    db()->prepare('UPDATE editions SET self_register_open = ? WHERE id = ?')->execute([$new, (int)$ed['id']]);
    flash($new ? 'Self check-in is now OPEN — attendees can register themselves.' : 'Self check-in is now closed.');
    redirect('/admin/self-register');
}

/** Admin: group arrivals log (all batches for the active edition). */
function batches(): void
{
    require_admin();
    $ed = active_edition();

    $batchList = [];
    if ($ed) {
        // One row per batch: congregation, arrival time, headcount.
        $stmt = db()->prepare(
            'SELECT r.batch_id,
                    MIN(r.registered_at) AS arrived_at,
                    c.name AS congregation_name,
                    COUNT(*) AS headcount
             FROM registrations r
             LEFT JOIN congregations c ON c.id = r.congregation_id
             WHERE r.batch_id IS NOT NULL AND r.edition_id = ?
             GROUP BY r.batch_id, c.name
             ORDER BY arrived_at DESC'
        );
        $stmt->execute([(int)$ed['id']]);
        $rows = $stmt->fetchAll();

        // Load members for each batch.
        $memberStmt = db()->prepare(
            'SELECT a.full_name, a.gender, r.reg_number, r.reg_seq
             FROM registrations r
             JOIN attendees a ON a.id = r.attendee_id
             WHERE r.batch_id = ?
             ORDER BY r.reg_seq'
        );
        foreach ($rows as $b) {
            $memberStmt->execute([$b['batch_id']]);
            $b['members'] = $memberStmt->fetchAll();
            $batchList[]  = $b;
        }
    }

    view('admin/self_batches', [
        'title'     => 'Group arrivals',
        'edition'   => $ed,
        'batchList' => $batchList,
    ]);
}
