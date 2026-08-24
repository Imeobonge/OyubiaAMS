<?php
/**
 * Forms service — shared logic for the admin builder (controllers/forms.php)
 * and the public form pages (controllers/public_form.php).
 *
 * Tables: forms, form_fields, form_responses, form_answers (see schema.sql).
 */

class FormError extends RuntimeException {}

/** The supported question types and their display labels. */
function form_field_types(): array
{
    return [
        'short_text'      => 'Short text',
        'paragraph'       => 'Paragraph',
        'multiple_choice' => 'Multiple choice',
        'checkboxes'      => 'Checkboxes',
        'dropdown'        => 'Dropdown',
        'email'           => 'Email',
        'phone'           => 'Phone',
        'number'          => 'Number',
        'date'            => 'Date',
        'file_upload'     => 'File upload',
    ];
}

/** Choice-based types that carry an options list. */
function form_field_has_options(string $type): bool
{
    return in_array($type, ['multiple_choice', 'checkboxes', 'dropdown'], true);
}

/* --------------------------- File uploads --------------------------- */

/** Max upload size in bytes (config override: upload_max_bytes). */
function form_upload_max_bytes(): int
{
    $cfg = config()['upload_max_bytes'] ?? null;
    return $cfg ? (int)$cfg : 5 * 1024 * 1024; // 5 MB default
}

/**
 * Allowed upload types: extension => list of acceptable MIME types.
 * Images + PDF only — deliberately excludes anything executable.
 */
function form_upload_allowed(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'pdf'  => ['application/pdf'],
    ];
}

/** The "accept" attribute for the file input. */
function form_upload_accept(): string
{
    return '.jpg,.jpeg,.png,.gif,.webp,.heic,.pdf,image/*,application/pdf';
}

/**
 * Absolute path to the (private) upload directory — created if missing.
 * Defaults to "<one level above the app folder>/form_uploads", which sits
 * OUTSIDE every web root in our cPanel layout. Override with config 'upload_dir'.
 */
function form_upload_dir(): string
{
    $dir = config()['upload_dir'] ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'form_uploads');
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        // Defense in depth: deny web access if this ever lands under a web root.
        @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\nDeny from all\n");
    }
    return $dir;
}

/**
 * Validate + store one uploaded file. $file is a single-file slice of $_FILES
 * (keys: name, type, tmp_name, error, size). Returns answer metadata array
 * ['name','stored','size','mime'] or throws FormError.
 */
function store_uploaded_file(array $file, string $label): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new FormError('“' . $label . '”: that file is too large.');
    }
    if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new FormError('“' . $label . '”: the file could not be uploaded. Please try again.');
    }
    if (($file['size'] ?? 0) > form_upload_max_bytes()) {
        throw new FormError('“' . $label . '”: file exceeds the ' . round(form_upload_max_bytes() / 1048576) . ' MB limit.');
    }

    $allowed = form_upload_allowed();
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new FormError('“' . $label . '”: only images and PDF files are allowed.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) { finfo_close($finfo); }
    if (!in_array($mime, $allowed[$ext], true)) {
        throw new FormError('“' . $label . '”: that file’s contents don’t match its type.');
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest   = form_upload_dir() . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new FormError('“' . $label . '”: could not save the file. Please try again.');
    }
    @chmod($dest, 0600);

    return [
        'name'   => mb_substr(preg_replace('/[\r\n"]+/', '', (string)$file['name']), 0, 200),
        'stored' => $stored,
        'size'   => (int)$file['size'],
        'mime'   => $mime,
    ];
}

