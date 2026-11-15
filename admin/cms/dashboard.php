<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

require_once __DIR__ . '/../../conn.php';

// === Fetch Statistics ===
$stats = [
    'categories' => $conn->query("SELECT COUNT(*) FROM categories")->fetch_row()[0] ?? 0,
    'products'   => $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0] ?? 0,
    'orders'     => $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0] ?? 0,
    'users'      => $conn->query("SELECT COUNT(*) FROM admin_users")->fetch_row()[0] ?? 0,
    'revenue_today' => $conn->query("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM orders 
        WHERE DATE(OrderDate) = CURDATE() AND OrderStatus = 'Completed'
    ")->fetch_row()[0] ?? 0
];

// === Last 7 Days Orders Chart ===
$labels = $values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime("-$i days"));
    $count = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(OrderDate) = '$date'")->fetch_row()[0] ?? 0;
    $values[] = (int)$count;
}

// === Products by Category (Doughnut Chart) ===
$catResult = $conn->query("
    SELECT c.name, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id  
    ORDER BY product_count DESC
");

$catLabels = $catData = [];
$colors = ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#f97316','#6366f1','#14b8a6'];
while ($row = $catResult->fetch_assoc()) {
    $catLabels[] = $row['name'] ?: 'Uncategorized';
    $catData[] = (int)$row['product_count'];
}

// === Recent Orders ===
$recentOrders = $conn->query("
    SELECT id, customer_name, total_amount, OrderStatus, 
           DATE_FORMAT(OrderDate, '%d %b %Y %H:%i') AS formatted_date 
    FROM orders 
    ORDER BY OrderDate DESC 
    LIMIT 6
");
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Khmer Food Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
        .card-hover:hover { transform: translateY(-10px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); transition: all 0.3s; }
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
            <a href="dashboard.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl">
                <i class="ti ti-layout-dashboard mr-4 text-xl"></i> Dashboard
            </a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">
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
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-800">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</h2>
            <p class="text-gray-600 mt-2 text-lg">Today is <?= date('l, d F Y') ?> • Phnom Penh, Cambodia</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="bg-gradient-to-br from-blue-600 to-blue-800 text-white rounded-3xl shadow-xl p-8 card-hover">
                <i class="ti ti-category text-5xl opacity-30 mb-4"></i>
                <p class="text-blue-100 text-lg">Total Categories</p>
                <p class="text-5xl font-extrabold mt-3"><?= number_format($stats['categories']) ?></p>
            </div>
            <div class="bg-gradient-to-br from-emerald-600 to-green-700 text-white rounded-3xl shadow-xl p-8 card-hover">
                <i class="ti ti-package text-5xl opacity-30 mb-4"></i>
                <p class="text-emerald-100 text-lg">Menu Items</p>
                <p class="text-5xl font-extrabold mt-3"><?= number_format($stats['products']) ?></p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-orange-600 text-white rounded-3xl shadow-xl p-8 card-hover">
                <i class="ti ti-receipt text-5xl opacity-30 mb-4"></i>
                <p class="text-amber-100 text-lg">Total Orders</p>
                <p class="text-5xl font-extrabold mt-3"><?= number_format($stats['orders']) ?></p>
            </div>
            <div class="bg-gradient-to-br from-purple-600 to-pink-600 text-white rounded-3xl shadow-xl p-8 card-hover">
                <i class="ti ti-currency-dollar text-5xl opacity-30 mb-4"></i>
                <p class="text-purple-100 text-lg">Today's Revenue</p>
                <p class="text-5xl font-extrabold mt-3">$<?= number_format($stats['revenue_today'], 2) ?></p>
            </div>
        </div>

        <!-- Charts + Recent Orders -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-12">
            <!-- Orders Chart -->
            <div class="xl:col-span-2 bg-white rounded-3xl shadow-xl p-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <i class="ti ti-trending-up text-green-600"></i> Orders - Last 7 Days
                </h3>
                <div id="orderChart" class="h-96"></div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-3xl shadow-xl p-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <i class="ti ti-clock text-blue-600"></i> Recent Orders
                </h3>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
                        <?php while ($order = $recentOrders->fetch_assoc()): ?>
                        <div class="border-b pb-4 last:border-0 hover:bg-gray-50 rounded-lg p-3 transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold">#<?= $order['id'] ?> - <?= htmlspecialchars($order['customer_name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= $order['formatted_date'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-lg">$<?= number_format($order['total_amount'], 2) ?></p>
                                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full mt-1
                                        <?= $order['OrderStatus'] == 'Completed' ? 'bg-green-100 text-green-800' : 
                                           ($order['OrderStatus'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= $order['OrderStatus'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No orders yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Category Chart -->
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                <i class="ti ti-pie-chart text-purple-600"></i> Products by Category
            </h3>
            <div class="max-w-3xl mx-auto">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <footer class="mt-16 text-center text-gray-500 text-sm">
            © <?= date('Y') ?> Khmer Food Restaurant • Made with <i class="ti ti-heart text-red-500"></i> in Phnom Penh
        </footer>
    </div>

    <!-- Charts Scripts -->
    <script>
        // Orders Chart
        new ApexCharts(document.querySelector("#orderChart"), {
            series: [{ name: 'Orders', data: <?= json_encode($values) ?> }],
            chart: { type: 'area', height: 380, toolbar: { show: false }, animations: { enabled: true } },
            colors: ['#10b981'],
            stroke: { curve: 'smooth', width: 4 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.1 }},
            xaxis: { categories: <?= json_encode($labels) ?>, labels: { style: { fontSize: '14px' }}},
            yaxis: { labels: { formatter: val => Math.round(val) }},
            grid: { borderColor: '#e2e8f0', strokeDashArray: 6 },
            tooltip: { theme: 'dark' }
        }).render();

        // Category Doughnut Chart
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catData) ?>,
                    backgroundColor: <?= json_encode(array_slice($colors, 0, count($catData))) ?>,
                    borderColor: '#fff',
                    borderWidth: 5,
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 25, font: { size: 14 }, usePointStyle: true }},
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw} items` }}
                }
            }
        });
    </script>
</body>
</html>