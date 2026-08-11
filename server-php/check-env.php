<?php

/**
 * AICOUNTLY Partner Portal environment check.
 *
 * Prints the resolved configuration (secrets masked) and tests the connection
 * to this portal's own Partner Master database. Safe to run on the server:
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

$adminKey = $read('PARTNER_ADMIN_KEY');
echo 'PARTNER_ADMIN_KEY=' . ($adminKey === '' ? '(EMPTY — Engage\'s Partner Master screens will get 503)' : '*** (' . strlen($adminKey) . " chars)") . "\n";

// The literal word "null" in a file-path .env setting is a footgun: CodeIgniter
// treats it as the STRING "null", not "unset" — silently breaking whatever
// feature relies on that path (most seriously: session.savePath, which breaks
// login persistence — a partner can sign in but is logged out on the very next
// request). Flag it here so it's caught before it reaches production.
foreach (['session.savePath', 'cache.storePath'] as $pathSetting) {
    if (strtolower($read($pathSetting)) === 'null') {
        fwrite(STDERR, "\nWARNING: {$pathSetting}=null in .env is read literally as the word \"null\", not as \"use the default\".\n");
        fwrite(STDERR, "Delete that line entirely so the application's coded default takes effect.\n");
    }
}

echo "\n--- Partner Master database (from .env; owned by this portal) ---\n";
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

    $table = $pdo->query("SELECT to_regclass('public.partners') IS NOT NULL AS present")->fetchColumn();
    if ($driver === 'MySQLi') {
        $table = (bool) $pdo->query("SHOW TABLES LIKE 'partners'")->fetchColumn();
    }

    if (! $table) {
        fwrite(STDERR, "partners: MISSING — run `php spark migrate` (this portal owns the schema; nothing else creates it).\n");

        exit(1);
    }

    echo "partners: found\n";

    $counts = $pdo->query(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'active' AND deleted_at IS NULL) AS active,
                COUNT(*) FILTER (WHERE password_hash IS NOT NULL AND deleted_at IS NULL) AS with_access
         FROM partners"
    )->fetch(PDO::FETCH_ASSOC);

    echo "partner rows: {$counts['total']} total, {$counts['active']} active, {$counts['with_access']} with portal credentials\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'connection: FAILED — ' . $e->getMessage() . "\n");

    exit(1);
}

echo "\nEnvironment looks good.\n";
