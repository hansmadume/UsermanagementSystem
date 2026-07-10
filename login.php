<?php
session_start();
require "db.php";

// Login UI (redirect-only file in this project).

// Get form data
$username = trim($_POST['username']);
$password = $_POST['password'];

// Validation
$errors = [];

if (empty($username)) {
    $errors[] = "Username is required.";
}

if (empty($password)) {
    $errors[] = "Password is required.";
}

// If there are validation errors
if (!empty($errors)) {
    $_SESSION["error"] = implode("<br>", $errors);
    header("Location: index.php");
    exit();
}

// Check if username exists
$sql = "SELECT id, username, password FROM users WHERE username = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        // Prevent Session Fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        header("Location: dashboard.php");
        exit();

    } else {

        $_SESSION["error"] = "Incorrect password.";
        header("Location: index.php");
        exit();

    }

} else {

    $_SESSION["error"] = "User not found.";
    header("Location: index.php");
    exit();

}

$stmt->close();
$conn->close();
?>
