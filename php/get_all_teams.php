<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Ensure this path is correct

header('Content-Type: application/json');

try {
    $query = "
        SELECT t.TeamID, t.TeamName, t.RegistrationDate, t.Session, t.HomeBarFirstPick, t.HomeBarSecondPick, t.DayDivision, p.PlayerID, p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        ORDER BY STR_TO_DATE(t.Session, '%M %Y') DESC, t.RegistrationDate DESC, t.TeamID, p.PlayerID
    ";

    $result = $db->query($query);

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'No teams found']);
        exit;
    }

    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $session = $row['Session'];
        $team_id = $row['TeamID'];
        if (!isset($sessions[$session])) {
            $sessions[$session] = [];
        }
        if (!isset($sessions[$session][$team_id])) {
            $sessions[$session][$team_id] = [
                'TeamID' => $team_id,
                'TeamName' => $row['TeamName'],
                'RegistrationDate' => $row['RegistrationDate'],
                'HomeBarFirstPick' => $row['HomeBarFirstPick'],
                'HomeBarSecondPick' => $row['HomeBarSecondPick'],
                'DayDivision' => $row['DayDivision'],
                'Players' => []
            ];
        }
        $sessions[$session][$team_id]['Players'][] = [
            'PlayerID' => $row['PlayerID'],
            'PlayerName' => $row['PlayerName'],
            'Email' => $row['Email'],
            'Phone' => $row['Phone']
        ];
    }

    // Flatten sessions array for JSON encoding
    $final_output = [];
    foreach ($sessions as $session_name => $teams) {
        $final_output[$session_name] = array_values($teams);
    }

    echo json_encode($final_output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
