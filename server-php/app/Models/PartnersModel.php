<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Partner Master. The Partner Portal owns every write to this table —
 * Engage's admin screens call this portal's admin API rather than writing to
 * a database of their own, so there is a single source of truth.
 */
class PartnersModel extends Model
{
    protected $table          = 'partners';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = [
        'partner_uid', 'name', 'contact_name', 'email', 'phone', 'partner_type',
        'website', 'country', 'city',
        'password_hash', 'password_set_at', 'status',
        'account_id', 'owner_id',
        'last_login_at', 'last_login_ip', 'failed_attempts', 'locked_until',
        'notes', 'metadata',
    ];

    /** Columns that must never leave the API. */
    public const HIDDEN_FIELDS = ['password_hash'];

    /**
     * Look up a live (non-deleted) partner by email, case-insensitively.
     * Used by the public login flow.
     */
    public function findLiveByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->where('LOWER(email)', $email)->first();
    }

    /**
     * Is this email already taken by a live partner (optionally excluding one id)?
     */
    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $qb = $this->where('LOWER(email)', strtolower(trim($email)));
        if ($exceptId !== null) {
            $qb->where('id !=', $exceptId);
        }

        return $qb->countAllResults() > 0;
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

    /**
     * Strip credential columns before a row is returned over the API.
     */
    public static function publicRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        foreach (self::HIDDEN_FIELDS as $field) {
            unset($row[$field]);
        }

        $row['has_portal_access'] = ! empty($row['password_set_at']);

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicRows(array $rows): array
    {
        return array_map(static fn (array $r) => self::publicRow($r), $rows);
    }

    public static function newPartnerUid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
