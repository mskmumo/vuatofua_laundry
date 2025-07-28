<?php
// Customer area header with custom styling
$page_title = isset($page_title) ? $page_title : 'VuaToFua Laundry - Customer Area';
$base_path = '../';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="<?php echo $base_path; ?>js/main.js" defer></script>
    <style>
        :root {
            --dark-bg: #131C21;
            --title-color: #E5D1B8;
            --text-color: #FFFFFF;
            --accent-color: #C4A484;
            --dark-accent: #1a252b;
            --light-accent: rgba(196, 164, 132, 0.1);
            --border-color: rgba(196, 164, 132, 0.2);
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        #main-header {
            background: var(--dark-accent);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #branding h1 {
            margin: 0;
        }

        #branding h1 a {
            color: var(--title-color);
            text-decoration: none;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        #branding h1 a:hover {
            color: var(--accent-color);
            transition: color 0.3s ease;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        nav ul li a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        nav ul li a:hover {
            color: var(--accent-color);
            background: rgba(196, 164, 132, 0.1);
        }

        nav ul li a.btn-nav {
            background: transparent;
            border: 2px solid var(--accent-color);
            color: var(--text-color);
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        nav ul li a.btn-nav:hover {
            background: var(--accent-color);
            color: var(--dark-bg);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(196, 164, 132, 0.3);
        }

        .main-content {
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }

        @media (max-width: 768px) {
            nav ul {
                flex-direction: column;
                gap: 1rem;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--dark-accent);
                border-top: 1px solid var(--border-color);
                padding: 1rem;
                display: none;
            }

            nav ul.show {
                display: flex;
            }

            .mobile-menu-toggle {
                display: block;
                background: none;
                border: none;
                color: var(--text-color);
                font-size: 1.5rem;
                cursor: pointer;
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div id="branding">
                <h1><a href="<?php echo $base_path; ?>index.php">VuaToFua</a></h1>
            </div>
            <nav>
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
                <ul id="nav-menu">
                    <?php if (is_logged_in()): ?>
                        <li><a href="<?php echo $base_path; ?>dashboard.php">Home</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $base_path; ?>locations.php">Locations</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="<?php echo $base_path; ?>customer/profile.php">My Profile</a></li>
                        <li><a href="<?php echo $base_path; ?>customer/my_contacts.php">My Contacts</a></li>
                        <li><a href="<?php echo $base_path; ?>customer/change-password.php">Change Password</a></li>
                        <li><a href="<?php echo $base_path; ?>logout.php" class="btn-nav">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>login.php" class="btn-nav">Login</a></li>
                        <li><a href="<?php echo $base_path; ?>register.php" class="btn-nav">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="main-content">

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('nav-menu');
            menu.classList.toggle('show');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('nav-menu');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!menu.contains(event.target) && !toggle.contains(event.target)) {
                menu.classList.remove('show');
            }
        });
    </script>
