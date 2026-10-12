<?php
// Build-time self-test: every image build must pass this or it is not published.
declare(strict_types=1);

$failed = [];
$check = function (string $name, callable $test) use (&$failed): void {
    try {
        $ok = $test() === true;
    } catch (Throwable $e) {
        $ok = false;
        echo "     ", $e->getMessage(), "\n";
    }
    echo $ok ? "ok   " : "FAIL ", $name, "\n";
    if (!$ok) {
        $failed[] = $name;
    }
};

// Requested features, exercised for real (not just "loaded")
$check('pdo_mysql', fn () => in_array('mysql', PDO::getAvailableDrivers(), true)
    && defined('PDO::MYSQL_ATTR_SSL_CA'));
$check('mbstring', fn () => mb_strlen('héllo wörld', 'UTF-8') === 11
    && mb_strtoupper('ümlaut', 'UTF-8') === 'ÜMLAUT');
$check('json', fn () => json_decode(json_encode(['k' => 'ü', 'n' => [1, 2]], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
    === ['k' => 'ü', 'n' => [1, 2]]);
$check('filter', fn () => filter_var('user@example.com', FILTER_VALIDATE_EMAIL) === 'user@example.com'
    && filter_var('not-an-email', FILTER_VALIDATE_EMAIL) === false
    && filter_var('42', FILTER_VALIDATE_INT) === 42);
$check('hash', fn () => hash('sha256', 'abc') === 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad'
    && hash_hmac('sha256', 'data', 'key') === '5031fe3d989c6d1537a013fa6e739da23463fdaec3b70137d828e36ace221bd0');
$check('openssl', function (): bool {
    $key = openssl_random_pseudo_bytes(32);
    $iv = openssl_random_pseudo_bytes(12);
    $cipher = openssl_encrypt('secret', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $cipher !== false
        && openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag) === 'secret'
        && OPENSSL_VERSION_NUMBER >= 0x30000000;
});
$check('date', fn () => date_default_timezone_get() === 'UTC'
    && (new DateTimeImmutable('2024-02-29 12:00', new DateTimeZone('Europe/Amsterdam')))->getTimestamp() === 1709204400);
$check('bcrypt', function (): bool {
    $hash = password_hash('correct horse', PASSWORD_BCRYPT, ['cost' => 10]);
    return str_starts_with($hash, '$2y$10$')
        && password_verify('correct horse', $hash)
        && !password_verify('wrong', $hash);
});
$check('random_bytes', fn () => strlen(random_bytes(32)) === 32
    && random_bytes(16) !== random_bytes(16)
    && random_int(1, 6) >= 1);

// Everything else the egg advertises must at least be loaded
foreach (['bcmath', 'exif', 'gd', 'intl', 'mysqli', 'pdo_pgsql', 'zip', 'redis', 'Zend OPcache', 'sodium', 'curl'] as $ext) {
    $check($ext, fn () => extension_loaded($ext));
}

if ($failed) {
    echo "\nSelf-test FAILED: ", implode(', ', $failed), "\n";
    exit(1);
}
echo "\nSelf-test passed (PHP ", PHP_VERSION, ")\n";
