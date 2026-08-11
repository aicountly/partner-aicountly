<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Read-side view of the Partner Master owned by Engage.
 *
 * The Partner Portal never creates, edits or deletes partners — it only
 * authenticates against this table and records its own login bookkeeping
 * (last_login_*, failed_attempts, locked_until).
 */
class PartnersModel extends Model
{
    protected $table          = 'engage_partners';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    /**
     * Deliberately narrow: the only columns this portal may write.
     */
    protected $allowedFields = [
        'last_login_at', 'last_login_ip', 'failed_attempts', 'locked_until',
    ];

    /**
     * Look up a live (non-deleted) partner by email, case-insensitively.
     */
    public function findLiveByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->where('LOWER(email)', $email)->first();
    }

    public function recordSuccessfulLogin(int $id, string $ip): void
    {
        $this->update($id, [
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => substr($ip, 0, 64),
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);
    }

    /**
     * Count a failed attempt and lock the account once the limit is reached.
     *
     * @return bool true when this attempt locked the account
     */
    public function recordFailedAttempt(int $id, int $maxAttempts, int $lockMinutes): bool
    {
        $row      = $this->find($id);
        $attempts = (int) ($row['failed_attempts'] ?? 0) + 1;

        $data = ['failed_attempts' => $attempts];
        $lock = $attempts >= $maxAttempts;

        if ($lock) {
            $data['locked_until']    = date('Y-m-d H:i:s', time() + ($lockMinutes * 60));
            $data['failed_attempts'] = 0;
        }

        $this->update($id, $data);

        return $lock;
    }

    public static function isLocked(?array $partner): bool
    {
        $until = $partner['locked_until'] ?? null;

        return $until !== null && strtotime((string) $until) > time();
    }
}
