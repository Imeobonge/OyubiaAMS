<?php
/**
 * Demo data seeder (CLI only). Adds congregations (using real COC Nigeria names where
 * available) and a spread of attendees — group members, solo members, and visitors —
 * to the 2026 edition, using the real registration service so reg numbers/counters
 * stay correct.
 *
 * Real congregation names sourced from the Ojota Church of Christ Nigeria directory:
 *   https://ojotachurchofchrist.com/directories/coc-nigeria
 *
 *   php database/seed_demo.php
 *
 * Safe to delete after use. Will not run via the web.
 */
if (PHP_SAPI !== 'cli') { exit("This seeder runs from the command line only.\n"); }

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/services/registration.php';

$pdo = db();

// --- Make sure the 2026 edition is the active one ---
$ed = $pdo->query("SELECT * FROM editions WHERE year = 2026")->fetch();
if (!$ed) {
    $pdo->prepare("INSERT INTO editions (name, year, is_active) VALUES ('OYCF 2026', 2026, 1)")->execute();
    $ed = $pdo->query("SELECT * FROM editions WHERE year = 2026")->fetch();
}
$pdo->exec("UPDATE editions SET is_active = 0");
$pdo->prepare("UPDATE editions SET is_active = 1 WHERE id = ?")->execute([$ed['id']]);

$staffId = (int)$pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();

// Wipe any previous seeder run for this edition so re-running stays idempotent.
$pdo->exec("DELETE vd FROM visitor_details vd JOIN registrations r ON r.id=vd.registration_id WHERE r.edition_id={$ed['id']}");
$pdo->exec("DELETE FROM registrations WHERE edition_id={$ed['id']}");
$pdo->exec("DELETE a FROM attendees a WHERE NOT EXISTS (SELECT 1 FROM registrations r WHERE r.attendee_id=a.id)");
$pdo->exec("DELETE FROM reg_counters WHERE edition_id={$ed['id']}");
$pdo->exec("DELETE FROM congregations WHERE NOT EXISTS (SELECT 1 FROM registrations r WHERE r.congregation_id=congregations.id)");

// Normalize any congregation names from previous seeder runs.
$pdo->exec("UPDATE congregations SET name='Church of Christ, Aka Road, Uyo'   WHERE name IN ('Uyo Church of Christ','COC Uyo')");
$pdo->exec("UPDATE congregations SET name='Church of Christ, Ndoki Road, Aba'  WHERE name IN ('Aba Church of Christ','COC Aba')");
$pdo->exec("UPDATE congregations SET name='Church of Christ, Rumuomasi'         WHERE name='COC Port Harcourt'");

function ensure_cong(PDO $pdo, array $c): int
{
    $s = $pdo->prepare("SELECT id FROM congregations WHERE code = ? OR name = ?");
    $s->execute([$c['code'], $c['name']]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE congregations SET name=?, code=?, minister_name=?, minister_phone=?, address=?, home_state=?, home_city=? WHERE id=?")
            ->execute([$c['name'], $c['code'], $c['minister'], $c['phone'], $c['addr'], $c['state'], $c['city'], $id]);
        return (int)$id;
    }
    $pdo->prepare("INSERT INTO congregations (name, code, minister_name, minister_phone, address, home_state, home_city) VALUES (?,?,?,?,?,?,?)")
        ->execute([$c['name'], $c['code'], $c['minister'], $c['phone'], $c['addr'], $c['state'], $c['city']]);
    return (int)$pdo->lastInsertId();
}

function ph(): string
{
    $pre = ['0803','0805','0813','0703','0706','0814','0902','0701','0816','0808'];
    return $pre[array_rand($pre)] . sprintf('%07d', mt_rand(0, 9999999));
}
function maybe($v, int $p = 50) { return mt_rand(1, 100) <= $p ? $v : null; }
function pick(array $a) { return $a[array_rand($a)]; }

$states = ['Akwa Ibom','Cross River','Rivers','Lagos','Abia','Imo','Delta','Anambra','Enugu','FCT'];

