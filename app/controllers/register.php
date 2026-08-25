<?php
/** Registration controller (online form path). */

require_once __DIR__ . '/../services/registration.php';

function form(): void
{
    require_login();
    $ed = active_edition();
    if (!$ed) {
        flash('No active edition. Ask an administrator to set up this year\'s event.', 'warn');
        redirect('/');
    }

    $category = input('category');
    if (!in_array($category, ['group', 'solo', 'visitor'], true)) {
        view('register/pick', ['title' => 'Register attendee']);
        return;
    }

    $congregations = db()->query('SELECT id, name, code FROM congregations ORDER BY name')->fetchAll();
    view('register/form', [
        'title' => 'Register attendee',
        'category' => $category,
        'congregations' => $congregations,
        'old' => [],
    ]);
}

function submit(): void
{
    require_login();
    verify_csrf();
    $u = current_user();

    if (input('category') === 'group' && isset($_POST['attendees']) && is_array($_POST['attendees'])) {
        submit_group_batch((int)$u['id']);
        return;
    }

    $data = [
        'category'          => input('category'),
        'attendee_id'       => input('attendee_id'),
        'full_name'         => input('full_name'),
        'gender'            => input('gender'),
        'phone'             => input('phone'),
        'email'             => input('email'),
        'birth_day'         => input('birth_day'),
        'birth_month'       => input('birth_month'),
        'home_state'        => input('home_state'),
        'home_city'         => input('home_city'),
        'accommodation'     => input('accommodation'),
        'accommodation_note'=> input('accommodation_note'),
        'congregation_id'   => input('congregation_id'),
        'congregation_name' => input('congregation_name'),
        'congregation_code' => input('congregation_code'),
        'minister_name'     => input('minister_name'),
        'minister_phone'    => input('minister_phone'),
        'address'           => input('address'),
        'church_attended'   => input('church_attended'),
        'invited_by'        => input('invited_by'),
        'how_heard'         => input('how_heard'),
        'expectations'      => input('expectations'),
    ];

    try {
        $result = create_registration($data, (int)$u['id']);
    } catch (RegistrationError $ex) {
        $congregations = db()->query('SELECT id, name, code FROM congregations ORDER BY name')->fetchAll();
        http_response_code(422);
        view('register/form', [
            'title' => 'Register attendee',
            'category' => $data['category'] ?: 'group',
            'congregations' => $congregations,
            'old' => $data,
            'error' => $ex->getMessage(),
        ]);
        return;
    }

    view('register/done', [
        'title' => 'Registered',
        'result' => $result,
        'data' => $data,
    ]);
}

/** Register several members of one congregation in a single desk submission. */
function submit_group_batch(int $userId): void
{
    $congData = [
        'category'          => 'group',
        'congregation_id'   => input('congregation_id'),
        'congregation_name' => input('congregation_name'),
        'minister_name'     => input('minister_name'),
        'minister_phone'    => input('minister_phone'),
        'address'           => input('address'),
    ];
    $people = array_values(array_filter($_POST['attendees'], 'is_array'));
    $error = null;

    if (!$people) {
        $error = 'Add at least one person before submitting.';
    } else {
        try {
            $congregation = find_or_create_congregation($congData);
            if (!$congregation) {
                throw new RegistrationError('Please select or enter a congregation.');
            }
        } catch (\Throwable $ex) {
            $error = $ex->getMessage();
        }
    }

    if ($error !== null) {
        http_response_code(422);
        view('register/form', [
            'title' => 'Register congregation members', 'category' => 'group',
            'congregations' => db()->query('SELECT id, name, code FROM congregations ORDER BY name')->fetchAll(),
            'old' => array_merge($congData, ['attendees' => $people]), 'error' => $error,
        ]);
        return;
    }

    $batchId = generate_uuid();
    $results = [];
    $errors = [];
    foreach ($people as $i => $person) {
        $data = array_merge($congData, [
            'congregation_id'   => $congregation['id'],
            'attendee_id'       => $person['attendee_id'] ?? '',
            'full_name'         => trim($person['full_name'] ?? ''),
            'gender'            => $person['gender'] ?? '',
            'phone'             => trim($person['phone'] ?? ''),
            'email'             => trim($person['email'] ?? ''),
            'birth_day'         => $person['birth_day'] ?? '',
            'birth_month'       => $person['birth_month'] ?? '',
            'accommodation'     => $person['accommodation'] ?? 'camping',
            'accommodation_note'=> trim($person['accommodation_note'] ?? ''),
            'batch_id'          => $batchId,
        ]);
        try {
            if ($data['phone'] !== '') {
                $stmt = db()->prepare(
                    'SELECT r.reg_number FROM registrations r
                     JOIN attendees a ON a.id = r.attendee_id
                     WHERE r.edition_id = ? AND a.phone = ? LIMIT 1'
                );
                $ed = active_edition();
                $stmt->execute([(int)$ed['id'], $data['phone']]);
                if ($existing = $stmt->fetchColumn()) {
                    $results[] = [
                        'status' => 'already_registered', 'reg_number' => $existing,
                        'full_name' => $data['full_name'], 'gender' => $data['gender'],
                    ];
                    continue;
                }
            }
            $result = create_registration($data, $userId);
            $result['full_name'] = $data['full_name'];
            $result['gender'] = $data['gender'];
            $results[] = $result;
        } catch (RegistrationError $ex) {
            $errors[] = 'Person ' . ($i + 1) . ' (' . ($data['full_name'] ?: 'unnamed') . '): ' . $ex->getMessage();
        }
    }

    view('register/batch_done', [
        'title' => 'Group registered', 'results' => $results,
        'errors' => $errors, 'congregation' => $congregation,
    ]);
}
