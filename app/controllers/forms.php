<?php
/** Forms controller (admin) — build forms, manage status, view/export responses. */

require_once __DIR__ . '/../services/forms.php';

/** List all forms with response counts. */
function index(): void
{
    require_admin();
    $rows = db()->query(
        'SELECT f.*,
                (SELECT COUNT(*) FROM form_responses r WHERE r.form_id = f.id) AS response_count,
                (SELECT COUNT(*) FROM form_fields ff WHERE ff.form_id = f.id)   AS field_count
         FROM forms f ORDER BY f.created_at DESC'
    )->fetchAll();
    view('admin/forms/index', ['title' => 'Forms', 'rows' => $rows]);
}

/** Builder for a new form. */
function new_form(): void
{
    require_admin();
    view('admin/forms/builder', [
        'title' => 'New form',
        'form'  => ['id' => null, 'title' => '', 'description' => '', 'status' => 'draft'],
        'fields' => [],
    ]);
}

/** Builder for an existing form. */
function edit_form(string $id): void
{
    require_admin();
    $form = find_form((int)$id);
    if (!$form) {
        flash('That form no longer exists.', 'error');
        redirect('/admin/forms');
    }
    view('admin/forms/builder', [
        'title'  => 'Edit form',
        'form'   => $form,
        'fields' => form_fields((int)$form['id']),
    ]);
}

/** Create a form. */
function store(): void
{
    require_admin();
    verify_csrf();
    _save_form(null);
}

/** Update a form. */
function update(string $id): void
{
    require_admin();
    verify_csrf();
    $form = find_form((int)$id);
    if (!$form) {
        flash('That form no longer exists.', 'error');
        redirect('/admin/forms');
    }
    _save_form((int)$id);
}

/** Shared save path for store()/update(). Re-renders the builder on error. */
function _save_form(?int $id): void
{
    $u = current_user();
    $data = [
        'title'       => input('title'),
        'description' => input('description'),
        'status'      => input('status'),
    ];
    try {
        $fields = normalize_form_fields($_POST['fields'] ?? []);
        $formId = save_form_with_fields($id, $data, $fields, (int)$u['id']);
    } catch (FormError $ex) {
        http_response_code(422);
        // Rebuild the field list from the POST so the admin doesn't lose their work.
        $posted = [];
        foreach ((array)($_POST['fields'] ?? []) as $f) {
            if (!is_array($f)) { continue; }
            $posted[] = [
                'label'       => $f['label'] ?? '',
                'help_text'   => $f['help_text'] ?? '',
                'type'        => $f['type'] ?? 'short_text',
                'options'     => array_values(array_filter(array_map('trim', (array)($f['options'] ?? [])), fn($v) => $v !== '')),
                'is_required' => !empty($f['is_required']) ? 1 : 0,
            ];
        }
        view('admin/forms/builder', [
            'title'  => $id ? 'Edit form' : 'New form',
            'form'   => ['id' => $id, 'title' => $data['title'], 'description' => $data['description'], 'status' => $data['status'] ?: 'draft'],
            'fields' => $posted,
            'error'  => $ex->getMessage(),
        ]);
        return;
    }
    flash($id ? 'Form updated.' : 'Form created. Set it to “Open” to start collecting responses.');
    redirect('/admin/forms/' . $formId . '/edit');
}

/** Change a form's status (draft / open / closed). */
function set_status(string $id): void
{
    require_admin();
    verify_csrf();
    $status = input('status');
    if (!in_array($status, ['draft', 'open', 'closed'], true)) {
        flash('Invalid status.', 'error');
        redirect('/admin/forms');
    }
    db()->prepare('UPDATE forms SET status = ? WHERE id = ?')->execute([$status, (int)$id]);
    flash('Form is now ' . $status . '.');
    redirect('/admin/forms');
}

/** Delete a form (and, via FK cascade, its fields and responses). */
function delete(string $id): void
{
    require_admin();
    verify_csrf();
    // Remove any uploaded files first (DB cascade won't touch the filesystem).
    $stmt = db()->prepare(
        "SELECT fa.value FROM form_answers fa
         JOIN form_fields ff ON ff.id = fa.field_id
         WHERE ff.form_id = ? AND ff.type = 'file_upload' AND fa.value IS NOT NULL"
    );
    $stmt->execute([(int)$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $val) {
        $resolved = resolve_upload_path((string)$val);
        if ($resolved) { @unlink($resolved['path']); }
    }
    db()->prepare('DELETE FROM forms WHERE id = ?')->execute([(int)$id]);
    flash('Form deleted.');
    redirect('/admin/forms');
}

/** Stream an uploaded response file to an admin (download). */
function download_file(string $responseId, string $fieldId): void
{
    require_admin();
    $stmt = db()->prepare(
        'SELECT fa.value FROM form_answers fa WHERE fa.response_id = ? AND fa.field_id = ?'
    );
    $stmt->execute([(int)$responseId, (int)$fieldId]);
    $value = $stmt->fetchColumn();
    $resolved = $value ? resolve_upload_path((string)$value) : null;
    if (!$resolved) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: ' . $resolved['mime']);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[\r\n"]+/', '', $resolved['name']) . '"');
    header('Content-Length: ' . filesize($resolved['path']));
    header('X-Content-Type-Options: nosniff');
    readfile($resolved['path']);
    exit;
}

/** Load a form's fields plus all responses+answers (shared by view + CSV). */
function _load_responses(int $formId): array
{
    $fields = form_fields($formId);
    $responses = db()->prepare('SELECT * FROM form_responses WHERE form_id = ? ORDER BY submitted_at DESC, id DESC');
    $responses->execute([$formId]);
    $responses = $responses->fetchAll();

    if ($responses) {
        $ids = array_column($responses, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $aStmt = db()->prepare("SELECT response_id, field_id, value FROM form_answers WHERE response_id IN ($in)");
        $aStmt->execute($ids);
        $byResp = [];
        foreach ($aStmt->fetchAll() as $a) {
            $byResp[$a['response_id']][$a['field_id']] = $a['value'];
        }
        foreach ($responses as &$r) {
            $r['answers'] = $byResp[$r['id']] ?? [];
        }
        unset($r);
    }
    return [$fields, $responses];
}

/** Responses table for a form. */
function responses(string $id): void
{
    require_admin();
    $form = find_form((int)$id);
    if (!$form) {
        flash('That form no longer exists.', 'error');
        redirect('/admin/forms');
    }
    [$fields, $responses] = _load_responses((int)$form['id']);
    view('admin/forms/responses', [
        'title'     => 'Responses · ' . $form['title'],
        'form'      => $form,
        'fields'    => $fields,
        'responses' => $responses,
    ]);
}

/** Export a form's responses as CSV. */
function responses_csv(string $id): void
{
    require_admin();
    $form = find_form((int)$id);
    if (!$form) {
        flash('That form no longer exists.', 'error');
        redirect('/admin/forms');
    }
    [$fields, $responses] = _load_responses((int)$form['id']);

    $header = ['Submitted at'];
    foreach ($fields as $f) {
        $header[] = $f['label'];
    }
    $rows = [];
    foreach ($responses as $r) {
        $row = [$r['submitted_at']];
        foreach ($fields as $f) {
            $row[] = format_answer($r['answers'][$f['id']] ?? null, $f['type']);
        }
        $rows[] = $row;
    }
    $slugSafe = preg_replace('/[^a-z0-9]+/i', '-', $form['title']) ?: 'form';
    send_csv(strtolower($slugSafe) . '-responses.csv', $header, $rows);
}
