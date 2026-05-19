Library Role-Based Data Entry System

INSTALLATION
1. Copy the library_entry_system folder to XAMPP htdocs.
2. Start Apache and MySQL.
3. Open phpMyAdmin.
4. Import database.sql.
5. Visit: http://localhost/library_entry_system/

DEFAULT ADMIN LOGIN
Email: admin@library.local
Password: Admin@12345

IF LOGIN FAILS
1. Open this once in browser:
   http://localhost/library_entry_system/reset_admin.php
2. It will recreate/reset the admin account.
3. Login with:
   Email: admin@library.local
   Password: Admin@12345
4. Delete reset_admin.php after successful login.

DATABASE SETTINGS
File: includes/config.php
Default XAMPP credentials:
Host: localhost
Database: library_entry_system
Username: root
Password: blank

SECURITY NOTE
Passwords are stored using password_hash(..., PASSWORD_BCRYPT) and verified using password_verify(...).
