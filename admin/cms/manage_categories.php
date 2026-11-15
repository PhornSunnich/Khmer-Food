<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

require_once __DIR__ . '/../../conn.php';
$message = '';

// === ADD CATEGORY ===
if (isset($_POST['add_category'])) {
    $name        = trim($_POST['category_name']);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6">Category name is required!</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                          <i class="ti ti-check text-2xl"></i> Category added successfully!
                        </div>';
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6">Error adding category.</div>';
        }
        $stmt->close();
    }
}

// === DELETE CATEGORY ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $check = $conn->query("SELECT COUNT(*) FROM products WHERE category_id = $id")->fetch_row()[0];
    if ($check > 0) {
        $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-6 py-4 rounded-xl mb-6">
                      Cannot delete: This category has products!
                    </div>';
    } else {
        $conn->query("DELETE FROM categories WHERE id = $id");
        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6">
                      Category deleted successfully!
                    </div>';
    }
    header("Location: manage_categories.php");
    exit();
}

// === FETCH CATEGORIES WITH PRODUCT COUNT ===
$categories = $conn->query("
    SELECT c.id, c.name, c.description,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
    FROM categories c
    ORDER BY c.name
");
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl fixed h-full z-50 flex flex-col">
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
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold transition hover:scale-105">
                <i class="ti ti-logout mr-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <div class="mb-10">
            <h2 class="text-4xl font-bold text-gray-800 flex items-center gap-4">
                <i class="ti ti-category text-emerald-600"></i> Manage Categories
            </h2>
            <p class="text-gray-600 mt-2">Add, edit or remove menu categories</p>
        </div>

        <?= $message ?>

        <!-- Add Category Form -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-10">
            <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                <i class="ti ti-plus text-green-600"></i> Add New Category
            </h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="category_name" required placeholder="e.g., Main Course, Drinks"
                           class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:outline-none transition">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Short description in Khmer or English..."
                              class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:outline-none transition"></textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" name="add_category"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-10 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-3">
                        <i class="ti ti-plus"></i> Add Category
                    </button>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="p-8 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="ti ti-list text-blue-600"></i> All Categories
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-8 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ID</th>
                            <th class="px-8 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Category Name</th>
                            <th class="px-8 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Description</th>
                            <th class="px-8 py-5 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Products</th>
                            <th class="px-8 py-5 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-6 text-gray-700 font-medium">#<?= $cat['id'] ?></td>
                                    <td class="px-8 py-6 font-semibold text-gray-900"><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="px-8 py-6">
                                        <?php if (!empty($cat['description'])): ?>
                                            <p class="text-sm text-gray-600 line-clamp-2" title="<?= htmlspecialchars($cat['description']) ?>">
                                                <?= htmlspecialchars($cat['description']) ?>
                                            </p>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-emerald-100 text-emerald-800">
                                            <i class="ti ti-package mr-2"></i>
                                            <?= $cat['product_count'] ?> Items
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center space-x-4">
                                        <a href="edit_category.php?id=<?= $cat['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                            <i class="ti ti-edit text-xl"></i>
                                        </a>
                                        <a href="?delete=<?= $cat['id'] ?>" 
                                           onclick="return confirm('Delete <?= addslashes(htmlspecialchars($cat['name'])) ?>? This cannot be undone.')"
                                           class="text-red-600 hover:text-red-800">
                                            <i class="ti ti-trash text-xl"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-8 py-20 text-center text-gray-500">
                                    <i class="ti ti-inbox text-6xl mb-4 block"></i>
                                    <p class="text-xl">No categories yet</p>
                                    <p>Add your first category above!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="mt-16 text-center text-gray-500 text-sm">
            © <?= date('Y') ?> Khmer Food Restaurant • Made with <i class="ti ti-heart text-red-500"></i> in Phnom Penh
        </footer>
    </div>
</body>
</html>