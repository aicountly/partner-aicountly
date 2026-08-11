<?php

namespace App\Controllers;

use App\Libraries\PartnerAuth;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Services;

/**
 * Partner sign-in and sign-out.
 *
 * There is intentionally no signup, registration or self-service account
 * creation here — partner accounts are created only in Engage
 * (engage.aicountly.org) and this controller exposes no way to make one.
 */
class AuthController extends BaseController
{
    /** Sign-in attempts allowed per IP address per minute. */
    private const ATTEMPTS_PER_MINUTE = 10;

    public function index(): RedirectResponse
    {
        return ($this->auth->isLoggedIn() && $this->auth->currentPartner() !== null)
            ? redirect()->to('/dashboard')
            : redirect()->to('/login');
    }

    public function login(): string
    {
        return view('auth/login', [
            'title' => 'Sign in',
            'email' => old('email', ''),
        ]);
    }

    public function attemptLogin(): RedirectResponse
    {
        $rules = [
            'email'    => [
                'label'  => 'Email',
                'rules'  => 'required|valid_email|max_length[191]',
                'errors' => [
                    'required'    => 'Enter your email address.',
                    'valid_email' => 'Enter a valid email address.',
                ],
            ],
            'password' => [
                'label'  => 'Password',
                'rules'  => 'required',
                'errors' => ['required' => 'Enter your password.'],
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Brute-force protection: throttle by client IP before touching the DB.
        $ip = $this->request->getIPAddress();
        if (! Services::throttler()->check(md5('partner-login-' . $ip), self::ATTEMPTS_PER_MINUTE, MINUTE)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Too many sign-in attempts. Please wait a minute and try again.');
        }

        $result = $this->auth->attempt(
            (string) $this->request->getPost('email'),
            (string) $this->request->getPost('password'),
            $ip,
        );

        if (! $result['ok']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['error'] ?? PartnerAuth::GENERIC_ERROR);
        }

        return redirect()->to('/dashboard');
    }

    public function logout(): RedirectResponse
    {
        $this->auth->logout();

        return redirect()->to('/login')->with('message', 'You have been signed out.');
    }
}
