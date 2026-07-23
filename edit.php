<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/inc/helpers.php";

$user_id = requireLogin();
if (!$user_id) {
    header("Location: index.php");
    exit();
}

// Do NOT rotate CSRF here; rotation breaks the hidden CSRF value later.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$id = (int)($_GET['id'] ?? 0);

// Ownership enforced here too - a user can only ever load/edit their own
// record, even if they change the id in the URL.
$stmt = $conn->prepare("SELECT * FROM names WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    http_response_code(404);
    die("Record not found.");
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!requireCsrf()) {
        http_response_code(403);
        die("Invalid request.");
    }

    $data = collectBiodataInput();
    [$errors, $age] = validateBiodata($data);

    if (empty($errors)) {

        $stmt = $conn->prepare("
            UPDATE names
            SET name = ?, birthday = ?, gender = ?, email = ?,
                religion = ?, nationality = ?, address = ?, civil_status = ?
            WHERE id = ? AND user_id = ?
        ");

        $stmt->bind_param(
            "ssssssssii",
            $data['name'],
            $data['birthday'],
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

        $_SESSION['success'] = "Record updated successfully!";
        header("Location: dashboard.php");
        exit();
    }

    // Validation failed - keep the submitted values so the user doesn't
    // have to retype everything.
    $row = array_merge($row, $data);
}

$class = "edit";
include_once __DIR__ . "/inc/header.php";
?>

<div class="<?php echo $class; ?>">
    <div class="navbar">
        <h2>Edit Biodata</h2>
        <div>
            <span style="color:var(--text-muted-on-dark);font-size:14px;">
                Editing record #<?= $id; ?>
            </span>
        </div>
    </div>

    <hr>

    <div class="card" style="max-width:720px;margin:30px auto;">
        <h2>
            <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;font-size:24px;">edit</span>
            Update Record
        </h2>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="editForm" method="POST">
            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars($_SESSION['csrf']); ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($row['name']) ?>">
                    <span id="nameError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" id="birthday" name="birthday" value="<?= htmlspecialchars($row['birthday'] ?? '') ?>">
                    <span id="birthdayError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?= $row['gender'] == "Male" ? "selected" : "" ?>>Male</option>
                        <option value="Female" <?= $row['gender'] == "Female" ? "selected" : "" ?>>Female</option>
                    </select>
                    <span id="genderError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($row['email']) ?>">
                    <span id="emailError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="religion">Religion</label>
                    <input type="text" id="religion" name="religion" value="<?= htmlspecialchars($row['religion']) ?>">
                    <span id="religionError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="nationality">Nationality</label>
                    <input type="text" id="nationality" name="nationality" value="<?= htmlspecialchars($row['nationality']) ?>">
                    <span id="nationalityError" class="error-text"></span>
                </div>

                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($row['address']) ?>">
                    <span id="addressError" class="error-text"></span>
                </div>

                <div class="form-group">
                    <label for="civil_status">Civil Status</label>
                    <select id="civil_status" name="civil_status">
                        <option value="">Select Civil Status</option>
                        <option value="Single" <?= $row['civil_status'] == "Single" ? "selected" : "" ?>>Single</option>
                        <option value="Married" <?= $row['civil_status'] == "Married" ? "selected" : "" ?>>Married</option>
                        <option value="Legally Separated" <?= $row['civil_status'] == "Legally Separated" ? "selected" : "" ?>>Legally Separated</option>
                        <option value="Widowed" <?= $row['civil_status'] == "Widowed" ? "selected" : "" ?>>Widowed</option>
                        <option value="Annulled" <?= $row['civil_status'] == "Annulled" ? "selected" : "" ?>>Annulled</option>
                    </select>
                    <span id="civilStatusError" class="error-text"></span>
                </div>
            </div>

            <div class="edit-actions">
                <button type="submit" class="update-btn">
                    <span class="material-symbols-outlined" style="font-size:18px;">save</span>
                    Update
                </button>
                <a class="back-btn" href="dashboard.php">
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
                    Back
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById("editForm").addEventListener("submit", function(event){
    let valid = true;

    document.getElementById("nameError").textContent = "";
    document.getElementById("birthdayError").textContent = "";
    document.getElementById("genderError").textContent = "";
    document.getElementById("emailError").textContent = "";
    document.getElementById("religionError").textContent = "";
    document.getElementById("nationalityError").textContent = "";
    document.getElementById("addressError").textContent = "";
    document.getElementById("civilStatusError").textContent = "";

    let name = document.getElementById("name").value.trim();
    let birthday = document.getElementById("birthday").value.trim();
    let gender = document.getElementById("gender").value;
    let email = document.getElementById("email").value.trim();
    let religion = document.getElementById("religion").value.trim();
    let nationality = document.getElementById("nationality").value.trim();
    let address = document.getElementById("address").value.trim();
    let civilStatus = document.getElementById("civil_status").value;

    // Name Validation
    const namePattern = /^[A-Za-zÀ-ÿ' -]+$/;
    let nameParts = name.split(/\s+/);

    if (name === "") {
        document.getElementById("nameError").textContent = "Name is required.";
        valid = false;
    } else if (!namePattern.test(name)) {
        document.getElementById("nameError").textContent = "Name can only contain letters, spaces, apostrophes (') and hyphens (-).";
        valid = false;
    } else if (nameParts.length < 2) {
        document.getElementById("nameError").textContent = "Please enter your first and last name. Middle name is optional.";
        valid = false;
    }

    if (birthday === "") {
        document.getElementById("birthdayError").textContent = "Birthday is required.";
        valid = false;
    }

    if (gender === "") {
        document.getElementById("genderError").textContent = "Please select a gender.";
        valid = false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email === "") {
        document.getElementById("emailError").textContent = "Email is required.";
        valid = false;
    } else if (!emailPattern.test(email)) {
        document.getElementById("emailError").textContent = "Invalid email address.";
        valid = false;
    }

    if (religion === "") {
        document.getElementById("religionError").textContent = "Religion is required.";
        valid = false;
    }

    if (nationality === "") {
        document.getElementById("nationalityError").textContent = "Nationality is required.";
        valid = false;
    }

    if (address === "") {
        document.getElementById("addressError").textContent = "Address is required.";
        valid = false;
    }

    if (civilStatus === "") {
        document.getElementById("civilStatusError").textContent = "Please select a civil status.";
        valid = false;
    }

    if (!valid) {
        event.preventDefault();
    }
});
</script>

<?php include_once __DIR__ . "/inc/footer.php"; ?>

