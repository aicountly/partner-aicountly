<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Read-only view of the partner's own record. Partner details and credentials
 * are maintained in Engage, so the portal exposes them but never edits them —
 * there is no write endpoint here by design.
 */
class ProfileController extends BaseApiController
{
    public function show(): ResponseInterface
    {
        $partner = $this->auth->currentPartner();
        if ($partner === null) {
            return $this->fail('Not authenticated.', 401);
        }

        return $this->ok([
            'partner_uid'   => $partner['partner_uid'] ?? null,
            'name'          => $partner['name'] ?? null,
            'contact_name'  => $partner['contact_name'] ?? null,
            'email'         => $partner['email'] ?? null,
            'phone'         => $partner['phone'] ?? null,
            'partner_type'  => $partner['partner_type'] ?? null,
            'website'       => $partner['website'] ?? null,
            'country'       => $partner['country'] ?? null,
            'city'          => $partner['city'] ?? null,
            'status'        => $partner['status'] ?? null,
            'last_login_at' => $partner['last_login_at'] ?? null,
            'created_at'    => $partner['created_at'] ?? null,
        ]);
    }
}
