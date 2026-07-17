<?php

require_once __DIR__ . "/inc/helpers.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST logout and protect with CSRF
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(405);
    die('Method not allowed.');
}

if (!requireCsrf()) {
    http_response_code(403);
    die('Invalid request.');
}


session_destroy();

header("Location: index.php");
exit;




