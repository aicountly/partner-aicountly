<?php

/**
 * AICOUNTLY Partner Portal — CodeIgniter front controller.
 *
 * Deployed flat into the cPanel document root for partner.aicountly.com
 * (PROD_SSH_REMOTE_ROOT), matching how the other AICOUNTLY portals are hosted.
 * .htaccess denies direct access to app/, writable/, vendor/ and .env.
 */

use CodeIgniter\Boot;
use Config\Paths;

$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf('PHP %s+ required. Current: %s', $minPhpVersion, PHP_VERSION);

    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . 'app/Config/Paths.php';

$paths = new Paths();

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Composer dependencies missing. Run `composer install` in the deployment root.\n";

    exit(1);
}

require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
