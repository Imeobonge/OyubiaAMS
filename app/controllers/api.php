<?php
/** JSON API — offline sync + bootstrap data for the PWA. */

require_once __DIR__ . '/../services/registration.php';

/** Read JSON body. */
function _json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** CSRF check for JSON requests (token in body or X-CSRF header). */
function _api_csrf(array $body): void
{
    $sent = $body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_response(['error' => 'csrf'], 419);
    }
}

/**
 * POST /api/sync
 * Body: { _csrf, items: [ {client_uuid, category, full_name, ...}, ... ] }
 * Returns: { results: [ {client_uuid, status, reg_number|error} ] }
 */
function sync(): void
{
    $u = require_login(); // returns JSON 401 if not authed
    $body = _json_body();
    _api_csrf($body);

    $items = $body['items'] ?? [];
    if (!is_array($items)) {
        json_response(['error' => 'bad_request'], 400);
    }

    $results = [];
    foreach ($items as $item) {
        if (!is_array($item)) { continue; }
        $uuid = $item['client_uuid'] ?? null;
        try {
            $res = create_registration($item, (int)$u['id']);
            $results[] = [
                'client_uuid' => $uuid,
                'status' => $res['status'],
                'reg_number' => $res['reg_number'] ?? null,
                'registration_id' => $res['registration_id'] ?? null,
            ];
        } catch (RegistrationError $e) {
            $results[] = ['client_uuid' => $uuid, 'status' => 'error', 'error' => $e->getMessage()];
        }
    }
    json_response(['results' => $results]);
}

/**
 * GET /api/attendees/search?q=...
 * Finds existing people (across all years) to link as returning attendees.
 */
function attendees_search(): void
{
    require_login();
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        json_response(['results' => []]);
    }
    $ed = active_edition();
    $like = '%' . $q . '%';
    $stmt = db()->prepare(
        "SELECT a.id, a.full_name, a.gender, a.is_member, a.phone, a.email,
                a.birth_day, a.birth_month, a.home_state, a.home_city,
                (SELECT GROUP_CONCAT(e.year ORDER BY e.year SEPARATOR ', ')
                   FROM registrations r JOIN editions e ON e.id = r.edition_id
                   WHERE r.attendee_id = a.id) AS years,
                (SELECT COUNT(*) FROM registrations r
                   WHERE r.attendee_id = a.id AND r.edition_id = ?) AS in_active
         FROM attendees a
         WHERE a.full_name LIKE ? OR a.phone LIKE ?
         ORDER BY a.full_name LIMIT 10"
    );
    $stmt->execute([$ed ? (int)$ed['id'] : 0, $like, $like]);

    $results = [];
    foreach ($stmt as $a) {
        $results[] = [
            'id' => (int)$a['id'],
            'full_name' => $a['full_name'],
            'gender' => $a['gender'],
            'is_member' => (int)$a['is_member'],
            'phone' => $a['phone'],
            'email' => $a['email'],
            'birth_day' => $a['birth_day'] ? (int)$a['birth_day'] : null,
            'birth_month' => $a['birth_month'] ? (int)$a['birth_month'] : null,
            'home_state' => $a['home_state'],
            'home_city' => $a['home_city'],
            'years' => $a['years'] ?: '',
            'in_active' => (int)$a['in_active'] > 0,
        ];
    }
    json_response(['results' => $results]);
}

/**
 * GET /api/bootstrap
 * Active edition + congregations + a fresh CSRF token, for offline caching.
 */
function bootstrap(): void
{
    require_login();
    $ed = active_edition();
    $congs = db()->query('SELECT id, name, code FROM congregations ORDER BY name')->fetchAll();
    json_response([
        'edition' => $ed ? ['id' => (int)$ed['id'], 'name' => $ed['name'], 'year' => (int)$ed['year']] : null,
        'congregations' => $congs,
        'csrf' => csrf_token(),
        'server_time' => date('c'),
    ]);
}
