<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.php");
    exit();
}

require __DIR__ . "/db.php";

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM names WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: dashboard.php");
exit();
?>