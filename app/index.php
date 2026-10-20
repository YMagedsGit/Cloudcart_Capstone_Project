<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart_lib.php';
$user = current_user();

$result = db()->query('SELECT id, name, price FROM products ORDER BY id');
?>
<!doctype html>
<html>
<head><title>CloudCart Online Store</title></head>
<body>
<h1>CloudCart Online Store</h1>

<?php if ($user): ?>
  <p>Signed in as <strong><?= htmlspecialchars($user['email']) ?></strong>
     (<?= htmlspecialchars(implode(', ', $user['groups']) ?: 'no groups') ?>)
     — <a href="/cart.php">Cart</a>
     — <?php if (user_in_group('admin')): ?><a href="/admin.php">Admin</a> — <?php endif; ?>
     <a href="/logout.php">Sign out</a></p>
<?php else: ?>
  <p><a href="/login.php">Sign in</a></p>
<?php endif; ?>

<h2>Product Catalog</h2>
<table border="1" cellpadding="8">
<tr><th>ID</th><th>Name</th><th>Price</th><th></th></tr>
<?php while ($row = $result->fetch_assoc()): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td>$<?= htmlspecialchars($row['price']) ?></td>
    <td>
      <?php if ($user): ?>
        <form method="post" action="/cart.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
          <button type="submit">Add to cart</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
<?php endwhile; ?>
</table>
<p>Served by: <?= htmlspecialchars(gethostname()) ?></p>
</body>
</html>
