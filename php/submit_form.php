<?php
require_once 'db_connect.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $teamName = mysqli_real_escape_string($db, $_POST['teamName']);
    $dayDivision = mysqli_real_escape_string($db, $_POST['dayDivision']);
    $homeBarFirst = mysqli_real_escape_string($db, $_POST['homeBarFirst']);
    $homeBarSecond = mysqli_real_escape_string($db, $_POST['homeBarSecond']);
    $captainName = mysqli_real_escape_string($db, $_POST['captainName']);
    $captainEmail = mysqli_real_escape_string($db, $_POST['captainEmail']);
    $captainPhone = mysqli_real_escape_string($db, $_POST['captainPhone']);
    $player2 = mysqli_real_escape_string($db, $_POST['player2']);
    $registrationDate = date('Y-m-d'); // Current date

    // Insert query
    $sql = "INSERT INTO SportsTeam (TeamName, DayDivision, HomeBarFirstPick, HomeBarSecondPick, CaptainName, CaptainEmail, CaptainPhone, Player2Name, RegistrationDate) VALUES ('$teamName', '$dayDivision', '$homeBarFirst', '$homeBarSecond', '$captainName', '$captainEmail', '$captainPhone', '$player2', '$registrationDate')";

    if(mysqli_query($db, $sql)){
        echo "Records added successfully.";
    } else{
        echo "ERROR: Could not able to execute $sql. " . mysqli_error($db);
    }

    mysqli_close($db);
}
?>
