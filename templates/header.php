<?php
// This file assumes session_start() has been called on the parent page.
// It also assumes functions.php has been included for is_logged_in().
// session_start() is now called on each page that needs it.

$page_title = isset($page_title) ? $page_title : 'VuaToFua Laundry';
$base_path = (isset($is_admin_page) && $is_admin_page) ? '../' : './';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/animations.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/accents.css">
    <script src="<?php echo $base_path; ?>js/main.js" defer></script>
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div id="branding">
                <h1><a href="<?php 
                    if (is_logged_in() && has_role('admin')) {
                        echo $base_path . 'admin/index.php';
                    } else {
                        echo $base_path . 'index.php';
                    }
                ?>">VuaToFua</a></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'home' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo $base_path; ?>locations.php">Locations</a></li>
                    <?php if (is_logged_in()): ?>
                        <?php if (has_role('admin')):
                            $dashboard_path = $base_path . 'admin/index.php';
                        ?>
                            <li><a href="<?php echo $dashboard_path; ?>">Dashboard</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/manage_orders.php">Manage Orders</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/manage_users.php">Manage Users</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/manage_contacts.php">Manage Contacts</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/dropoffs.php">Drop-offs</a></li>
                        <?php else:
                            $dashboard_path = $base_path . 'dashboard.php';
                        ?>
                            <li><a href="<?php echo $dashboard_path; ?>">Dashboard</a></li>
                            <li><a href="<?php echo $base_path; ?>customer/my_contacts.php" class="btn-nav">My Contacts</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo $base_path; ?>logout.php" class="btn-nav">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>login.php" class="btn-nav">Login</a></li>
                        <li><a href="<?php echo $base_path; ?>register.php" class="btn-nav">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
