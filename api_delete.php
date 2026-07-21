<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";
if (!function_exists('jsonFail')) { die('helpers.php missing jsonFail'); }

// Provide a lightweight fallback for requireLogin if helpers.php doesn't define it.
if (!function_exists('requireLogin')) {
    function requireLogin(): int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }
}



header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail(405, 'Method not allowed.');
}

$user_id = requireLogin();
if (!$user_id) {
    jsonFail(401, 'Unauthorized');
}

if (!requireCsrf()) {
    jsonFail(403, 'Invalid or missing CSRF token.');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonFail(422, 'Invalid record id.');
}

try {
    // Ownership is enforced in the WHERE clause itself, not just the check
    // above - this is what stops user A from deleting user B's records by
    // guessing an id.
    $stmt = $conn->prepare("DELETE FROM names WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        jsonFail(404, 'Record not found.');
    }

    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} catch (Throwable $e) {
    error_log('api_delete.php error: ' . $e->getMessage());
    jsonFail(500, 'A server error occurred.');
}
