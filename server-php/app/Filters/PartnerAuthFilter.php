<?php

namespace App\Filters;

use App\Libraries\PartnerAuth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Guards every authenticated endpoint. Anonymous callers get 401, and a
 * partner deactivated or deleted in Engage is signed out on their next request.
 */
class PartnerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new PartnerAuth();

        if (! $auth->isLoggedIn()) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['ok' => false, 'error' => 'Not authenticated.']);
        }

        if ($auth->currentPartner() === null) {
            $auth->logout();

            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'ok'    => false,
                    'error' => 'Your partner account is no longer active. Please contact AICOUNTLY support.',
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
