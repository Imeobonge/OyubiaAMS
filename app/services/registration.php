<?php
/**
 * Registration service — the single code path used by BOTH the online form
 * (controllers/register.php) and the offline sync endpoint (controllers/api.php).
 *
 * Given a normalized data array, it find-or-creates the congregation, creates
 * the attendee + registration, allocates the reg number, and stores visitor
 * details. Reg numbers are always assigned here (server-side), so offline
 * records get their authoritative number at sync time.
 */

class RegistrationError extends RuntimeException {}

/**
 * Find an existing congregation by id or name, or create a new one.
 * Returns the congregation row (or null for visitors).
 */
function find_or_create_congregation(array $d): ?array
{
    $pdo = db();

    if (!empty($d['congregation_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM congregations WHERE id = ?');
        $stmt->execute([(int)$d['congregation_id']]);
        if ($row = $stmt->fetch()) {
            return $row;
        }
    }

    $name = trim($d['congregation_name'] ?? '');
    if ($name === '') {
        return null;
    }

    // Match an existing congregation by name, treating "COC" and
    // "Church of Christ" as equivalent (normalize_cong_name handles this).
    $normalizedInput = normalize_cong_name($name);
    $all  = $pdo->query('SELECT * FROM congregations ORDER BY name')->fetchAll();
    $row  = null;
    foreach ($all as $candidate) {
        if (normalize_cong_name($candidate['name']) === $normalizedInput) {
            $row = $candidate;
            break;
        }
    }
    if ($row) {
        // Backfill minister info if it was missing.
        if (empty($row['minister_name']) && !empty($d['minister_name'])) {
            $pdo->prepare('UPDATE congregations SET minister_name=?, minister_phone=?, address=? WHERE id=?')
                ->execute([$d['minister_name'] ?? null, $d['minister_phone'] ?? null, $d['address'] ?? null, $row['id']]);
        }
        return $row;
    }

    $code = trim($d['congregation_code'] ?? '');
    $code = $code !== '' ? unique_congregation_code($code) : unique_congregation_code(suggest_congregation_code($name));
    $pdo->prepare(
        'INSERT INTO congregations (name, code, minister_name, minister_phone, address, home_state, home_city)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([
        $name, $code,
        $d['minister_name'] ?? null, $d['minister_phone'] ?? null, $d['address'] ?? null,
        $d['home_state'] ?? null, $d['home_city'] ?? null,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM congregations WHERE id = ?');
    $stmt->execute([$pdo->lastInsertId()]);
    return $stmt->fetch();
}

/**
 * Create a registration. Returns:
 *   ['status'=>'created'|'duplicate', 'reg_number'=>..., 'registration_id'=>..., 'attendee'=>[...]]
 */
function create_registration(array $d, ?int $userId): array
{
    $ed = active_edition();
    if (!$ed) {
        throw new RegistrationError('No active event edition. An administrator must set one up first.');
    }
    $pdo = db();

    $category = $d['category'] ?? '';
    if (!in_array($category, ['group', 'solo', 'visitor'], true)) {
        throw new RegistrationError('Please choose how the person is attending.');
    }
    $fullName = trim($d['full_name'] ?? '');
    if ($fullName === '') {
        throw new RegistrationError('Full name is required.');
    }

    $isMember = $category !== 'visitor';
    $gender = in_array($d['gender'] ?? '', ['male', 'female'], true) ? $d['gender'] : null;
    if ($isMember && !$gender) {
        throw new RegistrationError('Gender is required for Church of Christ members (it sets the Bro./Sis. title).');
    }

    // Offline dedupe: if this client_uuid already synced, return the existing record.
    $clientUuid = trim($d['client_uuid'] ?? '') ?: null;
    if ($clientUuid) {
        $stmt = $pdo->prepare('SELECT id, reg_number, attendee_id FROM registrations WHERE client_uuid = ?');
        $stmt->execute([$clientUuid]);
        if ($existing = $stmt->fetch()) {
            return [
                'status' => 'duplicate',
                'reg_number' => $existing['reg_number'],
                'registration_id' => (int)$existing['id'],
                'client_uuid' => $clientUuid,
            ];
        }
    }

    // Returning attendee: link to an existing person instead of creating a new one.
    $linkedAttendeeId = !empty($d['attendee_id']) ? (int)$d['attendee_id'] : null;
    if ($linkedAttendeeId) {
        $stmt = $pdo->prepare('SELECT id FROM attendees WHERE id = ?');
        $stmt->execute([$linkedAttendeeId]);
        if (!$stmt->fetch()) {
            $linkedAttendeeId = null; // stale/unknown id — fall back to creating new
        } else {
            // Already registered for the active edition? Return the existing record.
            $stmt = $pdo->prepare('SELECT id, reg_number FROM registrations WHERE edition_id = ? AND attendee_id = ?');
            $stmt->execute([(int)$ed['id'], $linkedAttendeeId]);
            if ($already = $stmt->fetch()) {
                return [
                    'status' => 'already_registered',
                    'reg_number' => $already['reg_number'],
                    'registration_id' => (int)$already['id'],
                    'attendee_id' => $linkedAttendeeId,
                ];
            }
        }
    }

    $congregation = null;
    if ($category !== 'visitor') {
        $congregation = find_or_create_congregation($d);
        if (!$congregation) {
            throw new RegistrationError('A congregation is required for members.');
        }
    }

    $pdo->beginTransaction();
    try {
        $attendeeFields = [
            $fullName, $gender, $isMember ? 1 : 0,
            ($d['phone'] ?? '') ?: null, ($d['email'] ?? '') ?: null,
            !empty($d['birth_day']) ? (int)$d['birth_day'] : null,
            !empty($d['birth_month']) ? (int)$d['birth_month'] : null,
            ($d['home_state'] ?? '') ?: null, ($d['home_city'] ?? '') ?: null,
        ];
        if ($linkedAttendeeId) {
            // Reuse the existing person, refreshing their details with the latest info.
            $pdo->prepare(
                'UPDATE attendees SET full_name=?, gender=?, is_member=?, phone=?, email=?,
                        birth_day=?, birth_month=?, home_state=?, home_city=? WHERE id=?'
            )->execute(array_merge($attendeeFields, [$linkedAttendeeId]));
            $attendeeId = $linkedAttendeeId;
        } else {
            $pdo->prepare(
                'INSERT INTO attendees (full_name, gender, is_member, phone, email, birth_day, birth_month, home_state, home_city)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute($attendeeFields);
            $attendeeId = (int)$pdo->lastInsertId();
        }

        // Reg number — one continuous sequence per edition (see allocate_reg_number)
        $alloc = allocate_reg_number((int)$ed['id'], (int)$ed['year']);

        $accommodation = in_array($d['accommodation'] ?? '', ['camping', 'outside'], true) ? $d['accommodation'] : null;

        $pdo->prepare(
            'INSERT INTO registrations
                (edition_id, attendee_id, congregation_id, category, reg_number, reg_seq,
                 accommodation, accommodation_note, registered_by, batch_id, client_uuid)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (int)$ed['id'], $attendeeId, $congregation['id'] ?? null, $category,
            $alloc['reg_number'], $alloc['seq'],
            $accommodation, ($d['accommodation_note'] ?? '') ?: null, $userId,
            ($d['batch_id'] ?? null) ?: null, $clientUuid,
        ]);
        $regId = (int)$pdo->lastInsertId();

        if ($category === 'visitor') {
            $pdo->prepare(
                'INSERT INTO visitor_details (registration_id, church_attended, invited_by, how_heard, expectations)
                 VALUES (?,?,?,?,?)'
            )->execute([
                $regId,
                ($d['church_attended'] ?? '') ?: null,
                ($d['invited_by'] ?? '') ?: null,
                ($d['how_heard'] ?? '') ?: null,
                ($d['expectations'] ?? '') ?: null,
            ]);
        }

        $pdo->commit();
    } catch (\Throwable $ex) {
        $pdo->rollBack();
        throw new RegistrationError('Could not save registration: ' . $ex->getMessage());
    }

    return [
        'status' => 'created',
        'reg_number' => $alloc['reg_number'],
        'registration_id' => $regId,
        'client_uuid' => $clientUuid,
        'attendee' => ['full_name' => $fullName, 'gender' => $gender, 'is_member' => $isMember],
        'congregation' => $congregation ? $congregation['name'] : null,
    ];
}
