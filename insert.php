<?php
session_start();
require __DIR__ . "/db.php";

$name = trim($_POST["name"]);
$age = trim($_POST["age"]);
$gender = trim($_POST["gender"]);
$email = trim($_POST["email"]);
$religion = trim($_POST["religion"]);
$nationality = trim($_POST["nationality"]);
$address = trim($_POST["address"]);
$civil_status = trim($_POST["civil_status"]);

$errors = [];

// Name
if (empty($name)) {
    $errors[] = "Name is required.";
}

// Age
if (empty($age)) {
    $errors[] = "Age is required.";
} elseif (!is_numeric($age) || $age < 1 || $age > 120) {
    $errors[] = "Age must be between 1 and 120.";
}

// Gender
if ($gender != "Male" && $gender != "Female") {
    $errors[] = "Please select a valid gender.";
}

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
}

// Religion
if (empty($religion)) {
    $errors[] = "Religion is required.";
}

// Nationality
if (empty($nationality)) {
    $errors[] = "Nationality is required.";
}

// Address
if (empty($address)) {
    $errors[] = "Address is required.";
}

// Civil Status
$allowedStatus = [
    "Single",
    "Married",
    "Legally Separated",
    "Widowed",
    "Annulled"
];

if (!in_array($civil_status, $allowedStatus)) {
    $errors[] = "Please select a valid civil status.";
}

// If there are errors
if (!empty($errors)) {
    $_SESSION["error"] = implode("<br>", $errors);
    header("Location: dashboard.php");
    exit();
}

// Insert into database
$stmt = $conn->prepare(
    "INSERT INTO names
    (name, age, gender, email, religion, nationality, address, civil_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sissssss",
    $name,
    $age,
    $gender,
    $email,
    $religion,
    $nationality,
    $address,
    $civil_status
);

$stmt->execute();

$_SESSION["success"] = "Record added successfully.";

header("Location: dashboard.php");
exit();