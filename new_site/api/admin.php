<?php
/**
 * Authenticated admin API router for the dashboard. Every action below
 * requires a logged-in admin session.
 *
 * Dispatched by ?action=...   Reads use GET, mutations use POST.
 *
 * Admins:      list_admins, add_admin, delete_admin
 * Config:      list_config, add_region, update_region, delete_region,
 *              add_location, update_location, delete_location,
 *              add_session, update_session, delete_session, toggle_session,
 *              add_division, update_division, delete_division
 * Signups:     list_signups, update_team, delete_team,
 *              add_player, update_player, delete_player
 * Poster:      upload_poster, set_poster
 * Email:       send_email
 */
require_once __DIR__ . '/db.php';

ns_require_admin();
$action = $_GET['action'] ?? '';

function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ns_json(['error' => 'POST required.'], 405);
    }
}
function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

try {
    switch ($action) {

    // =====================================================  ADMINS  =========
    case 'list_admins':
        ns_json(['admins' => ns_all($db, "SELECT id, username, created_at FROM ns_admins ORDER BY username")]);

    case 'add_admin': {
        require_post();
        $b = ns_body();
        $username = trim($b['username'] ?? '');
        $password = (string) ($b['password'] ?? '');
        if ($username === '' || strlen($password) < 4) {
            ns_json(['error' => 'Username required and password must be at least 4 characters.'], 400);
        }
        $exists = ns_one($db, "SELECT id FROM ns_admins WHERE username = ?", 's', [$username]);
        if ($exists) ns_json(['error' => 'That username already exists.'], 409);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        ns_exec($db, "INSERT INTO ns_admins (username, password_hash, created_at) VALUES (?, ?, NOW())", 'ss', [$username, $hash])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_admin': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $total = (int) ns_one($db, "SELECT COUNT(*) c FROM ns_admins")['c'];
        if ($total <= 1) ns_json(['error' => 'Cannot delete the last remaining admin.'], 400);
        if ($id === (int) $_SESSION['ns_admin_id']) ns_json(['error' => 'You cannot delete your own account while logged in.'], 400);
        ns_exec($db, "DELETE FROM ns_admins WHERE id = ?", 'i', [$id])->close();
        ns_json(['ok' => true]);
    }

    // =====================================================  CONFIG  =========
    case 'list_config': {
        $regions = ns_all($db, "SELECT id, name, slug, sort_order, active FROM ns_regions ORDER BY sort_order, name");
        foreach ($regions as &$r) {
            $rid = (int) $r['id'];
            $r['locations'] = ns_all($db, "SELECT id, name, address, map_embed, sort_order, active FROM ns_locations WHERE region_id = ? ORDER BY sort_order, name", 'i', [$rid]);
            $r['sessions']  = ns_all($db, "SELECT id, name, is_open, sort_order FROM ns_sessions WHERE region_id = ? ORDER BY sort_order, name", 'i', [$rid]);
            $r['divisions'] = ns_all($db, "SELECT id, name, sort_order, active FROM ns_divisions WHERE region_id = ? ORDER BY sort_order, name", 'i', [$rid]);
        }
        unset($r);
        ns_json(['regions' => $regions]);
    }

    case 'add_region': {
        require_post();
        $b = ns_body();
        $name = trim($b['name'] ?? '');
        if ($name === '') ns_json(['error' => 'Region name required.'], 400);
        $slug = slugify($name);
        $exists = ns_one($db, "SELECT id FROM ns_regions WHERE slug = ?", 's', [$slug]);
        if ($exists) ns_json(['error' => 'A region with a similar name already exists.'], 409);
        $order = (int) ns_one($db, "SELECT COALESCE(MAX(sort_order),0)+1 n FROM ns_regions")['n'];
        ns_exec($db, "INSERT INTO ns_regions (name, slug, sort_order, active) VALUES (?, ?, ?, 1)", 'ssi', [$name, $slug, $order])->close();
        ns_json(['ok' => true, 'id' => $db->insert_id]);
    }

    case 'update_region': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $name = trim($b['name'] ?? '');
        $active = isset($b['active']) ? (int) (bool) $b['active'] : 1;
        if (!$id || $name === '') ns_json(['error' => 'Region id and name required.'], 400);
        ns_exec($db, "UPDATE ns_regions SET name = ?, active = ? WHERE id = ?", 'sii', [$name, $active, $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_region': {
        require_post();
        $id = (int) (ns_body()['id'] ?? 0);
        ns_exec($db, "DELETE FROM ns_locations WHERE region_id = ?", 'i', [$id])->close();
        ns_exec($db, "DELETE FROM ns_sessions WHERE region_id = ?", 'i', [$id])->close();
        ns_exec($db, "DELETE FROM ns_divisions WHERE region_id = ?", 'i', [$id])->close();
        ns_exec($db, "DELETE FROM ns_regions WHERE id = ?", 'i', [$id])->close();
        ns_json(['ok' => true]);
    }

    // ---- Locations --------------------------------------------------------
    case 'add_location': {
        require_post();
        $b = ns_body();
        $rid = (int) ($b['region_id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$rid || $name === '') ns_json(['error' => 'Region and location name required.'], 400);
        $addr = trim($b['address'] ?? '');
        $embed = trim($b['map_embed'] ?? '');
        $order = (int) ns_one($db, "SELECT COALESCE(MAX(sort_order),0)+1 n FROM ns_locations WHERE region_id = ?", 'i', [$rid])['n'];
        ns_exec($db, "INSERT INTO ns_locations (region_id, name, address, map_embed, sort_order, active) VALUES (?, ?, ?, ?, ?, 1)", 'isssi', [$rid, $name, $addr, $embed, $order])->close();
        ns_json(['ok' => true]);
    }

    case 'update_location': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$id || $name === '') ns_json(['error' => 'Location id and name required.'], 400);
        $addr = trim($b['address'] ?? '');
        $embed = trim($b['map_embed'] ?? '');
        $active = isset($b['active']) ? (int) (bool) $b['active'] : 1;
        ns_exec($db, "UPDATE ns_locations SET name = ?, address = ?, map_embed = ?, active = ? WHERE id = ?", 'sssii', [$name, $addr, $embed, $active, $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_location': {
        require_post();
        ns_exec($db, "DELETE FROM ns_locations WHERE id = ?", 'i', [(int) (ns_body()['id'] ?? 0)])->close();
        ns_json(['ok' => true]);
    }

    // ---- Sessions ---------------------------------------------------------
    case 'add_session': {
        require_post();
        $b = ns_body();
        $rid = (int) ($b['region_id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$rid || $name === '') ns_json(['error' => 'Region and session name required.'], 400);
        $order = (int) ns_one($db, "SELECT COALESCE(MAX(sort_order),0)+1 n FROM ns_sessions WHERE region_id = ?", 'i', [$rid])['n'];
        ns_exec($db, "INSERT INTO ns_sessions (region_id, name, is_open, sort_order) VALUES (?, ?, 1, ?)", 'isi', [$rid, $name, $order])->close();
        ns_json(['ok' => true]);
    }

    case 'update_session': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$id || $name === '') ns_json(['error' => 'Session id and name required.'], 400);
        ns_exec($db, "UPDATE ns_sessions SET name = ? WHERE id = ?", 'si', [$name, $id])->close();
        ns_json(['ok' => true]);
    }

    case 'toggle_session': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $isOpen = (int) (bool) ($b['is_open'] ?? 0);
        if (!$id) ns_json(['error' => 'Session id required.'], 400);
        ns_exec($db, "UPDATE ns_sessions SET is_open = ? WHERE id = ?", 'ii', [$isOpen, $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_session': {
        require_post();
        ns_exec($db, "DELETE FROM ns_sessions WHERE id = ?", 'i', [(int) (ns_body()['id'] ?? 0)])->close();
        ns_json(['ok' => true]);
    }

    // ---- Divisions --------------------------------------------------------
    case 'add_division': {
        require_post();
        $b = ns_body();
        $rid = (int) ($b['region_id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$rid || $name === '') ns_json(['error' => 'Region and division name required.'], 400);
        $order = (int) ns_one($db, "SELECT COALESCE(MAX(sort_order),0)+1 n FROM ns_divisions WHERE region_id = ?", 'i', [$rid])['n'];
        ns_exec($db, "INSERT INTO ns_divisions (region_id, name, sort_order, active) VALUES (?, ?, ?, 1)", 'isi', [$rid, $name, $order])->close();
        ns_json(['ok' => true]);
    }

    case 'update_division': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        $name = trim($b['name'] ?? '');
        if (!$id || $name === '') ns_json(['error' => 'Division id and name required.'], 400);
        $active = isset($b['active']) ? (int) (bool) $b['active'] : 1;
        ns_exec($db, "UPDATE ns_divisions SET name = ?, active = ? WHERE id = ?", 'sii', [$name, $active, $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_division': {
        require_post();
        ns_exec($db, "DELETE FROM ns_divisions WHERE id = ?", 'i', [(int) (ns_body()['id'] ?? 0)])->close();
        ns_json(['ok' => true]);
    }

    // ====================================================  SIGNUPS  =========
    case 'list_signups': {
        $where = [];
        $types = '';
        $params = [];
        if (!empty($_GET['region_id'])) { $where[] = 't.region_id = ?'; $types .= 'i'; $params[] = (int) $_GET['region_id']; }
        if (!empty($_GET['session_id'])) { $where[] = 't.session_id = ?'; $types .= 'i'; $params[] = (int) $_GET['session_id']; }
        if (!empty($_GET['division_id'])) { $where[] = 't.division_id = ?'; $types .= 'i'; $params[] = (int) $_GET['division_id']; }
        if (!empty($_GET['q'])) {
            $where[] = '(t.team_name LIKE ? OR EXISTS (SELECT 1 FROM ns_players p WHERE p.team_id = t.id AND (p.name LIKE ? OR p.email LIKE ?)))';
            $like = '%' . $_GET['q'] . '%';
            $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $teams = ns_all($db, "SELECT * FROM ns_teams t $clause ORDER BY t.created_at DESC, t.id DESC", $types, $params);
        $ids = array_map(fn($t) => (int) $t['id'], $teams);
        $playersByTeam = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $players = ns_all($db, "SELECT * FROM ns_players WHERE team_id IN ($in) ORDER BY is_captain DESC, id", str_repeat('i', count($ids)), $ids);
            foreach ($players as $p) { $playersByTeam[(int) $p['team_id']][] = $p; }
        }
        foreach ($teams as &$t) { $t['players'] = $playersByTeam[(int) $t['id']] ?? []; }
        unset($t);
        ns_json(['teams' => $teams]);
    }

    case 'update_team': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        if (!$id) ns_json(['error' => 'Team id required.'], 400);
        ns_exec($db,
            "UPDATE ns_teams SET team_name = ?, session_name = ?, division_name = ?, home_bar_first = ?, home_bar_second = ? WHERE id = ?",
            'sssssi',
            [trim($b['team_name'] ?? ''), trim($b['session_name'] ?? ''), trim($b['division_name'] ?? ''),
             trim($b['home_bar_first'] ?? ''), trim($b['home_bar_second'] ?? ''), $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_team': {
        require_post();
        $id = (int) (ns_body()['id'] ?? 0);
        ns_exec($db, "DELETE FROM ns_players WHERE team_id = ?", 'i', [$id])->close();
        ns_exec($db, "DELETE FROM ns_teams WHERE id = ?", 'i', [$id])->close();
        ns_json(['ok' => true]);
    }

    case 'add_player': {
        require_post();
        $b = ns_body();
        $tid = (int) ($b['team_id'] ?? 0);
        if (!$tid) ns_json(['error' => 'Team id required.'], 400);
        ns_exec($db, "INSERT INTO ns_players (team_id, name, email, phone, is_captain) VALUES (?, ?, ?, ?, 0)",
            'isss', [$tid, trim($b['name'] ?? ''), trim($b['email'] ?? ''), trim($b['phone'] ?? '')])->close();
        ns_json(['ok' => true]);
    }

    case 'update_player': {
        require_post();
        $b = ns_body();
        $id = (int) ($b['id'] ?? 0);
        if (!$id) ns_json(['error' => 'Player id required.'], 400);
        ns_exec($db, "UPDATE ns_players SET name = ?, email = ?, phone = ? WHERE id = ?",
            'sssi', [trim($b['name'] ?? ''), trim($b['email'] ?? ''), trim($b['phone'] ?? ''), $id])->close();
        ns_json(['ok' => true]);
    }

    case 'delete_player': {
        require_post();
        ns_exec($db, "DELETE FROM ns_players WHERE id = ?", 'i', [(int) (ns_body()['id'] ?? 0)])->close();
        ns_json(['ok' => true]);
    }

    // =====================================================  POSTER  =========
    case 'set_poster': {
        // Point the home poster at an existing URL/path, or toggle visibility.
        require_post();
        $b = ns_body();
        if (isset($b['url'])) {
            $url = trim($b['url']);
            ns_exec($db, "INSERT INTO ns_settings (setting_key, setting_value) VALUES ('home_poster', ?)
                          ON DUPLICATE KEY UPDATE setting_value = ?", 'ss', [$url, $url])->close();
        }
        if (isset($b['enabled'])) {
            $en = ((int) (bool) $b['enabled']) ? '1' : '0';
            ns_exec($db, "INSERT INTO ns_settings (setting_key, setting_value) VALUES ('home_poster_enabled', ?)
                          ON DUPLICATE KEY UPDATE setting_value = ?", 'ss', [$en, $en])->close();
        }
        ns_json(['ok' => true]);
    }

    case 'upload_poster': {
        require_post();
        if (empty($_FILES['poster']) || $_FILES['poster']['error'] !== UPLOAD_ERR_OK) {
            ns_json(['error' => 'No file uploaded or upload error.'], 400);
        }
        $f = $_FILES['poster'];
        if ($f['size'] > 8 * 1024 * 1024) ns_json(['error' => 'Image must be under 8MB.'], 400);
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) ns_json(['error' => 'File is not a valid image.'], 400);
        $extByMime = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp',
        ];
        $mime = $info['mime'] ?? '';
        if (!isset($extByMime[$mime])) ns_json(['error' => 'Only JPG, PNG, GIF or WEBP allowed.'], 400);
        $ext = $extByMime[$mime];
        $dir = __DIR__ . '/../uploads';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fname = 'poster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $dir . '/' . $fname;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            ns_json(['error' => 'Could not save uploaded file.'], 500);
        }
        // Stored as a path relative to the /new_site pages (which live one level up from /api).
        $rel = 'uploads/' . $fname;
        ns_exec($db, "INSERT INTO ns_settings (setting_key, setting_value) VALUES ('home_poster', ?)
                      ON DUPLICATE KEY UPDATE setting_value = ?", 'ss', [$rel, $rel])->close();
        ns_exec($db, "INSERT INTO ns_settings (setting_key, setting_value) VALUES ('home_poster_enabled', '1')
                      ON DUPLICATE KEY UPDATE setting_value = '1'")->close();
        ns_json(['ok' => true, 'url' => $rel]);
    }

    // ======================================================  EMAIL  =========
    case 'send_email': {
        require_post();
        $b = ns_body();
        $subject = trim($b['subject'] ?? '');
        $message = (string) ($b['message'] ?? '');
        $teamIds = $b['team_ids'] ?? [];
        if (is_string($teamIds)) $teamIds = array_filter(array_map('trim', explode(',', $teamIds)));
        $teamIds = array_values(array_filter(array_map('intval', (array) $teamIds)));
        if ($subject === '' || $message === '' || !$teamIds) {
            ns_json(['error' => 'Subject, message and at least one team are required.'], 400);
        }
        $in = implode(',', array_fill(0, count($teamIds), '?'));
        $rows = ns_all($db, "SELECT DISTINCT email FROM ns_players WHERE team_id IN ($in) AND email <> ''",
            str_repeat('i', count($teamIds)), $teamIds);
        $recipients = array_values(array_unique(array_map(fn($r) => $r['email'], $rows)));
        if (!$recipients) ns_json(['error' => 'None of the selected teams have email addresses on file.'], 400);

        require_once __DIR__ . '/../../vendor/Exception.php';
        require_once __DIR__ . '/../../vendor/PHPMailer.php';
        require_once __DIR__ . '/../../vendor/SMTP.php';

        $sent = 0; $failed = [];
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'mail.pvpoolleagues.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply@pvpoolleagues.com';
            $mail->Password = 'coinop911!';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
            $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $htmlBody = nl2br(htmlspecialchars($message, ENT_QUOTES));
            foreach ($recipients as $addr) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($addr);
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $message;
                    $mail->send();
                    $sent++;
                } catch (Throwable $e) {
                    $failed[] = $addr;
                }
            }
        } catch (Throwable $e) {
            ns_json(['error' => 'Mailer setup failed: ' . $e->getMessage()], 500);
        }
        ns_json(['ok' => true, 'sent' => $sent, 'recipients' => count($recipients), 'failed' => $failed]);
    }

    default:
        ns_json(['error' => 'Unknown action.'], 400);
    }
} catch (Exception $e) {
    ns_json(['error' => $e->getMessage()], 500);
}
