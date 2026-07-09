<?php
session_start();
require "db.php";

if (isset($_POST["selected_ids"])) {

    $stmt = $conn->prepare("DELETE FROM names WHERE id = ?");

    foreach ($_POST["selected_ids"] as $id) {

        $id = (int)$id;

        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    $stmt->close();
}

header("Location: dashboard.php");
exit();
?>