<?php
session_start();

// This check was MISSING entirely in the original file - anyone holding any
// valid session (or none, depending on server config) could bulk-delete
// records. Restored to match the rest of the app.
if (!isset($_SESSION["username"], $_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/db.php";

$user_id = (int)$_SESSION['user_id'];

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST["csrf"], $_SESSION["csrf"]) ||
    !hash_equals($_SESSION["csrf"], $_POST["csrf"])
) {
    http_response_code(403);
    die("Invalid request.");
}

if (!empty($_POST["selected_ids"]) && is_array($_POST["selected_ids"])) {

    // Ownership enforced per-row so one user can never delete another
    // user's records by sending extra ids in the request.
    $stmt = $conn->prepare("DELETE FROM names WHERE id = ? AND user_id = ?");

    foreach ($_POST["selected_ids"] as $rawId) {
        $id = (int)$rawId;
        if ($id <= 0) {
            continue;
        }
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
    }

    $stmt->close();
}

header("Location: dashboard.php");
exit();