// ---------------------------------------------------------------------------
// Congregations
// ---------------------------------------------------------------------------
// Uyo/Akwa Ibom names from ojotachurchofchrist.com/directories/coc-nigeria/105-akwa-ibom
// PHC names from ojotachurchofchrist.com/directories/coc-nigeria/140-rivers-state
// Aba names from ojotachurchofchrist.com/directories/coc-nigeria/100-abia-state
// ---------------------------------------------------------------------------
$congs = [
    // --- Akwa Ibom ---
    'UYO'    => ['name'=>'Church of Christ, Aka Road, Uyo',         'code'=>'UYO',    'minister'=>'Imeh Akpan',        'phone'=>ph(),'addr'=>'144 Aka Road, Uyo',                              'state'=>'Akwa Ibom','city'=>'Uyo'],
    'FTOWNS' => ['name'=>'Four Towns Church of Christ, Uyo',        'code'=>'FTOWNS', 'minister'=>'Asuquo Umoh',       'phone'=>ph(),'addr'=>'140 Abak Road, Uyo',                            'state'=>'Akwa Ibom','city'=>'Uyo'],
    'EKT'    => ['name'=>'COC Eket',                                'code'=>'EKT',    'minister'=>'Sunday Udoh',       'phone'=>ph(),'addr'=>'5 Marina St, Eket',                             'state'=>'Akwa Ibom','city'=>'Eket'],
    'IKE'    => ['name'=>'COC Ikot Ekpene',                         'code'=>'IKE',    'minister'=>'Daniel Umoh',       'phone'=>ph(),'addr'=>'7 Abak Rd, Ikot Ekpene',                       'state'=>'Akwa Ibom','city'=>'Ikot Ekpene'],
    // --- Cross River ---
    'CAL'    => ['name'=>'COC Calabar',                             'code'=>'CAL',    'minister'=>'Effiong Bassey',    'phone'=>ph(),'addr'=>'20 Ndidem Rd, Calabar',                         'state'=>'Cross River','city'=>'Calabar'],
    // --- Rivers State ---
    'PHC'    => ['name'=>'Church of Christ, Rumuomasi, Port Harcourt', 'code'=>'PHC', 'minister'=>'Tamuno George',    'phone'=>ph(),'addr'=>'14 Rumuokoro Street, Rumuomasi, Port Harcourt', 'state'=>'Rivers','city'=>'Port Harcourt'],
    'ELEN'   => ['name'=>'Church of Christ, Elelenwo, Port Harcourt',  'code'=>'ELEN','minister'=>'Soibi Briggs',     'phone'=>ph(),'addr'=>'Elelenwo, Port Harcourt',                       'state'=>'Rivers','city'=>'Port Harcourt'],
    // --- Lagos + FCT ---
    'LAG'    => ['name'=>'COC Lagos',                               'code'=>'LAG',    'minister'=>'Tunde Adeyemi',     'phone'=>ph(),'addr'=>'15 Ikorodu Rd, Lagos',                          'state'=>'Lagos','city'=>'Lagos'],
    'ABJ'    => ['name'=>'COC Abuja',                               'code'=>'ABJ',    'minister'=>'Grace Bello',       'phone'=>ph(),'addr'=>'9 Wuse Zone 4, Abuja',                          'state'=>'FCT','city'=>'Abuja'],
    // --- Abia State ---
    'ABA'    => ['name'=>'Church of Christ, Ndoki Road, Aba',       'code'=>'ABA',    'minister'=>'Emeka Nwosu',       'phone'=>ph(),'addr'=>'5 Ndoki Road, Aba',                             'state'=>'Abia','city'=>'Aba'],
    'OGBOR'  => ['name'=>'Ogbor Hill Church of Christ, Aba',        'code'=>'OGBOR',  'minister'=>'Justice Akataobi',  'phone'=>ph(),'addr'=>'2 Ukaegbu Road, Ogbor Hill, Aba',               'state'=>'Abia','city'=>'Aba'],
];
$congId = [];
foreach ($congs as $k => $c) { $congId[$k] = ensure_cong($pdo, $c); }

