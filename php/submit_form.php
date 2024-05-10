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

    // Insert Team information
    $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate);
    $stmt->execute();
    $teamID = $db->insert_id;

    // Initialize player data array
    $playerData = [];

    // Parse player data from POST
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'player') === 0 && $value) {
            list($prefix, $index, $field) = explode('_', $key);
            $playerData[$index][$field] = $value;
        }
    }

    // Debug output
    echo "<pre>Player Data: " . print_r($playerData, true) . "</pre>";

    // Prepare statement for players
    $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "Prepare failed: (" . $db->errno . ") " . $db->error;
    }

    // Insert each player
    foreach ($playerData as $index => $data) {
        if (!empty($data['name'])) {
            $stmt->bind_param("isss", $teamID, $data['name'], $data['email'], $data['phone']);
            $stmt->execute();
            echo "<p>Inserted: " . $data['name'] . "</p>";
        }
    }

    $stmt->close();

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

    // Attempt to send email to each player
    foreach ($playerData as $data) {
        if (!empty($data['email'])) {
            $mail->addAddress($data['email']);
        }
    }

    $mail->addAddress('eliplanda@gmail.com');  // Additional recipient for testing
    $mail->Body    = "Hello, <br><br>Thank you for registering your team, '" . $teamName . "', in the PV Pool League.<br><br>Friar - League Coordinator<br><img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;'/>";
    $mail->AltBody = "Hello, \n\nThank you for registering your team, '" . $teamName . "', in the PV Pool League.";

    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    } else {
        echo "Registration successful and email sent.";
    }

    mysqli_close($db);
}
?>