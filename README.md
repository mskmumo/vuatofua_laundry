# VuaToFua Laundry Management System

VuaToFua is a web-based laundry management system that handles customer orders, loyalty points, and administrative tasks for a laundry service business.

## Features

- 🔐 Secure User Authentication
- 👤 Customer & Admin Portals
- 📍 Multiple Drop-off Locations
- 📦 Order Management
- 💎 Loyalty Points System
- 📱 SMS Notifications
- 📊 Admin Dashboard
- 🔒 Session Management
- 📧 Email Verification

## Tech Stack

- PHP 7.4+
- MySQL 5.7+
- HTML5/CSS3
- JavaScript (ES6+)
- PHPMailer for emails
- Bootstrap for styling

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/vuatofua.git
cd vuatofua
```

2. Create a MySQL database and import the schema:
```bash
mysql -u your_username -p your_database_name < sql/vuatofua_schema.sql
```

3. Configure the database connection:
- Copy `config.example.php` to `config.php`
- Update the database credentials in `config.php`

4. Configure email settings:
- Update `mail_config.php` with your SMTP details

5. Set up a web server (Apache/Nginx) pointing to the project directory

## Default Admin Account

The system comes with a pre-configured admin account:
- Email: admin@vuatofua.com
- Password: Admin123
- Phone: 0700000000

Note: The password meets all security requirements:
- At least 8 characters
- Contains uppercase letter (A)
- Contains lowercase letters (dmin)
- Contains numbers (123)

You can find these credentials in `sql/vuatofua_schema.sql`.

## Project Structure

```
vuatofua/
├── admin/              # Admin portal files
│   ├── dropoffs.php    # Manage drop-off locations
│   ├── index.php      # Admin dashboard
│   ├── loyalty.php    # Loyalty points management
│   ├── manage_orders.php
│   ├── manage_users.php
│   └── view_messages.php
├── css/               # Stylesheets
├── images/           # Image assets
├── sql/              # Database schema
├── templates/        # Reusable templates
├── vendor/          # Dependencies
├── config.php       # Database configuration
├── functions.php    # Core functions
├── index.php        # Home page
├── login.php        # User login
├── register.php     # User registration
└── README.md        # This file
```

## Core Features Documentation

### User Management
- Registration with email/phone verification
- Secure password requirements:
  - Minimum 8 characters
  - At least one uppercase letter
  - At least one lowercase letter
  - At least one number
- Session management with security features
- Password reset functionality
- Account status tracking

### Order Management
- Create new orders
- Track order status
- View order history
- Multiple service types
- Drop-off location selection

### Loyalty System
- Points earned per order
- Points redemption
- Points history tracking
- Automated calculations

### Admin Features
1. User Management
   - View all users
   - Manage user status
   - Reset passwords
   - View user activity

2. Order Management
   - View all orders
   - Update order status
   - Generate reports
   - Track deliveries

3. Location Management
   - Add/edit drop-off points
   - Manage operating hours
   - Set location capacity

4. System Monitoring
   - View SMS logs
   - Track login attempts
   - Monitor system status

## Security Features

- Argon2id password hashing
- CSRF protection
- Session security
- Input sanitization
- Rate limiting
- SQL injection prevention
- XSS protection

## Database Schema

The database includes several key tables:
- users
- orders
- drop_off_locations
- loyalty_points
- sms_logs
- user_sessions

Full schema details can be found in `sql/vuatofua_schema.sql`.

## Development Guidelines

1. Security
   - Always sanitize user input
   - Use prepared statements
   - Implement CSRF tokens
   - Validate all data

2. Code Style
   - Follow PHP PSR standards
   - Comment complex logic
   - Use meaningful variable names
   - Keep functions focused

3. Testing
   - Test all form submissions
   - Verify email functionality
   - Check security measures
   - Validate user flows

## License

[Your License Here]

## Support

For support, email support@vuatofua.com or create an issue in the repository.
