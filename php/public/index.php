<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ProjectsController;
use App\Controllers\CredentialsController;
use App\Controllers\SettingsController;
use App\Controllers\AccessControlController;

session_start();

$settings = [
    'api_url' => $_ENV['KEYMASTER_API_URL'] ?? 'http://localhost:8000',
    'api_key' => $_ENV['KEYMASTER_API_KEY'] ?? 'admin-secret',
    'admin_username' => $_ENV['ADMIN_USERNAME'] ?? 'admin',
    'admin_password' => $_ENV['ADMIN_PASSWORD'] ?? 'admin',
];

$api = new \App\Services\ApiService($settings['api_url'], $settings['api_key']);

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

function requireAuth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: /login');
        exit;
    }
}

function route($method, $path, $callback) {
    global $uri, $method;
    
    if ($method !== $callback[1]) return false;
    
    $pattern = preg_replace('/\{[a-z]+\}/', '([^/]+)', $path);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $uri, $matches)) {
        array_shift($matches);
        return $callback($matches);
    }
    return false;
}

function view($html) {
    echo $html;
    exit;
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

if ($method === 'GET' && $uri === '/') {
    requireAuth();
    $controller = new DashboardController($api);
    echo $controller->index();
    exit;
}

if ($method === 'GET' && $uri === '/login') {
    $controller = new AuthController($settings);
    echo $controller->login();
    exit;
}

if ($method === 'POST' && $uri === '/login') {
    $controller = new AuthController($settings);
    $controller->doLogin();
    exit;
}

if ($method === 'GET' && $uri === '/logout') {
    session_destroy();
    redirect('/login');
}

if ($method === 'GET' && $uri === '/credentials') {
    requireAuth();
    $controller = new CredentialsController($api);
    echo $controller->index();
    exit;
}

if ($method === 'GET' && preg_match('#^/credentials/([^/]+)/edit$#', $uri, $m)) {
    requireAuth();
    $controller = new CredentialsController($api);
    echo $controller->edit($m[1]);
    exit;
}

if ($method === 'PUT' && preg_match('#^/credentials/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $controller = new CredentialsController($api);
    $controller->update($m[1]);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/credentials/([^/]+)$#', $uri, $m)) {
    requireAuth();
    $controller = new CredentialsController($api);
    $controller->delete($m[1]);
    exit;
}

if ($method === 'GET' && $uri === '/credentials/new') {
    requireAuth();
    $controller = new CredentialsController($api);
    echo $controller->new();
    exit;
}

if ($method === 'POST' && $uri === '/credentials') {
    requireAuth();
    $controller = new CredentialsController($api);
    $controller->create();
    exit;
}

if ($method === 'POST' && $uri === '/credentials/groups') {
    requireAuth();
    $controller = new CredentialsController($api);
    $controller->createGroup();
    exit;
}

if ($method === 'POST' && $uri === '/credentials/register') {
    requireAuth();
    $controller = new CredentialsController($api);
    $controller->register();
    exit;
}

if ($method === 'GET' && $uri === '/projects') {
    requireAuth();
    $controller = new ProjectsController($api);
    echo $controller->index();
    exit;
}

if ($method === 'GET' && $uri === '/projects/new') {
    requireAuth();
    $controller = new ProjectsController($api);
    echo $controller->new();
    exit;
}

if ($method === 'POST' && $uri === '/projects') {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->create();
    exit;
}

if ($method === 'GET' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    echo $controller->show($m[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/projects/(\d+)/edit$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    echo $controller->edit($m[1]);
    exit;
}

if ($method === 'PUT' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->update($m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)$#', $uri, $m) && isset($_POST['_METHOD']) && $_POST['_METHOD'] === 'DELETE') {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->delete($m[1]);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/projects/(\d+)$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->delete($m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)/credentials$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->addCredential($m[1]);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/projects/(\d+)/credentials/(.+)$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->removeCredential($m[1], $m[2]);
    exit;
}

if ($method === 'POST' && preg_match('#^/projects/(\d+)/ips$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->addIp($m[1]);
    exit;
}

if ($method === 'DELETE' && preg_match('#^/projects/(\d+)/ips/(.+)$#', $uri, $m)) {
    requireAuth();
    $controller = new ProjectsController($api);
    $controller->removeIp($m[1], $m[2]);
    exit;
}

if ($method === 'GET' && $uri === '/settings') {
    requireAuth();
    $controller = new SettingsController($api);
    echo $controller->index();
    exit;
}

if ($method === 'POST' && $uri === '/settings' && isset($_POST['_METHOD']) && $_POST['_METHOD'] === 'PUT') {
    requireAuth();
    $controller = new SettingsController($api);
    $controller->update();
    exit;
}

if ($method === 'GET' && $uri === '/access-control') {
    requireAuth();
    $controller = new AccessControlController($api);
    echo $controller->index();
    exit;
}

http_response_code(404);
echo "Not Found";
