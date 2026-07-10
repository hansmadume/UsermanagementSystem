<?php
$pageTitle = "Register";
require "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Check if username already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "<div class='alert error'>Username already exists.</div>";
    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashedPassword);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Account created successfully!</div>";
        } else {
            $message = "<div class='alert error'>Error creating account.</div>";
        }

        $stmt->close();
    }

    $check->close();
    $conn->close();
}

$class = str_replace(".php", "", basename(__FILE__));
include_once("inc/header.php");
?>

<div class="<?php echo $class; ?>">

    <h2 class="font-effect-fire">Create Account</h2>

    <form method="POST" class="auth-form">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" placeholder="Choose a username" autocomplete="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Choose a password" autocomplete="new-password" required>

        <div class="show-password-row">
            <input type="checkbox" id="showPassword" name="showPassword" aria-describedby="showPasswordHelp">
            <label for="showPassword">Show Password</label>
        </div>

        <button type="submit" class="btn btn--register">Register</button>

        <?php echo $message; ?>

    </form>

    <div class="register">
        <p>Already have an account?</p>
        <a href="index.php">Login Here</a>
    </div>

</div>

<?php include_once("inc/footer.php"); ?>

