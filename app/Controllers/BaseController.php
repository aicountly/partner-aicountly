<?php

namespace App\Controllers;

use App\Libraries\PartnerAuth;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $helpers = ['form', 'url'];

    protected PartnerAuth $auth;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auth = new PartnerAuth();
    }

    /**
     * The signed-in partner, re-read from the Partner Master on every request.
     * PartnerAuthFilter guarantees this is non-null on authenticated routes.
     */
    protected function partner(): array
    {
        return $this->auth->currentPartner() ?? [];
    }
}
