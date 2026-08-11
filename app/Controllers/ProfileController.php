<?php

namespace App\Controllers;

/**
 * Read-only view of the partner's own record. Partner details and credentials
 * are maintained in Engage, so the portal shows them but never edits them.
 */
class ProfileController extends BaseController
{
    public function index(): string
    {
        return view('profile/index', [
            'title'   => 'Profile',
            'partner' => $this->partner(),
        ]);
    }
}
