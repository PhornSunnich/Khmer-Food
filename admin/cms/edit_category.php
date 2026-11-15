<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

require_once __DIR__ . '/../../conn.php';

$message = '';

// Get category ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_categories.php");
    exit();
}

$category_id = (int)$_GET['id'];

// Fetch current category data
$stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_categories.php");
    exit();
}

$category = $result->fetch_assoc();
$stmt->close();

// === UPDATE CATEGORY ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['category_name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6">
                      <i class="ti ti-alert-circle mr-2"></i> Category name is required!
                    </div>';
    } else {
        $stmt = $conn->prepare("UPDATE categories SET name = ?, Description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $category_id);

        if ($stmt->execute()) {
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                          <i class="ti ti-check text-2xl"></i> Category updated successfully!
                        </div>';
            // Refresh data
            $category['CategoryName'] = $name;
            $category['Description'] = $description;
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6">
                          Error updating category.
                        </div>';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl flex flex-col fixed h-full z-50">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold flex items-center justify-center gap-3">
                <i class="ti ti-bowl text-3xl"></i> Khmer Food
            </h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>

        <nav class="flex-1 mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">
                <i class="ti ti-layout-dashboard mr-4 text-xl"></i> Dashboard
            </a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl">
                <i class="ti ti-category mr-4 text-xl"></i> Categories
            </a>
            <a href="manage_products.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">
                <i class="ti ti-package mr-4 text-xl"></i> Menu Items
            </a>
            <a href="manage_orders.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">
                <i class="ti ti-shopping-cart mr-4 text-xl"></i> Orders
            </a>
            <?php if ($_SESSION['role'] === 'Admin'): ?>
            <a href="manage_users.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">
                <i class="ti ti-users mr-4 text-xl"></i> Manage Users
            </a>
            <?php endif; ?>
        </nav>

        <div class="p-6 border-t border-emerald-700">
            <div class="flex items-center mb-4">
                <i class="ti ti-user-circle text-4xl mr-3"></i>
                <div>
                    <p class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
                    <p class="text-emerald-300 text-sm"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></p>
                </div>
            </div>
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold transition transform hover:scale-105">
                <i class="ti ti-logout mr-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-6 lg:p-10">
        <div class="mb-10">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-3xl lg:text-4xl font-bold text-gray-800 flex items-center gap-4">
                        <i class="ti ti-edit text-emerald-600 text-5xl"></i>
                        Edit Category
                    </h2>
                    <p class="text-gray-600 mt-2 text-lg">Update category details below</p>
                </div>
                <a href="manage_categories.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 px-6 rounded-xl transition flex items-center gap-3">
                    <i class="ti ti-arrow-left"></i> Back to Categories
                </a>
            </div>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Edit Form -->
        <div class="bg-white rounded-3xl shadow-xl p-10 max-w-3xl mx-auto card-hover transition-all">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center">
                    <i class="ti ti-category text-3xl text-emerald-600"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Category ID: #<?= $category_id ?></h3>
                    <p class="text-gray-500">You are editing: <strong><?= htmlspecialchars($category['name']) ?></strong></p>
                </div>
            </div>

            <form method="POST" class="space-y-8">
                <div>
                    <label class="block text-gray-700 font-semibold mb-3 text-lg">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="category_name" required value="<?= htmlspecialchars($category['name']) ?>" 
                           class="w-full px-6 py-5 border-2 border-gray-300 rounded-2xl focus:border-emerald-500 focus:outline-none transition text-lg">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-3 text-lg">
                        Description <span class="text-gray-400">(Optional)</span>
                    </label>
                    <textarea name="description" rows="4" placeholder="Describe this category..." 
                              class="w-full px-6 py-5 border-2 border-gray-300 rounded-2xl focus:border-emerald-500 focus:outline-none transition text-lg resize-none"><?= htmlspecialchars($category['Description'] ?? '') ?></textarea>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="submit" 
                            class="bg-gradient-to-r from-emerald-600 to-green-700 hover:from-emerald-700 hover:to-green-800 text-white font-bold py-5 px-12 rounded-2xl shadow-xl transition transform hover:scale-105 flex items-center gap-3 text-lg">
                        <i class="ti ti-device-floppy"></i> Save Changes
                    </button>
                    <a href="manage_categories.php" 
                       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-5 px-12 rounded-2xl transition flex items-center gap-3 text-lg">
                        <i class="ti ti-x"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Product Count Info -->
        <?php
        $product_count = $conn->query("SELECT COUNT(*) FROM products WHERE category_id = $category_id")->fetch_row()[0];
        ?>
        <div class="mt-10 bg-amber-50 border-2 border-amber-200 rounded-3xl p-8 max-w-3xl mx-auto">
            <div class="flex items-center gap-4">
                <i class="ti ti-info-circle text-4xl text-amber-600"></i>
                <div>
                    <p class="text-amber-900 font-semibold text-lg">This category contains <strong><?= $product_count ?> product(s)</strong></p>
                    <p class="text-amber-700 mt-1">Changing the name will update it across all menu items.</p>
                </div>
            </div>
        </div>

        <footer class="mt-16 text-center text-gray-500 text-sm">
            © <?= date('Y') ?> Khmer Food Restaurant • Made with <i class="ti ti-heart text-red-500"></i> in Phnom Penh
        </footer>
    </div>
</body>
</html>