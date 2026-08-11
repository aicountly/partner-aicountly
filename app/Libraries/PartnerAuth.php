<?php

namespace App\Libraries;

use App\Models\PartnersModel;
use CodeIgniter\Session\Session;
use Throwable;

/**
 * Session-based authentication for partner.aicountly.com.
 *
 * Credentials live in the Partner Master maintained by Engage; this class only
 * verifies them. A partner may sign in when, and only when, all of these hold:
 *
 *   - the partner row exists and is not soft-deleted
 *   - status is `active`
 *   - Engage has set a password (password_hash is present)
 *   - the account is not temporarily locked after repeated failures
 *
 * Every failure returns the same generic message so the login form can never be
 * used to discover which email addresses belong to a partner.
 */
class PartnerAuth
{
    public const SESSION_KEY = 'partner';

    /** Generic message used for every credential failure. */
    public const GENERIC_ERROR = 'Invalid email address or password.';

    public const LOCKED_ERROR = 'This account is temporarily locked after too many failed sign-in attempts. Please try again later or contact AICOUNTLY support.';

    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    private PartnersModel $partners;

    private Session $session;

    public function __construct(?PartnersModel $partners = null, ?Session $session = null)
    {
        $this->partners = $partners ?? new PartnersModel();
        $this->session  = $session ?? session();
    }

    /**
     * Verify credentials and start an authenticated session.
     *
     * @return array{ok: bool, error?: string}
     */
    public function attempt(string $email, string $password, string $ip = ''): array
    {
        $partner = $this->partners->findLiveByEmail($email);

        // No such partner — still spend time hashing so that a missing account
        // and a wrong password take the same amount of time to answer.
        if ($partner === null) {
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');

            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        $passwordMatches = ! empty($partner['password_hash'])
            && password_verify($password, (string) $partner['password_hash']);

        if (PartnersModel::isLocked($partner)) {
            // Only tell the caller about the lock when they already proved they
            // know the password — otherwise this would leak account existence.
            return ['ok' => false, 'error' => $passwordMatches ? self::LOCKED_ERROR : self::GENERIC_ERROR];
        }

        if (! $passwordMatches) {
            $this->safely(fn () => $this->partners->recordFailedAttempt(
                (int) $partner['id'],
                self::MAX_FAILED_ATTEMPTS,
                self::LOCK_MINUTES,
            ));

            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        // Password is correct — now apply the access rules from the Partner
        // Master. Inactive and deleted partners get the same generic message.
        if (($partner['status'] ?? '') !== 'active') {
            log_message('info', 'Partner login refused (status={status}) for partner #{id}', [
                'status' => (string) ($partner['status'] ?? 'unknown'),
                'id'     => (string) $partner['id'],
            ]);

            return ['ok' => false, 'error' => self::GENERIC_ERROR];
        }

        $this->safely(fn () => $this->partners->recordSuccessfulLogin((int) $partner['id'], $ip));

        $this->startSession($partner);

        return ['ok' => true];
    }

    /**
     * Re-read the signed-in partner from the Partner Master so that a partner
     * deactivated or deleted in Engage loses access on their very next request.
     */
    public function currentPartner(): ?array
    {
        $id = (int) ($this->session->get(self::SESSION_KEY)['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $partner = $this->partners->find($id);
        if ($partner === null || ($partner['status'] ?? '') !== 'active') {
            return null;
        }

        unset($partner['password_hash']);

        return $partner;
    }

    public function isLoggedIn(): bool
    {
        return $this->session->get(self::SESSION_KEY) !== null;
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->destroy();
    }

    private function startSession(array $partner): void
    {
        // New session id on privilege change — defeats session fixation.
        $this->session->regenerate(true);

        $this->session->set(self::SESSION_KEY, [
            'id'          => (int) $partner['id'],
            'partner_uid' => $partner['partner_uid'] ?? null,
            'name'        => $partner['name'] ?? '',
            'email'       => $partner['email'] ?? '',
            'logged_in_at' => date('c'),
        ]);
    }

    /**
     * Login bookkeeping must never break the sign-in itself (for example when
     * the portal's database role is read-only).
     */
    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            log_message('error', 'Partner login bookkeeping failed: ' . $e->getMessage());
        }
    }
}
