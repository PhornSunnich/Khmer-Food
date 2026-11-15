<?php
session_start();

// Security check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: /first-PHP-learning/Assignment/website/index.php");
    exit;
}

require_once __DIR__ . '/../../conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Validate status
    $valid_statuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        die("Invalid status");
    }

    // Update database
    $stmt = $conn->prepare("UPDATE orders SET OrderStatus = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        // Success message (you can use session flash later)
        $_SESSION['status_success'] = "Order #$order_id status updated to <strong>$new_status</strong>";
    } else {
        $_SESSION['status_error'] = "Failed to update status.";
    }
    
    $stmt->close();
}

// Redirect back to orders list
header("Location: manage_orders.php");
exit;
?>