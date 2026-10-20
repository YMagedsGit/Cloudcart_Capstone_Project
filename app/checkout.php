<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart_lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /cart.php'); exit; }
verify_csrf();

$user  = current_user();
$items = cart_items($user['sub']);
if (!$items) { header('Location: /cart.php'); exit; }

$db = db();
$db->begin_transaction();
try {
    $total = 0.0;
    foreach ($items as $i) { $total += $i['price'] * $i['quantity']; }

    $stmt = $db->prepare('INSERT INTO orders (user_sub, total, status) VALUES (?, ?, "pending")');
    $stmt->bind_param('sd', $user['sub'], $total);
    $stmt->execute();
    $order_id = (int)$db->insert_id;
    $stmt->close();

    $ins = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
    foreach ($items as $i) {
        $ins->bind_param('iiid', $order_id, $i['product_id'], $i['quantity'], $i['price']);
        $ins->execute();
    }
    $ins->close();

    $del = $db->prepare('DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_sub = ?');
    $del->bind_param('s', $user['sub']);
    $del->execute();
    $del->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    die('Checkout failed: ' . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html><html><head><title>Order placed</title></head><body>
<h1>Order #<?= (int)$order_id ?> placed</h1>
<p>Total: $<?= number_format($total, 2) ?></p>
<p><a href="/">Back to store</a></p>
</body></html>