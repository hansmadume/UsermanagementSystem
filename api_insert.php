<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('jsonFail')) {
    function jsonFail(int $status, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail(405, 'Method not allowed.');
}

// requireLogin() and requireCsrf() are defined in inc/helpers.php.
$user_id = requireLogin();
if (!$user_id) {
    jsonFail(401, 'Unauthorized');
}

if (!requireCsrf()) {
    jsonFail(403, 'CSRF token check failed.');
}

// collectBiodataInput() and validateBiodata() are both defined in
// inc/helpers.php. Important: validateBiodata() there returns a TWO-ELEMENT
// TUPLE — [$errors, $age] — because age is derived from birthday inside the
// birthday validator, not read from POST. It must be destructured, not
// assigned directly to $errors, or $errors ends up holding [errorsArray, age]
// instead of a flat list of messages (this was the source of the
// "Array to string conversion" warning / "Array\n24" response).
$data = collectBiodataInput();
[$errors, $age] = validateBiodata($data);

if (!empty($errors)) {
    jsonFail(422, implode("\n", $errors));
}

try {
    $stmt = $conn->prepare("
        INSERT INTO names
        (user_id, name, birthday, age, gender, email, religion, nationality, address, civil_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ississssss",
        $user_id,
        $data['name'],
        $data['birthday'],
        $age,
        $data['gender'],
        $data['email'],
        $data['religion'],
        $data['nationality'],
        $data['address'],
        $data['civil_status']
    );

    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Record added successfully.']);

} catch (Throwable $e) {
    error_log('api_insert.php error: ' . $e->getMessage());
    jsonFail(500, 'A server error occurred.');
}

