<?php
/**
 * Shared database + helper layer for the NEW SITE (/new_site).
 *
 * Reuses the existing production DB credentials defined in /php/db_connect.php
 * so we never duplicate the secrets. All new-site tables are prefixed with
 * "ns_" so they live alongside (and never clash with) the existing
 * SportsTeam / Player tables used by the old site.
 */

// ---- Connect using the existing credentials -------------------------------
// db_connect.php defines DB_SERVER/DB_USERNAME/DB_PASSWORD/DB_DATABASE and
// opens a mysqli connection into $db. We include it once.
require_once __DIR__ . '/../../php/db_connect.php';

if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection unavailable.']);
    exit;
}

// ---- Session (used for admin auth) ----------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('PVNS_SESSID');
    session_start();
}

// ---- Small helpers ---------------------------------------------------------
function ns_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function ns_body() {
    // Accept JSON bodies as well as classic form posts.
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function ns_is_logged_in() {
    return !empty($_SESSION['ns_admin_id']);
}

function ns_require_admin() {
    if (!ns_is_logged_in()) {
        ns_json(['error' => 'Not authenticated.'], 401);
    }
}

/**
 * Prepared-statement helper. $types is the bind string (e.g. "ssi").
 * Returns the mysqli_stmt (already executed) or throws.
 */
function ns_exec(mysqli $db, $sql, $types = '', $params = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** Fetch all rows for a query as an associative array. */
function ns_all(mysqli $db, $sql, $types = '', $params = []) {
    $stmt = ns_exec($db, $sql, $types, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** Fetch a single row (or null). */
function ns_one(mysqli $db, $sql, $types = '', $params = []) {
    $rows = ns_all($db, $sql, $types, $params);
    return $rows[0] ?? null;
}
