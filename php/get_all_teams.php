<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php'; // Ensure this path is correct

header('Content-Type: application/json');

try {
    // Query adjusted to filter only "FALL 2024" session
    $query = "
        SELECT t.TeamID, t.TeamName, t.RegistrationDate, t.Session, t.HomeBarFirstPick, t.HomeBarSecondPick, t.DayDivision, 
               p.PlayerID, p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        WHERE t.Session = 'FALL 2024'
        ORDER BY STR_TO_DATE(t.Session, '%M %Y') DESC, t.RegistrationDate DESC, t.TeamID, p.PlayerID
    ";

    $result = $db->query($query);

    // Check if the query execution was successful
    if (!$result) {
        echo json_encode(['error' => 'Database query failed: ' . $db->error]);
        exit;
    }

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'No teams found']);
        exit;
    }

    $sessions = [];
    $summary = [
        'totalTeams' => 0,
        'totalPlayers' => 0,
        'locations' => [
            'Calgary' => ['totalTeams' => 0, 'totalPlayers' => 0, 'divisions' => []],
            'Cochrane' => ['totalTeams' => 0, 'totalPlayers' => 0, 'divisions' => []]
        ]
    ];

    while ($row = $result->fetch_assoc()) {
        $session = $row['Session'];
        $team_id = $row['TeamID'];
        $isCochrane = stripos($row['TeamName'], 'Cochrane') !== false || stripos($row['HomeBarFirstPick'], 'Cochrane') !== false;
        $location = $isCochrane ? 'Cochrane' : 'Calgary';
        $division = $row['DayDivision'];

        // Initialize sessions data
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
                'DayDivision' => $division,
                'Players' => []
            ];

            // Update summary data
            $summary['totalTeams']++;
            $summary['locations'][$location]['totalTeams']++;
            if (!isset($summary['locations'][$location]['divisions'][$division])) {
                $summary['locations'][$location]['divisions'][$division] = 0;
            }
            $summary['locations'][$location]['divisions'][$division]++;
        }

        // Update player count
        if ($row['PlayerID']) {
            $sessions[$session][$team_id]['Players'][] = [
                'PlayerID' => $row['PlayerID'],
                'PlayerName' => $row['PlayerName'],
                'Email' => $row['Email'],
                'Phone' => $row['Phone']
            ];
            $summary['totalPlayers']++;
            $summary['locations'][$location]['totalPlayers']++;
        }
    }

    // Flatten sessions array for JSON encoding
    $final_output = [
        'sessions' => [],
        'summary' => $summary
    ];
    foreach ($sessions as $session_name => $teams) {
        $final_output['sessions'][$session_name] = array_values($teams);
    }

    echo json_encode($final_output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
