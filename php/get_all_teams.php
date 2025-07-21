<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php'; // Ensure this path is correct
header('Content-Type: application/json');

try {
    // Get filter parameters from request
    $filters = [
        'session' => $_GET['session'] ?? null,
        'division' => $_GET['division'] ?? null,
        'location' => $_GET['location'] ?? null,
        'team_name' => $_GET['team_name'] ?? null,
        'player_name' => $_GET['player_name'] ?? null,
        'limit' => $_GET['limit'] ?? null,
        'offset' => $_GET['offset'] ?? 0
    ];
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if ($filters['session']) {
        $whereConditions[] = "t.Session = ?";
        $params[] = $filters['session'];
        $types .= 's';
    }
    
    if ($filters['division']) {
        $whereConditions[] = "t.DayDivision = ?";
        $params[] = $filters['division'];
        $types .= 's';
    }
    
    if ($filters['location']) {
        if (strtolower($filters['location']) === 'cochrane') {
            $whereConditions[] = "(t.TeamName LIKE ? OR t.HomeBarFirstPick LIKE ?)";
            $params[] = '%Cochrane%';
            $params[] = '%Cochrane%';
            $types .= 'ss';
        } else if (strtolower($filters['location']) === 'calgary') {
            $whereConditions[] = "(t.TeamName NOT LIKE ? AND (t.HomeBarFirstPick NOT LIKE ? OR t.HomeBarFirstPick IS NULL))";
            $params[] = '%Cochrane%';
            $params[] = '%Cochrane%';
            $types .= 'ss';
        }
    }
    
    if ($filters['team_name']) {
        $whereConditions[] = "t.TeamName LIKE ?";
        $params[] = '%' . $filters['team_name'] . '%';
        $types .= 's';
    }
    
    if ($filters['player_name']) {
        $whereConditions[] = "p.PlayerName LIKE ?";
        $params[] = '%' . $filters['player_name'] . '%';
        $types .= 's';
    }
    
    // Build the WHERE clause
    $whereClause = $whereConditions ? ' WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Build the main query
    $query = "
        SELECT t.TeamID, t.TeamName, t.RegistrationDate, t.Session, 
               t.HomeBarFirstPick, t.HomeBarSecondPick, t.DayDivision,
               p.PlayerID, p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        $whereClause
        ORDER BY STR_TO_DATE(CONCAT('01 ', t.Session), '%d %M %Y') DESC, 
                 t.RegistrationDate DESC, t.TeamID, p.PlayerID
    ";
    
    // Add LIMIT if specified
    if ($filters['limit']) {
        $query .= " LIMIT ? OFFSET ?";
        $params[] = (int)$filters['limit'];
        $params[] = (int)$filters['offset'];
        $types .= 'ii';
    }
    
    // Prepare and execute the query
    if ($params) {
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $db->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($query);
    }
    
    // Check if the query execution was successful
    if (!$result) {
        throw new Exception('Database query failed: ' . $db->error);
    }
    
    // Get available sessions for filter dropdown (separate query)
    $sessionsQuery = "SELECT DISTINCT Session FROM SportsTeam ORDER BY STR_TO_DATE(CONCAT('01 ', Session), '%d %M %Y') DESC";
    $sessionsResult = $db->query($sessionsQuery);
    $availableSessions = [];
    while ($row = $sessionsResult->fetch_assoc()) {
        $availableSessions[] = $row['Session'];
    }
    
    // Get available divisions for filter dropdown
    $divisionsQuery = "SELECT DISTINCT DayDivision FROM SportsTeam WHERE DayDivision IS NOT NULL ORDER BY DayDivision";
    $divisionsResult = $db->query($divisionsQuery);
    $availableDivisions = [];
    while ($row = $divisionsResult->fetch_assoc()) {
        $availableDivisions[] = $row['DayDivision'];
    }
    
    // Process the main results
    $sessions = [];
    $summary = [
        'totalTeams' => 0,
        'totalPlayers' => 0,
        'locations' => [
            'Calgary' => ['totalTeams' => 0, 'totalPlayers' => 0, 'divisions' => []],
            'Cochrane' => ['totalTeams' => 0, 'totalPlayers' => 0, 'divisions' => []]
        ]
    ];
    
    $processedTeams = [];
    $processedPlayers = [];
    
    while ($row = $result->fetch_assoc()) {
        $session = $row['Session'];
        $team_id = $row['TeamID'];
        $player_id = $row['PlayerID'];
        
        // Determine location
        $isCochrane = stripos($row['TeamName'], 'Cochrane') !== false || 
                     stripos($row['HomeBarFirstPick'], 'Cochrane') !== false;
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
                'Location' => $location,
                'Players' => []
            ];
            
            // Update summary data for unique teams
            if (!isset($processedTeams[$team_id])) {
                $processedTeams[$team_id] = true;
                $summary['totalTeams']++;
                $summary['locations'][$location]['totalTeams']++;
                
                if ($division) {
                    if (!isset($summary['locations'][$location]['divisions'][$division])) {
                        $summary['locations'][$location]['divisions'][$division] = 0;
                    }
                    $summary['locations'][$location]['divisions'][$division]++;
                }
            }
        }
        
        // Add player if exists
        if ($player_id && !isset($processedPlayers[$player_id])) {
            $sessions[$session][$team_id]['Players'][] = [
                'PlayerID' => $player_id,
                'PlayerName' => $row['PlayerName'],
                'Email' => $row['Email'],
                'Phone' => $row['Phone']
            ];
            
            // Update player count
            $processedPlayers[$player_id] = true;
            $summary['totalPlayers']++;
            $summary['locations'][$location]['totalPlayers']++;
        }
    }
    
    // Calculate total unique sessions and divisions
    $summary['totalSessions'] = count($sessions);
    $allDivisions = [];
    foreach ($summary['locations'] as $loc => $data) {
        $allDivisions = array_merge($allDivisions, array_keys($data['divisions']));
    }
    $summary['totalDivisions'] = count(array_unique($allDivisions));
    
    // Flatten sessions array for JSON encoding
    $final_output = [
        'sessions' => [],
        'summary' => $summary,
        'filters' => [
            'availableSessions' => $availableSessions,
            'availableDivisions' => $availableDivisions,
            'appliedFilters' => array_filter($filters)
        ]
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