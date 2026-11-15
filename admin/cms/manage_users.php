<?php 
session_start(); 
require '../../conn.php'; 
if(!isset($_SESSION['login'])) header("Location: ../../index.php");

// Search & Filter
$search = $_GET['s'] ?? '';
$role = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM admin_users WHERE 1=1";
if($search) {
    $search = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (fullname LIKE '%$search%' OR username LIKE '%$search%')";
}
if($role !== 'all') $sql .= " AND role = '$role'";
if($status !== 'all') $sql .= $status == '1' ? " AND status = 1" : " AND status = 0";
$sql .= " ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="km" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងអ្នកប្រើប្រាស់ - ម្ហូបខ្មែរ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex">
    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-green-700 to-green-900 text-white shadow-2xl">
        <div class="p-6 text-center border-b border-green-800">
            <h1 class="text-2xl font-bold">ម្ហូបខ្មែរ</h1>
        </div>
        <nav class="mt-6">
            <a href="dashboard.php" class="flex items-center px-6 py-4 hover:bg-green-800 transition">ផ្ទាំងដើម</a>
            <a href="manage_categories.php" class="flex items-center px-6 py-4 hover:bg-green-800 transition">ប្រភេទម្ហូប</a>
            <a href="manage_products.php" class="flex items-center px-6 py-4 hover:bg-green-800 transition">មុឺនុយម្ហូប</a>
            <a href="manage_orders.php" class="flex items-center px-6 py-4 hover:bg-green-800 transition">កម្មង់ទំនិញ</a>
            <a href="manage_users.php" class="flex items-center px-6 py-4 bg-green-800 border-l-4 border-yellow-400 font-semibold">អ្នកប្រើប្រាស់</a>
        </nav>
    </div>

    <div class="flex-1 p-8">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">គ្រប់គ្រងអ្នកប្រើប្រាស់ប្រព័ន្ធ</h2>
            <a href="addUser.php" class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl shadow-lg transition transform hover:scale-105">
                បន្ថែមអ្នកប្រើ
            </a>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="s" value="<?= htmlspecialchars($search) ?>" placeholder="ស្វែងរកឈ្មោះ ឬ username..." 
                       class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-green-500">
                <select name="role" class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-green-500">
                    <option value="all">តួនាទីទាំងអស់</option>
                    <option value="admin" <?= $role=='admin'?'selected':'' ?>>អ្នកគ្រប់គ្រង</option>
                    <option value="staff" <?= $role=='staff'?'selected':'' ?>>បុគ្គលិក</option>
                </select>
                <select name="status" class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-green-500">
                    <option value="all">ស្ថានភាពទាំងអស់</option>
                    <option value="1" <?= $status=='1'?'selected':'' ?>>សកម្ម</option>
                    <option value="0" <?= $status=='0'?'selected':'' ?>>អសកម្ម</option>
                </select>
                <div class="flex gap-3">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl">ស្វែងរក</button>
                    <a href="manage_users.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold px-6 py-3 rounded-xl">សម្អាត</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-green-600 to-emerald-700 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left">ល.រ</th>
                        <th class="px-6 py-4 text-left">ឈ្មោះពេញ</th>
                        <th class="px-6 py-4 text-left">ឈ្មោះអ្នកប្រើ</th>
                        <th class="px-6 py-4 text-center">តួនាទី</th>
                        <th class="px-6 py-4 text-center">ស្ថានភាព</th>
                        <th class="px-6 py-4 text-center">សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php $no=1; while($row=mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4"><?= $no++ ?></td>
                        <td class="px-6 py-4 font-medium"><?= htmlspecialchars($row['fullname']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($row['username']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-4 py-2 rounded-full text-white font-bold text-sm <?= $row['role']=='admin'?'bg-red-600':'bg-blue-600' ?>">
                                <?= $row['role']=='admin'?'អ្នកគ្រប់គ្រង':'បុគ្គលិក' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-4 py-2 rounded-full text-white font-bold text-sm <?= $row['status']==1?'bg-green-600':'bg-gray-500' ?>">
                                <?= $row['status']==1?'សកម្ម':'អសកម្ម' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center space-x-3">
                            <a href="addUser.php?edit=<?= $row['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium transition">កែ</a>
                            <?php if($row['id'] > 1): // កុំឱ្យលុប admin ដើម ?>
                            <a href="addUser.php?delete=<?= $row['id'] ?>" onclick="return confirm('លុបមែនទេ?')" 
                               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition">លុប</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>