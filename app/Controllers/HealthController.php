<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Throwable;

/**
 * Public deployment probe. Reports whether the portal is configured and can
 * reach the Partner Master — without disclosing any configuration values.
 */
class HealthController extends Controller
{
    public function index(): ResponseInterface
    {
        $dbConfigured = (string) env('PARTNER_DB_NAME', (string) env('database.default.database', '')) !== '';

        $dbAlive       = false;
        $partnerMaster = false;

        try {
            $db = Database::connect();
            $db->query('SELECT 1');
            $dbAlive       = true;
            $partnerMaster = $db->tableExists('engage_partners');
        } catch (Throwable $e) {
            log_message('error', 'Partner portal health check failed: ' . $e->getMessage());
        }

        $ok = $dbAlive && $partnerMaster;

        return $this->response->setStatusCode($ok ? 200 : 503)->setJSON([
            'ok'        => $ok,
            'service'   => 'aicountly-partner-portal',
            'status'    => $ok ? 'ready' : 'misconfigured',
            'timestamp' => gmdate('c'),
            'checks'    => [
                'db_env'         => $dbConfigured ? 'ok' : 'missing database settings in .env',
                'db_connection'  => $dbAlive ? 'ok' : 'unreachable',
                'partner_master' => $partnerMaster ? 'ok' : 'engage_partners table not found',
            ],
        ]);
    }
}
