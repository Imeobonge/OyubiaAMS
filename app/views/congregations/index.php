<?php /** @var array $rows */ /** @var array|null $ed */ ?>
<div class="page-head">
    <h1>Congregations</h1>
    <a class="btn" href="<?= url('/congregations/new') ?>">+ Add congregation</a>
</div>

<div class="card">
<?php if (!$rows): ?>
    <p class="muted">No congregations yet. Add the first one to start grouping attendees.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Minister</th>
                <th>Phone</th>
                <?php if ($ed): ?>
                <th style="text-align:center" title="Brothers registered this edition">Bros</th>
                <th style="text-align:center" title="Sisters registered this edition">Sis</th>
                <th style="text-align:center" title="Total registered this edition">Total</th>
                <th style="text-align:center">Acc. given</th>
                <?php else: ?>
                <th style="text-align:center">Members</th>
                <?php endif; ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr>
                <td>
                    <a href="<?= url('/congregations/' . $c['id']) ?>"><?= e($c['name']) ?></a>
                    <?php if ($c['home_state']): ?>
                        <div class="muted" style="font-size:.8rem"><?= e(trim(($c['home_city'] ? $c['home_city'].', ' : '').$c['home_state'])) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge group"><?= e($c['code']) ?></span></td>
                <td><?= e($c['minister_name'] ?: '—') ?></td>
                <td><?= e($c['minister_phone'] ?: '—') ?></td>
                <?php if ($ed): ?>
                <td style="text-align:center;color:var(--primary);font-weight:600"><?= (int)$c['brothers_count'] ?: '—' ?></td>
                <td style="text-align:center;color:#8a6900;font-weight:700"><?= (int)$c['sisters_count'] ?: '—' ?></td>
                <td style="text-align:center"><?= (int)$c['attendee_count'] ?: '—' ?></td>
                <td style="text-align:center">
                    <div class="inline-actions" style="justify-content:center;gap:.3rem">
                        <form method="post" action="<?= url('/congregations/' . $c['id'] . '/accommodation') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="which" value="brothers">
                            <button type="submit"
                                    class="btn small <?= $c['brothers_done'] ? '' : 'ghost' ?>"
                                    style="<?= $c['brothers_done'] ? 'background:var(--success-fg);border-color:var(--success-fg);color:#fff' : '' ?>"
                                    title="<?= $c['brothers_done'] ? 'Brothers — accommodation given (click to undo)' : 'Mark brothers accommodation as given' ?>">
                                <?= $c['brothers_done'] ? '✓' : '○' ?> Bros
                            </button>
                        </form>
                        <form method="post" action="<?= url('/congregations/' . $c['id'] . '/accommodation') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="which" value="sisters">
                            <button type="submit"
                                    class="btn small <?= $c['sisters_done'] ? '' : 'ghost' ?>"
                                    style="<?= $c['sisters_done'] ? 'background:#111;border-color:#111;color:#f5c400' : '' ?>"
                                    title="<?= $c['sisters_done'] ? 'Sisters — accommodation given (click to undo)' : 'Mark sisters accommodation as given' ?>">
                                <?= $c['sisters_done'] ? '✓' : '○' ?> Sis
                            </button>
                        </form>
                    </div>
                </td>
                <?php else: ?>
                <td style="text-align:center"><?= (int)$c['attendee_count'] ?></td>
                <?php endif; ?>
                <td class="inline-actions">
                    <a class="btn small secondary" href="<?= url('/congregations/' . $c['id'] . '/edit') ?>">Edit</a>
                    <?php if ((int)$c['brothers_count'] > 0): ?><a class="btn small ghost" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster?gender=male') ?>">Brothers</a><?php endif; ?>
                    <?php if ((int)$c['sisters_count'] > 0): ?><a class="btn small ghost" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster?gender=female') ?>">Sisters</a><?php endif; ?>
                    <a class="btn small ghost" target="_blank" href="<?= url('/congregations/' . $c['id'] . '/roster') ?>">All</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
