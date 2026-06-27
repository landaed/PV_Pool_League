<?php
/**
 * One-time installer for the NEW SITE.
 *
 * Visit /new_site/api/setup.php once in a browser (while logged into the
 * server / or just open it) to:
 *   - create all ns_* tables (idempotent: CREATE TABLE IF NOT EXISTS)
 *   - seed the default admin (username: eli, password: 1!Cheddar) if none exist
 *   - seed the four regions + their locations / sessions / divisions if empty
 *
 * Running it again is safe; it never overwrites existing data.
 */
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

// Light guard so the installer isn't triggered by drive-by bots.
// Visit:  /new_site/api/setup.php?run=1
if (($_GET['run'] ?? '') !== '1') {
    echo "PV Pool League — new site installer.\n\n";
    echo "To create the database tables and seed the default admin + regions,\n";
    echo "re-open this page with ?run=1 appended, i.e.:\n\n";
    echo "    /new_site/api/setup.php?run=1\n\n";
    echo "Running it is safe to repeat; it never overwrites existing data.\n";
    echo "Delete this file once setup is complete.\n";
    exit;
}

$log = [];

function step(&$log, $db, $sql) {
    if ($db->query($sql) === false) {
        $log[] = 'ERROR: ' . $db->error . ' :: ' . substr($sql, 0, 80);
    }
}

