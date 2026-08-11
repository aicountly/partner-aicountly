<?php

namespace App\Filters;

use App\Libraries\PartnerAuth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Keeps an already signed-in partner from seeing the login form again.
 */
class GuestFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new PartnerAuth();

        if ($auth->isLoggedIn() && $auth->currentPartner() !== null) {
            return redirect()->to('/dashboard');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
