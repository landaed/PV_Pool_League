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
    $registrationDate = date('Y-m-d'); // Current date

    // Insert Team information
    $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate);
    $stmt->execute();
    $teamID = $db->insert_id;  // Get the auto-incremented Team ID

    // Handle multiple player registration
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'player') !== false && $value != '') {
            $field = explode('_', $key);
            $playerIndex = $field[1];
            $playerData[$playerIndex][$field[2]] = $value;  // Organize player data by index and field
        }
    }

    // Insert each player
    foreach ($playerData as $data) {
        $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $teamID, $data['name'], $data['email'], $data['phone']);
        $stmt->execute();
    }

    $stmt->close();
    mysqli_close($db);

    // Sending confirmation email to the captain (the first player)
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.pvpoolleagues.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@pvpoolleagues.com';
        $mail->Password   = 'coinop911!';
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;

        $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
        $mail->addAddress($playerData[1]['email']);  // Assuming the first player is the captain

        $mail->isHTML(true);
        $mail->Subject = 'Registration Confirmation - PV Pool League';
        $mail->Body    = "Hello " . $playerData[1]['name'] . ",<br><br>Thank you for registering your team, " . $teamName . ", in the PV Pool League.<br><br>Friar - League Coordinator<br><img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;'/>";

        $mail->send();
        echo "Registration successful and email sent.";
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
