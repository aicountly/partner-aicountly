<?php

namespace Config;

use App\Filters\GuestFilter;
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
        // Blocks anonymous visitors from authenticated pages.
        'partner-auth'  => PartnerAuthFilter::class,
        // Keeps signed-in partners away from the login form.
        'guest'         => GuestFilter::class,
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
            // Verifies the CSRF token on every state-changing request
            // (POST/PUT/PATCH/DELETE); GETs pass straight through.
            'csrf',
        ],
        'after' => [
            'secureheaders',
        ],
    ];

    public array $methods = [];

    public array $filters = [];
}
