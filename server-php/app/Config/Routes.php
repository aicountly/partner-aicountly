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
    // Engage creates them.
    $routes->post('auth/login', 'Api\\V1\\AuthController::login');

    // Everything below requires an authenticated, active partner session.
    $routes->group('', ['filter' => 'partner-auth'], static function ($routes): void {
        $routes->get('me', 'Api\\V1\\AuthController::me');
        $routes->post('auth/logout', 'Api\\V1\\AuthController::logout');
        $routes->get('profile', 'Api\\V1\\ProfileController::show');
    });
});
