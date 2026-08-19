<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// -------------------------------------------------------
// AUTH (Public)
// -------------------------------------------------------
$routes->post('api/auth/login',           'AuthController::login');
$routes->post('api/auth/logout',          'AuthController::logout');
$routes->post('api/auth/forgot-password', 'AuthController::forgotPassword');
$routes->post('api/auth/reset-password',  'AuthController::resetPassword');

// Portal personalizado por empresa (público, sin auth)
$routes->get('api/portal/(:segment)', 'AdminController::getCompanyBySlug/$1');

// -------------------------------------------------------
// KNOWLEDGE BASE (solo usuarios internos @dataholics.com.mx)
// -------------------------------------------------------
$routes->group('api/kb', ['filter' => 'internal'], static function ($routes) {
    $routes->get('',          'KbController::index');
    $routes->get('categories','KbController::categories');
    $routes->get('(:num)',    'KbController::show/$1');
    $routes->post('',         'KbController::create');
    $routes->put('(:num)',    'KbController::update/$1');
    $routes->delete('(:num)', 'KbController::delete/$1');
});

// -------------------------------------------------------
// PROTECTED ROUTES (Auth required)
// -------------------------------------------------------
$routes->group('api', ['filter' => 'auth'], static function ($routes) {
    $routes->get('auth/me', 'AuthController::me');

    // Tickets
    $routes->get('tickets',            'TicketController::index');
    $routes->post('tickets',           'TicketController::create');
    $routes->get('tickets/(:num)',     'TicketController::show/$1');
    $routes->put('tickets/(:num)',     'TicketController::update/$1');
    $routes->post('tickets/(:num)/comment', 'TicketController::comment/$1');
    $routes->get('teams', 'AdminController::listTeams', ['filter' => 'internal']);

    // Admin routes (admin only)
    $routes->group('admin', ['filter' => 'auth:admin'], static function ($routes) {
        $routes->get('companies',            'AdminController::listCompanies');
        $routes->post('companies',           'AdminController::createCompany');
        $routes->put('companies/(:num)',     'AdminController::updateCompany/$1');
        $routes->delete('companies/(:num)', 'AdminController::deleteCompany/$1');

        $routes->get('users',             'AdminController::listUsers');
        $routes->post('users',            'AdminController::createUser');
        $routes->put('users/(:num)',       'AdminController::updateUser/$1');

        $routes->get('teams',             'AdminController::listTeams');
        $routes->post('teams',            'AdminController::createTeam');
        $routes->get('teams/(:num)/members', 'AdminController::getTeamMembers/$1');
        $routes->put('teams/(:num)/members', 'AdminController::setTeamMembers/$1');

        $routes->get('settings/ticket-routing', 'AdminController::getRoutingSettings');
        $routes->put('settings/ticket-routing', 'AdminController::updateRoutingSettings');

        // Agentes de Desarrollo
        $routes->get('agents',            'AgentController::index');
    });
});
