<?php /** @var array $c */ /** @var string $action */ ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>

<form method="post" action="<?= e($action) ?>" class="card" style="max-width:680px">
    <?= csrf_field() ?>
    <div class="field">
        <label>Congregation name <span class="req">*</span></label>
        <input type="text" name="name" value="<?= e($c['name']) ?>" required>
    </div>
    <div class="field">
        <label>Code</label>
        <input type="text" name="code" value="<?= e($c['code']) ?>" maxlength="12" placeholder="Auto-generated from the name if left blank">
        <div class="help">Short code used in reg numbers, e.g. <strong>UYO</strong> → OYCF-<?= date('Y') ?>-UYO-014. Leave blank to auto-generate; you can edit it.</div>
    </div>
    <div class="row">
        <div class="field"><label>Minister's name</label><input type="text" name="minister_name" value="<?= e($c['minister_name']) ?>"></div>
        <div class="field"><label>Minister's phone</label><input type="tel" name="minister_phone" value="<?= e($c['minister_phone']) ?>"></div>
    </div>
    <div class="field"><label>Congregation address</label><input type="text" name="address" value="<?= e($c['address']) ?>"></div>
    <div class="row">
        <div class="field"><label>Home state</label><input type="text" name="home_state" value="<?= e($c['home_state']) ?>" placeholder="e.g. Akwa Ibom"></div>
        <div class="field"><label>Home city / town</label><input type="text" name="home_city" value="<?= e($c['home_city']) ?>"></div>
    </div>
    <div class="inline-actions">
        <button class="btn" type="submit">Save congregation</button>
        <a class="btn ghost" href="<?= url('/congregations') ?>">Cancel</a>
    </div>
</form>
