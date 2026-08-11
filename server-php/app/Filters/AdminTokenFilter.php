<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Shared-secret auth for the admin API (Engage -> Partner Portal).
 *
 * Engage's Partner Master screens (Add/Edit/Delete/List) call this API rather
 * than storing partner data themselves. Sender must include:
 *
 *   X-Partner-Admin-Key: <PARTNER_ADMIN_KEY>
 *
 * This is separate from the partner-facing session-cookie login — no partner
 * session can reach these routes, and no admin key can authenticate a partner.
 */
class AdminTokenFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $expected = (string) env('PARTNER_ADMIN_KEY', '');
        if ($expected === '') {
            return service('response')->setStatusCode(503)->setJSON([
                'ok'    => false,
                'error' => 'Server misconfigured: PARTNER_ADMIN_KEY missing in api/.env',
            ]);
        }

        $provided = (string) $request->getHeaderLine('X-Partner-Admin-Key');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return service('response')->setStatusCode(401)->setJSON([
                'ok'    => false,
                'error' => 'Invalid X-Partner-Admin-Key.',
            ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
