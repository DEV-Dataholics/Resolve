<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * InternalFilter
 *
 * Permite el acceso únicamente a usuarios autenticados cuya empresa
 * tiene is_internal = true (es decir, cuentas @dataholics.com.mx).
 */
class InternalFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('logged_in')) {
            return service('response')->setJSON([
                'status'  => 'error',
                'message' => 'Unauthenticated',
            ])->setStatusCode(401);
        }

        if (!session()->get('is_internal')) {
            return service('response')->setJSON([
                'status'  => 'error',
                'message' => 'Acceso restringido al equipo Dataholics',
            ])->setStatusCode(403);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
