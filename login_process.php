<?php
require_once 'security.php';
require_once 'conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const MAX_FAILS_PER_EMAIL = 5;   // failed attempts per email
const MAX_FAILS_PER_IP    = 15;  // failed attempts per IP
const LOCK_MINUTES        = 15;

function back_with_error(string $msg): void
{
    header("Location: index.php?error=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    back_with_error("Session expired. Please try again.");
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$ip       = client_ip();

if ($email === '' || $password === '' || strlen($email) > 254 || strlen($password) > 200) {
    back_with_error("Please enter email and password");
}

try {
    // ---------- Brute-force protection ----------
    $mins = LOCK_MINUTES;

    $q = $conn->prepare(
        "SELECT COUNT(*) AS c FROM login_attempts
         WHERE email = ? AND attempted_at > (NOW() - INTERVAL $mins MINUTE)"
    );
    $q->bind_param("s", $email);
    $q->execute();
    $emailFails = (int)$q->get_result()->fetch_assoc()['c'];
    $q->close();

    $q = $conn->prepare(
        "SELECT COUNT(*) AS c FROM login_attempts
         WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL $mins MINUTE)"
    );
    $q->bind_param("s", $ip);
    $q->execute();
    $ipFails = (int)$q->get_result()->fetch_assoc()['c'];
    $q->close();

    if ($emailFails >= MAX_FAILS_PER_EMAIL || $ipFails >= MAX_FAILS_PER_IP) {
        back_with_error("Too many failed attempts. Try again in " . LOCK_MINUTES . " minutes.");
    }

    // ---------- Look up user (active accounts only) ----------
    $stmt = $conn->prepare(
        "SELECT id, fullname, email, password, role
         FROM admin_users
         WHERE email = ? AND status = 1
         LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $valid = false;
    if ($user) {
        $valid = password_verify($password, $user['password']);
    } else {
        // Same cost when the email doesn't exist (prevents timing attacks)
        password_hash($password, PASSWORD_DEFAULT);
    }

    if ($valid) {
        // Upgrade the hash if PHP's default algorithm/cost changed
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $up->bind_param("si", $newHash, $user['id']);
            $up->execute();
            $up->close();
        }

        // Clear failed attempts for this email
        $del = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        // Prevent session fixation + rotate CSRF token
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);

        $_SESSION['loggedin']      = true;
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['fullname'] ?: $user['email'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['role']          = ucfirst($user['role']);
        $_SESSION['last_activity'] = time();

        header("Location: admin/cms/dashboard.php");
        exit();
    }

    // ---------- Failed login: record it ----------
    $log = $conn->prepare("INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())");
    $log->bind_param("ss", $email, $ip);
    $log->execute();
    $log->close();

    usleep(300000); // small delay slows down automated guessing
    back_with_error("Invalid email or password");

} catch (mysqli_sql_exception $e) {
    error_log("Login error: " . $e->getMessage());
    back_with_error("Something went wrong. Please try again later.");
}