<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should return the request object.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('logged_in')) {
            return service('response')->setJSON([
                'status'  => 'error',
                'message' => 'Unauthenticated'
            ])->setStatusCode(401);
        }

        // Si se especificó un rol en los argumentos (ej: role:admin)
        if ($arguments) {
            $requiredRole = $arguments[0];
            $userRole = session()->get('role');

            if ($userRole !== 'admin' && $userRole !== $requiredRole) {
                return service('response')->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthorized'
                ])->setStatusCode(403);
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow short-circuiting
     * the process, and for that reason it should not return
     * any value.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // 
    }
}
