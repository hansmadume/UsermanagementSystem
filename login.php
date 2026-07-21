<?php
session_start();
require_once "db.php";

const REDIRECT_LOGIN_PAGE = "index.php";
const REDIRECT_HEADER = "Location: ";

// CSRF check
if (
    !isset($_POST["csrf"], $_SESSION["csrf"]) ||
    !hash_equals($_SESSION["csrf"], $_POST["csrf"])
) {
    $_SESSION["error"] = "Invalid request. Please try again.";
    header(REDIRECT_HEADER . REDIRECT_LOGIN_PAGE);
    exit();
}

// Very basic brute-force throttle: after 5 failed attempts, force a short wait.
$_SESSION['login_attempts'] ??= 0;
$_SESSION['login_last_attempt'] ??= 0;

if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_last_attempt']) < 30) {
    $_SESSION["error"] = "Too many failed attempts. Please wait 30 seconds and try again.";
    header(REDIRECT_HEADER . REDIRECT_LOGIN_PAGE);
    exit();
}

// Get form data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
$errors = [];

if ($username === '') {
    $errors[] = "Username is required.";
}

if ($password === '') {
    $errors[] = "Password is required.";
}

if (!empty($errors)) {
    $_SESSION["error"] = implode("<br>", array_map('htmlspecialchars', $errors));
    header("Location: " . REDIRECT_LOGIN_PAGE);
    exit();
}

// Check if username exists
$sql = "SELECT id, username, password FROM users WHERE username = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

// --- UNIVERSAL FIX: Avoid get_result() ---
$stmt->store_result();

// Bind the columns from your SELECT statement directly to PHP variables
$stmt->bind_result($db_id, $db_username, $db_password);

// Fetch the matching row if it exists
if ($stmt->num_rows === 1 && $stmt->fetch()) {
    $stmt->close();

    // Verify the password against the retrieved hash
    if ($db_password !== null && password_verify($password, $db_password)) {
        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $db_id;
        $_SESSION['username'] = $db_username;
        unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);

        header(REDIRECT_HEADER . 'dashboard.php');
        exit();
    }
} else {
    $stmt->close();
}

// If we reach here, either the user wasn't found or password verification failed
$_SESSION['login_attempts']++;
$_SESSION['login_last_attempt'] = time();

$_SESSION["error"] = "Invalid username or password.";
header(REDIRECT_HEADER . 'index.php');
exit();
