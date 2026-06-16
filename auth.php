<?php
session_start();
header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN CREDENTIALS
// Replace the password hash below using:  password_hash('yourPassword', PASSWORD_BCRYPT)
// Default credentials:  admin / admin123
// ─────────────────────────────────────────────────────────────────────────────
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$Zn/28NyJ13brKHK22mtWMOoHm4yWOdL0VNoAh6floG/1l.IXAxQVG'); // password: password
// To set your own password, run: echo password_hash('your_new_password', PASSWORD_BCRYPT);


$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username']  = $username;
    $_SESSION['login_time']      = time();
    echo json_encode(['success' => true, 'message' => 'Login successful.']);
} else {
    // Small delay to deter brute-force
    sleep(1);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
}
?>