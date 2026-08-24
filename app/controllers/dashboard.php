<?php
/** Dashboard controller. */

function index(): void
{
    require_login();
    $ed = active_edition();

    $stats = [
        'total' => 0, 'group' => 0, 'solo' => 0, 'visitor' => 0,
        'congregations' => 0, 'today' => 0, 'pending' => 0,
    ];
    $byCongregation = [];

    if ($ed) {
        $pdo = db();
        $edId = (int)$ed['id'];

        $stmt = $pdo->prepare(
            "SELECT category, COUNT(*) c FROM registrations WHERE edition_id = ? GROUP BY category"
        );
        $stmt->execute([$edId]);
        foreach ($stmt as $r) {
            $stats[$r['category']] = (int)$r['c'];
            $stats['total'] += (int)$r['c'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE edition_id = ? AND reg_number IS NULL");
        $stmt->execute([$edId]);
        $stats['pending'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE edition_id = ? AND DATE(registered_at) = CURDATE()");
        $stmt->execute([$edId]);
        $stats['today'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT c.id, c.name, c.code, COUNT(r.id) c
             FROM registrations r JOIN congregations c ON c.id = r.congregation_id
             WHERE r.edition_id = ?
             GROUP BY c.id, c.name, c.code ORDER BY c DESC, c.name"
        );
        $stmt->execute([$edId]);
        $byCongregation = $stmt->fetchAll();
        $stats['congregations'] = count($byCongregation);
    }

    view('dashboard/index', [
        'title' => 'Dashboard',
        'ed' => $ed,
        'stats' => $stats,
        'byCongregation' => $byCongregation,
    ]);
}
