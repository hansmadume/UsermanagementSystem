<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";
if (!function_exists('jsonFail')) { die('helpers.php missing jsonFail'); }

// Provide a simple fallback for the login helper in case helpers.php doesn't define it
if (!function_exists('requireLogin')) {
    function requireLogin() {
        // Prefer a project-provided require_login() if available, otherwise fall
        // back to the session-stored user_id.
        if (function_exists('require_login')) {
            return require_login();
        }
        return $_SESSION['user_id'] ?? null;
    }
}

// Provide escapeLike if helpers.php doesn't define it. Escapes %, _ and backslash
// (moved above first use - PHP hoists function *declarations* fine even after
// use in most cases, but keeping it here removes any doubt / lint noise)
if (!function_exists('escapeLike')) {
    function escapeLike(string $s): string {
        // Escape backslash first, then % and _ with a backslash so they are treated literally in LIKE
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail(405, 'Method not allowed.');
}

$user_id = requireLogin();
if (!$user_id) {
    jsonFail(401, 'Unauthorized');
}

$search = trim($_POST['search'] ?? '');

// Same pagination model as dashboard.php: "page" is the page number,
// $sqlOffset is the derived row offset - the two are never conflated, and
// only $sqlOffset (an int) ever reaches the SQL string.
$record_per_page = 15;
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// Explicit column list instead of SELECT * so we never accidentally leak
// columns like user_id to the browser via JSON.
$columns = "id, name, birthday, gender, email, religion, nationality, address, civil_status";

try {
    // --- count first (mirrors the filter used by the data query below) ---
    if ($search !== '') {
        $term = '%' . escapeLike($search) . '%';

        $countStmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM names
            WHERE user_id = ?
              AND (
                    name        LIKE ? ESCAPE '\\\\'
                 OR email       LIKE ? ESCAPE '\\\\'
                 OR religion    LIKE ? ESCAPE '\\\\'
                 OR nationality LIKE ? ESCAPE '\\\\'
                 OR address     LIKE ? ESCAPE '\\\\'
              )
        ");
        $countStmt->bind_param("isssss", $user_id, $term, $term, $term, $term, $term);
    } else {
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM names WHERE user_id = ?");
        $countStmt->bind_param("i", $user_id);
    }
    $countStmt->execute();
    $total_rows = (int) $countStmt->get_result()->fetch_assoc()['total'];

    // ceil(), not raw division, so a trailing partial page is still reachable.
    $record_pages = max(1, (int) ceil($total_rows / $record_per_page));
    if ($page > $record_pages) {
        $page = $record_pages;
    }

    // The only place a real SQL OFFSET is computed, cast to int right
    // before use - no raw $_POST value is ever interpolated into the query.
    $sqlOffset = ($page - 1) * $record_per_page;

    // --- data query, now paginated to match the count above ---
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT $columns
            FROM names
            WHERE user_id = ?
              AND (
                    name        LIKE ? ESCAPE '\\\\'
                 OR email       LIKE ? ESCAPE '\\\\'
                 OR religion    LIKE ? ESCAPE '\\\\'
                 OR nationality LIKE ? ESCAPE '\\\\'
                 OR address     LIKE ? ESCAPE '\\\\'
              )
            LIMIT $record_per_page
            OFFSET $sqlOffset
        ");
        $stmt->bind_param("isssss", $user_id, $term, $term, $term, $term, $term);
    } else {
        $stmt = $conn->prepare("SELECT $columns FROM names WHERE user_id = ? LIMIT $record_per_page OFFSET $sqlOffset");
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode([
        'success'         => true,
        'rows'            => $rows,
        'page'            => $page,
        'record_pages'    => $record_pages,
        'total_rows'      => $total_rows,
        'record_per_page' => $record_per_page,
    ]);

} catch (Throwable $e) {
    error_log('api_search.php error: ' . $e->getMessage());
    jsonFail(500, 'A server error occurred.');
}