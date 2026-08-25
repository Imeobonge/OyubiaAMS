<?php
/** @var array $form */ /** @var array $fields */ /** @var array $old */
/** @var bool $done */ /** @var string|null $error */
$old = $old ?? [];
/** Was this field answered before (for repopulating after an error)? */
$oldVal = function ($fieldId) use ($old) {
    return $old[$fieldId] ?? null;
};
$hasFileField = false;
foreach ($fields as $__f) { if ($__f['type'] === 'file_upload') { $hasFileField = true; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($form['title']) ?></title>
    <meta name="theme-color" content="#173b86">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap">
    <div class="form-brand">
        <div class="login-logo" aria-hidden="true">OYCF</div>
    </div>

    <?php if (!empty($done)): ?>
        <div class="card form-card form-done">
            <div class="form-done-mark">✓</div>
            <h1><?= e($form['title']) ?></h1>
            <p class="muted">Thank you — your response has been recorded.</p>
            <p><a class="btn secondary" href="<?= url('/f/' . $form['slug']) ?>">Submit another response</a></p>
        </div>
    <?php else: ?>
        <div class="card form-card">
            <h1><?= e($form['title']) ?></h1>
            <?php if (!empty($form['description'])): ?>
                <p class="form-desc"><?= nl2br(e($form['description'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="flash flash-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/f/' . $form['slug']) ?>" class="public-form" novalidate<?= $hasFileField ? ' enctype="multipart/form-data"' : '' ?>>
                <?= csrf_field() ?>
                <!-- honeypot: hidden from people, tempting to bots -->
                <div class="hp" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <?php foreach ($fields as $f): $name = 'field[' . $f['id'] . ']'; $prev = $oldVal($f['id']); ?>
                    <div class="field">
                        <label>
                            <?= e($f['label']) ?>
                            <?php if ($f['is_required']): ?><span class="req">*</span><?php endif; ?>
                        </label>
                        <?php if (!empty($f['help_text'])): ?><div class="help"><?= e($f['help_text']) ?></div><?php endif; ?>

                        <?php if ($f['type'] === 'paragraph'): ?>
                            <textarea name="<?= $name ?>" <?= $f['is_required'] ? 'required' : '' ?>><?= e(is_array($prev) ? '' : $prev) ?></textarea>

                        <?php elseif ($f['type'] === 'multiple_choice'): ?>
                            <?php foreach ($f['options'] as $opt): ?>
                                <label class="choice">
                                    <input type="radio" name="<?= $name ?>" value="<?= e($opt) ?>" <?= ((string)$prev === (string)$opt) ? 'checked' : '' ?> <?= $f['is_required'] ? 'required' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                            <?php endforeach; ?>

                        <?php elseif ($f['type'] === 'checkboxes'): ?>
                            <?php $prevArr = is_array($prev) ? $prev : []; ?>
                            <?php foreach ($f['options'] as $opt): ?>
                                <label class="choice">
                                    <input type="checkbox" name="<?= $name ?>[]" value="<?= e($opt) ?>" <?= in_array($opt, $prevArr, true) ? 'checked' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                            <?php endforeach; ?>

                        <?php elseif ($f['type'] === 'dropdown'): ?>
                            <select name="<?= $name ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                                <option value="">Select…</option>
                                <?php foreach ($f['options'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= ((string)$prev === (string)$opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($f['type'] === 'file_upload'): ?>
                            <input type="file" name="upload[<?= (int)$f['id'] ?>]" accept="<?= e(form_upload_accept()) ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                            <div class="help">Images or PDF, up to <?= round(form_upload_max_bytes() / 1048576) ?> MB.</div>

                        <?php else:
                            $inputType = ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$f['type']] ?? 'text';
                        ?>
                            <input type="<?= $inputType ?>" name="<?= $name ?>" value="<?= e(is_array($prev) ? '' : $prev) ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button class="btn btn-block" type="submit">Submit</button>
            </form>
        </div>
    <?php endif; ?>

    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
</body>
</html>
