<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}
require_once __DIR__ . '/../../conn.php';
$message = '';

// === FILTERS & SEARCH (NO MORE WARNINGS) ===
$search          = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$min_price       = $_GET['min_price'] ?? '';
$max_price       = $_GET['max_price'] ?? '';

// Convert to proper values safely
$min_price = ($min_price !== '' && is_numeric($min_price)) ? (float)$min_price : '';
$max_price = ($max_price !== '' && is_numeric($max_price)) ? (float)$max_price : '';

// Build WHERE clause
$where = []; $params = []; $types = '';
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $types .= "ss";
}
if ($category_filter !== '' && is_numeric($category_filter)) {
    $where[] = "p.category_id = ?"; $params[] = $category_filter; $types .= "i";
}
if ($status_filter !== '') {
    $where[] = "p.isAvailable = ?"; $params[] = $status_filter; $types .= "s";
}
if ($min_price !== '') { $where[] = "p.price >= ?"; $params[] = $min_price; $types .= "d"; }
if ($max_price !== '') { $where[] = "p.price <= ?"; $params[] = $max_price; $types .= "d"; }
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Build current filter string for Edit/Delete links
$current_filters = http_build_query(array_filter([
    'search'    => $search,
    'category'  => $category_filter,
    'status'    => $status_filter,
    'min_price' => $min_price,
    'max_price' => $max_price
]));

// === SAVE PRODUCT ===
if (isset($_POST['save_product'])) {
    $id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $price = (float)$_POST['price'];
    $category_id = (int)$_POST['category_id'];
    $isAvailable = $_POST['isAvailable'] ?? 'Available';

    $image_path = $edit_product['image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 5*1024*1024) {
            $new_name = uniqid('prod_') . '.' . $ext;
            $dir = __DIR__ . '/../../assets/images/products/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $new_name)) {
                if ($image_path && file_exists(__DIR__ . '/../../' . $image_path)) {
                    unlink(__DIR__ . '/../../' . $image_path);
                }
                $image_path = 'assets/images/products/' . $new_name;
            }
        }
    }

    if (empty($name) || $price <= 0 || $category_id <= 0) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-xl mb-6">Please fill all required fields!</div>';
    } else {
        if ($id > 0) {
            $sql = "UPDATE products SET name=?, description=?, price=?, category_id=?, image=?, isAvailable=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdissi", $name, $description, $price, $category_id, $image_path, $isAvailable, $id);
        } else {
            $sql = "INSERT INTO products (name, description, price, category_id, image, isAvailable) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdiss", $name, $description, $price, $category_id, $image_path, $isAvailable);
        }
        if ($stmt->execute()) {
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                          Product saved successfully!
                        </div>';
        }
        $stmt->close();
    }
}

// === DELETE PRODUCT ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc()['image'] ?? '';
    if ($img && file_exists(__DIR__ . '/../../' . $img)) unlink(__DIR__ . '/../../' . $img);
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: manage_products.php?" . $current_filters);
    exit();
}

// === FETCH CATEGORIES & PRODUCTS ===
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name");

$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause 
        ORDER BY p.id DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();

