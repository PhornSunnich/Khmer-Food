<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /first-PHP-learning/Assignment/website/index.php");
    exit;
}

require_once __DIR__ . '/../../conn.php';

// ==================== PDF EXPORT WITH QR CODE & KHMER SUPPORT ====================
if (isset($_GET['export_pdf']) && $_GET['export_pdf'] == 1 && isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];

    // Fetch order
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) die("Order not found.");

    // Fetch items
    $stmt = $conn->prepare("
        SELECT oi.*, p.name, p.price AS unit_price 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result();
    $stmt->close();

    require_once __DIR__ . '/tcpdf/tcpdf.php';

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetTitle('Invoice #' . str_pad($order_id, 6, '0', STR_PAD_LEFT));
    $pdf->SetMargins(12, 15, 12);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();

    // === KHMER FONT (Works on ALL TCPDF versions) ===
    $font_path = __DIR__ . '/tcpdf/fonts/NotoSansKhmer-Regular.ttf';
    $khmer_font = 'helvetica'; // fallback
    if (file_exists($font_path)) {
        if (method_exists($pdf, 'addTTFfont')) {
            $pdf->addTTFfont($font_path, 'TrueTypeUnicode', '', 32);
            $khmer_font = 'notosanskhmer';
        } elseif (method_exists($pdf, 'addTTFfont')) {
            $pdf->addTTFfont($font_path, 'TrueTypeUnicode', '', 32);
            $khmer_font = 'notosanskhmer';
        }
    }

    // === HEADER ===
    $pdf->SetFillColor(34, 139, 34);
    $pdf->Rect(0, 0, 210, 38, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($khmer_font, 'B', 28);
    $pdf->Cell(0, 12, 'Khmer Food', 0, 1, 'L');
    $pdf->SetFont($khmer_font, '', 11);
    $pdf->Cell(0, 6, 'Restaurant & Delivery', 0, 1, 'L');
    $pdf->SetFont($khmer_font, '', 9);
    $pdf->Cell(0, 6, 'Street 123, Phnom Penh | Tel: 071 858 7895', 0, 1, 'L');
    $pdf->Ln(2);

    // Invoice Info
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($khmer_font, 'B', 22);
    $pdf->Cell(0, 8, 'INVOICE', 0, 1, 'R');
    $pdf->SetFont($khmer_font, '', 11);
    $pdf->Cell(0, 6, 'No: #' . str_pad($order_id, 6, '0', STR_PAD_LEFT), 0, 1, 'R');
    $pdf->Cell(0, 6, 'Date: ' . date('d M Y H:i', strtotime($order['OrderDate'])), 0, 1, 'R');
    $pdf->Ln(8);

    // Customer Info
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont($khmer_font, 'B', 11);
    $pdf->Cell(0, 6, 'Customer:', 0, 1);
    $pdf->SetFont($khmer_font, '', 11);
    $pdf->Cell(0, 6, $order['customer_name'], 0, 1);
    if (!empty($order['customer_phone'])) {
        $pdf->SetFont($khmer_font, '', 10);
        $pdf->Cell(0, 5, 'Phone: ' . $order['customer_phone'], 0, 1);
    }
    if (!empty($order['customer_email'])) {
        $pdf->SetFont($khmer_font, '', 10);
        $pdf->Cell(0, 5, 'Email: ' . $order['customer_email'], 0, 1);
    }
    if (!empty($order['customer_address'])) {
        $pdf->SetFont($khmer_font, '', 10);
        $pdf->MultiCell(0, 5, 'Address: ' . $order['customer_address'], 0, 'L');
    }
    $pdf->Ln(6);

    // Items Table
    $pdf->SetFillColor(34, 139, 34);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($khmer_font, 'B', 10);
    $pdf->Cell(90, 8, 'Item', 1, 0, 'L', true);
    $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Price', 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'Total', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont($khmer_font, '', 10);
    while ($item = $items->fetch_assoc()) {
        $total = $item['unit_price'] * $item['quantity'];
        $item_name = mb_substr($item['name'], 0, 38);
        $pdf->Cell(90, 7, $item_name, 1, 0, 'L');
        $pdf->Cell(20, 7, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 7, '$' . number_format($item['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(35, 7, '$' . number_format($total, 2), 1, 1, 'R');
    }

    // Grand Total
    $pdf->Ln(5);
    $pdf->SetFillColor(34, 139, 34);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont($khmer_font, 'B', 16);
    $pdf->Cell(140, 12, 'GRAND TOTAL', 0, 0, 'R', true);
    $pdf->Cell(35, 12, '$' . number_format($order['total_amount'], 2), 0, 1, 'R', true);

    // === QR CODE ===
    $pdf->Ln(10);
    $qr_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/view_order.php?id=" . $order_id;
    $pdf->write2DBarcode($qr_url, 'QRCODE,Q', 150, $pdf->GetY(), 40, 40, ['position' => 'R'], 'N');
    $pdf->SetY($pdf->GetY() + 42);
    $pdf->SetFont($khmer_font, '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Scan to view order online', 0, 1, 'R');

    // Footer
    $pdf->Ln(8);
    $pdf->SetFont($khmer_font, 'I', 10);
    $pdf->Cell(0, 6, 'Thank you for your order!', 0, 1, 'C');

    $pdf->Output('Invoice_' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '.pdf', 'D');
    exit;
}

// ==================== NORMAL HTML VIEW ====================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}

$order_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT o.*, DATE_FORMAT(o.OrderDate, '%d %b %Y %H:%i') AS formatted_date 
    FROM orders o WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: manage_orders.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.price AS unit_price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?> | Khmer Food</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Khmer&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Khmer', sans-serif; }
        .sidebar-active { background: rgba(255,255,255,0.1); border-left: 5px solid #fbbf24; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gradient-to-b from-emerald-800 via-emerald-900 to-emerald-950 text-white shadow-2xl fixed h-full z-50">
        <div class="p-6 text-center border-b border-emerald-700">
            <h1 class="text-2xl font-bold">Khmer Food</h1>
            <p class="text-emerald-200 text-sm mt-1">Admin Panel</p>
        </div>
        <nav class="flex-1 mt-6 space-y-1 px-4">
            <a href="dashboard.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Dashboard</a>
            <a href="manage_categories.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Categories</a>
            <a href="manage_products.php" class="flex items-center px-5 py-4 hover:bg-emerald-800 rounded-l-xl transition">Menu Items</a>
            <a href="manage_orders.php" class="flex items-center px-5 py-4 sidebar-active font-semibold rounded-l-xl">Orders</a>
        </nav>
        <div class="p-6 border-t border-emerald-700">
            <a href="../../logout.php" class="block text-center bg-red-600 hover:bg-red-700 py-3 rounded-xl font-bold">Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-8 lg:p-12">
        <div class="mb-10 flex justify-between items-center">
            <div>
                <h2 class="text-4xl font-bold text-gray-800">Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></h2>
                <p class="text-gray-600 mt-2 text-lg">Placed on <?= $order['formatted_date'] ?></p>
            </div>
            <div class="flex gap-4">
                <a href="manage_orders.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-8 rounded-xl transition">
                    Back to Orders
                </a>
                <a href="?id=<?= $order_id ?>&export_pdf=1" class="bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-8 rounded-xl shadow-lg transition hover:scale-105 flex items-center gap-3">
                    Export PDF
                </a>
            </div>
        </div>

        <!-- Beautiful Invoice -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-5xl mx-auto overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-green-700 text-white p-12">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-6xl font-bold mb-2">Khmer Food</h1>
                        <p class="text-emerald-100 text-xl">Restaurant & Delivery</p>
                        <div class="mt-8 opacity-90">
                            <p>Street 123, Phnom Penh</p>
                            <p>Tel: 071 858 7895 | khmerfood@gmail.com</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <h2 class="text-5xl font-bold">INVOICE</h2>
                        <p class="text-3xl mt-4">#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></p>
                        <p class="mt-3 text-xl">Date: <?= $order['formatted_date'] ?></p>
                    </div>
                </div>
            </div>

            <div class="p-12 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-6">Bill To:</h3>
                        <p class="text-2xl font-bold"><?= htmlspecialchars($order['customer_name']) ?></p>
                        <?php if (!empty($order['customer_phone'])): ?>
                            <p class="text-lg text-gray-700 mt-3">Phone: <?= htmlspecialchars($order['customer_phone']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_email'])): ?>
                            <p class="text-lg text-gray-700">Email: <?= htmlspecialchars($order['customer_email']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_address'])): ?>
                            <div class="mt-6">
                                <p class="font-bold text-gray-800">Delivery Address:</p>
                                <p class="text-gray-700 mt-2 leading-relaxed"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <p class="text-xl"><strong>Payment Method:</strong> <?= htmlspecialchars($order['PaymentMethod']) ?></p>
                        <div class="mt-8 inline-block">
                            <span class="px-10 py-5 rounded-full text-2xl font-bold
                                <?= $order['OrderStatus'] == 'Completed' ? 'bg-green-100 text-green-800' :
                                   ($order['OrderStatus'] == 'Processing' ? 'bg-blue-100 text-blue-800' :
                                   ($order['OrderStatus'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) ?>">
                                <?= $order['OrderStatus'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-12">
                <h3 class="text-3xl font-bold text-gray-800 mb-8">Order Items</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-5 font-bold text-gray-700 text-lg">Item</th>
                            <th class="text-center py-5 font-bold text-gray-700 text-lg">Qty</th>
                            <th class="text-right py-5 font-bold text-gray-700 text-lg">Price</th>
                            <th class="text-right py-5 font-bold text-gray-700 text-lg">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-8 text-lg font-medium text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="py-8 text-center text-gray-700">× <?= $item['quantity'] ?></td>
                                <td class="py-8 text-right text-gray-700">$<?= number_format($item['unit_price'], 2) ?></td>
                                <td class="py-8 text-right font-bold text-emerald-600">
                                    $<?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div class="mt-12 text-right">
                    <div class="inline-block bg-emerald-50 rounded-3xl p-10 min-w-96 shadow-lg">
                        <div class="flex justify-between text-4xl font-bold text-emerald-600">
                            <span>GRAND TOTAL:</span>
                            <span>$<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 p-12 text-center rounded-b-3xl">
                <p class="text-3xl font-bold text-gray-800 mb-4">Thank You!</p>
                <p class="text-xl text-gray-600">We hope to serve you again soon</p>
            </div>
        </div>
    </div>
</body>
</html>