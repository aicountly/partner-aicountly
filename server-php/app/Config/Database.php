<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * The Partner Portal owns the Partner Master — table `partners` in this
 * portal's own database. Engage stores no partner data; its admin screens
 * call this portal's admin API instead.
 *
 * Credentials come only from .env; nothing is hardcoded. Use a database
 * dedicated to this portal, not Engage's.
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
