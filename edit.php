<?php
session_start();
require __DIR__ . "/db.php";

$id = (int)($_GET['id'] ?? 0);

// Get current data
$stmt = $conn->prepare("SELECT * FROM names WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Record not found.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

   $name = trim($_POST["name"]);
$age = (int)$_POST["age"];
$gender = $_POST["gender"];
$email = trim($_POST["email"]);
$religion = trim($_POST["religion"]);
$nationality = trim($_POST["nationality"]);
$address = trim($_POST["address"]);
$civil_status = $_POST["civil_status"];

$stmt = $conn->prepare("
    UPDATE names
    SET
        name = ?,
        age = ?,
        gender = ?,
        email = ?,
        religion = ?,
        nationality = ?,
        address = ?,
        civil_status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "sissssssi",
    $name,
    $age,
    $gender,
    $email,
    $religion,
    $nationality,
    $address,
    $civil_status,
    $id
);

$stmt->execute();

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Biodata</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard">

<div class="card">

<h2>Edit Biodata</h2>


<form id="editForm" method="POST">

    <label>Name:</label><br>
    <input type="text" id="name" name="name" value="<?= htmlspecialchars($row['name']) ?>">
    <span id="nameError" style="color:red;"></span><br><br>

    <label>Age:</label><br>
    <input type="number" id="age" name="age" value="<?= htmlspecialchars($row['age']) ?>">
    <span id="ageError" style="color:red;"></span><br><br>

    <label>Gender:</label><br>
    <select id="gender" name="gender">
    <option value="Male" <?= $row['gender'] == "Male" ? "selected" : "" ?>>Male</option>
    <option value="Female" <?= $row['gender'] == "Female" ? "selected" : "" ?>>Female</option>
    </select>
    <span id="genderError" style="color:red;"></span><br><br>

    <label>Email Address:</label><br>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($row['email']) ?>">
    <span id="emailError" style="color:red;"></span><br><br>

    <label>Religion:</label><br>
    <input type="text" id="religion" name="religion" value="<?= htmlspecialchars($row['religion']) ?>">
    <span id="religionError" style="color:red;"></span><br><br>

    <label>Nationality:</label><br>
    <input type="text" id="nationality" name="nationality" value="<?= htmlspecialchars($row['nationality']) ?>">
    <span id="nationalityError" style="color:red;"></span><br><br>

    <label>Address:</label><br>
    <input type="text" id="address" name="address" value="<?= htmlspecialchars($row['address']) ?>">
    <span id="addressError" style="color:red;"></span><br><br>

    <label>Civil Status:</label><br>
    <select id="civil_status" name="civil_status">
    <option value="Single" <?= $row['civil_status'] == "Single" ? "selected" : "" ?>>Single</option>
    <option value="Married" <?= $row['civil_status'] == "Married" ? "selected" : "" ?>>Married</option>
    <option value="Legally Separated" <?= $row['civil_status'] == "Legally Separated" ? "selected" : "" ?>>Legally Separated</option>
    <option value="Widowed" <?= $row['civil_status'] == "Widowed" ? "selected" : "" ?>>Widowed</option>
    <option value="Annulled" <?= $row['civil_status'] == "Annulled" ? "selected" : "" ?>>Annulled</option>
</select>
    <span id="civilStatusError" style="color:red;"></span><br><br>

    <div class="edit-actions">
        <button type="submit" style="background:#2563eb;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-weight:bold;">Update</button>
        <a class="back-btn" href="dashboard.php" style="background:#94a3b8;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Back</a>

    </div>

</form>


    <script>
    document.getElementById("editForm").addEventListener("submit", function(event){

    let valid = true;

    document.getElementById("nameError").textContent = "";
    document.getElementById("ageError").textContent = "";
    document.getElementById("genderError").textContent = "";
    document.getElementById("emailError").textContent = "";
    document.getElementById("religionError").textContent = "";
    document.getElementById("nationalityError").textContent = "";
    document.getElementById("addressError").textContent = "";
    document.getElementById("civilStatusError").textContent = "";

    let name = document.getElementById("name").value.trim();
    let age = document.getElementById("age").value.trim();
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

    if (age === "") {
        document.getElementById("ageError").textContent = "Age is required.";
        valid = false;
    } else if (isNaN(age) || age < 1 || age > 120) {
        document.getElementById("ageError").textContent = "Age must be between 1 and 120.";
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
</body>
</html>