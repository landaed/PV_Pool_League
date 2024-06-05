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
        $captcha = $_POST['g-recaptcha-response'];
        $secretKey = "6LcdPNopAAAAAOGzGfP0cIF4BpCDe8pwkfNbNAi3"; // Replace with your secret key
        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($secretKey) . "&response=" . urlencode($captcha));
        $responseKeys = json_decode($response, true);

        if(intval($responseKeys["success"]) !== 1) {
            die('Captcha verification failed.');
        }
        $teamName = $_POST['teamName'];
        $session = $_POST['session'];
        $dayDivision = $_POST['dayDivision'];
        $homeBarFirst = $_POST['homeBarFirst'];
        $homeBarSecond = $_POST['homeBarSecond'];
        $registrationDate = date('Y-m-d');

        // Insert Team information
        $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, RegistrationDate, Session) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $db->errno . ") " . $db->error);
        }
        $stmt->bind_param("ssssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $registrationDate, $session);
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
        $mail->Password = 'coinop911!';  // Consider securing your credentials.
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
        $mail->isHTML(true);
        $mail->Subject = 'Registration Confirmation - PV Pool League';

        // Send email to each player
        foreach ($playerData as $data) {
            if (!empty($data['email'])) {
                $mail->clearAddresses();  // Clear addresses for the next loop iteration
                $mail->addAddress($data['email']);  // Add current player email
                $mail->addBCC('eliplanda@gmail.com');  // Admin copy
                $mail->addBCC('friar@pvpoolleagues.com');  // Admin copy

                $mail->Body = "Hello " . $data['name'] . ",<br><br>" . 
                    "Thank you for joining the team '" . $teamName . "' in the PV Pool League." . 
                    "<br><br>Your session is: " . $session . 
                    "<br><br>Your division day: " . $dayDivision . 
                    "<br>First Home Bar: " . $homeBarFirst . 
                    "<br>Second Home Bar: " . $homeBarSecond . 
                    "<br>Registration Date: " . $registrationDate . 
                    (!empty($data['phone']) ? "<br>Phone: " . $data['phone'] : "") . 
                    (!empty($data['email']) ? "<br>Email: " . $data['email'] : "") . 
                    "<br><br>Friar - League Coordinator" . 
                    "<br><img src='https://i.imgur.com/Xw7k2Gp.png' style='width:100px;'/>";

                $mail->AltBody = "Hello " . $data['name'] . ",\n\nThank you for joining the team '" . $teamName . "' in the PV Pool League.";

                if (!$mail->send()) {
                    throw new Exception("Mailer Error: " . $mail->ErrorInfo);
                }
            }
        }

        header("Location: /registration_success.html");
        exit();
    }
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}


mysqli_close($db);
?>