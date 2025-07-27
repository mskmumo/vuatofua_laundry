<?php
require_once 'config.php';
require_once 'functions.php';

secure_session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VuaToFua - Premium Laundry Services in Nairobi</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 6px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .contact-form .form-group {
            margin-bottom: 1rem;
        }
        .contact-form input, .contact-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .contact-form button[type="submit"] {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .contact-form button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .contact-form button[type="submit"]:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
    </style>
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
                        <li><a href="customer/my_contacts.php" class="btn-nav">My Contacts</a></li>
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
            <div>
                <div id="form-status"></div>
                <form id="contact-form" action="contact_handler.php" method="POST" class="contact-form">
                    <h3>Send us a Message</h3>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" name="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn">
                        <span class="btn-text">Send Message</span>
                        <span class="btn-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Sending...
                        </span>
                    </button>
                </form>
            </div>
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

            // Handle URL status parameters
            if (status) {
                let message = '';
                let messageClass = '';

                if (status === 'success') {
                    message = 'Thank you! Your message has been sent successfully. We will get back to you soon.';
                    messageClass = 'alert-success';
                } else if (status === 'error') {
                    message = 'Sorry, something went wrong. Please try again later.';
                    messageClass = 'alert-danger';
                } else if (status === 'invalid_email') {
                    message = 'Please enter a valid email address.';
                    messageClass = 'alert-danger';
                } else if (status === 'missing_fields') {
                    message = 'Please fill in all required fields.';
                    messageClass = 'alert-danger';
                } else if (status === 'csrf_error') {
                    message = 'Security error. Please refresh the page and try again.';
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

            // Handle form submission with AJAX
            const contactForm = document.getElementById('contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const btnText = submitBtn.querySelector('.btn-text');
                    const btnLoading = submitBtn.querySelector('.btn-loading');
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnLoading.style.display = 'inline';
                    
                    // Clear previous status
                    formStatusDiv.innerHTML = '';
                    
                    // Submit form data
                    const formData = new FormData(this);
                    
                    fetch('contact_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        let messageClass = data.success ? 'alert-success' : 'alert-danger';
                        formStatusDiv.innerHTML = `<div class="alert ${messageClass}">${data.message}</div>`;
                        
                        if (data.success) {
                            // Reset form on success
                            contactForm.reset();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        formStatusDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.disabled = false;
                        btnText.style.display = 'inline';
                        btnLoading.style.display = 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>
