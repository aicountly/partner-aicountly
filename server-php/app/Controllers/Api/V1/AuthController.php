<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseApiController;
use App\Libraries\PartnerAuth;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Partner sign-in and sign-out.
 *
 * Authentication is a same-origin session cookie (HttpOnly, Secure,
 * SameSite=Strict) rather than a token in localStorage, so an XSS in the SPA
 * cannot read the credential. CSRF is enforced by the framework filter.
 *
 * There is intentionally no signup, registration or self-service account
 * creation endpoint — partners exist only when Engage creates them.
 */
class AuthController extends BaseApiController
{
    /** Sign-in attempts allowed per IP address per minute. */
    private const ATTEMPTS_PER_MINUTE = 10;

    public function login(): ResponseInterface
    {
        $data     = $this->input();
        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Enter your email address.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }
        if ($errors !== []) {
            return $this->fail('Please check the details you entered.', 422, $errors);
        }

        // Brute-force protection by client IP, before touching the database.
        $ip = $this->request->getIPAddress();
        if (! Services::throttler()->check(md5('partner-login-' . $ip), self::ATTEMPTS_PER_MINUTE, MINUTE)) {
            return $this->fail('Too many sign-in attempts. Please wait a minute and try again.', 429);
        }

        $result = $this->auth->attempt($email, $password, $ip);
        if (! $result['ok']) {
            return $this->fail($result['error'] ?? PartnerAuth::GENERIC_ERROR, 401);
        }

        return $this->ok(['partner' => $this->auth->currentPartner()]);
    }

    public function logout(): ResponseInterface
    {
        $this->auth->logout();

        return $this->ok(['message' => 'You have been signed out.']);
    }

    /**
     * The signed-in partner, re-read from the Partner Master on every call so a
     * partner deactivated or deleted in Engage loses access immediately.
     */
    public function me(): ResponseInterface
    {
        $partner = $this->auth->currentPartner();
        if ($partner === null) {
            return $this->fail('Not authenticated.', 401);
        }

        return $this->ok($partner);
    }
}
