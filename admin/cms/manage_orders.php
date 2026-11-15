<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /first-PHP-learning/Assignment/website/index.php");
    exit;
}

require_once __DIR__ . '/../../conn.php';

// === SEARCH & FILTER LOGIC ===
$where = [];
$params = [];
$types = "";

// 1. Search keyword (Order ID, Name, Phone, Email)
if (!empty($_GET['q'])) {
    $q = "%" . trim($_GET['q']) . "%";
    $where[] = "(o.id LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)";
    $params[] = $q; $params[] = $q; $params[] = $q; $params[] = $q;
    $types .= "ssss";
}

// 2. Status filter
if (!empty($_GET['status']) && in_array($_GET['status'], ['Pending', 'Processing', 'Completed', 'Cancelled'])) {
    $where[] = "o.OrderStatus = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// 3. Date range filter
if (!empty($_GET['from'])) {
    $where[] = "DATE(o.OrderDate) >= ?";
    $params[] = $_GET['from'];
    $types .= "s";
}
if (!empty($_GET['to'])) {
    $where[] = "DATE(o.OrderDate) <= ?";
    $params[] = $_GET['to'];
    $types .= "s";
}

// Build final query
$sql = "SELECT o.*, DATE_FORMAT(o.OrderDate, '%d %b %Y %H:%i') AS formatted_date 
        FROM orders o";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY o.OrderDate DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Khmer&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Khmer', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white fixed h-full z-50 shadow-2xl">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold">Khmer Food</h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>
        <nav class="mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="block px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Dashboard</a>
            <a href="manage_categories.php" class="block px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Categories</a>
            <a href="manage_products.php" class="block px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Menu Items</a>
            <a href="manage_orders.php" class="block px-5 py-4 sidebar-active font-semibold rounded-l-xl">Orders</a>
        </nav>
        <div class="p-6 border-t border-emerald-700">
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold">Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-8">Manage Orders</h1>

        <!-- Success Message -->
        <?php if (isset($_SESSION['status_success'])): ?>
            <div class="mb-6 p-5 bg-green-100 border border-green-400 text-green-800 rounded-xl text-center font-medium">
                <?= $_SESSION['status_success'] ?> 
            </div>
            <?php unset($_SESSION['status_success']); ?>
        <?php endif; ?>

        <!-- Search & Filter Bar -->
        <form method="GET" class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                           placeholder="Search by ID, Name, Phone, Email..." 
                           class="w-full px-5 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- Status Filter -->
                <div>
                    <select name="status" class="w-full px-5 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">All Status</option>
                        <option value="Pending" <?= ($_GET['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= ($_GET['status'] ?? '') == 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Completed" <?= ($_GET['status'] ?? '') == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= ($_GET['status'] ?? '') == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <!-- From Date -->
                <div>
                    <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>" 
                           class="w-full px-5 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- To Date -->
                <div>
                    <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>" 
                           class="w-full px-5 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="mt-4 flex gap-3 justify-center lg:justify-end">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-xl font-bold shadow-md transition">
                    Apply Filters
                </button>
                <a href="manage_orders.php" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-xl font-bold shadow-md transition">
                    Clear
                </a>
            </div>
        </form>

        <!-- Orders Table -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-emerald-600 text-white">
                        <tr>
                            <th class="px-8 py-5 text-left">Order ID</th>
                            <th class="px-8 py-5 text-left">Customer</th>
                            <th class="px-8 py-5 text-left">Date</th>
                            <th class="px-8 py-5 text-left">Total</th>
                            <th class="px-8 py-5 text-left">Status</th>
                            <th class="px-8 py-5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($order = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-8 py-6 font-medium">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td class="px-8 py-6">
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6"><?= $order['formatted_date'] ?></td>
                                    <td class="px-8 py-6 font-bold text-emerald-600">$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td class="px-8 py-6">
                                        <form method="POST" action="update_status.php" class="inline-flex items-center gap-3">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="new_status" onchange="this.form.submit()" class="px-4 py-2 rounded-lg border text-sm font-bold cursor-pointer
                                                <?= $order['OrderStatus'] == 'Completed' ? 'bg-green-100 text-green-800' :
                                                   ($order['OrderStatus'] == 'Processing' ? 'bg-blue-100 text-blue-800' :
                                                   ($order['OrderStatus'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) ?>">
                                                <option value="Pending" <?= $order['OrderStatus'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Processing" <?= $order['OrderStatus'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                <option value="Completed" <?= $order['OrderStatus'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $order['OrderStatus'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <a href="view_order.php?id=<?= $order['id'] ?>" class="text-indigo-600 hover:underline font-bold">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-16 text-gray-500 text-xl">
                                    No orders found matching your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>