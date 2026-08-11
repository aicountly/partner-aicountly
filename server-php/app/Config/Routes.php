<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static function () {
    return service('response')->setJSON([
        'ok'      => true,
        'service' => 'aicountly-partner-api',
        'version' => 'v1',
    ]);
});

// Deployment/uptime probe — no secrets, no partner data.
$routes->get('health', 'HealthController::index');

$routes->group('v1', static function ($routes): void {
    // Public: sign-in only. There is deliberately NO signup, registration or
    // self-service account-creation route — partner accounts exist only when
    // Engage creates them. CSRF applies here (browser-facing); it does not
    // apply to the admin group below (server-to-server, shared secret).
    $routes->post('auth/login', 'Api\\V1\\AuthController::login', ['filter' => 'csrf']);

    // Everything below requires an authenticated, active partner session.
    $routes->group('', ['filter' => ['partner-auth', 'csrf']], static function ($routes): void {
        $routes->get('me', 'Api\\V1\\AuthController::me');
        $routes->post('auth/logout', 'Api\\V1\\AuthController::logout');
        $routes->get('profile', 'Api\\V1\\ProfileController::show');
    });

    // Partner Master admin API — called only by Engage's Add/Edit/Delete/List
    // screens (engage.aicountly.org), authenticated with a shared secret
    // (X-Partner-Admin-Key). No partner session can reach these routes.
    $routes->group('admin', ['filter' => 'admin-token'], static function ($routes): void {
        $routes->post('partners/(:num)/activate',   'Api\\V1\\Admin\\PartnersController::activate/$1');
        $routes->post('partners/(:num)/deactivate', 'Api\\V1\\Admin\\PartnersController::deactivate/$1');
        $routes->post('partners/(:num)/password',   'Api\\V1\\Admin\\PartnersController::setPassword/$1');
        $routes->post('partners/(:num)/unlock',     'Api\\V1\\Admin\\PartnersController::unlock/$1');
        $routes->post('partners/(:num)/restore',    'Api\\V1\\Admin\\PartnersController::restore/$1');
        $routes->resource('partners', ['controller' => 'Api\\V1\\Admin\\PartnersController']);
    });
});