// --- Members per congregation: [full name, gender, category] ---
$members = [
    'UYO'    => [['Aniekan Etuk','male','group'],['Emem Bassey','female','group'],['Ubong Akpan','male','group'],['Mfon Udo','female','group'],['Ima Essien','female','solo'],['Nsikak Okon','male','group']],
    'FTOWNS' => [['Inemesit Daniel','female','group'],['Bassey Okon','male','group'],['Nkoyo Etim','female','group'],['Amos Ekong','male','solo']],
    'EKT'    => [['Idongesit Sunday','female','group'],['Ekene Obi','male','group'],['Glory Sam','female','group'],['Victor Inyang','male','group'],['Blessing Effiong','female','group'],['Samuel Udeme','male','group'],['Peace Akpabio','female','solo']],
    'IKE'    => [['Daniel Umoh','male','group'],['Joy Williams','female','group'],['Promise Etim','male','group'],['Comfort Bassey','female','group'],['Itoro Asuquo','female','group'],['Edidiong Paul','male','group']],
    'CAL'    => [['Ekaette Eyo','female','group'],['Bassey Ekpo','male','group'],['Maria Edet','female','group'],['Okon Henshaw','male','group'],['Stella Out','female','solo']],
    'PHC'    => [['Chioma Wike','female','group'],['Tamuno George','male','group'],['Ngozi Amadi','female','group']],
    'ELEN'   => [['Soibi Briggs','male','group'],['Ada Okoye','female','group'],['Frank Eze','male','group']],
    'LAG'    => [['Tunde Adeyemi','male','group'],['Funke Bello','female','group'],['Segun Ola','male','solo'],['Aisha Bello','female','group']],
    'ABJ'    => [['Hauwa Musa','female','group'],['John Audu','male','group'],['Esther Yakubu','female','group']],
    'ABA'    => [['Chinedu Okafor','male','group'],['Adaeze Nwankwo','female','group'],['Uchenna Obi','male','solo']],
    'OGBOR'  => [['Chidinma Mba','female','group'],['Ifeanyi Eze','male','group'],['Nkechi Okafor','female','solo']],
];

$count = 0;
foreach ($members as $code => $people) {
    foreach ($people as [$name, $gender, $category]) {
        $data = [
            'category' => $category,
            'congregation_id' => $congId[$code],
            'full_name' => $name,
            'gender' => $gender,
            'phone' => ph(),
            'email' => maybe(strtolower(str_replace(' ', '.', $name)) . '@example.com', 40),
            'birth_day' => maybe((string)mt_rand(1, 28), 60),
            'birth_month' => maybe((string)mt_rand(1, 12), 60),
            'home_state' => $congs[$code]['state'],
            'home_city' => $congs[$code]['city'],
            'accommodation' => pick(['camping', 'outside']),
        ];
        create_registration($data, $staffId);
        $count++;
    }
}

// --- Visitors (not members) ---
$visitors = [
    ['Kelechi Eze','male','Living Faith Church','Aniekan Etuk','Friend','To grow spiritually and meet people'],
    ['Damilola Johnson','female','RCCG','Glory Sam','Social media','Learn more about the Church of Christ'],
    ['Favour Ndubuisi','female','Catholic Church','Promise Etim','Church announcement','Fellowship and learning'],
    ['Abdul Rahman','male','None','John Audu','Friend','Curious about the program'],
    ['Chidinma Okeke','female','Anglican Church','Joy Williams','Family member','Spiritual growth'],
    ['Tobi Adewale','male','MFM','Segun Ola','Radio','To be encouraged'],
    ['Rita Bassey','female','Winners Chapel','Emem Bassey','Friend','Make new friends'],
];
foreach ($visitors as [$name, $gender, $church, $invited, $heard, $expect]) {
    create_registration([
        'category' => 'visitor',
        'full_name' => $name,
        'gender' => $gender,
        'phone' => ph(),
        'email' => maybe(strtolower(str_replace(' ', '.', $name)) . '@example.com', 40),
        'home_state' => pick($states),
        'accommodation' => pick(['camping', 'outside']),
        'church_attended' => $church,
        'invited_by' => $invited,
        'how_heard' => $heard,
        'expectations' => $expect,
    ], $staffId);
    $count++;
}

echo "Seeded $count attendees across " . count($congId) . " congregations into {$ed['name']}.\n";
$total = $pdo->query("SELECT COUNT(*) FROM registrations WHERE edition_id = {$ed['id']}")->fetchColumn();
echo "Total registrations now in 2026: $total\n";
