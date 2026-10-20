<?php
require_once __DIR__ . '/auth.php';
require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        if ($name === '' || $price <= 0) {
            $msg = 'Name and positive price required.';
        } else {
            $stmt = db()->prepare('INSERT INTO products (name, price) VALUES (?, ?)');
            $stmt->bind_param('sd', $name, $price);
            $stmt->execute();
            $stmt->close();
            $msg = 'Product created.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Product deleted.';
    }
}

$products = db()->query('SELECT id, name, price FROM products ORDER BY id');
?>
<!doctype html><html><head><title>Admin</title></head><body>
<h1>Admin — Products</h1>
<p><a href="/">← Store</a></p>
<?php if ($msg): ?><p><strong><?= htmlspecialchars($msg) ?></strong></p><?php endif; ?>

<h2>Add product</h2>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  Name: <input type="text" name="name" required>
  Price: <input type="number" step="0.01" min="0.01" name="price" required>
  <button type="submit">Create</button>
</form>

<h2>Existing products</h2>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>Name</th><th>Price</th><th></th></tr>
<?php while ($r = $products->fetch_assoc()): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td>$<?= htmlspecialchars($r['price']) ?></td>
  <td>
    <form method="post" style="display:inline" onsubmit="return confirm('Delete?')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button type="submit">Delete</button>
    </form>
  </td>
</tr>
<?php endwhile; ?>
</table>
</body></html>