<?php
/** @var array|null $edition */ /** @var string $joinUrl */ /** @var bool $isOpen */
$qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=10&data=' . urlencode($joinUrl);
?>
<div class="page-head">
    <h1>Self check-in (QR)</h1>
    <a class="btn ghost" href="<?= url('/admin/self-register/batches') ?>">Group arrivals →</a>
</div>

<?php if (!$edition): ?>
    <div class="card"><p class="muted">No active edition. Create and activate one under <a href="<?= url('/admin/editions') ?>">Editions</a> first.</p></div>
<?php else: ?>

<div class="grid cols-2">
    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">Status</h2>
        <p style="margin:.2rem 0 1rem">
            Self check-in is
            <?php if ($isOpen): ?>
                <span class="badge solo">OPEN</span> — attendees can register themselves.
            <?php else: ?>
                <span class="badge visitor">closed</span> — the QR page shows a "not open" message.
            <?php endif; ?>
        </p>
        <form method="post" action="<?= url('/admin/self-register/toggle') ?>">
            <?= csrf_field() ?>
            <button class="btn <?= $isOpen ? 'ghost' : '' ?>" type="submit">
                <?= $isOpen ? 'Close self check-in' : 'Open self check-in' ?>
            </button>
        </form>

        <hr style="border:0;border-top:1px solid var(--border);margin:1.2rem 0">

        <h2 style="font-size:1rem">Public link</h2>
        <p class="muted" style="margin:.2rem 0 .5rem">People scan the QR or open this link to register themselves:</p>
        <div class="inline-actions">
            <input type="text" id="joinUrl" value="<?= e($joinUrl) ?>" readonly style="max-width:340px">
            <button type="button" class="btn small secondary" id="copyJoin">Copy</button>
        </div>
        <p class="help">Tip: open it during the event and close it afterwards so the link stops accepting entries.</p>
    </div>

    <div class="card qr-card">
        <h2 style="font-size:1rem;margin-top:0">Scan-to-register QR</h2>
        <div class="qr-poster">
            <div class="qr-poster-title"><?= e($edition['name']) ?></div>
            <img class="qr-img" src="<?= e($qrSrc) ?>" alt="Registration QR code" width="300" height="300">
            <div class="qr-poster-cta">Scan with your phone camera to register</div>
            <div class="qr-poster-url"><?= e($joinUrl) ?></div>
        </div>
        <div class="inline-actions" style="justify-content:center;margin-top:1rem" data-print-hide>
            <button type="button" class="btn secondary" onclick="window.print()">Print poster</button>
        </div>
        <p class="help" style="text-align:center" data-print-hide>Print this ahead of time and display it at the venue.</p>
    </div>
</div>

<script>
(function () {
    var copy = document.getElementById('copyJoin');
    var url = document.getElementById('joinUrl');
    if (copy && url) copy.addEventListener('click', function () {
        url.select();
        navigator.clipboard.writeText(url.value).then(function () {
            var t = copy.textContent; copy.textContent = 'Copied!';
            setTimeout(function () { copy.textContent = t; }, 1500);
        }, function () {});
    });
})();
</script>
<?php endif; ?>
