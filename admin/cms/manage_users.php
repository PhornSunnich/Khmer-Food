<?php
require_once __DIR__ . '/../../security.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}
if (($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}
require_once __DIR__ . '/../../conn.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }

$myId = (int)($_SESSION['user_id'] ?? 0);

// ================== POST ACTIONS (delete / toggle status) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'សំណើមិនត្រឹមត្រូវ សូមព្យាយាមម្តងទៀត។');
    } else {
        $uid = (int)($_POST['user_id'] ?? 0);

        // Load target user
        $q = $conn->prepare("SELECT id, role, status FROM admin_users WHERE id = ?");
        $q->bind_param("i", $uid);
        $q->execute();
        $target = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$target) {
            flash('error', 'រកមិនឃើញអ្នកប្រើប្រាស់នេះទេ។');
        } elseif ($uid === $myId) {
            flash('error', 'អ្នកមិនអាចធ្វើសកម្មភាពនេះលើគណនីខ្លួនឯងបានទេ។');
        } else {
            try {
                // Never remove / disable the last active admin
                $isLastAdmin = false;
                if ($target['role'] === 'admin') {
                    $cnt = (int)$conn->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND status = 1")->fetch_row()[0];
                    $isLastAdmin = ($cnt <= 1 && (int)$target['status'] === 1);
                }

                if (isset($_POST['delete_user'])) {
                    if ($isLastAdmin) {
                        flash('error', 'មិនអាចលុប admin ចុងក្រោយបានទេ។');
                    } else {
                        $d = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
                        $d->bind_param("i", $uid);
                        $d->execute();
                        $d->close();
                        flash('success', 'បានលុបអ្នកប្រើប្រាស់ដោយជោគជ័យ។');
                    }
                } elseif (isset($_POST['toggle_status'])) {
                    if ($isLastAdmin) {
                        flash('error', 'មិនអាចបិទ admin ចុងក្រោយបានទេ។');
                    } else {
                        $new = ((int)$target['status'] === 1) ? 0 : 1;
                        $u = $conn->prepare("UPDATE admin_users SET status = ? WHERE id = ?");
                        $u->bind_param("ii", $new, $uid);
                        $u->execute();
                        $u->close();
                        flash('success', $new === 1 ? 'បានអនុម័តគណនី (សកម្ម)។' : 'បានបិទគណនី (អសកម្ម)។');
                    }
                }
            } catch (mysqli_sql_exception $ex) {
                error_log("manage_users: " . $ex->getMessage());
                flash('error', 'មានបញ្ហាក្នុងការដំណើរការ សូមព្យាយាមម្តងទៀត។');
            }
        }
    }
    header("Location: manage_users.php");
    exit();
}

// ================== SEARCH & FILTER ==================
$search = trim($_GET['s'] ?? '');
$role   = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';

$sql = "SELECT id, fullname, email, role, status FROM admin_users WHERE 1=1";
$params = []; $types = '';
if ($search !== '') {
    $sql .= " AND (fullname LIKE ? OR email LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
if (in_array($role, ['admin', 'staff'], true)) {
    $sql .= " AND role = ?"; $params[] = $role; $types .= 's';
}
if ($status === '1' || $status === '0') {
    $sql .= " AND status = ?"; $params[] = (int)$status; $types .= 'i';
}
$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="km" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងអ្នកប្រើប្រាស់ | Khmer Food Admin</title>
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
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl fixed h-full z-50 flex flex-col">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold flex items-center justify-center gap-3"><i class="ti ti-bowl text-3xl"></i> Khmer Food</h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>
        <nav class="flex-1 mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-layout-dashboard mr-4 text-xl"></i> Dashboard</a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-category mr-4 text-xl"></i> Categories</a>
            <a href="manage_products.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-package mr-4 text-xl"></i> Menu Items</a>
            <a href="manage_orders.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition"><i class="ti ti-shopping-cart mr-4 text-xl"></i> Orders</a>
            <a href="manage_users.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl"><i class="ti ti-users mr-4 text-xl"></i> Manage Users</a>
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

    <!-- Main -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center gap-3"><i class="ti ti-users text-emerald-600"></i> គ្រប់គ្រងអ្នកប្រើប្រាស់ប្រព័ន្ធ</h2>
            <a href="addUser.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-2">
                <i class="ti ti-plus"></i> បន្ថែមអ្នកប្រើ
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="mb-6 px-6 py-4 rounded-xl border <?= $flash['type'] === 'success' ? 'bg-green-100 border-green-400 text-green-800' : 'bg-red-100 border-red-400 text-red-700' ?>">
                <?= e($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="s" value="<?= e($search) ?>" placeholder="ស្វែងរកឈ្មោះ ឬអ៊ីមែល..."
                       class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-emerald-500/40 outline-none">
                <select name="role" class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-emerald-500/40 outline-none">
                    <option value="all">តួនាទីទាំងអស់</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>អ្នកគ្រប់គ្រង</option>
                    <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>បុគ្គលិក</option>
                </select>
                <select name="status" class="px-5 py-3 border rounded-xl focus:ring-4 focus:ring-emerald-500/40 outline-none">
                    <option value="all">ស្ថានភាពទាំងអស់</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>សកម្ម</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>អសកម្ម</option>
                </select>
                <div class="flex gap-3">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3 rounded-xl">ស្វែងរក</button>
                    <a href="manage_users.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold px-6 py-3 rounded-xl">សម្អាត</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-emerald-600 to-green-700 text-white">
                    <tr>
                        <th class="px-6 py-4 text-left">ល.រ</th>
                        <th class="px-6 py-4 text-left">ឈ្មោះពេញ</th>
                        <th class="px-6 py-4 text-left">អ៊ីមែល</th>
                        <th class="px-6 py-4 text-center">តួនាទី</th>
                        <th class="px-6 py-4 text-center">ស្ថានភាព</th>
                        <th class="px-6 py-4 text-center">សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php $no = 1; if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): $isMe = ((int)$row['id'] === $myId); ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4"><?= $no++ ?></td>
                        <td class="px-6 py-4 font-medium">
                            <?= e($row['fullname']) ?>
                            <?php if ($isMe): ?><span class="ml-2 text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full">អ្នក</span><?php endif; ?>
                        </td>
                        <td class="px-6 py-4"><?= e($row['email']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-4 py-2 rounded-full text-white font-bold text-sm <?= $row['role'] === 'admin' ? 'bg-red-600' : 'bg-blue-600' ?>">
                                <?= $row['role'] === 'admin' ? 'អ្នកគ្រប់គ្រង' : 'បុគ្គលិក' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-4 py-2 rounded-full text-white font-bold text-sm <?= (int)$row['status'] === 1 ? 'bg-green-600' : 'bg-gray-500' ?>">
                                <?= (int)$row['status'] === 1 ? 'សកម្ម' : 'អសកម្ម' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="addUser.php?edit=<?= (int)$row['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium transition">កែ</a>
                                <?php if (!$isMe): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" name="toggle_status" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium transition">
                                        <?= (int)$row['status'] === 1 ? 'បិទ' : 'អនុម័ត' ?>
                                    </button>
                                </form>
                                <form method="POST" class="inline" onsubmit="return confirm('លុបមែនទេ?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" name="delete_user" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition">លុប</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="px-6 py-16 text-center text-gray-500">រកមិនឃើញអ្នកប្រើប្រាស់ទេ។</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>