<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists for any page that needs to render a form.
// IMPORTANT: Do NOT rotate the token here; only create it if missing.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
// IMPORTANT: do not rotate csrf here; keep token stable for the session.


?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Sofia&effect=fire">
</head>

<?php
// Decide layout based on current PHP page name.
// This prevents login/register centering from breaking when $class isn't set correctly.
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isAuth = ($currentPage === 'index.php' || $currentPage === 'register.php' || $currentPage === 'login.php');
$bodyClass = $isAuth ? 'login-page' : 'dashboard';
?>
<body class="<?php echo $bodyClass; ?>">

<?php if ($isAuth) : ?>
    <div class="container">
<?php endif; ?>
