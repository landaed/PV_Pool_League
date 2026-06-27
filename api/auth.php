<?php
/**
 * Authentication endpoints for the admin dashboard.
 *
 *   GET  ?action=me      -> { loggedIn: bool, username }
 *   POST ?action=login   -> { username, password }
 *   POST ?action=logout
 */
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'me') {
        ns_json([
            'loggedIn' => ns_is_logged_in(),
            'username' => $_SESSION['ns_admin_username'] ?? null,
        ]);
    }

    if ($action === 'login') {
        $body = ns_body();
        $username = trim($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            ns_json(['error' => 'Username and password are required.'], 400);
        }
        $row = ns_one($db, "SELECT id, username, password_hash FROM ns_admins WHERE username = ?", 's', [$username]);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            ns_json(['error' => 'Invalid username or password.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['ns_admin_id'] = (int) $row['id'];
        $_SESSION['ns_admin_username'] = $row['username'];
        ns_json(['loggedIn' => true, 'username' => $row['username']]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        ns_json(['loggedIn' => false]);
    }

    ns_json(['error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    ns_json(['error' => $e->getMessage()], 500);
}
