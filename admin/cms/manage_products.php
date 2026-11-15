<?php
require_once __DIR__ . '/../../security.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}
require_once __DIR__ . '/../../conn.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }

$uploadDirAbs = __DIR__ . '/../../assets/images/products/';

// === FILTERS & SEARCH ===
$search          = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$status_filter   = $_GET['status'] ?? '';
$min_price       = $_GET['min_price'] ?? '';
$max_price       = $_GET['max_price'] ?? '';

$min_price = ($min_price !== '' && is_numeric($min_price)) ? (float)$min_price : '';
$max_price = ($max_price !== '' && is_numeric($max_price)) ? (float)$max_price : '';
if (!in_array($status_filter, ['Available', 'Unavailable'], true)) $status_filter = '';

$where = []; $params = []; $types = '';
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $types .= "ss";
}
if ($category_filter !== '' && is_numeric($category_filter)) {
    $where[] = "p.category_id = ?"; $params[] = (int)$category_filter; $types .= "i";
}
if ($status_filter !== '') {
    $where[] = "p.isAvailable = ?"; $params[] = $status_filter; $types .= "s";
}
if ($min_price !== '') { $where[] = "p.price >= ?"; $params[] = $min_price; $types .= "d"; }
if ($max_price !== '') { $where[] = "p.price <= ?"; $params[] = $max_price; $types .= "d"; }
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$current_filters = http_build_query(array_filter([
    'search'    => $search,
    'category'  => $category_filter,
    'status'    => $status_filter,
    'min_price' => $min_price,
    'max_price' => $max_price
], fn($v) => $v !== '' && $v !== null));

// === POST: SAVE / DELETE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request. Please try again.');
        header("Location: manage_products.php");
        exit();
    }

    try {
        // ---------- SAVE PRODUCT ----------
        if (isset($_POST['save_product'])) {
            $id          = (int)($_POST['product_id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price       = (float)($_POST['price'] ?? 0);
            $category_id = (int)($_POST['category_id'] ?? 0);
            $isAvailable = ($_POST['isAvailable'] ?? 'Available') === 'Unavailable' ? 'Unavailable' : 'Available';

            if ($name === '' || $price <= 0 || $category_id <= 0) {
                flash('error', 'Please fill all required fields!');
            } else {
                // Current image of this product (when editing)
                $image_path = '';
                if ($id > 0) {
                    $q = $conn->prepare("SELECT image FROM products WHERE id = ?");
                    $q->bind_param("i", $id);
                    $q->execute();
                    $image_path = $q->get_result()->fetch_assoc()['image'] ?? '';
                    $q->close();
                }

                // New image upload (validated)
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $okExt  = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                    $okSize = $_FILES['image']['size'] <= 5 * 1024 * 1024;
                    $okImg  = @getimagesize($_FILES['image']['tmp_name']) !== false;

                    if ($okExt && $okSize && $okImg) {
                        if (!is_dir($uploadDirAbs)) mkdir($uploadDirAbs, 0755, true);
                        $new_name = uniqid('prod_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDirAbs . $new_name)) {
                            if ($image_path && file_exists(__DIR__ . '/../../' . $image_path)) {
                                @unlink(__DIR__ . '/../../' . $image_path);
                            }
                            $image_path = 'assets/images/products/' . $new_name;
                        }
                    } else {
                        flash('error', 'Image must be JPG, PNG or WEBP and under 5 MB.');
                        header("Location: manage_products.php");
                        exit();
                    }
                }

                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE products SET name=?, description=?, price=?, category_id=?, image=?, isAvailable=? WHERE id=?");
                    $stmt->bind_param("ssdissi", $name, $description, $price, $category_id, $image_path, $isAvailable, $id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, image, isAvailable) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssdiss", $name, $description, $price, $category_id, $image_path, $isAvailable);
                }
                $stmt->execute();
                $stmt->close();
                flash('success', 'Product saved successfully!');
            }
        }

        // ---------- DELETE PRODUCT ----------
        if (isset($_POST['delete_product'])) {
            $id = (int)($_POST['product_id'] ?? 0);

            $q = $conn->prepare("SELECT image FROM products WHERE id = ?");
            $q->bind_param("i", $id);
            $q->execute();
            $img = $q->get_result()->fetch_assoc()['image'] ?? '';
            $q->close();

            $d = $conn->prepare("DELETE FROM products WHERE id = ?");
            $d->bind_param("i", $id);
            $d->execute();   // throws if the product is used in order_items (foreign key)
            $d->close();

            if ($img && file_exists(__DIR__ . '/../../' . $img)) @unlink(__DIR__ . '/../../' . $img);
            flash('success', 'Product deleted successfully!');
        }
    } catch (mysqli_sql_exception $ex) {
        error_log("manage_products: " . $ex->getMessage());
        if ((int)$ex->getCode() === 1451) {
            flash('error', 'Cannot delete: this product is used in existing orders. Set it to Unavailable instead.');
        } else {
            flash('error', 'Something went wrong. Please try again.');
        }
    }

    header("Location: manage_products.php" . ($current_filters ? '?' . $current_filters : ''));
    exit();
}

