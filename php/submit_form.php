<?php
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/Exception.php';
require '../vendor/PHPMailer.php';
require '../vendor/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assume validation is already done for these fields
    $teamName = $_POST['teamName'];
    $dayDivision = $_POST['dayDivision'];
    $homeBarFirst = $_POST['homeBarFirst'];
    $homeBarSecond = $_POST['homeBarSecond'];
    $captainName = preg_replace("/[^a-zA-Z\s]/", "", $_POST['captainName']);
    $captainEmail = $_POST['captainEmail'];
    $captainPhone = $_POST['captainPhone'];
    $player2 = $_POST['player2'];
    $registrationDate = date('Y-m-d'); // Assuming you're using the current date

    // Prepare an insert statement
    $stmt = $db->prepare("INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, CaptainName, CaptainEmail, CaptainPhone, Player2Name, RegistrationDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Bind variables to the prepared statement as parameters
    $stmt->bind_param("sssssssss", $teamName, $dayDivision, $homeBarFirst, $homeBarSecond, $captainName, $captainEmail, $captainPhone, $player2, $registrationDate);

    // Attempt to execute the prepared statement
    if ($stmt->execute()) {
        $mail = new PHPMailer(true);
           try {
               // Server settings
               $mail->isSMTP();
               $mail->Host       = 'mail.pvpoolleagues.com';
               $mail->SMTPAuth   = true;
               $mail->Username   = 'noreply@pvpoolleagues.com';
               $mail->Password   = 'coinop911!'; // Replace with the actual password
               $mail->SMTPSecure = 'ssl';  // Enable SSL encryption
               $mail->Port       = 465;  // SMTP SSL port

               // Recipients
               $mail->setFrom('noreply@pvpoolleagues.com', 'PV Pool Leagues');
               $mail->addAddress($captainEmail);     // Add a recipient for the captain
               $mail->addAddress('eliplanda@gmail.com'); // Add a recipient for the company

               // Content
               $mail->isHTML(true);
               $mail->Subject = 'Registration Confirmation - PV Pool League';
               $mail->Body = "Hello " .
                $captainName .
                ",<br><br>Thank you for registering your team, " .
                $teamName .
                ", in the PV Pool League." .
                "<br><br>Friar - League Coordinator<br>friar@pvpoolleagues.com<br><br>" .
                "<img src='https://i.imgur.com/Xw7k2Gp.png' alt='PV Pool Leagues Logo' style='width:100px;'/>";

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

       // Close statement
       $stmt->close();
       mysqli_close($db);
}
?>
