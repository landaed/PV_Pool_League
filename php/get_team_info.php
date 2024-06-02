<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Ensure this path is correct

header('Content-Type: application/json');

if (!isset($_GET['team_id'])) {
    echo json_encode(['error' => 'Team ID is required']);
    exit;
}

$team_id = $_GET['team_id'];

try {
    $query = "
        SELECT t.TeamName, t.RegistrationDate, t.HomeBarFirstPick, t.HomeBarSecondPick, p.PlayerID, p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        WHERE t.TeamID = ?
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Team not found']);
        exit;
    }

    $team_info = [
        'TeamName' => '',
        'RegistrationDate' => '',
        'HomeBarFirstPick' => '',
        'HomeBarSecondPick' => '',
        'Players' => []
    ];

    while ($row = $result->fetch_assoc()) {
        if (empty($team_info['TeamName'])) {
            $team_info['TeamName'] = $row['TeamName'];
            $team_info['RegistrationDate'] = $row['RegistrationDate'];
            $team_info['HomeBarFirstPick'] = $row['HomeBarFirstPick'];
            $team_info['HomeBarSecondPick'] = $row['HomeBarSecondPick'];
        }
        $team_info['Players'][] = [
            'PlayerID' => $row['PlayerID'],
            'PlayerName' => $row['PlayerName'],
            'Email' => $row['Email'],
            'Phone' => $row['Phone']
        ];
    }

    echo json_encode($team_info);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
