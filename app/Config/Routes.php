<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Deployment/uptime probe — no secrets, no partner data.
$routes->get('health', 'HealthController::index');

// Entry point: sends signed-in partners to the dashboard, everyone else to login.
$routes->get('/', 'AuthController::index');

// Sign-in. There is deliberately NO signup, registration or password-reset
// self-service route — partner accounts exist only when Engage creates them.
$routes->group('', ['filter' => 'guest'], static function ($routes): void {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attemptLogin');
});

// Everything below requires an authenticated, active partner session.
$routes->group('', ['filter' => 'partner-auth'], static function ($routes): void {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('profile', 'ProfileController::index');
    $routes->post('logout', 'AuthController::logout');
});
