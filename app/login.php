<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    if ($token === '') {
        $error = 'Paste a token.';
    } else {
        try {
            $claims = verify_id_token($token);
            $sub    = $claims['sub'];
            $email  = $claims['email'] ?? '';
            $groups = $claims['cognito:groups'] ?? [];

            $stmt = db()->prepare(
                'INSERT INTO customers (user_sub, email) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE email = VALUES(email), last_seen = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param('ss', $sub, $email);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user'] = ['sub' => $sub, 'email' => $email, 'groups' => $groups];
            header('Location: /');
            exit;
        } catch (Throwable $e) {
            $error = 'Invalid token: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html><html><head><title>Sign in</title></head><body>
<h1>Sign in</h1>
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<p>Mint a token with:<br>
<code>aws cognito-idp admin-initiate-auth --user-pool-id &lt;pool&gt; --client-id &lt;client&gt; --auth-flow ADMIN_USER_PASSWORD_AUTH --auth-parameters USERNAME=you@example.com,PASSWORD='yourpass' --region eu-north-1 --query 'AuthenticationResult.IdToken' --output text</code></p>
<form method="post">
  <textarea name="token" rows="10" cols="80" placeholder="Paste IdToken here"></textarea><br>
  <button type="submit">Sign in</button>
</form>
</body></html>
