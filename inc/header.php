<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists for any page that needs to render a form.
// IMPORTANT: Do NOT rotate the token here; only create it if missing.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Determine page title
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$pageTitleMap = [
    'index.php' => 'Login',
    'register.php' => 'Create Account',
    'dashboard.php' => 'Dashboard',
    'edit.php' => 'Edit Biodata',
];
$pageTitle = $pageTitleMap[$currentPage] ?? 'User Management';

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> | User Management</title>

    <link rel="stylesheet" href="style.css">

    <!-- Google Material Symbols (outlined) for icons -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>

<?php
// Decide layout based on current PHP page name.
$isAuth = ($currentPage === 'index.php' || $currentPage === 'register.php' || $currentPage === 'login.php');
$bodyClass = $isAuth ? 'login-page' : 'dashboard';
?>
<body class="<?php echo $bodyClass; ?>">

<?php if ($isAuth) : ?>
    <div class="container">
<?php endif; ?>
