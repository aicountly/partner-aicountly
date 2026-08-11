<?php

namespace Config;

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
            // Verifies the CSRF token on every state-changing request
            // (POST/PUT/PATCH/DELETE); GETs pass straight through. The SPA
            // reads the token from the X-CSRF-TOKEN response header and
            // echoes it back on writes.
            'csrf',
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
