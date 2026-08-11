<?php

namespace App\Controllers;

use App\Libraries\PartnerAuth;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Shared response contract for the Partner Portal API, matching Engage:
 *
 *   { "ok": true,  "data": ... }
 *   { "ok": false, "error": "message", "details": ... }
 */
abstract class BaseApiController extends Controller
{
    protected PartnerAuth $auth;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auth = new PartnerAuth();
    }

    protected function ok(mixed $data = [], int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok'   => true,
            'data' => $data,
        ]);
    }

    protected function fail(string $message, int $status = 400, mixed $extra = null): ResponseInterface
    {
        $body = ['ok' => false, 'error' => $message];
        if ($extra !== null) {
            $body['details'] = $extra;
        }

        return $this->response->setStatusCode($status)->setJSON($body);
    }

    /**
     * Read a JSON body, falling back to form data.
     */
    protected function input(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }

        return (array) $this->request->getPost();
    }
}
