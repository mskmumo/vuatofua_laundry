<?php
require_once 'config.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VuaToFua - Premium Laundry Services in Nairobi</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>

    <header id="home-header">
        <div class="container">
            <div class="logo">VuaToFua</div>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="locations.php">Locations</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="dashboard.php" class="btn-nav">Dashboard</a></li>
                        <li><a href="logout.php" class="btn-nav">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn-nav">Login</a></li>
                        <li><a href="register.php" class="btn-nav">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section id="hero">
        <div class="hero-content">
            <h1>Welcome to VuaToFua</h1>
            <p>Effortless Laundry Management, Just a Click Away</p>
            <a href="register.php" class="btn btn-lg">Get Started Today</a>
        </div>
    </section>

    <section id="about" class="content-section">
        <div class="container grid-2">
            <div class="about-image">
                <img src="images/image-4.webp" alt="Holding folded laundry">
            </div>
            <div class="about-text">
                <h2>About VuaToFua</h2>
                <p>VuaToFua is a revolutionary laundry service in Nairobi, combining technology with convenience. Designed for busy individuals and families, we provide a seamless, high-quality laundry experience through our easy-to-use digital platform. From order placement and tracking to loyalty rewards and simulated SMS alerts, our goal is to make laundry hassle-free, giving you more time for what truly matters.</p>
            </div>
        </div>
    </section>

    <section id="services" class="content-section bg-dark">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <img src="images/image-1.webp" alt="Laundromat">
                    <h3>Wash, Dry & Fold</h3>
                    <p>Comprehensive service for your everyday laundry needs. We wash, dry, and perfectly fold your clothes.</p>
                </div>
                <div class="service-card">
                    <img src="images/image-2.webp" alt="Dry Cleaning">
                    <h3>Dry Cleaning</h3>
                    <p>Expert dry cleaning for your delicate and special garments, ensuring they stay in pristine condition.</p>
                </div>
                <div class="service-card">
                    <img src="images/image-3.webp" alt="Washing Machines">
                    <h3>Ironing Services</h3>
                    <p>Get perfectly pressed clothes with our professional ironing service. Look sharp for any occasion.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="content-section">
        <div class="container">
            <h2 class="section-title">Testimonials</h2>
            <div class="testimonial-card">
                <img src="https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1" alt="Customer Testimonial" class="testimonial-avatar">
                <p class="testimonial-text">"VuaToFua has been a game-changer for me. The service is fast, reliable, and the app is so easy to use. My clothes always come back perfect!"</p>
                <p class="testimonial-author">- Jane D., Nairobi</p>
            </div>
        </div>
    </section>

    <section id="contact" class="content-section bg-dark">
        <div class="container grid-2">
            <div class="contact-info">
                <h3>Contact Information</h3>
                <p><i class="fas fa-map-marker-alt"></i> 123 Kimathi Street, Nairobi, Kenya</p>
                <p><i class="fas fa-phone"></i> +254 700 000 000</p>
                <p><i class="fas fa-envelope"></i> contact@vuatofua.com</p>
            </div>
            <div id="form-status"></div>
            <form action="contact_handler.php" method="POST" class="contact-form">
                <h3>Send us a Message</h3>
                <div class="form-group">
                    <input type="text" name="name" placeholder="Your Name" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Your Email" required>
                </div>
                <div class="form-group">
                    <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
                </div>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>
    </section>

    <footer id="main-footer">
        <div class="container">
            <p>Copyright &copy; 2025 VuaToFua. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            const params = new URLSearchParams(window.location.search);
            const status = params.get('status');
            const formStatusDiv = document.getElementById('form-status');

            if (status) {
                let message = '';
                let messageClass = '';

                if (status === 'success') {
                    message = 'Thank you! Your message has been sent successfully.';
                    messageClass = 'alert-success';
                } else if (status === 'error') {
                    message = 'Sorry, something went wrong. Please try again later.';
                    messageClass = 'alert-danger';
                } else if (status === 'invalid_email') {
                    message = 'Please enter a valid email address.';
                    messageClass = 'alert-danger';
                }

                if (message) {
                    formStatusDiv.innerHTML = `<div class="alert ${messageClass}">${message}</div>`;
                    // Clear the status from URL to avoid re-showing message on refresh
                    if (history.pushState) {
                        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '#contact';
                        history.pushState({path: cleanUrl}, '', cleanUrl);
                    }
                }
            }
        });
    </script>
</body>
</html>
