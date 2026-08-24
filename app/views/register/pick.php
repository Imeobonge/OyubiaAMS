<div class="page-head"><h1>Register attendee</h1></div>
<p class="muted">How is this person attending?</p>

<div class="cat-picker">
    <a class="cat-card" href="<?= url('/register?category=group') ?>">
        <h3>Came with a congregation</h3>
        <p>Part of a group from a Church of Christ congregation. We capture the congregation &amp; minister once, then add everyone to it.</p>
    </a>
    <a class="cat-card" href="<?= url('/register?category=solo') ?>">
        <h3>Came alone (member)</h3>
        <p>A Church of Christ member who travelled on their own. We record which congregation they belong to.</p>
    </a>
    <a class="cat-card" href="<?= url('/register?category=visitor') ?>">
        <h3>Visitor (not a member)</h3>
        <p>Not a member of the Church of Christ. We capture their church, who invited them, and their expectations.</p>
    </a>
</div>
