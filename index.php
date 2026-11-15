<?php
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: ../website/admin/cms/dashboard.php");
    exit();
}

require_once 'conn.php';
$error = $success = '';

if (isset($_GET['registered'])) $success = "Registration successful! Please log in.";
if (isset($_GET['loggedout'])) $success = "You have been logged out.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, email, password, role FROM admin_users WHERE email = ? AND status = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['fullname'] ?: $user['email'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = ucfirst($user['role']);
                header("Location: ../website/admin/cms/dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Email not found or account disabled.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .label-float {
            transform: translateY(-22px) scale(0.85);
            transform-origin: left;
            color: #a0aec0;
            pointer-events: none;
            transition: all 0.2s ease;
        }
        .input-filled ~ .label-float,
        .input-focus ~ .label-float {
            transform: translateY(-32px) scale(0.75);
            color: #34d399;
            font-weight: 600;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 bg-cover bg-center bg-no-repeat bg-fixed"
      style="background-image: url('assets/images/banner/banner.jpg');">

    <!-- Super dark full overlay -->
    <div class="fixed inset-0 bg-black bg-opacity-60"></div>

    <div class="relative z-10 bg-white/10 backdrop-blur-lg rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-white/20">

        <div class="p-8 text-center">
            <!-- Header -->
            <div class="mb-8">
                <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-2xl">
                    <i class="ti ti-bowl text-white text-5xl"></i>
                </div>
                <h1 class="text-4xl font-bold text-green-400 drop-shadow-md mt-6">Khmer Food</h1>
                <p class="text-gray-300 text-lg">Restaurant Admin Panel</p>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="bg-green-500/20 border border-green-400/50 text-green-300 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 backdrop-blur-sm">
                    <i class="ti ti-check text-2xl"></i>
                    <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-400/50 text-red-300 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 backdrop-blur-sm">
                    <i class="ti ti-alert-circle text-2xl"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form with Floating Labels -->
            <form method="POST" class="space-y-8">
                <div class="relative">
                    <input type="email"
                           name="email"
                           required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="input-filled peer w-full px-5 py-4 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-green-400 focus:ring-4 focus:ring-green-400/30 transition"
                           placeholder="Email Address"
                           autocomplete="email">
                    <label class="absolute left-2 top-2 text-gray-400 label-float pointer-events-none transition-all duration-200">
                        Email Address
                    </label>
                </div>

                <div class="relative">
                    <input type="password"
                           name="password"
                           required
                           class="input-filled peer w-full px-5 py-4 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-green-400 focus:ring-4 focus:ring-green-400/30 transition"
                           placeholder="Password"
                           autocomplete="current-password">
                    <label class="absolute left-2 top-2 text-gray-400 label-float pointer-events-none transition-all duration-200">
                        Password
                    </label>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold py-4 rounded-xl shadow-xl transition transform hover:scale-105 duration-200 flex items-center justify-center gap-3">
                    <i class="ti ti-login text-xl"></i>
                    Login to Dashboard
                </button>
            </form>

            <!-- Links -->
            <div class="mt-8 text-center space-y-4 text-gray-300">
                <a href="register.php" class="block text-green-500 hover:text-green-400 transition text-sm">
                    <i class="ti ti-user-plus"></i> Create new account
                </a>
                <a href="forgot_password.php" class="block text-red-500 text-sm hover:text-red-400 transition">
                    <i class="ti ti-lock"></i> Forgot password?
                </a>
            </div>
        </div>
    </div>
</body>
</html>