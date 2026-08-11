<?php

/**
 * AICOUNTLY Partner Portal environment check.
 *
 * Prints the resolved configuration (secrets masked) and tests the connection
 * to the Partner Master owned by Engage. Safe to run on the server:
 *
 *   php check-env.php
 */

declare(strict_types=1);

$root = __DIR__;
chdir($root);

echo "=== AICOUNTLY Partner Portal environment check ===\n";
echo 'PHP: ' . PHP_VERSION . ' (' . PHP_SAPI . ")\n";
echo 'pdo_pgsql: ' . (extension_loaded('pdo_pgsql') ? 'yes' : 'NO — enable in cPanel MultiPHP') . "\n";
echo 'pgsql: ' . (extension_loaded('pgsql') ? 'yes' : 'NO — required by the Postgre driver') . "\n";
echo 'intl: ' . (extension_loaded('intl') ? 'yes' : 'NO — required by CodeIgniter') . "\n";
echo '.env file: ' . (is_file($root . '/.env') ? 'yes (' . filesize($root . '/.env') . ' bytes)' : 'MISSING') . "\n";

if (! is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/ missing — run composer install\n");

    exit(1);
}

require $root . '/vendor/autoload.php';

if (class_exists(\CodeIgniter\Config\DotEnv::class) && is_file($root . '/.env')) {
    (new \CodeIgniter\Config\DotEnv($root))->load();
}

$read = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return ($value === false || $value === null) ? $default : trim((string) $value, "'\" ");
};

$first = static function (array $keys, string $default = '') use ($read): string {
    foreach ($keys as $key) {
        $value = $read($key);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
};

$host   = $first(['PARTNER_DB_HOST', 'database.default.hostname'], '127.0.0.1');
$port   = $first(['PARTNER_DB_PORT', 'database.default.port'], '5432');
$name   = $first(['PARTNER_DB_NAME', 'database.default.database']);
$user   = $first(['PARTNER_DB_USER', 'database.default.username']);
$pass   = $first(['PARTNER_DB_PASSWORD', 'database.default.password']);
$driver = $first(['PARTNER_DB_DRIVER', 'database.default.DBDriver'], 'Postgre');

echo "\n--- Application ---\n";
echo 'CI_ENVIRONMENT=' . ($read('CI_ENVIRONMENT', 'production')) . "\n";
echo 'app.baseURL=' . ($read('app.baseURL') ?: '(not set — using the compiled default)') . "\n";

$encryptionKey = $read('encryption.key');
echo 'encryption.key=' . ($encryptionKey === '' ? '(EMPTY — run: php spark key:generate)' : '*** (' . strlen($encryptionKey) . " chars)") . "\n";
echo 'cookie.secure=' . ($read('cookie.secure', '(unset — defaults to on in production)')) . "\n";

echo "\n--- Partner Master database (from .env) ---\n";
echo "host={$host}\nport={$port}\ndatabase=" . ($name === '' ? '(EMPTY — set it in .env)' : $name) . "\n";
echo 'username=' . ($user === '' ? '(EMPTY — set it in .env)' : $user) . "\n";
echo 'password=*** (' . strlen($pass) . " chars)\n";
echo "driver={$driver}\n";

if ($name === '' || $user === '') {
    fwrite(STDERR, "\nDatabase settings incomplete — the portal cannot authenticate partners.\n");

    exit(1);
}

echo "\n--- Connection test ---\n";

try {
    $dsn = $driver === 'MySQLi'
        ? "mysql:host={$host};port={$port};dbname={$name}"
        : "pgsql:host={$host};port={$port};dbname={$name}";

    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "connection: ok\n";

    $table = $pdo->query("SELECT to_regclass('public.engage_partners') IS NOT NULL AS present")->fetchColumn();
    if ($driver === 'MySQLi') {
        $table = (bool) $pdo->query("SHOW TABLES LIKE 'engage_partners'")->fetchColumn();
    }

    if (! $table) {
        fwrite(STDERR, "engage_partners: MISSING — point the portal at the Engage database and run Engage's migrations.\n");

        exit(1);
    }

    echo "engage_partners: found\n";

    $counts = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'active' AND deleted_at IS NULL) AS active,
                COUNT(*) FILTER (WHERE password_hash IS NOT NULL AND deleted_at IS NULL) AS with_access
         FROM engage_partners"
    )->fetch(PDO::FETCH_ASSOC);

    echo "partners: {$counts['total']} total, {$counts['active']} active, {$counts['with_access']} with portal credentials\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'connection: FAILED — ' . $e->getMessage() . "\n");

    exit(1);
}

echo "\nEnvironment looks good.\n";
