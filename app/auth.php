<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

$session_dir = '/mnt/efs/sessions';
if (!is_dir($session_dir)) { @mkdir($session_dir, 0770, true); }
session_save_path($session_dir);
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool   { return current_user() !== null; }
function user_in_group(string $g): bool {
    $u = current_user();
    return $u !== null && in_array($g, $u['groups'] ?? [], true);
}
function require_login(): void {
    if (!is_logged_in()) { header('Location: /login.php'); exit; }
}
function require_admin(): void {
    require_login();
    if (!user_in_group('admin')) { http_response_code(403); die('Forbidden: admin required.'); }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403); die('CSRF check failed.');
    }
}

function cognito_issuer(): string {
    return 'https://cognito-idp.' . COGNITO_REGION . '.amazonaws.com/' . COGNITO_USER_POOL;
}

function load_jwks(bool $force = false): array {
    $cache = '/mnt/efs/jwks.json';
    if (!$force && file_exists($cache) && (time() - filemtime($cache)) < 3600) {
        $raw = file_get_contents($cache);
        if ($raw !== false) {
            $d = json_decode($raw, true);
            if (is_array($d)) return $d;
        }
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $raw = @file_get_contents(cognito_issuer() . '/.well-known/jwks.json', false, $ctx);
    if ($raw === false) throw new RuntimeException('JWKS fetch failed');
    $tmp = $cache . '.tmp.' . getmypid();
    file_put_contents($tmp, $raw);
    rename($tmp, $cache);
    return json_decode($raw, true) ?: [];
}

function verify_id_token(string $jwt): array {
    $jwks = load_jwks(false);
    try {
        return decode_with_jwks($jwt, $jwks);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        return decode_with_jwks($jwt, load_jwks(true));
    }
}

function decode_with_jwks(string $jwt, array $jwks): array {
    $keys = JWK::parseKeySet($jwks, 'RS256');
    $claims = (array) JWT::decode($jwt, $keys);
    if (($claims['iss'] ?? '') !== cognito_issuer())     throw new RuntimeException('Bad iss');
    if (($claims['aud'] ?? '') !== COGNITO_CLIENT_ID)    throw new RuntimeException('Bad aud');
    if (($claims['token_use'] ?? '') !== 'id')           throw new RuntimeException('Not an id token');
    return $claims;
}