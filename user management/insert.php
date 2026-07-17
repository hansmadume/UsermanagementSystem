<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";

header('Content-Type: application/json');

function jsonFail($message, $status = 400)
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// User must be logged in. Fallback when helper function is not available: use session user_id.
if (function_exists('require_login')) {
    $user_id = require_login();
} else {
    $user_id = $_SESSION['user_id'] ?? null;
}
if (!$user_id) {
    jsonFail('You must be logged in.', 401);
}

// CSRF protection (use the single shared mechanism from inc/helpers.php)
if (!requireCsrf()) {
    jsonFail('Invalid request. Please reload the page and try again.', 403);
}

// Same fallback pattern as insert.php: prefer helpers.php's implementation
// if it defines these, otherwise fall back to a local one, so this file
// works standalone even if helpers.php hasn't been updated yet.
if (!function_exists('collectbiodatainput')) {
    function collectbiodatainput()
    {
        $fields = [
            'name', 'birthday', 'gender', 'email', 'religion', 'nationality', 'address', 'civil_status'
        ];
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = isset($_POST[$f]) ? trim((string) $_POST[$f]) : '';
        }
        return $data;
    }
}
if (!function_exists('collectBiodataInput')) {
    function collectBiodataInput()
    {
        if (function_exists('collectbiodatainput')) {
            return collectbiodatainput();
        }

        $spec = [
            'name' => FILTER_DEFAULT,
            'birthday' => FILTER_DEFAULT,
            'gender' => FILTER_DEFAULT,
            'email' => FILTER_DEFAULT,
            'religion' => FILTER_DEFAULT,
            'nationality' => FILTER_DEFAULT,
            'address' => FILTER_DEFAULT,
            'civil_status' => FILTER_DEFAULT,
        ];

        $raw = filter_input_array(INPUT_POST, $spec) ?: [];
        $data = [];
        foreach ($spec as $key => $_) {
            $val = isset($raw[$key]) ? $raw[$key] : '';
            $data[$key] = is_string($val) ? trim($val) : '';
        }

        return $data;
    }
}

if (!function_exists('validateBiodata')) {
    function validateBiodata(array $data)
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Name is required.';
        }

        $age = null;
        if ($data['birthday'] === '') {
            $errors[] = 'Birthday is required.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $data['birthday']);
            if (!$d) {
                $errors[] = 'Birthday must be in YYYY-MM-DD format.';
            } else {
                $now = new DateTime();
                $age = $now->diff($d)->y;
                if ($age < 0 || $age > 150) {
                    $errors[] = 'Birthday is not a valid date.';
                }
            }
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is not valid.';
        }

        return [$errors, $age];
    }
}

$data = collectBiodataInput();
$result = validateBiodata($data);

if (is_array($result)) {
    [$errors, $age] = $result;
} else {
    $errors = [];
    $age = null;
}

if (!empty($errors)) {
    // 422 (not 200) so ajaxJSON() in script.js throws and surfaces this in
    // the alert - a 200 with success:false would be swallowed silently by
    // `if (json?.success) { ... }` doing nothing on failure.
    jsonFail(implode(' ', $errors), 422);
}

try {
    $stmt = $conn->prepare("
        INSERT INTO names
            (user_id, name, birthday, gender, email, religion, nationality, address, civil_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issssssss",
        $user_id,
        $data['name'],
        $data['birthday'],
        $data['gender'],
        $data['email'],
        $data['religion'],
        $data['nationality'],
        $data['address'],
        $data['civil_status']
    );

    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'id' => $newId]);
    exit();
} catch (Throwable $e) {
    error_log('api_insert.php error: ' . $e->getMessage());
    jsonFail('Something went wrong while saving. Please try again.', 500);
}