/** Extract a single file's slice from $_FILES['upload'] for a given field id. */
function uploaded_file_slice(int $fieldId): ?array
{
    if (empty($_FILES['upload']) || !isset($_FILES['upload']['name'][$fieldId])) {
        return null;
    }
    $f = $_FILES['upload'];
    if (($f['error'][$fieldId] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return [
        'name'     => $f['name'][$fieldId] ?? '',
        'type'     => $f['type'][$fieldId] ?? '',
        'tmp_name' => $f['tmp_name'][$fieldId] ?? '',
        'error'    => $f['error'][$fieldId] ?? UPLOAD_ERR_NO_FILE,
        'size'     => $f['size'][$fieldId] ?? 0,
    ];
}

/** Resolve a stored file's absolute path from an answer's JSON value (safe). */
function resolve_upload_path(string $answerValue): ?array
{
    $meta = json_decode($answerValue, true);
    if (!is_array($meta) || empty($meta['stored'])) {
        return null;
    }
    $stored = basename((string)$meta['stored']); // strip any path component
    $path = form_upload_dir() . DIRECTORY_SEPARATOR . $stored;
    if (!is_file($path)) {
        return null;
    }
    return ['path' => $path, 'name' => $meta['name'] ?? $stored, 'mime' => $meta['mime'] ?? 'application/octet-stream'];
}

/** Generate a short, unguessable, unique slug for a form's public URL. */
function generate_form_slug(): string
{
    $pdo = db();
    do {
        $slug = substr(bin2hex(random_bytes(6)), 0, 10); // 10 hex chars
        $stmt = $pdo->prepare('SELECT 1 FROM forms WHERE slug = ?');
        $stmt->execute([$slug]);
    } while ($stmt->fetchColumn());
    return $slug;
}

/** Load a form row by id (admin) or by slug (public). Returns null if absent. */
function find_form(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM forms WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
function find_form_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM forms WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** Ordered fields for a form, with options decoded to arrays. */
function form_fields(int $formId): array
{
    $stmt = db()->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY position, id');
    $stmt->execute([$formId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['options'] = $r['options'] ? (json_decode($r['options'], true) ?: []) : [];
    }
    return $rows;
}

/**
 * Normalize the posted fields[] array into clean field definitions.
 * Each item: ['label','help_text','type','options'(array),'is_required'(0/1)].
 * Drops fields with an empty label. Throws if nothing usable remains.
 */
function normalize_form_fields($posted): array
{
    $types = form_field_types();
    $out = [];
    foreach ((array)$posted as $f) {
        if (!is_array($f)) {
            continue;
        }
        $label = trim((string)($f['label'] ?? ''));
        if ($label === '') {
            continue; // skip blank rows
        }
        $type = (string)($f['type'] ?? 'short_text');
        if (!isset($types[$type])) {
            $type = 'short_text';
        }
        $options = [];
        if (form_field_has_options($type)) {
            foreach ((array)($f['options'] ?? []) as $opt) {
                $opt = trim((string)$opt);
                if ($opt !== '') {
                    $options[] = mb_substr($opt, 0, 200);
                }
            }
            if (!$options) {
                throw new FormError('“' . $label . '” is a choice question, so it needs at least one option.');
            }
        }
        $out[] = [
            'label'       => mb_substr($label, 0, 255),
            'help_text'   => mb_substr(trim((string)($f['help_text'] ?? '')), 0, 255) ?: null,
            'type'        => $type,
            'options'     => $options,
            'is_required' => !empty($f['is_required']) ? 1 : 0,
        ];
    }
    if (!$out) {
        throw new FormError('Add at least one question with a label before saving.');
    }
    return $out;
}

/**
 * Create or update a form together with its fields, in one transaction.
 * $id is null to create. Returns the form id.
 */
function save_form_with_fields(?int $id, array $data, array $fields, ?int $userId): int
{
    $pdo = db();
    $title = trim($data['title'] ?? '');
    if ($title === '') {
        throw new FormError('A form title is required.');
    }
    $description = trim($data['description'] ?? '') ?: null;
    $status = in_array($data['status'] ?? '', ['draft', 'open', 'closed'], true) ? $data['status'] : 'draft';

    $pdo->beginTransaction();
    try {
        if ($id) {
            $pdo->prepare('UPDATE forms SET title=?, description=?, status=? WHERE id=?')
                ->execute([$title, $description, $status, $id]);
            $pdo->prepare('DELETE FROM form_fields WHERE form_id=?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO forms (title, description, slug, status, created_by) VALUES (?,?,?,?,?)')
                ->execute([$title, $description, generate_form_slug(), $status, $userId]);
            $id = (int)$pdo->lastInsertId();
        }

        $ins = $pdo->prepare(
            'INSERT INTO form_fields (form_id, label, help_text, type, options, is_required, position)
             VALUES (?,?,?,?,?,?,?)'
        );
        $pos = 0;
        foreach ($fields as $f) {
            $ins->execute([
                $id, $f['label'], $f['help_text'], $f['type'],
                $f['options'] ? json_encode(array_values($f['options'])) : null,
                $f['is_required'], $pos++,
            ]);
        }
        $pdo->commit();
    } catch (\Throwable $ex) {
        $pdo->rollBack();
        if ($ex instanceof FormError) {
            throw $ex;
        }
        throw new FormError('Could not save the form: ' . $ex->getMessage());
    }
    return $id;
}

/**
 * Validate and store a public submission for $form.
 * $post is the raw $_POST. Returns the new response id.
 * Throws FormError (with a user-safe message) on validation failure.
 */
function record_response(array $form, array $post): int
{
    $pdo = db();
    $fields = form_fields((int)$form['id']);
    $answers = []; // field_id => stored string value

    foreach ($fields as $f) {
        $raw = $post['field'][$f['id']] ?? null;

        if ($f['type'] === 'file_upload') {
            $slice = uploaded_file_slice((int)$f['id']);
            if (!$slice) {
                if ($f['is_required']) {
                    throw new FormError('Please attach a file for: “' . $f['label'] . '”.');
                }
                $answers[$f['id']] = null;
                continue;
            }
            $meta = store_uploaded_file($slice, $f['label']);
            $answers[$f['id']] = json_encode($meta);
            continue;
        }

        if ($f['type'] === 'checkboxes') {
            $picked = array_values(array_filter(array_map(
                fn($v) => trim((string)$v),
                (array)($raw ?? [])
            ), fn($v) => $v !== ''));
            // keep only known options
            $picked = array_values(array_intersect($picked, $f['options']));
            if ($f['is_required'] && !$picked) {
                throw new FormError('Please answer: “' . $f['label'] . '”.');
            }
            $answers[$f['id']] = $picked ? json_encode($picked) : null;
            continue;
        }

        $val = is_array($raw) ? '' : trim((string)$raw);
        if ($f['is_required'] && $val === '') {
            throw new FormError('Please answer: “' . $f['label'] . '”.');
        }
        if ($val !== '') {
            // type-specific validation
            if ($f['type'] === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                throw new FormError('“' . $f['label'] . '” needs a valid email address.');
            }
            if ($f['type'] === 'number' && !is_numeric($val)) {
                throw new FormError('“' . $f['label'] . '” needs a number.');
            }
            if (in_array($f['type'], ['multiple_choice', 'dropdown'], true)
                && !in_array($val, $f['options'], true)) {
                throw new FormError('Please choose a valid option for “' . $f['label'] . '”.');
            }
            $val = mb_substr($val, 0, 5000);
        }
        $answers[$f['id']] = $val !== '' ? $val : null;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO form_responses (form_id, respondent_ip) VALUES (?,?)')
            ->execute([(int)$form['id'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null]);
        $respId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO form_answers (response_id, field_id, value) VALUES (?,?,?)');
        foreach ($answers as $fieldId => $value) {
            $ins->execute([$respId, $fieldId, $value]);
        }
        $pdo->commit();
    } catch (\Throwable $ex) {
        $pdo->rollBack();
        throw new FormError('Could not save your response. Please try again.');
    }
    return $respId;
}

/** Number of responses for a form. */
function form_response_count(int $formId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM form_responses WHERE form_id = ?');
    $stmt->execute([$formId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Render a stored answer value as a plain display string.
 * Checkboxes (JSON arrays) become comma-separated text.
 */
function format_answer(?string $value, string $type): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if ($type === 'checkboxes') {
        $arr = json_decode($value, true);
        return is_array($arr) ? implode(', ', $arr) : $value;
    }
    if ($type === 'file_upload') {
        $meta = json_decode($value, true);
        return is_array($meta) ? ($meta['name'] ?? 'file') : $value;
    }
    return $value;
}
