<?php
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require 'vendor/Exception.php';
require 'vendor/PHPMailer.php';
require 'vendor/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $teamName = filter_var($_POST['teamName'], FILTER_SANITIZE_STRING);
    $dayDivision = filter_var($_POST['dayDivision'], FILTER_SANITIZE_STRING);
    $homeBarFirst = filter_var($_POST['homeBarFirst'], FILTER_SANITIZE_STRING);
    $homeBarSecond = filter_var($_POST['homeBarSecond'], FILTER_SANITIZE_STRING);
    $captainName = preg_replace("/[^a-zA-Z\s]/", "", $_POST['captainName']); // Remove special characters and digits
    $captainEmail = filter_var($_POST['captainEmail'], FILTER_SANITIZE_EMAIL);
    $captainPhone = preg_replace("/[^0-9\-\(\) ]/", "", $_POST['captainPhone']); // Keep only numbers, dashes, parentheses, and spaces
    $player2 = filter_var($_POST['player2'], FILTER_SANITIZE_STRING);

    // Validate email format
    if (!filter_var($captainEmail, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format";
        exit();
    }
    // Insert query
    $sql = "INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, CaptainName, CaptainEmail, CaptainPhone, Player2Name, RegistrationDate) VALUES ('$teamName', '$dayDivision', '$homeBarFirst', '$homeBarSecond', '$captainName', '$captainEmail', '$captainPhone', '$player2', '$registrationDate')";

    if (mysqli_query($db, $sql)) {
           $mail = new PHPMailer(true);

           try {
               // Server settings
               $mail->isSMTP();
               $mail->Host       = 'mail.pvpoolleagues.com';
               $mail->SMTPAuth   = true;
               $mail->Username   = 'noreply@pvpoolleagues.com';
               $mail->Password   = 'coinop911!'; // Replace with the actual password
               $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
               $mail->Port       = 587;

               // Recipients
               $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
               $mail->addAddress($captainEmail);     // Add a recipient for the captain
               $mail->addAddress('pacvend@gmail.com'); // Add a recipient for the company

               // Content
               $mail->isHTML(true);
               $mail->Subject = 'Registration Confirmation - PV Pool League';
               $mail->Body    = "Hello " . $captainName . ",<br><br>Thank you for registering your team, " . $teamName . ", in the PV Pool League.";
               $mail->AltBody = "Hello " . $captainName . ",\n\nThank you for registering your team, " . $teamName . ", in the PV Pool League.";

               $mail->send();

               // Redirect to success page after sending the email
               header("Location: /registration_success.html");
               exit();
           } catch (Exception $e) {
               echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
               // Consider logging the error and not just displaying it
               // Also consider what to do next if the email sending fails
           }
       } else {
           echo "ERROR: Could not able to execute $sql. " . mysqli_error($db);
       }


    mysqli_close($db);
}
?>
