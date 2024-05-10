<?php
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/Exception.php';
require '../vendor/PHPMailer.php';
require '../vendor/SMTP.php';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $teamName = $_POST['teamName'];
        $dayDivision = $_POST['dayDivision'];
        $homeBarFirst = $_POST['homeBarFirst'];
        $homeBarSecond = $_POST['homeBarSecond'];
        $registrationDate = date('Y-m-d');

        // Insert Team information
        $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
        }
        $stmt->bind_param("sssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate);
        $stmt->execute();
        $teamID = $db->insert_id;

        // Prepare statement for players
       

        // Initialize player data array
        $playerData = [];

        // Parse player data from POST
        foreach ($_POST as $key => $value) {
            if (preg_match('/player(\d+)_(name|email|phone)/', $key, $matches)) {
                // $matches[1] will be the index, $matches[2] will be the field
                $index = $matches[1];
                $field = $matches[2];
                $playerData[$index][$field] = $value;
                
            }
        }

        // Insert each player
        foreach ($playerData as $index => $data) {
            if (!empty($data['name']) && !empty($data['email']) && !empty($data['phone'])) {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email, Phone) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                }
                $stmt->bind_param("isss", $teamID, $data['name'], $data['email'], $data['phone']);
                $stmt->execute();
                
            }
            else  if (!empty($data['name']) && !empty($data['email'])) {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Email) VALUES (?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                }
                $stmt->bind_param("iss", $teamID, $data['name'], $data['email']);
                $stmt->execute();
            }
            else  if (!empty($data['name']) && !empty($data['phone'])) {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName, Phone) VALUES (?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                }
                $stmt->bind_param("iss", $teamID, $data['name'], $data['phone']);
                $stmt->execute();
                
            }
            else  if (!empty($data['name'])) {
                $stmt = $db->prepare("INSERT INTO Player (TeamID, PlayerName) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
                }
                $stmt->bind_param("is", $teamID, $data['name']);
                $stmt->execute();
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
        $defaultBody = "Hello, <br><br>Thank you for your participation in the league.<br><br>Friar - League Coordinator";

        // Send email to each player
        foreach ($playerData as $data) {
            $mail->Body = $defaultBody;
            if (!empty($data['email'])) {
                $mail->addAddress($data['email']);
                
                $mail->Body    = "Hello " . $data['name'] . ",<br><br>Thank you for joining the team '" . $teamName . "' in the PV Pool League.<br><br>Friar - League Coordinator<br><img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;'/>";
                $mail->AltBody = "Hello " . $data['name'] . ",\n\nThank you for joining the team '" . $teamName . "' in the PV Pool League.";
                if (!$mail->send()) {
                    throw new Exception("Mailer Error: " . $mail->ErrorInfo);
                }
                $mail->clearAddresses();  // Clear addresses for the next loop iteration
                $mail->addAddress('eliplanda@gmail.com');  // Additional recipient for testing
                $mail->addAddress('friar@pvpoolleagues.com');  // Additional recipient for testing
            }
        } 
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        header("Location: /registration_success.html");
        exit();
    }
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}


mysqli_close($db);
?>