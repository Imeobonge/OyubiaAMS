<?php /** @var array $rows */ ?>
<div class="page-head"><h1>Users</h1></div>

<div class="grid cols-2">
    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">Staff &amp; admins</h2>
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $u['role']==='admin'?'group':'solo' ?>"><?= e($u['role']) ?></span></td>
                    <td><?= $u['is_active'] ? 'Active' : '<span class="muted">Disabled</span>' ?></td>
                    <td>
                        <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/toggle') ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn small ghost" type="submit"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;margin-top:0">Add user</h2>
        <form method="post" action="<?= url('/admin/users') ?>">
            <?= csrf_field() ?>
            <div class="field"><label>Name <span class="req">*</span></label><input type="text" name="name" required></div>
            <div class="field"><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
            <div class="field"><label>Password <span class="req">*</span></label><input type="password" name="password" minlength="6" required><div class="help">At least 6 characters.</div></div>
            <div class="field">
                <label>Role</label>
                <select name="role">
                    <option value="staff">Desk staff — register attendees only</option>
                    <option value="admin">Admin — full access</option>
                </select>
            </div>
            <button class="btn" type="submit">Create user</button>
        </form>
    </div>
</div>