// ---------------------------------------------------------------- tables ----
step($log, $db, "CREATE TABLE IF NOT EXISTS ns_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

step($log, $db, "CREATE TABLE IF NOT EXISTS ns_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

step($log, $db, "CREATE TABLE IF NOT EXISTS ns_regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

step($log, $db, "CREATE TABLE IF NOT EXISTS ns_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255) DEFAULT '',
    map_embed TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    INDEX (region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

step($log, $db, "CREATE TABLE IF NOT EXISTS ns_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_open TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX (region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

step($log, $db, "CREATE TABLE IF NOT EXISTS ns_divisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1,
    INDEX (region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Signups live in the SHARED SportsTeam / Player tables (same as the old site)
// so the new dashboard sees every existing registration. Create them only if
// this is a fresh database that doesn't already have them.
$hasSportsTeam = $db->query("SHOW TABLES LIKE 'SportsTeam'");
if ($hasSportsTeam && $hasSportsTeam->num_rows === 0) {
    step($log, $db, "CREATE TABLE SportsTeam (
        TeamID INT AUTO_INCREMENT PRIMARY KEY,
        TeamName VARCHAR(255) NOT NULL,
        DayDivision VARCHAR(255) DEFAULT NULL,
        HomeBarFirstPick VARCHAR(255) DEFAULT NULL,
        HomeBarSecondPick VARCHAR(255) DEFAULT NULL,
        RegistrationDate DATE DEFAULT NULL,
        Session VARCHAR(255) DEFAULT NULL,
        Region VARCHAR(120) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    step($log, $db, "CREATE TABLE Player (
        PlayerID INT AUTO_INCREMENT PRIMARY KEY,
        TeamID INT NOT NULL,
        PlayerName VARCHAR(255) DEFAULT NULL,
        Email VARCHAR(255) DEFAULT NULL,
        Phone VARCHAR(64) DEFAULT NULL,
        INDEX (TeamID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $log[] = 'Created SportsTeam / Player tables (fresh database).';
} else {
    // Existing install: make sure the Region tagging column is present.
    $hasRegion = $db->query("SHOW COLUMNS FROM SportsTeam LIKE 'Region'");
    if ($hasRegion && $hasRegion->num_rows === 0) {
        step($log, $db, "ALTER TABLE SportsTeam ADD COLUMN Region VARCHAR(120) DEFAULT NULL");
        $log[] = 'Added Region column to existing SportsTeam table.';
    } else {
        $log[] = 'SportsTeam.Region column already present.';
    }
}

$log[] = 'Tables ensured.';

// ----------------------------------------------------------- seed admin -----
$adminCount = (int) ($db->query("SELECT COUNT(*) c FROM ns_admins")->fetch_assoc()['c'] ?? 0);
if ($adminCount === 0) {
    $hash = password_hash('1!Cheddar', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO ns_admins (username, password_hash, created_at) VALUES (?, ?, NOW())");
    $u = 'eli';
    $stmt->bind_param('ss', $u, $hash);
    $stmt->execute();
    $stmt->close();
    $log[] = "Seeded default admin (username: eli).";
} else {
    $log[] = "Admins already present ($adminCount); skipped seeding.";
}

// --------------------------------------------------------- seed settings ----
$db->query("INSERT IGNORE INTO ns_settings (setting_key, setting_value)
            VALUES ('home_poster', '../assets/images/Fall_2025_league.jpg')");
$db->query("INSERT IGNORE INTO ns_settings (setting_key, setting_value)
            VALUES ('home_poster_enabled', '1')");

// --------------------------------------------------------- seed regions -----
$regionCount = (int) ($db->query("SELECT COUNT(*) c FROM ns_regions")->fetch_assoc()['c'] ?? 0);
if ($regionCount > 0) {
    $log[] = "Regions already present ($regionCount); skipped region seeding.";
    echo implode("\n", $log);
    $db->close();
    exit;
}

function add_region($db, $name, $slug, $order) {
    $stmt = $db->prepare("INSERT INTO ns_regions (name, slug, sort_order, active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param('ssi', $name, $slug, $order);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    return $id;
}
function add_loc($db, $rid, $name, $addr, $embed, $o) {
    $stmt = $db->prepare("INSERT INTO ns_locations (region_id, name, address, map_embed, sort_order, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('isssi', $rid, $name, $addr, $embed, $o);
    $stmt->execute();
    $stmt->close();
}
function add_session($db, $rid, $name, $o) {
    $stmt = $db->prepare("INSERT INTO ns_sessions (region_id, name, is_open, sort_order) VALUES (?, ?, 1, ?)");
    $stmt->bind_param('isi', $rid, $name, $o);
    $stmt->execute();
    $stmt->close();
}
function add_div($db, $rid, $name, $o) {
    $stmt = $db->prepare("INSERT INTO ns_divisions (region_id, name, sort_order, active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param('isi', $rid, $name, $o);
    $stmt->execute();
    $stmt->close();
}

// ---- Calgary --------------------------------------------------------------
$calgary = add_region($db, 'Calgary', 'calgary', 1);
$calgaryBars = [
    'Boston Pizza (7 Heritage Gate SE)',
    'Dixon’s Pub (15425 Bannister Rd SE #24)',
    'Huntington Hills Community Centre (520 78 Ave NW)',
    'Tipsy Pig Pub Evanston (2060 Symons Valley Pkwy NW)',
    'Bowness Legion #238 (138 Bowness Ctr NW)',
    'The Club House Lounge (5012 16 Ave NW)',
    'Bonasera Pizza (1204 Edmonton Tr NE)',
    'Leather Pocket Billiards (3715 Edmonton Tr NE)',
    'Chapelhow Legion #284 (606 38 Ave NE)',
    'Top Brass Restaurant (1725 32 Ave NE)',
    'Neighbourhood Pub (834 68 St NE)',
    'Boddums Up Pub (1704 61 St SE)',
    'Border Crossing Pub (1814 36 St SE)',
    'Forest Lawn Legion #275 (755 40 St SE)',
    'Kokonut Kove Pub & Grill (3309 17 Ave SE)',
    'Hose & Hound Pub (1030 9 Ave SE)',
    'Crazy Horse Bar & Grill (4068 Ogden Rd SE)',
    'Chill Billiards (134-13226 Macleod Tr SE)',
    'Chalks Billiards (15110 Bannister Rd SE)',
    'King’s Head Pub (9116 Macleod Tr S)',
    'Stonegate Pub (7640 Fairmont Dr SE)',
    'Pig & Duke Pub (1312 12 Ave SW)',
    'City Pub (2835 37 St SW)',
    'Murdoch’s Bar & Grill (1935 37 St SW)',
    'The Blues Can (2002 16th Ave NW)',
];
$o = 0; foreach ($calgaryBars as $b) { add_loc($db, $calgary, $b, '', '', $o++); }
add_session($db, $calgary, 'Fall 2025/2026', 0);
foreach (['MONDAY OPEN','MONDAY A+','TUESDAY A','TUESDAY B','WEDNESDAY A','WEDNESDAY B','THURSDAY LOWER B','SCOTCH DOUBLES A','SCOTCH DOUBLES B'] as $i => $d) {
    add_div($db, $calgary, $d, $i);
}

// ---- Cochrane -------------------------------------------------------------
$cochrane = add_region($db, 'Cochrane', 'cochrane', 2);
$cochraneBars = [
    'Cochrane Legion (114 5 Ave W)',
    'Texas Gate Bar & Grill (304 1 St W)',
    'Boston Pizza (15 Westside Dr, Cochrane)',
    'The Venue Music Sportsbar (79 Railway St E)',
];
$o = 0; foreach ($cochraneBars as $b) { add_loc($db, $cochrane, $b, '', '', $o++); }
add_session($db, $cochrane, 'Fall 2025/2026', 0);
add_div($db, $cochrane, 'COCHRANE', 0);
add_div($db, $cochrane, 'HIGH RIVER', 1);

// ---- Vancouver ------------------------------------------------------------
$vancouver = add_region($db, 'Vancouver', 'vancouver', 3);
add_session($db, $vancouver, 'Fall 2025/2026', 0);
add_div($db, $vancouver, 'OPEN', 0);

// ---- Vancouver Island -----------------------------------------------------
$island = add_region($db, 'Vancouver Island', 'vancouver-island', 4);
add_session($db, $island, 'Fall 2025/2026', 0);
add_div($db, $island, 'OPEN', 0);

$log[] = 'Seeded regions: Calgary, Cochrane, Vancouver, Vancouver Island.';
$log[] = 'SETUP COMPLETE.';

echo implode("\n", $log);
$db->close();