// === FETCH DATA ===
$categories = [];
$cres = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($c = $cres->fetch_assoc()) $categories[] = $c;

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
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $q = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $q->bind_param("i", $eid);
    $q->execute();
    $edit_product = $q->get_result()->fetch_assoc() ?: null;
    $q->close();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Khmer:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>
        body { font-family: 'Inter', 'Noto Sans Khmer', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl fixed h-full z-40 flex flex-col">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold flex items-center justify-center gap-3"><i class="ti ti-bowl text-3xl"></i> Khmer Food</h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>
        <nav class="flex-1 mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-layout-dashboard mr-4 text-xl"></i> Dashboard</a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-category mr-4 text-xl"></i> Categories</a>
            <a href="manage_products.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl"><i class="ti ti-package mr-4 text-xl"></i> Menu Items</a>
            <a href="manage_orders.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-shopping-cart mr-4 text-xl"></i> Orders</a>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <a href="manage_users.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-users mr-4 text-xl"></i> Manage Users</a>
            <?php endif; ?>
        </nav>
        <div class="p-6 border-t border-emerald-700">
            <div class="flex items-center mb-4">
                <i class="ti ti-user-circle text-4xl mr-3"></i>
                <div>
                    <p class="font-semibold text-lg"><?= e($_SESSION['username'] ?? 'User') ?></p>
                    <p class="text-emerald-300 text-sm"><?= e($_SESSION['role'] ?? 'Staff') ?></p>
                </div>
            </div>
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold transition hover:scale-105"><i class="ti ti-logout mr-2"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <div class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-4xl font-bold text-gray-800">Manage Menu Items</h2>
                <p class="text-gray-600 mt-2">Add, edit and organize your dishes</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-8 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-3">
                <i class="ti ti-plus"></i> Add Product
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="mb-6 px-6 py-4 rounded-xl border <?= $flash['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-800' : 'bg-red-100 border-red-400 text-red-700' ?>">
                <?= e($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-10">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name or description..." class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <select name="category" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (string)$category_filter === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                    <option value="">All Status</option>
                    <option value="Available" <?= $status_filter === 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Unavailable" <?= $status_filter === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <input type="number" step="0.01" name="min_price" value="<?= e($min_price) ?>" placeholder="Min Price" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <input type="number" step="0.01" name="max_price" value="<?= e($max_price) ?>" placeholder="Max Price" class="px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                <div class="lg:col-span-5 flex gap-4">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-10 rounded-xl">Apply Filters</button>
                    <a href="manage_products.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-8 rounded-xl">Clear</a>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if ($products->num_rows > 0): ?>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300">
                        <div class="h-56 bg-gray-100 relative">
                            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../../' . $p['image'])): ?>
                                <img src="../../<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-200"><i class="ti ti-photo text-6xl text-gray-400"></i></div>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4">
                                <span class="px-4 py-2 rounded-full text-xs font-bold <?= $p['isAvailable'] === 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= e($p['isAvailable']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-2"><?= e($p['name']) ?></h3>
                            <?php if (!empty($p['description'])): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= e($p['description']) ?></p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm italic mb-4">No description</p>
                            <?php endif; ?>
                            <div class="flex justify-between items-center mb-5">
                                <span class="text-3xl font-bold text-emerald-600">$<?= number_format($p['price'], 2) ?></span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><?= e($p['category_name'] ?? 'Uncategorized') ?></span>
                            </div>
                            <div class="flex gap-3">
                                <a href="?edit=<?= (int)$p['id'] ?><?= $current_filters ? '&' . e($current_filters) : '' ?>"
                                   class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold text-center transition">Edit</a>
                                <form method="POST" class="flex-1" onsubmit="return confirm('Delete <?= e(addslashes($p['name'])) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" name="delete_product" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl font-semibold transition">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-20">
                    <i class="ti ti-package-off text-8xl text-gray-300 mb-6 block"></i>
                    <p class="text-2xl text-gray-500 font-semibold">No products found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add/Edit Modal -->
        <div id="addModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-screen overflow-y-auto p-8">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-3xl font-bold"><?= $edit_product ? 'Edit Product' : 'Add New Product' ?></h3>
                    <?php if ($edit_product): ?>
                        <a href="manage_products.php<?= $current_filters ? '?' . e($current_filters) : '' ?>" class="text-gray-500 hover:text-gray-700">Close</a>
                    <?php else: ?>
                        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">Close</button>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="product_id" value="<?= e($edit_product['id'] ?? '') ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label class="block font-semibold mb-2">Product Name *</label>
                            <input type="text" name="name" required maxlength="150" value="<?= e($edit_product['name'] ?? '') ?>" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Price ($)*</label>
                            <input type="number" step="0.01" min="0.01" name="price" required value="<?= e($edit_product['price'] ?? '') ?>" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-semibold mb-2">Category *</label>
                            <select name="category_id" required class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none">
                                <option value="">Choose category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= ($edit_product && (int)$edit_product['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
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
                            <textarea name="description" rows="4" class="w-full px-5 py-4 border-2 border-gray-300 rounded-xl focus:border-emerald-500 outline-none"><?= e($edit_product['description'] ?? '') ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold mb-2">Product Image (JPG, PNG, WEBP, max 5 MB)</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full px-5 py-4 border-2 border-dashed border-gray-300 rounded-xl file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:bg-emerald-600 file:text-white">
                            <?php if ($edit_product && !empty($edit_product['image'])): ?>
                                <img src="../../<?= e($edit_product['image']) ?>" alt="" class="mt-4 h-48 rounded-xl shadow-lg object-cover">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex gap-4 justify-end">
                        <button type="submit" name="save_product" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 px-12 rounded-xl shadow-lg transition hover:scale-105">
                            <?= $edit_product ? 'Update' : 'Add' ?> Product
                        </button>
                        <?php if ($edit_product): ?>
                            <a href="manage_products.php<?= $current_filters ? '?' . e($current_filters) : '' ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-10 rounded-xl">Cancel</a>
                        <?php else: ?>
                            <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-10 rounded-xl">Cancel</button>
                        <?php endif; ?>
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