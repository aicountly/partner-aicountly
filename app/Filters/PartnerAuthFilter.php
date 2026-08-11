<?php

namespace App\Filters;

use App\Libraries\PartnerAuth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Guards every authenticated page. Anonymous visitors are sent to the login
 * form, and a partner who was deactivated or deleted in Engage is signed out on
 * their next request.
 */
class PartnerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new PartnerAuth();

        if (! $auth->isLoggedIn()) {
            return redirect()->to('/login')->with('error', 'Please sign in to continue.');
        }

        $partner = $auth->currentPartner();
        if ($partner === null) {
            $auth->logout();

            return redirect()->to('/login')
                ->with('error', 'Your partner account is no longer active. Please contact AICOUNTLY support.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
