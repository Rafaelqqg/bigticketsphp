<?php

declare(strict_types=1);

if (!function_exists('current_user') || !function_exists('current_profile')) {
    http_response_code(500);
    echo 'Funções de sessão não encontradas.';
    exit;
}

$usuario = current_user();
$perfil  = current_profile() ?? '';

if (!$usuario) {
    redirect('/login');
}

// Apenas perfis não "comum" podem gerenciar filiais (admin/moderador/recepção)
if (strtolower((string)$perfil) === 'comum') {
    redirect('/tickets/novo');
}

/** @var PDO $pdo */

$basePrefix = '/filiais';
$subPath = substr($uri, strlen($basePrefix));
$subPath = $subPath === '' ? '/' : $subPath;

// ================
// GET /filiais
// ================
if ($subPath === '/' && $method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM filiais ORDER BY CAST(codigo AS UNSIGNED)');
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    view('filiais', [
        'filiais' => $filiais,
        'perfil'  => $perfil,
        'msg'     => $_GET['msg'] ?? null,
        'erro'    => $_GET['erro'] ?? null,
    ]);
    return;
}

// ================
// POST /filiais  (criar)
// ================
if ($subPath === '/' && $method === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $cnpj   = trim($_POST['cnpj'] ?? '');

    if ($codigo === '' || $cnpj === '') {
        redirect('/filiais?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO filiais (codigo, cnpj) VALUES (?, ?)');
        $stmt->execute([$codigo, $cnpj]);
        redirect('/filiais?msg=' . urlencode('Filial cadastrada com sucesso!'));
    } catch (PDOException $e) {
        $erroMsg = 'Erro ao cadastrar filial.';
        if (in_array($e->errorInfo[1] ?? 0, [1062], true)) {
            $erroMsg = 'Já existe uma filial com esse código!';
        }
        redirect('/filiais?erro=' . urlencode($erroMsg));
    }
}

// ================
// POST /filiais/editar/{codigo}
// ================
if (preg_match('~^/editar/([^/]+)$~', $subPath, $m) && $method === 'POST') {
    $codigoAntigo = $m[1];
    $codigo       = trim($_POST['codigo'] ?? '');
    $cnpj         = trim($_POST['cnpj'] ?? '');

    if ($codigo === '' || $cnpj === '') {
        redirect('/filiais?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
    }

    try {
        $stmt = $pdo->prepare('UPDATE filiais SET codigo = ?, cnpj = ? WHERE codigo = ?');
        $stmt->execute([$codigo, $cnpj, $codigoAntigo]);
        redirect('/filiais?msg=' . urlencode('Filial editada com sucesso!'));
    } catch (PDOException $e) {
        $erroMsg = 'Erro ao editar filial.';
        if (in_array($e->errorInfo[1] ?? 0, [1062], true)) {
            $erroMsg = 'Já existe uma filial com esse código!';
        }
        redirect('/filiais?erro=' . urlencode($erroMsg));
    }
}

// ================
// Rota não encontrada em /filiais
// ================
http_response_code(404);
view('404', ['perfil' => $perfil]);

