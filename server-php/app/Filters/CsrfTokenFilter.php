<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Publishes the current CSRF token on every response as X-CSRF-TOKEN, so the
 * SPA can echo it back on state-changing requests.
 *
 * The token stays in the session — it is deliberately NOT put in a JavaScript
 * readable cookie, which would mean dropping HttpOnly. A cross-origin page
 * cannot read this header (CORS forbids it), so only the real front end,
 * served from the same origin, ever learns the value.
 */
class CsrfTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (function_exists('csrf_hash')) {
            $response->setHeader('X-CSRF-TOKEN', csrf_hash());
        }

        return $response;
    }
}
