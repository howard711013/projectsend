<?php

declare(strict_types=1);

function fail_test(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail_test($message);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fail_test($message . " Expected: " . var_export($expected, true) . " Actual: " . var_export($actual, true));
    }
}

$class_file = __DIR__ . '/includes/Classes/LoginShareToken.php';
if (!file_exists($class_file)) {
    fail_test('Missing class file: includes/Classes/LoginShareToken.php');
}

require_once $class_file;

if (!class_exists(\ProjectSend\Classes\LoginShareToken::class)) {
    fail_test('Class ProjectSend\\Classes\\LoginShareToken was not loaded.');
}

define('TABLE_LOGIN_SHARE_TOKENS', 'login_share_tokens');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE login_share_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id TEXT NOT NULL,
        token_hash TEXT NOT NULL,
        token_prefix TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at TEXT DEFAULT NULL,
        revoked_at TEXT DEFAULT NULL
    )'
);

$service = new \ProjectSend\Classes\LoginShareToken($pdo);

$created = $service->create(7, 'session-a');
assert_true(is_array($created), 'create() should return token payload.');
assert_true(isset($created['plain']), 'create() should return plain token.');
assert_true(str_starts_with($created['plain'], 'ps_login_'), 'Token should use ps_login_ prefix.');
assert_same(16, strlen($created['prefix']), 'Token prefix should fit existing database schema.');
assert_true(!empty($created['expires_at']), 'create() should return expires_at.');

$found = $service->findByPlainToken($created['plain']);
assert_true(is_array($found), 'findByPlainToken() should find created token.');
assert_same(7, (int)$found['user_id'], 'findByPlainToken() should return matching user_id.');
assert_same('session-a', $found['session_id'], 'findByPlainToken() should return matching session_id.');

$service->touchLastUsed((int)$found['id']);
$last_used_at = $pdo->query('SELECT last_used_at FROM login_share_tokens WHERE id = ' . (int)$found['id'])->fetchColumn();
assert_true(!empty($last_used_at), 'touchLastUsed() should update last_used_at.');

$service->revokeBySessionId('session-a', 7);
assert_same(false, $service->findByPlainToken($created['plain']), 'revoked session token should no longer validate.');

$user_a = $service->create(11, 'session-b');
$user_b = $service->create(11, 'session-c');
$other_user = $service->create(12, 'session-d');
$service->revokeAllForUser(11);
assert_same(false, $service->findByPlainToken($user_a['plain']), 'revokeAllForUser() should revoke first token.');
assert_same(false, $service->findByPlainToken($user_b['plain']), 'revokeAllForUser() should revoke second token.');
assert_true(is_array($service->findByPlainToken($other_user['plain'])), 'revokeAllForUser() should not revoke other users.');

$expired_plain = \ProjectSend\Classes\LoginShareToken::generatePlainToken();
$expired_hash = \ProjectSend\Classes\LoginShareToken::hashToken($expired_plain);
$expired_prefix = \ProjectSend\Classes\LoginShareToken::extractPrefix($expired_plain);
$statement = $pdo->prepare(
    'INSERT INTO login_share_tokens (user_id, session_id, token_hash, token_prefix, expires_at, revoked_at)
     VALUES (:user_id, :session_id, :token_hash, :token_prefix, :expires_at, NULL)'
);
$statement->execute([
    ':user_id' => 99,
    ':session_id' => 'expired-session',
    ':token_hash' => $expired_hash,
    ':token_prefix' => $expired_prefix,
    ':expires_at' => gmdate('Y-m-d H:i:s', time() - 3600),
]);

assert_same(false, $service->findByPlainToken($expired_plain), 'Expired token should not validate.');
$cleaned = $service->cleanupExpiredTokens();
assert_true($cleaned >= 1, 'cleanupExpiredTokens() should remove expired rows.');

fwrite(STDOUT, "PASS\n");
