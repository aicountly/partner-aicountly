<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * The Partner Portal reads the Partner Master owned by Engage
 * (engage.aicountly.org) — table `engage_partners` in the Engage PostgreSQL
 * database. There is deliberately no second partner schema.
 *
 * Credentials come only from .env; nothing is hardcoded. Point the portal at a
 * database role that can SELECT engage_partners and UPDATE only its login
 * bookkeeping columns.
 */
class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    public string $defaultGroup = 'default';

    public array $default = [
        'DSN'      => '',
        'hostname' => '',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
        'schema'   => 'public',
    ];

    public array $tests = [
        'DSN'      => '',
        'hostname' => '127.0.0.1',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
        'schema'   => 'public',
    ];

    public function __construct()
    {
        parent::__construct();

        // CodeIgniter also honours the native `database.default.*` names from
        // .env; these PARTNER_DB_* aliases mirror the Engage naming convention
        // so both portals are configured the same way on cPanel.
        $this->applyEnv('hostname', 'PARTNER_DB_HOST');
        $this->applyEnv('database', 'PARTNER_DB_NAME');
        $this->applyEnv('username', 'PARTNER_DB_USER');
        $this->applyEnv('password', 'PARTNER_DB_PASSWORD');
        $this->applyEnv('DBDriver', 'PARTNER_DB_DRIVER');
        $this->applyEnv('schema', 'PARTNER_DB_SCHEMA');

        $port = env('PARTNER_DB_PORT');
        if ($port !== null && $port !== '') {
            $this->default['port'] = (int) $port;
        }

        // Never leak SQL to the browser in production.
        $this->default['DBDebug'] = ENVIRONMENT !== 'production';

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }

    private function applyEnv(string $key, string $envName): void
    {
        $value = env($envName);
        if ($value !== null && $value !== '') {
            $this->default[$key] = $value;
        }
    }
}
