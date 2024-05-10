<?php
require_once 'db_connect.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/Exception.php';
require '../vendor/PHPMailer.php';
require '../vendor/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teamName = $_POST['teamName'];
    $dayDivision = $_POST['dayDivision'];
    $homeBarFirst = $_POST['homeBarFirst'];
    $homeBarSecond = $_POST['homeBarSecond'];
    $registrationDate = date('Y-m-d');
    $foundPlayer = "no players found...";

    // Insert Team information
    $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate);
    $stmt->execute();
    $teamID = $db->insert_id;

    // Prepare statement for players
    $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "Prepare failed: (" . $db->errno . ") " . $db->error;
    }

    // Handle multiple player registration and emails
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

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'player') === 0) {
            $parts = explode('_', $key);
            $index = $parts[1];
            $field = $parts[2];
            $playerData[$index][$field] = $value;
        }
    }

    foreach ($playerData as $index => $data) {
        // Only insert if the name is provided
        if (!empty($data['name'])) {
            $stmt->bind_param("isss", $teamID, $data['name'], $data['email'], $data['phone']);
            $stmt->execute();
            $foundPlayer = "found a player!";
            if (!empty($data['email'])) {
                $mail->addAddress($data['email']);
            }
            $mail->addAddress("eliplanda@gmail.com");
        }
    }

    $stmt->close();

    // Set email body and send
    $mail->Body    = $foundPlayer . " Hello, <br><br>Thank you for registering your team, '" . $teamName . "', in the PV Pool League.<br><br>Friar - League Coordinator<br><img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;'/>";
    $mail->AltBody = "Hello, \n\nThank you for registering your team, '" . $teamName . "', in the PV Pool League.";
    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    } else {
        echo "Registration successful and email sent.";
    }

    mysqli_close($db);
}
?>