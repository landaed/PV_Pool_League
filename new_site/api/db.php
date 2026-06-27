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

/**
 * Fetch all rows for a query as an associative array.
 *
 * Deliberately avoids mysqli_stmt::get_result()/fetch_all(), which require the
 * mysqlnd driver that some shared hosts don't compile in. Instead we bind the
 * result columns dynamically via result_metadata()/bind_result(), which works
 * on every mysqli build.
 */
function ns_all(mysqli $db, $sql, $types = '', $params = []) {
    $stmt = ns_exec($db, $sql, $types, $params);
    $rows = [];

    // Prefer get_result() when mysqlnd is available (faster, simpler).
    if (function_exists('mysqli_stmt_get_result')) {
        $res = @$stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $stmt->close();
            return $rows;
        }
    }

    // mysqlnd-free fallback.
    $meta = $stmt->result_metadata();
    if ($meta) {
        $fields = [];
        while ($field = $meta->fetch_field()) { $fields[] = $field->name; }
        $meta->free();

        $rowBuf = [];
        $bind = [];
        foreach ($fields as $f) { $rowBuf[$f] = null; $bind[] = &$rowBuf[$f]; }
        call_user_func_array([$stmt, 'bind_result'], $bind);

        $stmt->store_result();
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($fields as $f) { $copy[$f] = $rowBuf[$f]; }
            $rows[] = $copy;
        }
    }
    $stmt->close();
    return $rows;
}

/** Fetch a single row (or null). */
function ns_one(mysqli $db, $sql, $types = '', $params = []) {
    $rows = ns_all($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Determine a team's region. Uses the stored Region value when present
 * (new registrations); otherwise falls back to the legacy Calgary/Cochrane
 * heuristic the old site used, so pre-existing signups still group sensibly.
 */
function ns_region_for($stored, $teamName, $homeBar) {
    if ($stored !== null && trim((string) $stored) !== '') {
        return $stored;
    }
    $hay = strtolower(($teamName ?? '') . ' ' . ($homeBar ?? ''));
    return (strpos($hay, 'cochrane') !== false) ? 'Cochrane' : 'Calgary';
}
