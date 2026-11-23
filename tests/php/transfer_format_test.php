<?php
/** Pure-PHP migration data-format tests; no database or network access. */
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__ . '/../../database/transfer_lib.php';
require_once __DIR__ . '/../../includes/functions.php';
$ok = 0;
$fail = 0;
$check = static function (string $label, bool $success) use (&$ok, &$fail): void {
    if ($success) $ok++;
    else { $fail++; fwrite(STDERR, "FAIL: $label\n"); }
};
$user = ['user_id' => 4294967295, 'username' => 'Tester', 'email' => 'test@example.test',
    'password_hash' => '$2y$10$hash_value', 'role' => 'user', 'is_active' => true,
    'created_at' => '2026-01-01 00:00:00'];
$check('canonical large unsigned ID', canonical_row('users', $user)['user_id'] === '4294967295');
foreach ([true, 't', 'true', 1, '1'] as $variant) {
    $user['is_active'] = $variant;
    $check('canonical active ' . gettype($variant), canonical_row('users', $user)['is_active'] === '1');
}
foreach ([false, 'f', 'false', 0, '0'] as $variant) {
    $user['is_active'] = $variant;
    $check('canonical inactive ' . gettype($variant), canonical_row('users', $user)['is_active'] === '0');
}
foreach ([true, 't', 'true', 1, '1', false, 'f', 'false', 0, '0'] as $variant) {
    $check('pg_bool variant', pg_bool($variant) === in_array($variant, [true, 't', 'true', 1, '1'], true));
}
$user['is_active'] = true;
$extra = $user;
$extra['unsupported'] = 'must not be lost';
try { canonical_row('users', $extra); $check('unexpected field rejection', false); }
catch (RuntimeException $e) { $check('unexpected field rejection', true); }
$broken = $user;
unset($broken['password_hash']);
try { canonical_row('users', $broken); $check('missing field rejection', false); }
catch (RuntimeException $e) { $check('missing field rejection', true); }
$user['username'] = 'Unicode ☂';
$check('JSONL unicode preservation', str_contains(encode_row(canonical_row('users', $user)), 'Unicode ☂'));
$check('JSONL deterministic', encode_row(canonical_row('users', $user)) === encode_row(canonical_row('users', $user)));
putenv('DATABASE_URL=postgresql://reader:a%40b%23c@localhost:6543/test_db?sslmode=require');
[$dsn, $login, $secret] = db_connection_settings();
$check('URL percent decoding', $login === 'reader' && $secret === 'a@b#c' && str_contains($dsn, 'dbname=test_db'));
putenv('DATABASE_URL=postgresql://reader:a@localhost:6543/test_db?sslmode=disable');
try { db_connection_settings(); $check('reject no TLS', false); }
catch (RuntimeException $e) { $check('reject no TLS', true); }
putenv('DATABASE_URL');
echo "Transfer format ALL " . ($fail ? "FAILED: $ok passed, $fail failed" : "PASSED: $ok checks") . "\n";
exit($fail ? 1 : 0);
