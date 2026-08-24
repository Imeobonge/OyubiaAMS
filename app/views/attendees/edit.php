<?php /** @var array $a */ ?>
<div class="page-head"><h1>Edit · <?= e($a['full_name']) ?></h1></div>

<form method="post" action="<?= url('/attendees/' . $a['id']) ?>" class="card" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="field"><label>Full name</label><input type="text" name="full_name" value="<?= e($a['full_name']) ?>" required></div>
    <div class="row three">
        <div class="field">
            <label>Gender</label>
            <select name="gender">
                <option value="">—</option>
                <option value="male"   <?= $a['gender']==='male'?'selected':'' ?>>Male</option>
                <option value="female" <?= $a['gender']==='female'?'selected':'' ?>>Female</option>
            </select>
        </div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= e($a['phone']) ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($a['email']) ?>"></div>
    </div>
    <div class="row three">
        <div class="field"><label>Birthday day</label><input type="number" name="birth_day" min="1" max="31" value="<?= e($a['birth_day']) ?>"></div>
        <div class="field">
            <label>Birthday month</label>
            <select name="birth_month">
                <option value="">—</option>
                <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i=>$mn): ?>
                    <option value="<?= $i+1 ?>" <?= (int)$a['birth_month']===$i+1?'selected':'' ?>><?= $mn ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"></div>
    </div>
    <div class="row">
        <div class="field"><label>Home state</label><input type="text" name="home_state" value="<?= e($a['home_state']) ?>"></div>
        <div class="field"><label>Home city</label><input type="text" name="home_city" value="<?= e($a['home_city']) ?>"></div>
    </div>
    <div class="section-title">Accommodation</div>
    <div class="row">
        <div class="field">
            <label>Where they stay</label>
            <select name="accommodation">
                <option value="">—</option>
                <option value="camping" <?= $a['accommodation']==='camping'?'selected':'' ?>>Camping at event</option>
                <option value="outside" <?= $a['accommodation']==='outside'?'selected':'' ?>>Outside compound</option>
            </select>
        </div>
        <div class="field"><label>Note</label><input type="text" name="accommodation_note" value="<?= e($a['accommodation_note']) ?>"></div>
    </div>
    <div class="inline-actions">
        <button class="btn" type="submit">Save changes</button>
        <a class="btn ghost" href="<?= url('/attendees/' . $a['id']) ?>">Cancel</a>
    </div>
</form>
