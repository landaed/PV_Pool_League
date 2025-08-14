<?php
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/Exception.php';
require '../vendor/PHPMailer.php';
require '../vendor/SMTP.php';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // reCAPTCHA validation
        $captcha = $_POST['g-recaptcha-response'] ?? '';
        $secretKey = "6LcdPNopAAAAAOGzGfP0cIF4BpCDe8pwkfNbNAi3"; // TODO: move to env
        $response = file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($secretKey) .
            "&response=" . urlencode($captcha)
        );
        $responseKeys = json_decode($response, true);
        if (empty($responseKeys["success"])) {
            die('Captcha verification failed.');
        }

        // Team fields
        $teamName        = trim($_POST['teamName'] ?? '');
        $session         = trim($_POST['session'] ?? '');
        $dayDivision     = trim($_POST['dayDivision'] ?? '');
        $homeBarFirst    = trim($_POST['homeBarFirst'] ?? '');
        $homeBarSecond   = trim($_POST['homeBarSecond'] ?? '');
        $registrationDate = date('Y-m-d');

        // server-side sanity: home bars must differ
        if ($homeBarFirst && $homeBarSecond && $homeBarFirst === $homeBarSecond) {
            throw new Exception("Home Bar 1st and 2nd picks must be different.");
        }

        // Insert Team
        $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate, Session) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
        }
        $stmt->bind_param("ssssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate, $session);
        $stmt->execute();
        $teamID = $db->insert_id;
        $stmt->close();

        // Parse player data
        $playerData = [];
        foreach ($_POST as $key => $value) {
            if (preg_match('/player(\d+)_(name|email|phone)/', $key, $m)) {
                $idx = intval($m[1]);
                $field = $m[2];
                $playerData[$idx][$field] = trim($value);
            }
        }
        if (!empty($playerData)) {
            ksort($playerData, SORT_NUMERIC);
        }

        // Insert players
        foreach ($playerData as $idx => $data) {
            $name  = $data['name']  ?? '';
            $email = $data['email'] ?? '';
            $phone = $data['phone'] ?? '';

            if ($name === '' && $email === '' && $phone === '') continue;

            if ($name !== '' && $email !== '' && $phone !== '') {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                $stmt->bind_param("isss", $teamID, $name, $email, $phone);
            } elseif ($name !== '' && $email !== '') {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                $stmt->bind_param("iss", $teamID, $name, $email);
            } elseif ($name !== '' && $phone !== '') {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Phone) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                $stmt->bind_param("iss", $teamID, $name, $phone);
            } else { // name only
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName) VALUES (?, ?)");
                if (!$stmt) throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                $stmt->bind_param("is", $teamID, $name);
            }
            $stmt->execute();
            $stmt->close();
        }

        // Build roster summary (captain = first player index)
        $captain = null;
        $teammates = [];
        foreach ($playerData as $idx => $p) {
            $entry = [
                'name'  => $p['name']  ?? '',
                'email' => $p['email'] ?? '',
                'phone' => $p['phone'] ?? ''
            ];
            if ($captain === null) $captain = $entry; else $teammates[] = $entry;
        }

        // Email all players who provided an email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'mail.pvpoolleagues.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@pvpoolleagues.com';
        $mail->Password = 'coinop911!';  // TODO: move to env + rotate
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
        $mail->isHTML(true);
        $mail->Subject = 'Registration Confirmation - PV Pool League';

        // Prebuild roster HTML
        $rosterHtml = '';
        if ($captain) {
            $rosterHtml .= "<p><strong>Captain:</strong> " . htmlspecialchars($captain['name']);
            if (!empty($captain['email'])) $rosterHtml .= " &lt;" . htmlspecialchars($captain['email']) . "&gt;";
            if (!empty($captain['phone'])) $rosterHtml .= " — " . htmlspecialchars($captain['phone']);
            $rosterHtml .= "</p>";
        }
        if (!empty($teammates)) {
            $rosterHtml .= "<p><strong>Teammates:</strong></p><ul>";
            foreach ($teammates as $tm) {
                $line = htmlspecialchars($tm['name']);
                $bits = [];
                if (!empty($tm['email'])) $bits[] = "&lt;" . htmlspecialchars($tm['email']) . "&gt;";
                if (!empty($tm['phone'])) $bits[] = htmlspecialchars($tm['phone']);
                if (!empty($bits)) $line .= " — " . implode(' | ', $bits);
                $rosterHtml .= "<li>$line</li>";
            }
            $rosterHtml .= "</ul>";
        }

        foreach ($playerData as $p) {
            if (empty($p['email'])) continue;

            $mail->clearAddresses();
            $mail->addAddress($p['email']);
            $mail->addBCC('eliplanda@gmail.com');
            $mail->addBCC('pvpoolleague@gmail.com');

            $mail->Body =
                "Hello " . htmlspecialchars($p['name'] ?? 'Player') . ",<br><br>" .
                "You're registered for the <strong>PV Pool League</strong> as part of team <strong>'" . htmlspecialchars($teamName) . "'</strong>.<br><br>" .
                "<strong>Session:</strong> " . htmlspecialchars($session) . "<br>" .
                "<strong>Division Day:</strong> " . htmlspecialchars($dayDivision) . "<br>" .
                "<strong>Home Bar (1st pick):</strong> " . htmlspecialchars($homeBarFirst) . "<br>" .
                "<strong>Home Bar (2nd pick):</strong> " . htmlspecialchars($homeBarSecond) . "<br>" .
                "<strong>Registration Date:</strong> " . htmlspecialchars($registrationDate) . "<br><br>" .
                $rosterHtml .
                "<hr style='border:none;border-top:1px solid #ddd'/>" .
                "<p><em>Next steps:</em> We’ll confirm schedules, match times, and bar assignments once registrations close. " .
                "If anything looks off, reply to this email with corrections.</p>" .
                "<br>— Mark Mapatac<br>League Coordinator, PV Pool Leagues<br>" .
                "<img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;' alt='PV Logo'/>";

            $mail->AltBody =
                "Hello " . ($p['name'] ?? 'Player') . ",\n\n" .
                "You're registered for the PV Pool League as part of team '" . $teamName . "'.\n" .
                "Session: $session\nDivision Day: $dayDivision\n" .
                "Home Bar (1st): $homeBarFirst\nHome Bar (2nd): $homeBarSecond\n" .
                "Registration Date: $registrationDate\n\n" .
                "Captain/Teammates included in the HTML email.\n\n" .
                "— Mark Mapatac, League Coordinator";

            if (!$mail->send()) {
                throw new Exception("Mailer Error: " . $mail->ErrorInfo);
            }
        }

        header("Location: /registration_success.html");
        exit();
    }
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
} finally {
    if (isset($db) && $db instanceof mysqli) {
        mysqli_close($db);
    }
}
