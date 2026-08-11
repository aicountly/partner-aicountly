<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * In production the SPA and this API are the same origin
 * (partner.aicountly.com and partner.aicountly.com/api), so CORS is not used
 * at all. It exists for local development, where Vite runs on :5173.
 *
 * Credentials are allowed, so the origin is always echoed from an explicit
 * allow-list — never "*", which browsers reject with credentials anyway.
 */
class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        if ($origin === '' || ! in_array($origin, $this->allowedOrigins(), true)) {
            return null;
        }

        $response = service('response');
        $this->apply($response, $origin);

        // Answer the preflight without running the route.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && in_array($origin, $this->allowedOrigins(), true)) {
            $this->apply($response, $origin);
        }
    }

    private function apply(ResponseInterface $response, string $origin): void
    {
        $response->setHeader('Access-Control-Allow-Origin', $origin);
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, X-CSRF-TOKEN');
        $response->setHeader('Access-Control-Expose-Headers', 'X-CSRF-TOKEN');
        $response->setHeader('Vary', 'Origin');
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $raw = (string) env('PARTNER_ALLOWED_ORIGINS', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
