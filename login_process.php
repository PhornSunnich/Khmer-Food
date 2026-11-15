<?php
session_start();
require_once 'conn.php'; // Make sure this path is correct from your file location

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ================== REGISTRATION ==================
if (isset($_POST['register'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($fullname) || empty($email) || empty($password)) {
        header("Location: register.php?error=All fields are required");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=Invalid email address");
        exit();
    }

    if (strlen($password) < 6) {
        header("Location: register.php?error=Password must be at least 6 characters");
        exit();
    }

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        header("Location: register.php?error=Email already registered");
        exit();
    }
    $check->close();

    // Create new staff account
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $role   = 'staff'; // lowercase to match your DB

    $stmt = $conn->prepare("INSERT INTO admin_users (fullname, email, password, role, status) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("ssss", $fullname, $email, $hashed, $role);

    if ($stmt->execute()) {
        header("Location: index.php?registered=1");
    } else {
        header("Location: register.php?error=Registration failed");
    }
    $stmt->close();
    exit();
}

// ================== LOGIN ==================
if (isset($_POST['login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: index.php?error=Please enter email and password");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, fullname, email, password, role FROM admin_users WHERE email = ? AND status = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Login Success
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['fullname'] ?: $user['email'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']     = ucfirst($user['role']); // Admin or Staff

            header("Location: ../admin/cms/dashboard.php");
            exit();
        }
    }

    // If we get here → wrong email/password
    header("Location: "Location: index.php?error=Invalid email or password");
    exit();
}

$conn->close();
?>