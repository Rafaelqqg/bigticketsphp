<?php

declare(strict_types=1);

if (!function_exists('current_user') || !function_exists('current_profile')) {
    http_response_code(500);
    echo 'Funções de sessão não encontradas.';
    exit;
}

$usuario = current_user();
$perfil = current_profile() ?? '';

if (!$usuario) {
    redirect('/login');
}
if (strtolower((string)$perfil) === 'comum') {
    redirect('/tickets/novo');
}

$basePrefix = '/cadastro';
$subPath = substr($uri, strlen($basePrefix));
$subPath = $subPath === '' ? '/' : $subPath;

// Compatibilidade completa das rotas antigas de cadastro de filiais.
if ($subPath === '/' && $method === 'GET') {
    redirect('/filiais');
}
if ($subPath === '/filiais' && $method === 'GET') {
    redirect('/filiais');
}
if ($subPath === '/filial' && $method === 'POST') {
    $_POST['cnpj'] = $_POST['cnpj'] ?? $_POST['nome'] ?? '';
    $uri = '/filiais';
    require __DIR__ . '/filiais.php';
    return;
}
if (preg_match('~^/filiais/editar/([^/]+)$~', $subPath, $m) && $method === 'GET') {
    redirect('/filiais');
}
if (preg_match('~^/filiais/editar/([^/]+)$~', $subPath, $m) && $method === 'POST') {
    $uri = '/filiais/editar/' . $m[1];
    require __DIR__ . '/filiais.php';
    return;
}

http_response_code(404);
view('404', ['perfil' => $perfil]);
