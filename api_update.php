<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";
if (!function_exists('json_fail')) { die('helpers.php missing json_fail'); }
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        return false;
    }
}


if (!function_exists('collectBiodataInput')) {
    function collectBiodataInput(): array
    {
        return [
            'name' => trim((string)($_POST['name'] ?? '')),
            'birthday' => trim((string)($_POST['birthday'] ?? '')),
            'gender' => trim((string)($_POST['gender'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'religion' => trim((string)($_POST['religion'] ?? '')),
            'nationality' => trim((string)($_POST['nationality'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'civil_status' => trim((string)($_POST['civil_status'] ?? '')),
        ];
    }
}

    // Provide a local fallback for validateBiodata if helpers.php doesn't define it.
    if (!function_exists('validateBiodata')) {
        /**
         * Validate biodata fields and calculate age.
         * Returns [array $errors, int $age]
         */
        function validateBiodata(array $data): array
        {
            $errors = [];

            // name
            if ($data['name'] === '') {
                $errors[] = 'Name is required.';
            }

            // birthday -> must be YYYY-MM-DD
            $age = 0;
            if ($data['birthday'] === '') {
                $errors[] = 'Birthday is required.';
            } else {
                $d = DateTime::createFromFormat('Y-m-d', $data['birthday']);
                $valid = $d && $d->format('Y-m-d') === $data['birthday'];
                if (!$valid) {
                    $errors[] = 'Birthday must be in YYYY-MM-DD format.';
                } else {
                    $today = new DateTime('now');
                    $age = (int)$today->diff($d)->y;
                    if ($age < 0 || $age > 150) {
                        $errors[] = 'Birthday is not a plausible date.';
                    }
                }
            }

            // email simple validation
            if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email is not valid.';
            }

            return [$errors, $age];
        }
    }

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Method not allowed.');
}

$user_id = requireLogin();
if (!$user_id) {
    json_fail(401, 'Unauthorized');
}

if (!requireCsrf()) {
    json_fail(403, 'Invalid or missing CSRF token.');
}



$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    json_fail(422, 'Invalid record id.');
}

// Confirm the record exists AND belongs to this user before touching it.
// (Doing this as a separate check, rather than relying on affected_rows
// after the UPDATE, avoids a false "not found" when the submitted data is
// identical to what's already stored.)
$check = $conn->prepare("SELECT id FROM names WHERE id = ? AND user_id = ?");
$check->bind_param('ii', $id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    json_fail(404, 'Record not found.');
}

$data = collectBiodataInput();
[$errors, $age] = validateBiodata($data);

if (!empty($errors)) {
    json_fail(422, implode("\n", $errors));
}

try {
    $stmt = $conn->prepare("
        UPDATE names SET
            name = ?, birthday = ?, age = ?, gender = ?, email = ?,
            religion = ?, nationality = ?, address = ?, civil_status = ?
        WHERE id = ? AND user_id = ?
    ");

    $stmt->bind_param(
        'ssisssssii',
        $data['name'],
        $data['birthday'],
        $age,
        $data['gender'],
        $data['email'],
        $data['religion'],
        $data['nationality'],
        $data['address'],
        $data['civil_status'],
        $id,
        $user_id
    );

    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} catch (Throwable $e) {
    error_log('api_update.php error: ' . $e->getMessage());
    json_fail(500, 'A server error occurred.');
}
