<?php
/** @var string $category */ /** @var array $congregations */ /** @var array $old */
/** @var string|null $error */
$o = fn($k) => e($old[$k] ?? '');
$isMember = $category !== 'visitor';
$labels = ['group' => 'Came with a congregation', 'solo' => 'Came alone (member)', 'visitor' => 'Visitor'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · <?= e(config()['org_name']) ?></title>
    <meta name="theme-color" content="#173b86">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= asset('/assets/css/styles.css') ?>">
</head>
<body class="form-page">
<main class="form-wrap<?= $category === 'group' ? ' form-wrap-wide' : '' ?>">
    <div class="form-brand"><div class="login-logo" aria-hidden="true">OYCF</div></div>

    <?php if ($category === 'group'): /* ===== GROUP: bulk multi-person form ===== */ ?>

    <div class="card form-card">
        <h1>Register <span class="badge group" style="vertical-align:middle"><?= e($labels['group']) ?></span></h1>
        <p class="form-desc"><a href="<?= url('/join') ?>">← Choose a different option</a></p>

        <?php if (!empty($error)): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= url('/join') ?>" class="public-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="category" value="group">
            <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <p class="section-head" style="margin-top:0">Congregation</p>

            <div class="field">
                <label>Your congregation <span class="req">*</span></label>
                <select name="congregation_id" id="congSelect"
                        data-searchable="Type to search congregation by name or code…">
                    <option value="">— Not in the list / Add new —</option>
                    <?php foreach ($congregations as $cg): ?>
                        <option value="<?= (int)$cg['id'] ?>"
                            <?= ((int)($old['congregation_id'] ?? 0) === (int)$cg['id'] ? 'selected' : '') ?>>
                            <?= e($cg['name']) ?> (<?= e($cg['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help">Type to search — pick yours, or choose "Add new" if it isn't listed.</div>
            </div>
            <div id="newCongFields">
                <div class="field">
                    <label>Congregation name <span class="req">*</span></label>
                    <input type="text" name="congregation_name" value="<?= $o('congregation_name') ?>"
                           placeholder="e.g. Church of Christ, Aka Road, Uyo">
                </div>
                <div class="field">
                    <label>Minister's name <span class="req">*</span></label>
                    <input type="text" name="minister_name" value="<?= $o('minister_name') ?>" required>
                </div>
                <div class="field">
                    <label>Minister's phone <span class="req">*</span></label>
                    <input type="tel" name="minister_phone" value="<?= $o('minister_phone') ?>" required>
                </div>
                <div class="field">
                    <label>Congregation address <span class="req">*</span></label>
                    <input type="text" name="address" value="<?= $o('address') ?>"
                           placeholder="e.g. 12 Aka Road, Uyo" required>
                </div>
            </div>

            <p class="section-head">People in your group</p>
            <p class="muted" style="margin:-.4rem 0 .8rem;font-size:.9rem">Add each person who came with this congregation today.</p>

            <div id="attendeeRows"></div>

            <button type="button" id="addPerson" class="btn secondary" style="margin-top:.4rem">+ Add another person</button>

            <div style="margin-top:1.4rem">
                <button class="btn btn-block" type="submit" id="groupSubmit">Register 1 person</button>
            </div>
        </form>
    </div>

    <?php else: /* ===== SOLO / VISITOR: single-person form (unchanged) ===== */ ?>

    <div class="card form-card">
        <h1>Register <span class="badge <?= e($category) ?>" style="vertical-align:middle"><?= e($labels[$category]) ?></span></h1>
        <p class="form-desc"><a href="<?= url('/join') ?>">← Choose a different option</a></p>

        <?php if (!empty($error)): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= url('/join') ?>" class="public-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="category" value="<?= e($category) ?>">
            <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="field">
                <label>Full name <span class="req">*</span></label>
                <input type="text" name="full_name" value="<?= $o('full_name') ?>" required>
            </div>

            <div class="field">
                <label>Gender <?php if ($isMember): ?><span class="req">*</span><?php endif; ?></label>
                <select name="gender" <?= $isMember ? 'required' : '' ?>>
                    <option value=""><?= $isMember ? 'Select…' : 'Prefer not to say' ?></option>
                    <option value="male"   <?= (($old['gender'] ?? '')==='male'  ?'selected':'') ?>>Male<?= $isMember ? ' (Bro.)' : '' ?></option>
                    <option value="female" <?= (($old['gender'] ?? '')==='female'?'selected':'') ?>>Female<?= $isMember ? ' (Sis.)' : '' ?></option>
                </select>
            </div>

            <div class="field">
                <label>Phone <span class="req">*</span></label>
                <input type="tel" name="phone" value="<?= $o('phone') ?>" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="<?= $o('email') ?>">
            </div>

            <?php if ($category === 'solo'): ?>
            <div class="field">
                <label>Your congregation <span class="req">*</span></label>
                <select name="congregation_id" id="congSelect"
                        data-searchable="Type to search congregation by name or code…">
                    <option value="">— Not in the list / Add new —</option>
                    <?php foreach ($congregations as $cg): ?>
                        <option value="<?= (int)$cg['id'] ?>"
                            <?= ((int)($old['congregation_id'] ?? 0) === (int)$cg['id'] ? 'selected' : '') ?>>
                            <?= e($cg['name']) ?> (<?= e($cg['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help">Type to search — pick yours, or choose "Add new" if it isn't listed.</div>
            </div>
            <div id="newCongFields">
                <div class="field">
                    <label>Congregation name <span class="req">*</span></label>
                    <input type="text" name="congregation_name" value="<?= $o('congregation_name') ?>"
                           placeholder="e.g. Church of Christ, Aka Road, Uyo">
                </div>
            </div>
            <?php endif; ?>

            <?php if ($category === 'visitor'): ?>
            <div class="field">
                <label>What church do you attend?</label>
                <input type="text" name="church_attended" value="<?= $o('church_attended') ?>">
            </div>
            <div class="field">
                <label>Who invited you? <span class="req">*</span></label>
                <input type="text" name="invited_by" value="<?= $o('invited_by') ?>" required>
            </div>
            <div class="field">
                <label>How did you hear about the program? <span class="req">*</span></label>
                <input type="text" name="how_heard" value="<?= $o('how_heard') ?>" required placeholder="Friend, social media, church announcement…">
            </div>
            <div class="field">
                <label>What are your expectations? <span class="req">*</span></label>
                <textarea name="expectations" required><?= $o('expectations') ?></textarea>
            </div>
            <?php endif; ?>

            <div class="field">
                <label>Birthday <span class="muted" style="font-weight:400">(optional — no year)</span></label>
                <div style="display:flex;gap:.6rem">
                    <input type="number" name="birth_day" min="1" max="31" value="<?= $o('birth_day') ?>" placeholder="Day" style="max-width:90px">
                    <select name="birth_month">
                        <option value="">Month</option>
                        <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn): ?>
                            <option value="<?= $i+1 ?>" <?= ((int)($old['birth_month']??0)===$i+1?'selected':'') ?>><?= $mn ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Where will you stay?</label>
                <select name="accommodation">
                    <option value="">Select…</option>
                    <option value="camping" <?= (($old['accommodation'] ?? '')==='camping'?'selected':'') ?>>Camping at the event</option>
                    <option value="outside" <?= (($old['accommodation'] ?? '')==='outside'?'selected':'') ?>>Staying outside the compound</option>
                </select>
            </div>

            <button class="btn btn-block" type="submit">Register</button>
        </form>
    </div>

    <?php endif; ?>

    <p class="form-foot"><?= e(config()['org_name']) ?></p>
</main>
<script src="<?= asset('/assets/js/app.js') ?>" defer></script>
<?php if ($category === 'group'): ?>
<script>
(function () {
    var rows    = document.getElementById('attendeeRows');
    var addBtn  = document.getElementById('addPerson');
    var subBtn  = document.getElementById('groupSubmit');
    var counter = 0;

    var MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
    function monthOpts(name) {
        return '<select name="' + name + '"><option value="">Month</option>' +
            MONTHS.map(function (m, i) {
                return '<option value="' + (i + 1) + '">' + m + '</option>';
            }).join('') + '</select>';
    }

    function reLabel() {
        var nums = rows.querySelectorAll('.ar-num');
        var n = nums.length;
        for (var i = 0; i < n; i++) { nums[i].textContent = 'Person ' + (i + 1); }
        subBtn.textContent = 'Register ' + n + ' person' + (n === 1 ? '' : 's');
        var rmBtns = rows.querySelectorAll('.ar-rm');
        for (var j = 0; j < rmBtns.length; j++) {
            rmBtns[j].style.display = n > 1 ? '' : 'none';
        }
    }

    function addRow() {
        var i   = counter++;
        var div = document.createElement('div');
        div.className = 'attendee-row';
        div.innerHTML =
            '<div class="ar-head"><strong class="ar-num"></strong>' +
            '<button type="button" class="btn ghost small ar-rm">Remove</button></div>' +
            '<div class="ar-fields">' +
            '<div class="field"><label>Full name <span class="req">*</span></label>' +
            '<input type="text" name="attendees[' + i + '][full_name]" required autocomplete="off"></div>' +
            '<div class="field"><label>Gender <span class="req">*</span></label>' +
            '<select name="attendees[' + i + '][gender]" required>' +
            '<option value="">Select…</option>' +
            '<option value="male">Male (Bro.)</option>' +
            '<option value="female">Female (Sis.)</option>' +
            '</select></div>' +
            '<div class="field"><label>Phone <span class="ar-opt">(optional)</span></label>' +
            '<input type="tel" name="attendees[' + i + '][phone]" autocomplete="off"></div>' +
            '<div class="field"><label>Email <span class="ar-opt">(optional)</span></label>' +
            '<input type="email" name="attendees[' + i + '][email]" autocomplete="off"></div>' +
            '<div class="field"><label>Birthday — day <span class="ar-opt">(optional)</span></label>' +
            '<input type="number" name="attendees[' + i + '][birth_day]" min="1" max="31" placeholder="DD"></div>' +
            '<div class="field"><label>Birthday — month <span class="ar-opt">(optional)</span></label>' +
            monthOpts('attendees[' + i + '][birth_month]') +
            '</div>' +
            '</div>';
        rows.appendChild(div);
        reLabel();
        div.querySelector('input[type=text]').focus();
    }

    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.ar-rm');
        if (btn && rows.children.length > 1) {
            btn.closest('.attendee-row').remove();
            reLabel();
        }
    });

    addBtn.addEventListener('click', addRow);
    addRow(); // start with one row
})();
</script>
<?php endif; ?>
</body>
</html>
