<?php
/** @var array $rows */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$statusBadge = ['draft' => 'pending', 'open' => 'solo', 'closed' => 'visitor'];
?>
<div class="page-head">
    <h1>Forms</h1>
    <a class="btn" href="<?= url('/admin/forms/new') ?>">+ New form</a>
</div>

<div class="card">
<?php if (!$rows): ?>
    <p class="muted">No forms yet. Create one, set it to <strong>Open</strong>, then share its link so people can fill it in.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Title</th><th>Status</th><th>Questions</th><th>Responses</th><th>Public link</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $f): ?>
            <?php $link = $origin . url('/f/' . $f['slug']); ?>
            <tr>
                <td><a href="<?= url('/admin/forms/' . $f['id'] . '/edit') ?>"><?= e($f['title']) ?></a></td>
                <td><span class="badge <?= $statusBadge[$f['status']] ?? 'pending' ?>"><?= e($f['status']) ?></span></td>
                <td><?= (int)$f['field_count'] ?></td>
                <td>
                    <?php if ((int)$f['response_count'] > 0): ?>
                        <a href="<?= url('/admin/forms/' . $f['id'] . '/responses') ?>"><?= (int)$f['response_count'] ?></a>
                    <?php else: ?>
                        <span class="muted">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($f['status'] === 'open'): ?>
                        <button type="button" class="btn small ghost copy-link" data-link="<?= e($link) ?>">Copy link</button>
                    <?php else: ?>
                        <span class="muted" title="Open the form to activate its link">—</span>
                    <?php endif; ?>
                </td>
                <td class="inline-actions">
                    <a class="btn small secondary" href="<?= url('/admin/forms/' . $f['id'] . '/edit') ?>">Edit</a>
                    <a class="btn small secondary" href="<?= url('/admin/forms/' . $f['id'] . '/responses') ?>">Responses</a>
                    <?php $next = $f['status'] === 'open' ? 'closed' : 'open'; ?>
                    <form method="post" action="<?= url('/admin/forms/' . $f['id'] . '/status') ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= $next ?>">
                        <button class="btn small <?= $next === 'open' ? '' : 'ghost' ?>" type="submit"><?= $next === 'open' ? 'Open' : 'Close' ?></button>
                    </form>
                    <form method="post" action="<?= url('/admin/forms/' . $f['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('Delete this form and all its responses? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <button class="btn small ghost" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<script>
document.querySelectorAll('.copy-link').forEach(function (b) {
    b.addEventListener('click', function () {
        var link = b.getAttribute('data-link');
        navigator.clipboard.writeText(link).then(function () {
            var t = b.textContent; b.textContent = 'Copied!';
            setTimeout(function () { b.textContent = t; }, 1500);
        }, function () { window.prompt('Copy this link:', link); });
    });
});
</script>
