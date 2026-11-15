<?php
require_once 'security.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: admin/cms/dashboard.php");
    exit();
}

$error = $success = '';

if (!empty($_GET['error']))      $error   = (string)$_GET['error'];
if (isset($_GET['registered']))  $success = "Registration successful! Please log in.";
if (isset($_GET['loggedout']))   $success = "You have been logged out.";
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
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            transform-origin: left top;
            color: #cbd5e1;
            pointer-events: none;
            transition: all 0.25s ease;
            font-size: 1rem;
        }
        .peer:focus ~ .label-float,
        .peer:not(:placeholder-shown) ~ .label-float {
            top: 8px;
            transform: translateY(0) scale(0.75);
            color: #34d399;
            font-weight: 600;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 bg-cover bg-center bg-no-repeat bg-fixed"
      style="background-image: url('assets/images/banner/banner.jpg');">

    <div class="fixed inset-0 bg-black bg-opacity-60"></div>

    <div class="relative z-10 bg-white/10 backdrop-blur-lg rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-white/20">
        <div class="p-8 text-center">

            <div class="mb-8">
                <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-2xl">
                    <i class="ti ti-bowl text-white text-5xl"></i>
                </div>
                <h1 class="text-4xl font-bold text-green-400 drop-shadow-md mt-6">Khmer Food</h1>
                <p class="text-gray-300 text-lg">Restaurant Admin Panel</p>
            </div>

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

            <form method="POST" action="login_process.php" class="space-y-6 text-left">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="relative">
                    <input type="email" name="email" required maxlength="254"
                           class="peer w-full h-14 px-5 pt-5 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-green-400 focus:ring-4 focus:ring-green-400/30 transition"
                           placeholder=" " autocomplete="email">
                    <label class="label-float">Email Address</label>
                </div>

                <div class="relative">
                    <input type="password" name="password" required maxlength="200"
                           class="peer w-full h-14 px-5 pt-5 bg-white/20 border border-white/30 rounded-xl text-white placeholder-transparent focus:outline-none focus:border-green-400 focus:ring-4 focus:ring-green-400/30 transition"
                           placeholder=" " autocomplete="current-password">
                    <label class="label-float">Password</label>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold py-4 rounded-xl shadow-xl transition transform hover:scale-105 duration-200 flex items-center justify-center gap-3">
                    <i class="ti ti-login text-xl"></i>
                    Login to Dashboard
                </button>
            </form>

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