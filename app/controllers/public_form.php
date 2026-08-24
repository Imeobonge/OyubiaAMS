<?php
/**
 * Public form controller — anonymous form filling at /f/{slug}.
 * No login required. Only forms with status='open' accept submissions.
 */

require_once __DIR__ . '/../services/forms.php';

/** Show a public form (or an "unavailable" page). */
function show(string $slug): void
{
    $form = find_form_by_slug($slug);
    if (!$form || $form['status'] !== 'open') {
        http_response_code($form ? 403 : 404);
        view_raw('public/form_unavailable', [
            'reason' => $form ? 'closed' : 'missing',
        ]);
        return;
    }
    view_raw('public/form', [
        'form'   => $form,
        'fields' => form_fields((int)$form['id']),
        'old'    => [],
        'done'   => false,
    ]);
}

/** Handle a public submission. */
function submit(string $slug): void
{
    $form = find_form_by_slug($slug);
    if (!$form || $form['status'] !== 'open') {
        http_response_code($form ? 403 : 404);
        view_raw('public/form_unavailable', ['reason' => $form ? 'closed' : 'missing']);
        return;
    }

    verify_csrf();

    // Honeypot: real people leave this hidden field empty; bots fill it.
    // Pretend success without saving.
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        view_raw('public/form', [
            'form' => $form, 'fields' => form_fields((int)$form['id']),
            'old' => [], 'done' => true,
        ]);
        return;
    }

    try {
        record_response($form, $_POST);
    } catch (FormError $ex) {
        http_response_code(422);
        view_raw('public/form', [
            'form'   => $form,
            'fields' => form_fields((int)$form['id']),
            'old'    => $_POST['field'] ?? [],
            'done'   => false,
            'error'  => $ex->getMessage(),
        ]);
        return;
    }

    view_raw('public/form', [
        'form' => $form, 'fields' => form_fields((int)$form['id']),
        'old' => [], 'done' => true,
    ]);
}
