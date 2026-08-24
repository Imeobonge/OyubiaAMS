<?php
/** @var string $category */ /** @var array $congregations */ /** @var array $old */
$o = fn($k) => e($old[$k] ?? '');
$labels = ['group' => 'Came with a congregation', 'solo' => 'Came alone (member)', 'visitor' => 'Visitor (not a member)'];
$isMember = $category !== 'visitor';
?>
<div class="page-head">
    <h1>Register · <span class="badge <?= e($category) ?>"><?= e($labels[$category]) ?></span></h1>
    <a class="btn ghost" href="<?= url('/register') ?>">Change type</a>
</div>

<?php if (!empty($error)): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($category === 'group'): ?>
<form method="post" action="<?= url('/register') ?>" class="card" id="regForm" data-bulk style="max-width:900px">
    <?= csrf_field() ?>
    <input type="hidden" name="category" value="group">

    <div class="section-title">Congregation</div>
    <div class="field">
        <label>Select congregation</label>
        <select name="congregation_id" id="congSelect" data-searchable="Type to search congregation by name or code…">
            <option value="">— Add a new congregation —</option>
            <?php foreach ($congregations as $cg): ?>
                <option value="<?= (int)$cg['id'] ?>" <?= ((int)($old['congregation_id']??0)===(int)$cg['id']?'selected':'') ?>>
                    <?= e($cg['name']) ?> (<?= e($cg['code']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="help">Choose the congregation once, then add everyone who arrived with it.</div>
    </div>
    <div id="newCongFields">
        <div class="field"><label>New congregation name</label><input type="text" name="congregation_name" value="<?= $o('congregation_name') ?>"></div>
        <div class="row">
            <div class="field"><label>Minister's name</label><input type="text" name="minister_name" value="<?= $o('minister_name') ?>"></div>
            <div class="field"><label>Minister's phone</label><input type="tel" name="minister_phone" value="<?= $o('minister_phone') ?>"></div>
        </div>
        <div class="field"><label>Congregation address</label><input type="text" name="address" value="<?= $o('address') ?>"></div>
    </div>

    <div class="section-title">People in the congregation group</div>
    <p class="muted">Add every Brother and Sister in this arrival. Each person receives a separate registration number.</p>
    <div id="attendeeRows"></div>
    <button type="button" id="addPerson" class="btn secondary">+ Add another person</button>
    <div class="inline-actions" style="margin-top:1.2rem">
        <button class="btn" type="submit" id="groupSubmit">Register 1 person</button>
        <a class="btn ghost" href="<?= url('/') ?>">Cancel</a>
    </div>
</form>

<?php
$oldPeople = isset($old['attendees']) && is_array($old['attendees']) ? array_values($old['attendees']) : [];
?>
<script>
(function () {
    var rows = document.getElementById('attendeeRows');
    var addBtn = document.getElementById('addPerson');
    var subBtn = document.getElementById('groupSubmit');
    var counter = 0;
    var oldPeople = <?= json_encode($oldPeople, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    function esc(v) { var d=document.createElement('div'); d.textContent=v==null?'':String(v); return d.innerHTML; }
    function options(selected) { return '<option value="">Month</option>' + months.map(function(m,i){var v=String(i+1);return '<option value="'+v+'"'+(String(selected||'')===v?' selected':'')+'>'+m+'</option>';}).join(''); }
    function relabel() {
        var cards=rows.querySelectorAll('.attendee-row');
        cards.forEach(function(card,i){card.querySelector('.ar-num').textContent='Person '+(i+1); card.querySelector('.ar-rm').hidden=cards.length===1;});
        subBtn.textContent='Register '+cards.length+' '+(cards.length===1?'person':'people');
    }
    function addRow(p) {
        p=p||{}; var i=counter++; var div=document.createElement('div'); div.className='attendee-row';
        div.innerHTML='<div class="ar-head"><strong class="ar-num"></strong><button type="button" class="btn ghost small ar-rm">Remove</button></div>'+
        '<div class="ar-fields">'+
        '<div class="field"><label>Full name <span class="req">*</span></label><input type="text" name="attendees['+i+'][full_name]" value="'+esc(p.full_name)+'" required></div>'+
        '<div class="field"><label>Gender <span class="req">*</span></label><select name="attendees['+i+'][gender]" required><option value="">Select…</option><option value="male"'+(p.gender==='male'?' selected':'')+'>Male (Bro.)</option><option value="female"'+(p.gender==='female'?' selected':'')+'>Female (Sis.)</option></select></div>'+
        '<div class="field"><label>Phone</label><input type="tel" name="attendees['+i+'][phone]" value="'+esc(p.phone)+'"></div>'+
        '<div class="field"><label>Email</label><input type="email" name="attendees['+i+'][email]" value="'+esc(p.email)+'"></div>'+
        '<div class="field"><label>Birthday — day</label><input type="number" min="1" max="31" name="attendees['+i+'][birth_day]" value="'+esc(p.birth_day)+'"></div>'+
        '<div class="field"><label>Birthday — month</label><select name="attendees['+i+'][birth_month]">'+options(p.birth_month)+'</select></div>'+
        '<div class="field"><label>Accommodation</label><select name="attendees['+i+'][accommodation]"><option value="camping"'+(p.accommodation!=='outside'?' selected':'')+'>Camping</option><option value="outside"'+(p.accommodation==='outside'?' selected':'')+'>Outside</option></select></div>'+
        '<div class="field"><label>Accommodation note</label><input type="text" name="attendees['+i+'][accommodation_note]" value="'+esc(p.accommodation_note)+'"></div></div>';
        rows.appendChild(div); relabel();
    }
    rows.addEventListener('click',function(e){var b=e.target.closest('.ar-rm');if(b&&rows.children.length>1){b.closest('.attendee-row').remove();relabel();}});
    addBtn.addEventListener('click',function(){addRow({}); rows.lastElementChild.querySelector('input').focus();});
    (oldPeople.length?oldPeople:[{}]).forEach(addRow);
})();
</script>

<?php else: ?>
<form method="post" action="<?= url('/register') ?>" class="card" id="regForm" data-category="<?= e($category) ?>" style="max-width:760px">
    <?= csrf_field() ?>
    <input type="hidden" name="category" value="<?= e($category) ?>">

    <div class="section-title">Person</div>

    <input type="hidden" name="attendee_id" id="attendeeId" value="<?= $o('attendee_id') ?>">
    <div class="field lookup" id="returningWrap">
        <label>Returning attendee? <span class="muted" style="font-weight:400">(optional)</span></label>
        <input type="text" id="returningSearch" autocomplete="off"
               placeholder="Search past records by name or phone to link them…">
        <div class="lookup-results" id="returningResults" hidden></div>
        <div class="help" id="linkedNote" hidden></div>
    </div>

    <div class="field">
        <label>Full name <span class="req">*</span></label>
        <input type="text" name="full_name" value="<?= $o('full_name') ?>" required>
    </div>

    <div class="row three">
        <?php if ($isMember): ?>
        <div class="field">
            <label>Gender <span class="req">*</span></label>
            <select name="gender" required>
                <option value="">Select…</option>
                <option value="male"   <?= (($old['gender'] ?? '')==='male'?'selected':'') ?>>Male (Bro.)</option>
                <option value="female" <?= (($old['gender'] ?? '')==='female'?'selected':'') ?>>Female (Sis.)</option>
            </select>
        </div>
        <?php else: ?>
        <div class="field">
            <label>Gender</label>
            <select name="gender">
                <option value="">Prefer not to say</option>
                <option value="male"   <?= (($old['gender'] ?? '')==='male'?'selected':'') ?>>Male</option>
                <option value="female" <?= (($old['gender'] ?? '')==='female'?'selected':'') ?>>Female</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="field">
            <label>Phone <span class="req">*</span></label>
            <input type="tel" name="phone" value="<?= $o('phone') ?>" required>
        </div>
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="<?= $o('email') ?>">
        </div>
    </div>

    <div class="row three">
        <div class="field">
            <label>Birthday — day</label>
            <input type="number" name="birth_day" min="1" max="31" value="<?= $o('birth_day') ?>" placeholder="DD">
        </div>
        <div class="field">
            <label>Birthday — month</label>
            <select name="birth_month">
                <option value="">—</option>
                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn): ?>
                    <option value="<?= $i+1 ?>" <?= ((int)($old['birth_month']??0)===$i+1?'selected':'') ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>
            <div class="help">Year is not collected.</div>
        </div>
        <div class="field"></div>
    </div>

    <div class="row">
        <div class="field"><label>Home state</label><input type="text" name="home_state" value="<?= $o('home_state') ?>" placeholder="e.g. Lagos"></div>
        <div class="field"><label>Home city / town</label><input type="text" name="home_city" value="<?= $o('home_city') ?>"></div>
    </div>

    <?php if ($category === 'group' || $category === 'solo'): ?>
    <div class="section-title">Congregation</div>
    <div class="field">
        <label>Select congregation</label>
        <select name="congregation_id" id="congSelect" data-searchable="Type to search congregation by name or code…">
            <option value="">— Add a new congregation —</option>
            <?php foreach ($congregations as $cg): ?>
                <option value="<?= (int)$cg['id'] ?>" <?= ((int)($old['congregation_id']??0)===(int)$cg['id']?'selected':'') ?>>
                    <?= e($cg['name']) ?> (<?= e($cg['code']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="help"><?= $category==='group'
            ? 'Pick the congregation this group belongs to, or add it once and everyone after can be attached to it.'
            : 'Pick this member\'s congregation, or add it if it isn\'t listed yet.' ?></div>
    </div>

    <div id="newCongFields">
        <div class="field">
            <label>New congregation name <?php if($category==='group'):?><span class="req">*</span><?php endif;?></label>
            <input type="text" name="congregation_name" value="<?= $o('congregation_name') ?>">
        </div>
        <div class="row">
            <div class="field"><label>Minister's name</label><input type="text" name="minister_name" value="<?= $o('minister_name') ?>"></div>
            <div class="field"><label>Minister's phone</label><input type="tel" name="minister_phone" value="<?= $o('minister_phone') ?>"></div>
        </div>
        <div class="field"><label>Congregation address</label><input type="text" name="address" value="<?= $o('address') ?>"></div>
    </div>
    <?php endif; ?>

    <?php if ($category === 'visitor'): ?>
    <div class="section-title">Visitor details</div>
    <div class="field">
        <label>What church do you attend?</label>
        <input type="text" name="church_attended" value="<?= $o('church_attended') ?>">
    </div>
    <div class="row">
        <div class="field"><label>Who invited you? <span class="req">*</span></label><input type="text" name="invited_by" value="<?= $o('invited_by') ?>" required></div>
        <div class="field">
            <label>How did you hear about the program? <span class="req">*</span></label>
            <input type="text" name="how_heard" value="<?= $o('how_heard') ?>" required placeholder="Friend, social media, church announcement…">
        </div>
    </div>
    <div class="field">
        <label>What are your expectations from the program? <span class="req">*</span></label>
        <textarea name="expectations" required><?= $o('expectations') ?></textarea>
    </div>
    <?php endif; ?>

    <div class="section-title">Accommodation</div>
    <div class="row">
        <div class="field">
            <label>Where will they stay?</label>
            <select name="accommodation" id="accSelect">
                <option value="">Select…</option>
                <option value="camping" <?= (($old['accommodation'] ?? '')==='camping'?'selected':'') ?>>Camping at the event</option>
                <option value="outside" <?= (($old['accommodation'] ?? '')==='outside'?'selected':'') ?>>Staying outside the compound</option>
            </select>
        </div>
        <div class="field">
            <label>Accommodation note</label>
            <input type="text" name="accommodation_note" value="<?= $o('accommodation_note') ?>" placeholder="Optional — e.g. address where staying">
        </div>
    </div>

    <div class="inline-actions" style="margin-top:.5rem">
        <button class="btn" type="submit">Register &amp; assign reg number</button>
        <a class="btn ghost" href="<?= url('/') ?>">Cancel</a>
    </div>
</form>
<?php endif; ?>
