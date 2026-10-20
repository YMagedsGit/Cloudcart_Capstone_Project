<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart_lib.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $pid > 0) {
        cart_add($user['sub'], $pid, 1);
    } elseif ($action === 'remove' && $pid > 0) {
        cart_remove($user['sub'], $pid);
    }
    header('Location: /cart.php');
    exit;
}

$items = cart_items($user['sub']);
$total = 0.0;
foreach ($items as $i) { $total += $i['price'] * $i['quantity']; }
?>
<!doctype html>
<html><head><title>Your Cart</title></head><body>
<h1>Your Cart</h1>
<p><a href="/">Back to store</a></p>

<?php if (!$items): ?>
  <p>Your cart is empty.</p>
<?php else: ?>
<table border="1" cellpadding="8">
<tr><th>Product</th><th>Qty</th><th>Unit</th><th>Line</th><th></th></tr>
<?php foreach ($items as $i): ?>
  <tr>
    <td><?= htmlspecialchars($i['name']) ?></td>
    <td><?= (int)$i['quantity'] ?></td>
    <td>$<?= number_format($i['price'], 2) ?></td>
    <td>$<?= number_format($i['price'] * $i['quantity'], 2) ?></td>
    <td>
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="product_id" value="<?= (int)$i['product_id'] ?>">
        <button type="submit">Remove</button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
<tr>
  <td colspan="3"><strong>Total</strong></td>
  <td colspan="2"><strong>$<?= number_format($total, 2) ?></strong></td>
</tr>
</table>
<form method="post" action="/checkout.php">
  <?= csrf_field() ?>
  <button type="submit">Checkout</button>
</form>
<?php endif; ?>
</body></html>
