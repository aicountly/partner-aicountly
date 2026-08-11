<?php

namespace Config;

use App\Filters\AdminTokenFilter;
use App\Filters\CorsFilter;
use App\Filters\CsrfTokenFilter;
use App\Filters\PartnerAuthFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'cors'          => CorsFilter::class,
        // Publishes the current CSRF token as a response header for the SPA.
        'csrf-token'    => CsrfTokenFilter::class,
        // Rejects anonymous callers, and partners deactivated/deleted in Engage.
        'partner-auth'  => PartnerAuthFilter::class,
        // Shared-secret auth for Engage's admin API calls (X-Partner-Admin-Key).
        'admin-token'   => AdminTokenFilter::class,
    ];

    public array $required = [
        'before' => [
            'forcehttps',
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    public array $globals = [
        'before' => [
            'cors',
            // CSRF is applied per-route (login + the partner-auth group), not
            // globally: the admin API is a server-to-server call authenticated
            // by a shared secret, with no browser session to hold a token.
        ],
        'after' => [
            'cors',
            'csrf-token',
            'secureheaders',
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}
