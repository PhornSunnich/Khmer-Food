<?php
require_once 'security.php';
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: admin/cms/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 bg-cover bg-center bg-no-repeat bg-fixed"
      style="background-image: url('assets/images/banner/banner.jpg');">

    <!-- Dark overlay - same as Login & Register -->
    <div class="fixed inset-0 bg-black bg-opacity-60"></div>

    <div class="relative z-10 bg-white/10 backdrop-blur-lg rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-white/20 p-8">

        <!-- Header -->
        <div class="text-center mb-10">
            <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="ti ti-lock text-4xl text-green-400"></i>
            </div>
            <h1 class="text-4xl font-bold text-green-400 drop-shadow-md">Forgot Password?</h1>
            <p class="text-gray-300 mt-3 text-lg">Contact the administrator to reset it.</p>
        </div>

        <!-- Security Notice -->
        <div class="bg-gradient-to-br from-amber-600/25 to-orange-600/15 border border-amber-400/40 rounded-2xl p-7 text-center backdrop-blur-sm mb-7">
            <i class="ti ti-alert-triangle text-5xl text-amber-400 mb-4 block mx-auto"></i>
            <h3 class="text-xl font-bold text-white mb-2">Password Reset is Disabled</h3>
            <p class="text-gray-200 text-sm leading-relaxed">
                For security reasons, self-service password recovery is not available.<br>
                Only the <span class="text-green-300 font-semibold">System Administrator</span> can reset passwords.
            </p>
        </div>

        <!-- Contact Admin -->
        <div class="bg-white/10 border border-white/20 rounded-2xl p-7 backdrop-blur-sm mb-10">
            <div class="flex items-center gap-3 mb-5">
                <i class="ti ti-mail text-2xl text-green-400"></i>
                <h3 class="text-xl font-bold text-green-400">Contact Administrator</h3>
            </div>
            <div class="space-y-4 text-gray-200">
                <p>Email: <span class="text-white font-medium">admin@khmerfood.com</span></p>
                <p>Phone: <span class="text-white font-medium">096 123 4567</span></p>
                <p class="text-sm text-gray-400 pt-3 border-t border-white/10">
                    Or ask an admin directly at the restaurant
                </p>
            </div>
        </div>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="index.php"
               class="inline-flex items-center gap-3 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold py-4 px-14 rounded-2xl shadow-xl transition transform hover:scale-105 duration-200 text-lg">
                <i class="ti ti-arrow-left text-xl"></i>
                Back to Login
            </a>
        </div>
    </div>
</body>
</html>