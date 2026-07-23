<?php
$pageTitle = "Register";
require_once "db.php";

// header.php calls session_start() and (based on the page name) opens the
// shared .container div for auth pages. It must run first and only once -
// don't call session_start() again in this file.
$class = str_replace(".php", "", basename(__FILE__)); // used by footer.php
include_once "inc/header.php";

// Do NOT rotate CSRF here; rotation breaks the hidden CSRF value later.
// Keep the existing token and only create one if missing.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (
        !isset($_POST["csrf"], $_SESSION["csrf"]) ||
        !hash_equals($_SESSION["csrf"], $_POST["csrf"])
    ) {
        $message = "<div class='error-message'>Invalid request. Please try again.</div>";
    } else {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        $errors = [];

        if ($username === "") {
            $errors[] = "Username is required.";
        } elseif (!preg_match('/^\w{3,30}$/', $username)) {
            $errors[] = "Username must be 3-30 characters and contain only letters, numbers, and underscores.";
        }

        if ($password === "") {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }

        if (!empty($errors)) {
            $message = "<div class='error-message'>" . implode("<br>", array_map('htmlspecialchars', $errors)) . "</div>";
        } else {

            // Check if username already exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $message = "<div class='error-message'>Username already exists.</div>";
            } else {

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashedPassword);

                if ($stmt->execute()) {
                    $message = "<div class='success-message'>Account created successfully!</div>";

                } else {
                    $message = "<div class='error-message'>Error creating account.</div>";
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}
?>

<div class="<?php echo $class; ?>">

    <h2>
        <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px;font-size:28px;">person_add</span>
        Create Account
    </h2>

    <form method="POST" class="auth-form">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']); ?>">

        <label for="username">
            <span class="material-symbols-outlined" style="vertical-align:middle;font-size:15px;margin-right:4px;">badge</span>
            Username
        </label>
        <input id="username" type="text" name="username" placeholder="Choose a username" autocomplete="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+">

        <label for="password">
            <span class="material-symbols-outlined" style="vertical-align:middle;font-size:15px;margin-right:4px;">lock</span>
            Password
        </label>
        <input type="password" id="password" name="password" placeholder="Choose a password (min. 8 characters)" autocomplete="new-password" required minlength="8">

        <div class="show-password-row">
            <input type="checkbox" id="showPassword" name="showPassword" aria-describedby="showPasswordHelp">
            <label for="showPassword">Show Password</label>
        </div>

        <button type="submit" class="btn btn--register">
            <span class="material-symbols-outlined" style="font-size:18px;">how_to_reg</span>
            Register
        </button>

        <?php echo $message; ?>

    </form>

    <div class="register">
        <p>Already have an account?</p>
        <a href="index.php">Login Here</a>
    </div>

</div>

<?php include_once "inc/footer.php"; ?>
