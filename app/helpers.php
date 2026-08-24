<?php
/**
 * Shared helper functions.
 */

/** HTML-escape. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Build a URL respecting base_url (for subfolder installs). */
function url(string $path = ''): string
{
    $base = rtrim(config()['base_url'] ?? '', '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

/**
 * Version stamp for static assets. Bump this (alongside the service-worker
 * cache name) whenever CSS/JS changes so browsers fetch the new file instead
 * of a stale cached copy.
 */
const ASSET_VERSION = '11';

/** Build a versioned URL for a CSS/JS asset (cache-busting). */
function asset(string $path): string
{
    $u = url($path);
    return $u . (str_contains($u, '?') ? '&' : '?') . 'v=' . ASSET_VERSION;
}

/**
 * Normalize a congregation name for comparison only (not for storage).
 * Treats "COC" and "Church of Christ" as identical so that
 * "COC Uniuyo" and "Church of Christ Uniuyo" match the same record.
 */
function normalize_cong_name(string $name): string
{
    $name = trim($name);
    // Collapse all COC / C.O.C variants → canonical phrase, then lowercase.
    $name = preg_replace('/\bC\.?O\.?C\.?\b/i', 'church of christ', $name);
    return strtolower($name);
}

/** Generate a random UUID v4. */
function generate_uuid(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Redirect and stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Read a request input (GET/POST), trimmed. */
function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

/** Send a JSON response and stop. */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ----------------------------- Flash messages ----------------------------- */

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* --------------------------------- CSRF ----------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Session expired or invalid request token. Go back and try again.');
    }
}

/* ------------------------------ Domain logic ------------------------------ */

/** Derive a display title from membership + gender. */
function attendee_title(bool $isMember, ?string $gender): string
{
    if (!$isMember) {
        return ''; // visitors have no title
    }
    if ($gender === 'male')   return 'Bro.';
    if ($gender === 'female') return 'Sis.';
    return '';
}

/** Full display name with title. */
function display_name(array $attendee): string
{
    $title = attendee_title((bool)$attendee['is_member'], $attendee['gender'] ?? null);
    return trim($title . ' ' . $attendee['full_name']);
}

/** The currently active edition row, or null. */
function active_edition(): ?array
{
    static $ed = null;
    if ($ed === null) {
        $stmt = db()->query('SELECT * FROM editions WHERE is_active = 1 LIMIT 1');
        $ed = $stmt->fetch() ?: false;
    }
    return $ed ?: null;
}

/**
 * Auto-generate a congregation code from its name.
 * "Uyo church of Christ" -> "UYO". Falls back to first letters.
 */
function suggest_congregation_code(string $name): string
{
    $name = strtoupper($name);
    // strip common filler words
    $name = preg_replace('/\b(CHURCH|OF|CHRIST|THE|CONGREGATION|ASSEMBLY)\b/', ' ', $name);
    $name = preg_replace('/[^A-Z0-9 ]/', ' ', $name);
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return 'CG';
    }
    if (count($words) === 1) {
        return substr($words[0], 0, 3);
    }
    // first letter of up to 4 words
    $code = '';
    foreach (array_slice($words, 0, 4) as $w) {
        $code .= $w[0];
    }
    return $code;
}

/** Ensure a code is unique, appending a number if needed. */
function unique_congregation_code(string $base, ?int $ignoreId = null): string
{
    $base = preg_replace('/[^A-Z0-9]/', '', strtoupper($base)) ?: 'CG';
    $base = substr($base, 0, 12);
    $code = $base;
    $n = 1;
    while (true) {
        $sql = 'SELECT id FROM congregations WHERE code = ?' . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$code, $ignoreId] : [$code];
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $code;
        }
        $n++;
        $code = $base . $n;
    }
}

/**
 * Atomically allocate the next reg number for an edition. The sequence runs
 * continuously across the whole event (1, 2, 3 …), so the highest number equals
 * the total number of people registered for that edition.
 * Returns ['reg_number' => 'OYCF-2026-0014', 'seq' => 14].
 */
function allocate_reg_number(int $editionId, int $year): array
{
    $pdo = db();
    // A single counter per edition (keyed 'ALL') drives the running number.
    $pdo->prepare(
        'INSERT INTO reg_counters (edition_id, congregation_key, last_seq)
         VALUES (?, "ALL", 1)
         ON DUPLICATE KEY UPDATE last_seq = last_seq + 1'
    )->execute([$editionId]);

    $stmt = $pdo->prepare(
        'SELECT last_seq FROM reg_counters WHERE edition_id = ? AND congregation_key = "ALL"'
    );
    $stmt->execute([$editionId]);
    $seq = (int)$stmt->fetchColumn();

    $prefix = config()['reg_prefix'] ?? 'OYCF';
    $regNumber = sprintf('%s-%d-%04d', $prefix, $year, $seq);
    return ['reg_number' => $regNumber, 'seq' => $seq];
}

/**
 * Stream rows as a downloadable CSV file and stop.
 * $header is an array of column titles; $rows is an array of flat arrays.
 */
function send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
    // Pass separator/enclosure/escape explicitly: silences PHP 8.4+ deprecation
    // and produces RFC-4180-compliant output (empty escape = no backslash escaping).
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, $r, ',', '"', '');
    }
    fclose($out);
    exit;
}

/** Format a stored birthday (day + month, no year) for display/export. */
function format_birthday(?int $day, ?int $month): string
{
    if (!$day || !$month) {
        return '';
    }
    $months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
    return $day . ' ' . ($months[$month] ?? '');
}

/** Render a view file inside the main layout. */
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit("View not found: $name");
    }
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

/** Render a standalone view (no layout) — for print/PDF pages. */
function view_raw(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit("View not found: $name");
    }
    require $viewFile;
}