// === EDIT PRODUCT ===
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE id = $id");
    $edit_product = $res->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Khmer&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', 'Khmer', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl fixed h-full z-50 flex flex-col">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold flex items-center justify-center gap-3">Khmer Food</h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>
        <nav class="flex-1 mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Dashboard</a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Categories</a>
            <a href="manage_products.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl">Menu Items</a>
            <a href="manage_orders.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Orders</a>
        </nav>
        <div class="p-6 border-t border-emerald-700">
            <div class="flex items-center mb-4">
                <div>
                    <p class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
                    <p class="text-emerald-300 text-sm"><?= $_SESSION['role'] ?? 'Staff' ?></p>
                </div>
            </div>
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold transition hover:scale-105">Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <div class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-4xl font-bold text-gray-800">Manage Menu Items</h2>
                <p class="text-gray-600 mt-2">Add, edit and organize your dishes</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-8 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-3">
                Add Product
            </button>
        </div>

        <?= $message ?>

        <!-- Filters -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-10">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or description..." class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <select name="category" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                    <option value="">All Categories</option>
                    <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $category_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="status" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                    <option value="">All Status</option>
                    <option value="Available" <?= $status_filter === 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Unavailable" <?= $status_filter === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <input type="number" step="0.01" name="min_price" value="<?= $min_price ?>" placeholder="Min Price" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <input type="number" step="0.01" name="max_price" value="<?= $max_price ?>" placeholder="Max Price" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <div class="lg:col-span-5 flex gap-4">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-10 rounded-xl flex items-center gap-3">Apply Filters</button>
                    <a href="manage_products.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-8 rounded-xl flex items-center gap-2">Clear</a>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if ($products->num_rows > 0): ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300">
                        <div class="h-56 bg-gray-100 relative">
                            <?php if ($p['image'] && file_exists(__DIR__ . '/../../' . $p['image'])): ?>
                                <img src="../../<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                    <i class="ti ti-photo text-6xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4">
                                <span class="px-4 py-2 rounded-full text-xs font-bold <?= $p['isAvailable'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $p['isAvailable'] === 'Available' ? 'Available' : 'Unavailable' ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($p['name']) ?></h3>
                            <?php if (!empty($p['description'])): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($p['description']) ?></p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm italic mb-4">No description</p>
                            <?php endif; ?>

                            <div class="flex justify-between items-center mb-5">
                                <span class="text-3xl font-bold text-emerald-600">$<?= number_format($p['price'], 2) ?></span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    <?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?>
                                </span>
                            </div>

                            <div class="flex gap-3">
                                <a href="?edit=<?= $p['id'] ?>&<?= $current_filters ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold text-center flex items-center justify-center gap-2 transition">Edit</a>
                                <a href="?delete=<?= $p['id'] ?>&<?= $current_filters ?>" onclick="return confirm('Delete <?= addslashes(htmlspecialchars($p['name'])) ?>?')" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl font-semibold text-center flex items-center justify-center gap-2 transition">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-20">
                    <i class="ti ti-package-off text-8xl text-gray-300 mb-6"></i>
                    <p class="text-2xl text-gray-500 font-semibold">No products found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add/Edit Modal -->
        <div id="addModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-screen overflow-y-auto p-8">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-3xl font-bold"><?= $edit_product ? 'Edit Product' : 'Add New Product' ?></h3>
                    <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        Close
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?? '' ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label class="block font-semibold mb-2">Product Name *</label>
                            <input type="text" name="name" required value="<?= $edit_product['name'] ?? '' ?>" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Price ($)*</label>
                            <input type="number" step="0.01" name="price" required value="<?= $edit_product['price'] ?? '' ?>" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Category *</label>
                            <select name="category_id" required class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                                <option value="">Choose category</option>
                                <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($edit_product && $edit_product['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Status</label>
                            <select name="isAvailable" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                                <option value="Available" <?= (!$edit_product || $edit_product['isAvailable'] === 'Available') ? 'selected' : '' ?>>Available</option>
                                <option value="Unavailable" <?= ($edit_product && $edit_product['isAvailable'] === 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold mb-2">Description</label>
                            <textarea name="description" rows="4" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none"><?= $edit_product['description'] ?? '' ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold mb-2">Product Image</label>
                            <input type="file" name="image" accept="image/*" class="w-full px-5 py-4 border-2 border-dashed border-gray-300 rounded-xl file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:bg-emerald-600 file:text-white">
                            <?php if ($edit_product && $edit_product['image']): ?>
                                <img src="../../<?= htmlspecialchars($edit_product['image']) ?>" class="mt-4 h-48 rounded-xl shadow-lg object-cover">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex gap-4 justify-end">
                        <button type="submit" name="save_product" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-12 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-3">
                            <?= $edit_product ? 'Update' : 'Add' ?> Product
                        </button>
                        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-10 rounded-xl">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        <?php if ($edit_product): ?>
            document.getElementById('addModal').classList.remove('hidden');
        <?php endif; ?>
    </script>
</body>
</html>