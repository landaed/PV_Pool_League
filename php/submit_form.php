<?php
require_once 'db_connect.php';

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

    if(mysqli_query($db, $sql)){
        header("Location: /registration_success.html");
    } else{
        echo "ERROR: Could not able to execute $sql. " . mysqli_error($db);
    }

    mysqli_close($db);
}
?>
