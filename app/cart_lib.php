<?php
function cart_id_for(string $sub): int {
    $stmt = db()->prepare('INSERT INTO carts (user_sub) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->bind_param('s', $sub);
    $stmt->execute();
    $id = (int)db()->insert_id;
    $stmt->close();
    return $id;
}

function cart_add(string $sub, int $product_id, int $qty): void {
    $cart_id = cart_id_for($sub);
    $stmt = db()->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    $stmt->bind_param('iii', $cart_id, $product_id, $qty);
    $stmt->execute();
    $stmt->close();
}

function cart_remove(string $sub, int $product_id): void {
    $stmt = db()->prepare(
        'DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
         WHERE c.user_sub = ? AND ci.product_id = ?'
    );
    $stmt->bind_param('si', $sub, $product_id);
    $stmt->execute();
    $stmt->close();
}

function cart_items(string $sub): array {
    $stmt = db()->prepare(
        'SELECT ci.product_id, ci.quantity, p.name, p.price
         FROM cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         JOIN products p ON p.id = ci.product_id
         WHERE c.user_sub = ?
         ORDER BY p.name'
    );
    $stmt->bind_param('s', $sub);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}