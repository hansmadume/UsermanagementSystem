<?php

// Harden session cookies early (must run before session_start()).
if (PHP_SESSION_NONE === session_status()) {
    $secureCookie = (
        !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ) || (
        isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443'
    ) || (
        !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    // Moved inside this guard: ini_set() for session.* settings throws a
    // "Session ini settings cannot be changed when a session is active"
    // warning if a session is already running. api_search.php, api_delete.php,
    // and dashboard.php all call session_start() themselves before requiring
    // this file, so this call must only run when no session has started yet -
    // otherwise it fires the warning and corrupts the JSON output of every
    // AJAX endpoint that includes this file after starting its own session.
    ini_set('session.use_strict_mode', '1');
}


function jsonFail(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}


// Backwards-compatible wrapper (deprecated). Use jsonFail() instead.
// Note: named without underscore to satisfy coding standard.
if (!function_exists('jsonfail')) {
    function jsonfail(int $status, string $message): void {
        jsonFail($status, $message);
    }
}

function requireLogin(): ?int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return (int)$_SESSION['user_id'];
}

// CamelCase name to satisfy coding standards. Backwards-compatible snake_case wrapper below.
function requireCsrf(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf'])) {
        return false;
    }

    // Determine which POST key contains the CSRF token. Avoid nested ternary for clarity.
    if (array_key_exists('csrf', $_POST)) {
        $tokenSource = 'csrf';
    } elseif (array_key_exists('csrfToken', $_POST)) {
        $tokenSource = 'csrfToken';
    } else {
        $tokenSource = null;
    }

    $token = $tokenSource !== null ? $_POST[$tokenSource] : null;

    if ($token === null || trim((string)$token) === '' || trim((string)$_SESSION['csrf']) === '') {
        return false;
    }

    $ok = hash_equals((string)$_SESSION['csrf'], (string)$token);

    if (!$ok && function_exists('csrfDebugMessage')) {
        $_SESSION['csrf_debug_last'] = [
            'tokenSource' => $tokenSource,
            'tokenReceived' => is_scalar($token) ? (string)$token : null,
            'tokenSession' => is_scalar($_SESSION['csrf']) ? (string)$_SESSION['csrf'] : null,
            'message' => csrfDebugMessage($token)
        ];
    }

    return $ok;
}

// Note: snake_case wrapper for requireCsrf() was deprecated and removed.


function csrfDebugMessage(?string $token): string {
    $message = 'CSRF token mismatch.';

    if (!isset($_SESSION['csrf'])) {
        $message = 'CSRF session token missing.';
    } elseif ($token === null) {
        $message = 'CSRF token not found in request (missing csrf/csrfToken).';
    } elseif (trim((string)$token) === '') {
        $message = 'CSRF token in request is empty.';
    } elseif (trim((string)$_SESSION['csrf']) === '') {
        $message = 'CSRF session token is empty.';
    }

    return $message;
}




function escapeLike(string $value): string {
    // Escape LIKE wildcards for MySQL
    // %  -> \%
    // _  -> \_
    // \ -> \\
    return str_replace(
        ['\\', '%', '_'],
        ['\\\\', '\\%', '\\_'],
        $value
    );
}

// CamelCase name to satisfy coding standards. Backwards-compatible
// snake_case wrapper below.
function collectBiodataInput(): array {
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

// Backwards-compatible wrapper (deprecated). Use collectBiodataInput() instead.
// Renamed to conform to coding standard (no underscore in function name).
if (!function_exists('collectBiodataInputWrapper')) {
    function collectBiodataInputWrapper(): array {
        return collectBiodataInput();
    }
}

function validateBiodata(array $data): array {
    $errors = [];
    $age = null;

    // Name
    list($nameErrors) = validateBiodataName($data['name'] ?? '');
    $errors = array_merge($errors, $nameErrors);

    // Birthday + age
    list($birthdayErrors, $age) = validateBiodataBirthday($data['birthday'] ?? '');
    $errors = array_merge($errors, $birthdayErrors);

    // Gender
    // use camelCase validator present in this file
    $errors = array_merge($errors, validateBiodataGender($data['gender'] ?? ''));

    // Email
    $errors = array_merge($errors, validateBiodataEmail($data['email'] ?? ''));

    // Religion, Nationality, Address
    $errors = array_merge($errors, validateBiodataRequired('Religion is required.', $data['religion'] ?? ''));
    $errors = array_merge($errors, validateBiodataRequired('Nationality is required.', $data['nationality'] ?? ''));
    $errors = array_merge($errors, validateBiodataRequired('Address is required.', $data['address'] ?? ''));

    // Civil status
    $errors = array_merge($errors, validateBiodataCivil($data['civil_status'] ?? ''));

    if ($age === null) {
        $age = 0;
    }

    return [$errors, $age];
}

// Backwards-compatible wrapper (deprecated). Prefer validateBiodata().
if (!function_exists('validatebiodata')) {
    function validatebiodata(array $data): array {
        return validateBiodata($data);
    }
}

function validateBiodataName(string $name): array {
    $errors = [];
    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match("/^[A-Za-zÀ-ÿ' -]+$/u", $name)) {
        $errors[] = 'Name can only contain letters, spaces, apostrophes (\') and hyphens (-).';
    }
    return [$errors];
}

function validateBiodataBirthday(string $birthday): array {
    $errors = [];
    $age = null;
    if ($birthday === '') {
        $errors[] = 'Birthday is required.';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$dt) {
            $errors[] = 'Invalid birthday format.';
        } else {
            $today = new DateTime('today');
            $age = $today->diff($dt)->y;
            if ($age < 0 || $age > 120) {
                $errors[] = 'Invalid age derived from birthday.';
            }
        }
    }
    return [$errors, $age];
}

function validateBiodataGender(string $gender): array {
    $allowedGender = ['Male', 'Female'];
    if ($gender === '' || !in_array($gender, $allowedGender, true)) {
        return ['Please select a gender.'];
    }
    return [];
}

function validateBiodataEmail(string $email): array {
    if ($email === '') {
        return ['Email is required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['Invalid email address.'];
    }
    return [];
}

function validateBiodataRequired(string $message, string $value): array {
    return $value === '' ? [$message] : [];
}

// Backwards-compatible snake_case wrapper (deprecated) removed to satisfy
// coding standard (use validateBiodataRequired() instead).

function validateBiodataCivil(string $civil): array {
    $allowedCivil = ['Single', 'Married', 'Legally Separated', 'Widowed', 'Annulled'];
    if ($civil === '' || !in_array($civil, $allowedCivil, true)) {
        return ['Please select a civil status.'];
    }
    return [];
}