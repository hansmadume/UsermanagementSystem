<?php
$class = str_replace(".php","",basename(__FILE__));
include_once "inc/header.php";

// inc/header.php is expected to create $_SESSION['csrf'] if missing.
// Do NOT rotate CSRF here; rotation breaks the AJAX biodata checks.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
    <div class="<?php echo $class; ?>">

    <h2>
        <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:6px;font-size:28px;">lock</span>
        Login
    </h2>

    <form action="login.php" method="POST" class="auth-form">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']); ?>">

        <label for="username">
            <span class="material-symbols-outlined" style="vertical-align:middle;font-size:15px;margin-right:4px;">person</span>
            Username
        </label>
        <input id="username" type="text" name="username" placeholder="Enter your username" autocomplete="username" required>

        <label for="password">
            <span class="material-symbols-outlined" style="vertical-align:middle;font-size:15px;margin-right:4px;">key</span>
            Password
        </label>
        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>

        <div class="show-password-row">
            <input type="checkbox" id="showPassword" name="showPassword" aria-describedby="showPasswordHelp">
            <label for="showPassword">Show Password</label>
        </div>

        <button type="submit" class="btn btn--login">
            <span class="material-symbols-outlined" style="font-size:18px;">login</span>
            Login
        </button>

        <?php
        if (isset($_SESSION["error"])) {
            // Error messages set elsewhere in the app are already
            // htmlspecialchars-escaped before being stored in the session.
            echo "<div class='error-message' role='alert'>" . $_SESSION["error"] . "</div>";
            unset($_SESSION["error"]);
        }
        ?>

    </form>

    <div class="register">

        <p>Don't have an account?</p>

        <a href="register.php">Create Account</a>

    </div>

    </div>

<?php include_once "inc/footer.php"; ?>
