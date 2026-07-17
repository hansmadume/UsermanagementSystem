<?php
session_start();

if (!isset($_SESSION["username"], $_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";


$user_id = (int)$_SESSION['user_id'];

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !requireCsrf()
) {

    http_response_code(403);
    die("Invalid request.");
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    // Ownership enforced in the WHERE clause - a user can only delete their
    // own records, even if they tamper with the id in the form.
    $stmt = $conn->prepare("DELETE FROM names WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
}

header("Location: dashboard.php");
exit();
