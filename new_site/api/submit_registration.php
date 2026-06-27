<?php
/**
 * Public registration handler for the NEW SITE.
 *
 * Writes into the shared SportsTeam / Player tables (tagging the team's Region)
 * and emails every player who supplied an address.
 *
 * Expects a POST (form-encoded or JSON) with:
 *   regionSlug, session (id), dayDivision (id), teamName,
 *   homeBarFirst, homeBarSecond, and player<N>_(name|email|phone)
 */
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ns_json(['error' => 'POST required.'], 405);
    }

    $body = ns_body();

    $regionSlug   = trim($body['regionSlug'] ?? '');
    $sessionId    = (int) ($body['session'] ?? 0);
    $divisionId   = (int) ($body['dayDivision'] ?? 0);
    $teamName     = trim($body['teamName'] ?? '');
    $homeBarFirst = trim($body['homeBarFirst'] ?? '');
    $homeBarSecond= trim($body['homeBarSecond'] ?? '');

    if ($regionSlug === '' || $teamName === '') {
        ns_json(['error' => 'Region and team name are required.'], 400);
    }
    if ($homeBarFirst && $homeBarSecond && $homeBarFirst === $homeBarSecond) {
        ns_json(['error' => 'Home Bar 1st and 2nd picks must be different.'], 400);
    }

    // Resolve region + validate the session is real and OPEN.
    $region = ns_one($db, "SELECT id, name FROM ns_regions WHERE slug = ? AND active = 1", 's', [$regionSlug]);
    if (!$region) {
        ns_json(['error' => 'Unknown region.'], 400);
    }
    $regionId = (int) $region['id'];
    $regionName = $region['name'];

    $session = ns_one($db, "SELECT id, name, is_open FROM ns_sessions WHERE id = ? AND region_id = ?", 'ii', [$sessionId, $regionId]);
    if (!$session) {
        ns_json(['error' => 'Please choose a valid session.'], 400);
    }
    if ((int) $session['is_open'] !== 1) {
        ns_json(['error' => 'Registration for this session is closed.'], 400);
    }
    $sessionName = $session['name'];

    $divisionName = '';
    if ($divisionId) {
        $division = ns_one($db, "SELECT id, name FROM ns_divisions WHERE id = ? AND region_id = ?", 'ii', [$divisionId, $regionId]);
        if (!$division) {
            ns_json(['error' => 'Please choose a valid division.'], 400);
        }
        $divisionName = $division['name'];
    }

    $registrationDate = date('Y-m-d');

    // Insert team into the SHARED SportsTeam table (same data the old site uses),
    // tagging it with the region so the new dashboard can filter on it.
    $stmt = ns_exec($db,
        "INSERT INTO SportsTeam
            (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate, Session, Region)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        'sssssss',
        [$teamName, $divisionName, $homeBarFirst, $homeBarSecond, $registrationDate, $sessionName, $regionName]
    );
    $teamId = $db->insert_id;
    $stmt->close();

    // Parse player data: player<N>_(name|email|phone)
    $playerData = [];
    foreach ($body as $key => $value) {
        if (preg_match('/^player(\d+)_(name|email|phone)$/', $key, $m)) {
            $playerData[(int) $m[1]][$m[2]] = trim($value);
        }
    }
    ksort($playerData, SORT_NUMERIC);

    foreach ($playerData as $p) {
        $name  = $p['name']  ?? '';
        $email = $p['email'] ?? '';
        $phone = $p['phone'] ?? '';
        if ($name === '' && $email === '' && $phone === '') continue;
        $stmt = ns_exec($db,
            "INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)",
            'isss', [$teamId, $name, $email, $phone]);
        $stmt->close();
    }

    // ----------------------------------------------------------- email -----
    try {
        require_once __DIR__ . '/../../vendor/Exception.php';
        require_once __DIR__ . '/../../vendor/PHPMailer.php';
        require_once __DIR__ . '/../../vendor/SMTP.php';

        // Build roster HTML
        $captain = null; $teammates = [];
        foreach ($playerData as $p) {
            $entry = ['name' => $p['name'] ?? '', 'email' => $p['email'] ?? '', 'phone' => $p['phone'] ?? ''];
            if ($entry['name'] === '' && $entry['email'] === '' && $entry['phone'] === '') continue;
            if ($captain === null) $captain = $entry; else $teammates[] = $entry;
        }
        $rosterHtml = '';
        if ($captain) {
            $rosterHtml .= '<p><strong>Captain:</strong> ' . htmlspecialchars($captain['name']);
            if ($captain['email']) $rosterHtml .= ' &lt;' . htmlspecialchars($captain['email']) . '&gt;';
            if ($captain['phone']) $rosterHtml .= ' — ' . htmlspecialchars($captain['phone']);
            $rosterHtml .= '</p>';
        }
        if ($teammates) {
            $rosterHtml .= '<p><strong>Teammates:</strong></p><ul>';
            foreach ($teammates as $tm) {
                $line = htmlspecialchars($tm['name']);
                $bits = [];
                if ($tm['email']) $bits[] = '&lt;' . htmlspecialchars($tm['email']) . '&gt;';
                if ($tm['phone']) $bits[] = htmlspecialchars($tm['phone']);
                if ($bits) $line .= ' — ' . implode(' | ', $bits);
                $rosterHtml .= "<li>$line</li>";
            }
            $rosterHtml .= '</ul>';
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'mail.pvpoolleagues.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@pvpoolleagues.com';
        $mail->Password = 'coinop911!';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
        $mail->isHTML(true);
        $mail->Subject = 'Registration Confirmation - PV Pool League';

        foreach ($playerData as $p) {
            if (empty($p['email'])) continue;
            $mail->clearAddresses();
            $mail->addAddress($p['email']);
            $mail->addBCC('eliplanda@gmail.com');
            $mail->addBCC('pvpoolleague@gmail.com');
            $mail->Body =
                'Hello ' . htmlspecialchars($p['name'] ?? 'Player') . ',<br><br>' .
                "You're registered for the <strong>PV Pool League</strong> as part of team <strong>'" . htmlspecialchars($teamName) . "'</strong>.<br><br>" .
                '<strong>Region:</strong> ' . htmlspecialchars($regionName) . '<br>' .
                '<strong>Session:</strong> ' . htmlspecialchars($sessionName) . '<br>' .
                '<strong>Division Day:</strong> ' . htmlspecialchars($divisionName) . '<br>' .
                '<strong>Home Bar (1st pick):</strong> ' . htmlspecialchars($homeBarFirst) . '<br>' .
                '<strong>Home Bar (2nd pick):</strong> ' . htmlspecialchars($homeBarSecond) . '<br>' .
                '<strong>Registration Date:</strong> ' . htmlspecialchars($registrationDate) . '<br><br>' .
                $rosterHtml .
                "<hr style='border:none;border-top:1px solid #ddd'/>" .
                '<p><em>Next steps:</em> We’ll confirm schedules, match times, and bar assignments once registrations close. ' .
                'If anything looks off, email pvpoolleague@gmail.com with corrections.</p>' .
                '<br>— Mark Mapatac<br>League Coordinator, PV Pool Leagues';
            $mail->AltBody =
                'Hello ' . ($p['name'] ?? 'Player') . ",\n\n" .
                "You're registered for the PV Pool League as part of team '" . $teamName . "'.\n" .
                "Region: $regionName\nSession: $sessionName\nDivision Day: $divisionName\n" .
                "Home Bar (1st): $homeBarFirst\nHome Bar (2nd): $homeBarSecond\n" .
                "Registration Date: $registrationDate\n\n— Mark Mapatac, League Coordinator";
            @$mail->send();
        }
    } catch (Throwable $mailErr) {
        // Don't fail the registration if email has a problem — it's already saved.
    }

    // Browser form posts expect a redirect; JSON clients get JSON.
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        ns_json(['ok' => true, 'teamId' => $teamId]);
    }
    header('Location: ../registration_success.html');
    exit;

} catch (Throwable $e) {
    ns_json(['error' => $e->getMessage()], 500);
}
