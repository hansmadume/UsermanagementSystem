<?php
require_once __DIR__ . "/inc/helpers.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure a compatible requireLogin() exists (fallback if helpers.php doesn't define it)
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        return false;
    }
}

// Auth check now happens BEFORE any HTML is emitted (header.php used to be
// included first, which meant header() redirects below could silently fail
// with "headers already sent" - logged-out visitors would see a broken
// half-rendered page instead of being sent to the login page).
$user_id = requireLogin();
if (!$user_id) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/db.php";

$class = str_replace(".php","",basename(__FILE__));

include_once __DIR__ . "/inc/header.php";


$search = "";
if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}

$columns = "id, name, birthday, gender, email, religion, nationality, address, civil_status";

$record_per_page = 15;

// "page" is the page number the pagination UI shows/links to; the SQL
// OFFSET is a derived row count, not the same number - keeping them as one
// variable (the old $offset) was the root cause of wrong rows per page and
// of an unsanitized value going straight into the SQL string.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$current_url = explode("?", $_SERVER['REQUEST_URI']);
$full_url = $protocol . $_SERVER['HTTP_HOST'] . $current_url[0];

// Count query mirrors whatever filter the main query below uses (user_id,
// plus the search condition when active), so the page count and the
// "Showing X of Y" text match what's actually being paged through. The old
// count ("SELECT * FROM names" with no WHERE) counted every user's rows and
// ignored the active search filter.
if ($search !== "") {
    $searchTerm = "%" . escapeLike($search) . "%";

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
    $countStmt->bind_param("isssss", $user_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
} else {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM names WHERE user_id = ?");
    $countStmt->bind_param("i", $user_id);
}
$countStmt->execute();
$total_rows = (int) $countStmt->get_result()->fetch_assoc()['total'];

// ceil(), not raw division - otherwise a trailing partial page (e.g. 12
// rows / 5 per page = 2.4) never gets a button and is unreachable.
$record_pages = max(1, (int) ceil($total_rows / $record_per_page));

if ($page > $record_pages) {
    $page = $record_pages;
}

// The only place a real SQL OFFSET is computed, cast to int right before
// use - no raw $_GET value is ever interpolated into the query string.
$sqlOffset = ($page - 1) * $record_per_page;

if ($search !== "") {

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

    // Escape %, _ and \ so a search term can't act as an unintended wildcard.
    $stmt->bind_param(
        "isssss",
        $user_id,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

} else {

    $stmt = $conn->prepare("SELECT $columns FROM names WHERE user_id = ? LIMIT $record_per_page OFFSET $sqlOffset");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!-- jsPDF + autoTable: used client-side by script.js's downloadSelectedUsersPDF()
     to turn the checked rows into a downloadable PDF without any server round-trip. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<div class="<?php echo $class; ?>">
    <div class="navbar">
        <h2>User Management System</h2>

        <div class="logout-area">
            Welcome,
            <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>

            <div class="logout-card">
                <button type="button" class="logout-btn" id="logoutBtn">Logout</button>

                <dialog class="logout-modal" id="logoutModal" aria-labelledby="logoutModalTitle">
                    <div class="logout-modal-content">
                        <h3 id="logoutModalTitle">Confirm Logout</h3>
                        <p>Are you sure you want to logout?</p>

                        <div class="logout-modal-actions">
                            <button type="button" class="logout-cancel" id="logoutCancel">Cancel</button>

                            <form action="logout.php" method="POST" id="logoutForm" style="margin:0;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
                                <button type="submit" class="logout-confirm-btn" id="logoutConfirmBtn">Logout</button>
                            </form>
                        </div>
                    </div>
                </dialog>

                <script>
                    (function () {
                        const logoutBtn = document.getElementById('logoutBtn');
                        const logoutModal = document.getElementById('logoutModal');
                        const logoutCancel = document.getElementById('logoutCancel');

                        if (!logoutBtn || !logoutModal || !logoutCancel) return;

                        const show = () => logoutModal.classList.add('show');
                        const hide = () => logoutModal.classList.remove('show');

                        logoutBtn.addEventListener('click', show);
                        logoutCancel.addEventListener('click', hide);

                        logoutModal.addEventListener('click', function (e) {
                            if (e.target === logoutModal) hide();
                        });

                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') hide();
                        });
                    })();
                </script>
            </div>
        </div>
    </div>

    <hr>

    <div class="dashboard-wrap">
        <!-- Left: Add User -->
        <div class="card dashboard-card dashboard-add-card">
            <h2>Add User</h2>

            <?php if (isset($_SESSION['success'])): ?>
    <div class="success-message">
        <?= htmlspecialchars($_SESSION['success']); ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="error-message">
        <?= htmlspecialchars($_SESSION['error'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>


            <form id="userForm" action="insert.php" method="POST">
                <input
                    type="hidden"
                    name="csrf"
                    id="csrfToken"
                    value="<?= htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
                <label for="name">Name:</label><br>
                <input type="text" id="name" name="name">
                <span id="nameError" class="error-text"></span><br><br>

                <label for="birthday">Birthday:</label><br>
                <input type="date" id="birthday" name="birthday">
                <span id="birthdayError" class="error-text"></span><br><br>


                <label for="gender">Gender:</label><br>
                <select id="gender" name="gender">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <span id="genderError" class="error-text"></span><br><br>

                <label for="email">Email:</label><br>
                <input type="email" id="email" name="email">
                <span id="emailError" class="error-text"></span><br><br>

                <label for="religion">Religion:</label><br>
                <input type="text" id="religion" name="religion">
                <span id="religionError" class="error-text"></span><br><br>

                <label for="nationality">Nationality:</label><br>
                <input type="text" id="nationality" name="nationality">
                <span id="nationalityError" class="error-text"></span><br><br>

                <label for="address">Address:</label><br>
                <input type="text" id="address" name="address">
                <span id="addressError" class="error-text"></span><br><br>

                <label for="civil_status">Civil Status:</label><br>
                <select id="civil_status" name="civil_status">
                    <option value="">Select Civil Status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Legally Separated">Legally Separated</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Annulled">Annulled</option>
                </select>
                <span id="civilStatusError" class="error-text"></span><br><br>

                <input type="submit" value="Add User" class="add-btn">
            </form>
        </div>

        <!-- Right: Lists -->
        <div class="card dashboard-card dashboard-list-card">
            <h2>Lists</h2>

            <form method="GET" class="search-form" action="#">
                <label for="search" class="visually-hidden">Search user:</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Search user..."
                    value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                <button type="submit" class="search-btn">Search</button>
                <a href="dashboard.php" class="clear-btn">Clear</a>
            </form>

            <form action="delete_selected.php" method="POST" id="bulkDeleteForm">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">

                        <div class="list-actions">
                    <input
                        type="button"
                        class="print-selected-btn"
                        value="Print Selected Users"
                        id="printSelectedBtn">

                    <input
                        type="button"
                        class="download-pdf-btn"
                        value="Download Selected as PDF"
                        id="downloadPdfBtn">

                    <input
                        type="submit"
                        class="delete-selected-btn"
                        value="Delete Selected"
                        onclick="return confirm('Delete all selected records?')">
                </div>
                <div id="bulkDeleteError" class="error-text" style="display:none;"></div>

                <div class="table-container">
                    <table class="user-table">
                        <tr>
                            <th><input type="checkbox" id="selectAll" aria-label="Select all users"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Birthday</th>
                            <th>Age</th>

                            <th>Gender</th>
                            <th>Email</th>
                            <th>Religion</th>
                            <th>Nationality</th>
                            <th>Address</th>
                            <th>Civil Status</th>
                            <th>Actions</th>
                        </tr>

                        <tbody id="userTbody">
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <td>
                                    <?php $checkboxId = 'recordCheckbox-' . $row['id']; ?>
                                    <input
                                        type="checkbox"
                                        id="<?= $checkboxId; ?>"
                                        class="recordCheckbox"
                                        name="selected_ids[]"
                                        value="<?= $row['id']; ?>">
                                    <label for="<?= $checkboxId; ?>" class="visually-hidden">Select user <?= htmlspecialchars($row['name']); ?></label>
                                </td>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['birthday']); ?></td>
                                <td>
<?php
        $birthDate = new DateTime($row['birthday']);
        $today = new DateTime();
        echo $today->diff($birthDate)->y;
?>
                                </td>
                                <td><?= htmlspecialchars($row['gender']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['religion']); ?></td>
                                <td><?= htmlspecialchars($row['nationality']); ?></td>
                                <td><?= htmlspecialchars($row['address']); ?></td>
                                <td><?= htmlspecialchars($row['civil_status']); ?></td>
                                <td>
                                    <a class="edit-btn" href="edit.php?id=<?= $row['id']; ?>">Edit</a>
                                    <a class="delete-btn" href="delete.php?id=<?= $row['id']; ?>" data-delete-id="<?= $row['id']; ?>">Delete</a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="pagination-bar">
                <?php
                    $rangeStart = $total_rows === 0 ? 0 : $sqlOffset + 1;
                    $rangeEnd   = min($sqlOffset + $record_per_page, $total_rows);
                ?>
                <span id="recordCount" class="pagination-info">
                    Showing <?= $rangeStart; ?>&ndash;<?= $rangeEnd; ?> of <?= $total_rows; ?> registered entities
                </span>

                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a class="page-nav" aria-label="Previous page"
                           href="<?= htmlspecialchars($full_url . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                    <?php else: ?>
                        <button class="page-nav" aria-label="Previous page" disabled>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $record_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <button class="page-btn active" aria-current="page"><?= $i; ?></button>
                        <?php else: ?>
                            <?php $pageUrl = $full_url . '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>
                            <a href="<?= htmlspecialchars($pageUrl); ?>" class="page-btn"><?= $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $record_pages): ?>
                        <a class="page-nav" aria-label="Next page"
                           href="<?= htmlspecialchars($full_url . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    <?php else: ?>
                        <button class="page-nav" aria-label="Next page" disabled>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Select-all wiring, per-checkbox wiring, and cross-page selection
        // persistence all live in script.js now (wireSelectAll,
        // wireRecordCheckboxes, restoreCheckboxSelection) so there's a
        // single source of truth instead of two competing listeners.

        window.addEventListener("beforeunload", function () {
            sessionStorage.setItem("scrollPosition", window.scrollY);
        });

        window.addEventListener("load", function () {
            const pos = sessionStorage.getItem("scrollPosition");
            if (pos !== null) {
                window.scrollTo(0, pos);
                sessionStorage.removeItem("scrollPosition");
            }
        });
    </script>

</div>

<?php include_once "inc/footer.php"; ?>