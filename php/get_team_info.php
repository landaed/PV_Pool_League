<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Ensure this path is correct

header('Content-Type: application/json');

try {
    $query = "
        SELECT t.TeamID, t.TeamName, t.RegistrationDate, t.HomeBarFirstPick, t.HomeBarSecondPick, p.PlayerID, p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        ORDER BY t.TeamID, p.PlayerID
    ";

    $result = $db->query($query);

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'No teams found']);
        exit;
    }

    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $team_id = $row['TeamID'];
        if (!isset($teams[$team_id])) {
            $teams[$team_id] = [
                'TeamID' => $team_id,
                'TeamName' => $row['TeamName'],
                'RegistrationDate' => $row['RegistrationDate'],
                'HomeBarFirstPick' => $row['HomeBarFirstPick'],
                'HomeBarSecondPick' => $row['HomeBarSecondPick'],
                'Players' => []
            ];
        }
        $teams[$team_id]['Players'][] = [
            'PlayerID' => $row['PlayerID'],
            'PlayerName' => $row['PlayerName'],
            'Email' => $row['Email'],
            'Phone' => $row['Phone']
        ];
    }

    echo json_encode(array_values($teams));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
