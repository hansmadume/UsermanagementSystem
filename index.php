<?php include_once("inc/header.php"); ?>
<?php $class = str_replace(".php","",basename(__FILE__)); ?>
    <div class="<?php echo $class; ?>">

    <h2 class="font-effect-fire">Login</h2>

    <form action="login.php" method="POST">

        <label>Username</label>

        <input type="text" name="username" required>

        <label>Password</label>

        <input type="password" id="password" name="password" required>

        <div class="show-password-row">
            <label for="showPassword">Show Password</label>
            <input type="checkbox" id="showPassword" name="showPassword">
        </div>

        <input type="submit" value="Login">

        <?php
        if (isset($_SESSION["error"])) {
            echo "<p class='error'>" . $_SESSION["error"] . "</p>";
            unset($_SESSION["error"]);
        }
        ?>

    </form>

    <div class="register">

        <p>Don't have an account?</p>

        <a href="register.php">Create Account</a>

    </div>

    </div>

<?php include_once("inc/footer.php"); ?>