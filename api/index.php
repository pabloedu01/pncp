<?php

require __DIR__ . '/../vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno no servidor.',
        'details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

$router = new \Bramus\Router\Router();

// Define a base path para o roteador, já que estamos dentro do diretório /api
$router->setBasePath('/api');

// Teste de rota
$router->get('/ping', function() {
    echo json_encode(['status' => 'ok', 'message' => 'API rodando!']);
});

// Autenticação
$router->post('/auth/register', '\App\Controllers\AuthController@register');
$router->post('/auth/login', '\App\Controllers\AuthController@login');
$router->post('/auth/verify-2fa', '\App\Controllers\AuthController@verify2fa');
$router->post('/auth/forgot-password', '\App\Controllers\AuthController@forgotPassword');
$router->post('/auth/reset-password', '\App\Controllers\AuthController@resetPassword');
$router->post('/auth/lost-2fa', '\App\Controllers\AuthController@lost2fa');
$router->post('/auth/reset-lost-2fa', '\App\Controllers\AuthController@resetLost2fa');

// Middleware para verificar JWT
$router->before('GET|POST|PUT|DELETE', '/empresas.*|/users.*', function() {
    \App\Utils\Auth::checkToken();
});

// Empresas (Prefeituras)
$router->get('/empresas', '\App\Controllers\EmpresaController@index');
$router->post('/empresas', '\App\Controllers\EmpresaController@store');

// Usuários (MASTER)
$router->get('/users', '\App\Controllers\UserController@index');
$router->post('/users/reset-2fa', '\App\Controllers\UserController@reset2fa');

$router->set404(function() {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Rota não encontrada']);
});

$router->run();
