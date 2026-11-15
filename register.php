<?php
require_once 'security.php';
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: admin/cms/dashboard.php");
    exit();
}

require_once 'conn.php';

// Make mysqli throw exceptions so try/catch works
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1 = active immediately, 0 = pending admin approval (safer)
$defaultStatus = 1;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error    = '';
$success  = '';
$fullname = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Invalid request. Please refresh the page and try again.";
    } elseif ($fullname === '' || $email === '' || $password === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (mb_strlen($fullname, 'UTF-8') < 2 || mb_strlen($fullname, 'UTF-8') > 50) {
        $error = "Full name must be between 2 and 50 characters.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        try {
            $check = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "This email is already registered.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO admin_users (fullname, email, password, role, status, created_at)
                     VALUES (?, ?, ?, 'staff', ?, NOW())"
                );
                $stmt->bind_param("sssi", $fullname, $email, $hashed, $defaultStatus);
                $stmt->execute();
                $stmt->close();

                $success = "Account created successfully! You can now <a href='index.php' class='underline font-bold text-green-400'>log in</a>.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $fullname = '';
                $email    = '';
            }
            $check->close();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $error = "This email is already registered.";
            } else {
                error_log("Register error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Register error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Clean Floating Labels */
        .label-float {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            transform-origin: left top;
            color: #64748b;
            pointer-events: none;
            transition: all 0.25s ease;
            font-size: 1rem;
            white-space: nowrap;
        }
        .peer:focus ~ .label-float,
        .peer:not(:placeholder-shown) ~ .label-float {
            top: 8px;
            transform: translateY(0) scale(0.75);
            color: #059669;
            font-weight: 600;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 bg-cover bg-center bg-no-repeat bg-fixed"
      style="background-image: url('assets/images/banner/banner.jpg');">

    <div class="fixed inset-0 bg-black bg-opacity-60"></div>

    <div class="relative z-10 bg-white/10 backdrop-blur-lg rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-white/20 p-8">

        <div class="text-center mb-10">
            <h1 class="text-4xl font-bold text-green-400 drop-shadow-md">Create Account</h1>
            <p class="text-gray-300 mt-2 text-lg">Join Khmer Food Admin Team</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-500/20 border border-green-400/50 text-green-300 px-6 py-5 rounded-2xl mb-8 text-center backdrop-blur-sm">
                <i class="ti ti-check text-4xl block mb-3"></i>
                <div class="text-lg"><?= $success ?></div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-400/50 text-red-300 px-6 py-5 rounded-2xl mb-8 text-center backdrop-blur-sm">
                <i class="ti ti-alert-circle text-4xl block mb-3"></i>
                <div class="text-lg"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <?php if (!$success): ?>
        <form method="POST" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="relative">
                <input type="text" name="fullname" required maxlength="100"
                       value="<?= htmlspecialchars($fullname) ?>"
                       class="peer w-full h-14 px-5 pt-5 bg-white border border-white/30 rounded-2xl text-gray-900 placeholder-transparent focus:outline-none focus:border-green-500 focus:ring-4 focus:ring-green-400/30 transition"
                       placeholder=" " autocomplete="name">
                <label class="label-float">Full Name</label>
            </div>

            <div class="relative">
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($email) ?>"
                       class="peer w-full h-14 px-5 pt-5 bg-white border border-white/30 rounded-2xl text-gray-900 placeholder-transparent focus:outline-none focus:border-green-500 focus:ring-4 focus:ring-green-400/30 transition"
                       placeholder=" " autocomplete="email">
                <label class="label-float">Email Address</label>
            </div>

            <div class="relative">
                <input type="password" name="password" required minlength="6"
                       class="peer w-full h-14 px-5 pt-5 bg-white border border-white/30 rounded-2xl text-gray-900 placeholder-transparent focus:outline-none focus:border-green-500 focus:ring-4 focus:ring-green-400/30 transition"
                       placeholder=" " autocomplete="new-password">
                <label class="label-float">Password (min 6 characters)</label>
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold py-4 rounded-2xl shadow-xl transition transform hover:scale-105 duration-200">
                Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-10 text-gray-300">
            <p>Already have an account?
                <a href="index.php" class="text-green-400 font-bold hover:underline">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>