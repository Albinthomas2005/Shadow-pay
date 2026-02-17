<<<<<<< HEAD
# Shadow-pay
ShadowPay is a privacy-focused FinTech solution safeguarding transactions via disposable virtual cards. Built with HTML, CSS, JS, and MySQL, it masks financial details to prevent fraud. Features include Merchant Locking and granular spending controls, offering a secure dashboard for financial management.
=======
ShadowPay - local XAMPP setup

Files added:
- db.php - shared PDO connection (adjust credentials if needed)
- signup_process.php - handles registration
- login_process.php - handles login
- logout.php - signs out
- profile.php - example protected page
- db.sql - SQL to create database and users table

Quick start (Windows, XAMPP):
1. Copy this `ui` folder into your XAMPP `htdocs` (looks like it's already at `c:\xampp\htdocs\ui`).
2. Start Apache and MySQL via the XAMPP Control Panel.
3. Open phpMyAdmin (http://localhost/phpmyadmin), create the database and import `db.sql`: Select SQL tab, paste contents of `db.sql` and run.
4. Default DB settings in `db.php` use database name `shadowpay` and MySQL user `root` with no password. If your root has a password, update `db.php` accordingly.
5. Open the signup page in your browser: http://localhost/ui/signup/shadowpay_signup.html
6. After signing up you'll be redirected to the login page. On successful login you reach `profile.php`.

Security notes (local dev only):
- This is a simple example. For production you must enable HTTPS, set secure cookie flags, harden sessions, and not show DB errors.
- Use environment variables or a configuration file to store DB credentials instead of editing `db.php` in code.
>>>>>>> 8358aa4 (Shadow pay project final push)
