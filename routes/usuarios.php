<?php

declare(strict_types=1);

// Rotas de /usuarios – chamadas a partir de index.php

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

// Apenas administradores podem acessar /usuarios
if (strtolower((string)$perfil) !== 'administrador') {
    http_response_code(403);
    echo 'Acesso restrito ao administrador.';
    exit;
}

/** @var PDO $pdo já existe vindo de index.php */

// Determinar subcaminho
$basePrefix = '/usuarios';
$subPath = substr($uri, strlen($basePrefix));
$subPath = $subPath === '' ? '/' : $subPath;

// ================
// GET /usuarios
// ================
if ($subPath === '/' && $method === 'GET') {
    $filtroNome   = trim((string)($_GET['nome'] ?? ''));
    $filtroFilial = trim((string)($_GET['filial'] ?? ''));
    $filtroAtivo  = isset($_GET['ativo']) ? trim((string)$_GET['ativo']) : '';
    $filtroCargo  = trim((string)($_GET['cargo'] ?? ''));
    $page         = max(1, (int)($_GET['page'] ?? 1));
    $perPage      = max(10, min(50, (int)($_GET['perPage'] ?? 15)));
    $offset       = ($page - 1) * $perPage;

    $cargoColumnName = null;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM usuarios");
        if ($chk) {
            while ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
                if (strtolower($row['Field'] ?? '') === 'cargo') {
                    $cargoColumnName = $row['Field'];
                    break;
                }
            }
        }
    } catch (Throwable $e) {
    }

    $sqlBase = "
        FROM usuarios u
        LEFT JOIN filiais f ON f.codigo = u.filial_codigo
    ";
    $where = [];
    $params = [];
    if ($filtroNome !== '') {
        $where[] = " (u.nome LIKE :nome OR u.usuario LIKE :usuario_like) ";
        $params[':nome'] = '%' . $filtroNome . '%';
        $params[':usuario_like'] = '%' . $filtroNome . '%';
    }
    if ($filtroFilial !== '') {
        $where[] = " (u.filial_codigo = :filial) ";
        $params[':filial'] = $filtroFilial;
    }
    if ($filtroAtivo === '1' || $filtroAtivo === 'ativo') {
        $where[] = " (u.ativo = 1) ";
    } elseif ($filtroAtivo === '0' || $filtroAtivo === 'inativo') {
        $where[] = " (u.ativo = 0 OR u.ativo IS NULL) ";
    }
    if ($cargoColumnName !== null && $filtroCargo !== '' && in_array($filtroCargo, ['Colaborador', 'Gestor', 'Supervisor'], true)) {
        $where[] = " (u.`" . str_replace('`', '``', $cargoColumnName) . "` = :cargo) ";
        $params[':cargo'] = $filtroCargo;
    }
    $whereClause = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) " . $sqlBase . $whereClause;
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalItens = (int) $stmtCount->fetchColumn();

    $totalPaginas = $totalItens > 0 ? (int) ceil($totalItens / $perPage) : 1;
    $page = min(max(1, $page), $totalPaginas);
    $offset = ($page - 1) * $perPage;

    // Nível de acesso: administrador → moderador → recepção → manutenção primeiro; depois os demais (ex.: comum), por id
    $orderPerfil = "
        ORDER BY
            CASE LOWER(TRIM(COALESCE(u.perfil, '')))
                WHEN 'administrador' THEN 1
                WHEN 'moderador' THEN 2
                WHEN 'recepcao' THEN 3
                WHEN 'manutencao' THEN 4
                ELSE 5
            END ASC,
            u.id ASC
    ";
    $sql = "SELECT u.*, f.codigo AS filial_nome " . $sqlBase . $whereClause . $orderPerfil . " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paginacao = [
        'totalItens'   => $totalItens,
        'limit'        => $perPage,
        'paginaAtual'  => $page,
        'totalPaginas' => $totalPaginas,
    ];

    // Lista filiais
    $stmtFiliais = $pdo->query("SELECT * FROM filiais ORDER BY CAST(codigo AS UNSIGNED)");
    $filiais = $stmtFiliais->fetchAll(PDO::FETCH_ASSOC);

    // Solicitações de cadastro pendentes (se tabela existir)
    $solicitacoes = [];
    try {
        $stmtSol = $pdo->query("
            SELECT sc.*, f.codigo AS filial_nome
            FROM solicitacoes_cadastro sc
            LEFT JOIN filiais f ON f.codigo = sc.filial_codigo
            WHERE sc.status = 'pendente'
            ORDER BY sc.data_solicitacao DESC
        ");
        $solicitacoes = $stmtSol->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $solicitacoes = [];
    }

    $filtros = [
        'nome'   => $filtroNome,
        'filial' => $filtroFilial,
        'ativo'  => $filtroAtivo,
        'cargo'  => $filtroCargo,
    ];

    view('usuarios', [
        'usuarios'     => $usuarios,
        'filiais'      => $filiais,
        'solicitacoes' => $solicitacoes,
        'filtros'      => $filtros,
        'paginacao'    => $paginacao,
        'perfil'       => $perfil,
        'msg'          => $_GET['msg'] ?? null,
        'erro'         => $_GET['erro'] ?? null,
    ]);
    return;
}

// ================
// POST /usuarios  (criar)
// ================
if ($subPath === '/' && $method === 'POST') {
    $usuarioPost  = trim($_POST['usuario'] ?? '');
    $senha        = trim($_POST['senha'] ?? '');
    $perfilPost   = trim($_POST['perfil'] ?? '');
    $filialCodigo = trim((string)($_POST['filial_codigo'] ?? ''));
    $nome         = trim($_POST['nome'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $cargo        = trim($_POST['cargo'] ?? '');
    if (!in_array($cargo, ['Colaborador', 'Gestor', 'Supervisor'], true)) {
        $cargo = 'Colaborador';
    }
    if ($nome !== '') {
        $nome = function_exists('mb_strtoupper') ? mb_strtoupper($nome, 'UTF-8') : strtoupper($nome);
    }

    if ($usuarioPost === '' || $senha === '' || $perfilPost === '' || $filialCodigo === '') {
        redirect('/usuarios?erro=' . urlencode('Preencha todos os campos obrigatórios.'));
    }

    $stmtFil = $pdo->prepare('SELECT 1 FROM filiais WHERE codigo = ?');
    $stmtFil->execute([$filialCodigo]);
    if (!$stmtFil->fetch()) {
        redirect('/usuarios?erro=' . urlencode('Filial inválida ou não encontrada.'));
    }

    try {
        // Verificar duplicidade de nome de usuário
        $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?)');
        $stmtCheck->execute([$usuarioPost]);
        if ($stmtCheck->fetch()) {
            redirect('/usuarios?erro=' . urlencode('Já existe usuário com esse nome.'));
        }

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (usuario, senha, perfil, filial_codigo, nome, email, cargo)
            VALUES (:usuario, :senha, :perfil, :filial_codigo, :nome, :email, :cargo)
        ");
        $stmt->execute([
            'usuario'   => $usuarioPost,
            'senha'     => password_hash($senha, PASSWORD_DEFAULT),
            'perfil'    => $perfilPost,
            'filial_codigo' => $filialCodigo,
            'nome'      => $nome !== '' ? $nome : null,
            'email'     => $email !== '' ? $email : null,
            'cargo'     => $cargo,
        ]);
        redirect('/usuarios?msg=' . urlencode('Usuário cadastrado com sucesso!'));
    } catch (PDOException $e) {
        if (strpos((string)$e->getMessage(), "Unknown column 'cargo'") !== false) {
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (usuario, senha, perfil, filial_codigo, nome, email)
                VALUES (:usuario, :senha, :perfil, :filial_codigo, :nome, :email)
            ");
            $stmt->execute([
                'usuario'   => $usuarioPost,
                'senha'     => password_hash($senha, PASSWORD_DEFAULT),
                'perfil'    => $perfilPost,
                'filial_codigo' => $filialCodigo,
                'nome'      => $nome !== '' ? $nome : null,
                'email'     => $email !== '' ? $email : null,
            ]);
            redirect('/usuarios?msg=' . urlencode('Usuário cadastrado com sucesso!'));
        }
        $msg = 'Erro ao cadastrar usuário: ' . $e->getMessage();
        redirect('/usuarios?erro=' . urlencode($msg));
    }
}

// ================
// POST /usuarios/toggle-ativo/{id}
// ================
if (preg_match('~^/toggle-ativo/([0-9]+)$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    try {
        $stmt = $pdo->prepare('SELECT id, usuario, ativo FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            redirect('/usuarios?erro=' . urlencode('Usuário não encontrado.'));
        }
        if ($row['usuario'] === 'admin' && (int)$row['id'] === 1) {
            redirect('/usuarios?erro=' . urlencode('Não é permitido inativar o administrador principal.'));
        }

        $novoAtivo = ((int)($row['ativo'] ?? 1)) ? 0 : 1;
        $stmtUp = $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
        $stmtUp->execute([$novoAtivo, $id]);

        $msg = $novoAtivo ? 'Usuário ativado com sucesso!' : 'Usuário inativado com sucesso!';
        redirect('/usuarios?msg=' . urlencode($msg));
    } catch (PDOException $e) {
        redirect('/usuarios?erro=' . urlencode('Erro ao alterar status do usuário.'));
    }
}

// ================
// GET /usuarios/editar/{id}
// ================
if (preg_match('~^/editar/([0-9]+)$~', $subPath, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $stmt = $pdo->prepare("
        SELECT u.*, f.codigo AS filial_nome
        FROM usuarios u
        LEFT JOIN filiais f ON f.codigo = u.filial_codigo
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $usuarioEditar = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuarioEditar) {
        redirect('/usuarios?erro=' . urlencode('Usuário não encontrado.'));
    }
    $stmtF = $pdo->query("SELECT * FROM filiais ORDER BY CAST(codigo AS UNSIGNED)");
    $filiais = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    view('usuarios_editar', [
        'u'        => $usuarioEditar,
        'filiais'  => $filiais,
        'perfil'   => $perfil,
        'msg'      => $_GET['msg'] ?? null,
        'erro'     => $_GET['erro'] ?? null,
    ]);
    return;
}

// ================
// POST /usuarios/editar/{id}
// ================
if (preg_match('~^/editar/([0-9]+)$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $usuarioPost  = trim($_POST['usuario'] ?? '');
    $senha        = trim($_POST['senha'] ?? '');
    $perfilPost   = trim($_POST['perfil'] ?? '');
    $filialCodigo = trim((string)($_POST['filial_codigo'] ?? ''));
    $nome         = trim($_POST['nome'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $cargo        = trim($_POST['cargo'] ?? '');
    if (!in_array($cargo, ['Colaborador', 'Gestor', 'Supervisor'], true)) {
        $cargo = 'Colaborador';
    }
    if ($nome !== '') {
        $nome = function_exists('mb_strtoupper') ? mb_strtoupper($nome, 'UTF-8') : strtoupper($nome);
    }

    if ($usuarioPost === '' || $perfilPost === '') {
        redirect('/usuarios/editar/' . $id . '?erro=' . urlencode('Usuário e perfil são obrigatórios.'));
    }

    if ($filialCodigo !== '') {
        $stmtFil = $pdo->prepare('SELECT 1 FROM filiais WHERE codigo = ?');
        $stmtFil->execute([$filialCodigo]);
        if (!$stmtFil->fetch()) {
            redirect('/usuarios/editar/' . $id . '?erro=' . urlencode('Filial inválida ou não encontrada.'));
        }
    }

    $stmt = $pdo->prepare('SELECT id, usuario FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        redirect('/usuarios?erro=' . urlencode('Usuário não encontrado.'));
    }

    // Não permitir alterar login do admin principal para outro
    $isMainAdmin = ($row['usuario'] ?? '') === 'admin' && (int)$row['id'] === 1;

    $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id != ?');
    $stmtCheck->execute([$usuarioPost, $id]);
    if ($stmtCheck->fetch()) {
        redirect('/usuarios/editar/' . $id . '?erro=' . urlencode('Já existe usuário com esse nome.'));
    }

    $cargoColumnName = 'cargo';
    try {
        $chkCol = $pdo->query("SHOW COLUMNS FROM usuarios");
        if ($chkCol) {
            while ($colRow = $chkCol->fetch(PDO::FETCH_ASSOC)) {
                if (strtolower($colRow['Field'] ?? '') === 'cargo') {
                    $cargoColumnName = $colRow['Field'];
                    break;
                }
            }
        }
    } catch (Throwable $e) {
    }

    $sql = "UPDATE usuarios SET usuario = :usuario, perfil = :perfil, filial_codigo = :filial_codigo, nome = :nome, email = :email, `" . str_replace('`', '``', $cargoColumnName) . "` = :cargo";
    $params = [
        'usuario'   => $usuarioPost,
        'perfil'    => $perfilPost,
        'filial_codigo' => $filialCodigo !== '' ? $filialCodigo : null,
        'nome'      => $nome !== '' ? $nome : null,
        'email'     => $email !== '' ? $email : null,
        'cargo'     => $cargo,
    ];
    if ($senha !== '') {
        $sql .= ", senha = :senha";
        $params['senha'] = password_hash($senha, PASSWORD_DEFAULT);
    }
    $sql .= " WHERE id = :id";
    $params['id'] = $id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        if (strpos((string)$e->getMessage(), "Unknown column") !== false && strpos((string)$e->getMessage(), "cargo") !== false) {
            $sql = "UPDATE usuarios SET usuario = :usuario, perfil = :perfil, filial_codigo = :filial_codigo, nome = :nome, email = :email";
            unset($params['cargo']);
            if ($senha !== '') {
                $sql .= ", senha = :senha";
            }
            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            throw $e;
        }
    }

    redirect('/usuarios?msg=' . urlencode('Usuário editado com sucesso!'));
}

// ================
// POST /usuarios/solicitacoes/aprovar/{id}
// ================
if (preg_match('~^/solicitacoes/aprovar/([0-9]+)$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $aprovadoPor = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM solicitacoes_cadastro WHERE id = ? AND status = "pendente"');
        $stmt->execute([$id]);
        $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$solicitacao) {
            $pdo->rollBack();
            redirect('/usuarios?erro=' . urlencode('Solicitação não encontrada ou já processada.'));
        }

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) OR email = ?');
        $stmt->execute([$solicitacao['usuario'], $solicitacao['email']]);
        if ($stmt->fetch()) {
            $pdo->prepare('UPDATE solicitacoes_cadastro SET status = "rejeitado", aprovado_por = ?, data_aprovacao = NOW(), motivo_rejeicao = ? WHERE id = ?')
                ->execute([$aprovadoPor, 'Usuário ou e-mail já cadastrado no sistema', $id]);
            $pdo->commit();
            redirect('/usuarios?erro=' . urlencode('Usuário ou e-mail já existe no sistema. Solicitação rejeitada automaticamente.'));
        }

        $nomeSol = trim((string)($solicitacao['nome'] ?? ''));
        $nomeAprovado = $nomeSol !== ''
            ? (function_exists('mb_strtoupper') ? mb_strtoupper($nomeSol, 'UTF-8') : strtoupper($nomeSol))
            : null;

        try {
            $pdo->prepare('INSERT INTO usuarios (usuario, senha, perfil, filial_codigo, nome, email, cargo) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $solicitacao['usuario'],
                    $solicitacao['senha'],
                    'comum',
                    $solicitacao['filial_codigo'] ?? null,
                    $nomeAprovado,
                    $solicitacao['email'] ?? null,
                    'Colaborador',
                ]);
        } catch (PDOException $ex) {
            if (strpos((string)$ex->getMessage(), "Unknown column 'cargo'") !== false) {
                $pdo->prepare('INSERT INTO usuarios (usuario, senha, perfil, filial_codigo, nome, email) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $solicitacao['usuario'],
                        $solicitacao['senha'],
                        'comum',
                        $solicitacao['filial_codigo'] ?? null,
                        $nomeAprovado,
                        $solicitacao['email'] ?? null,
                    ]);
            } else {
                throw $ex;
            }
        }

        $pdo->prepare('UPDATE solicitacoes_cadastro SET status = "aprovado", aprovado_por = ?, data_aprovacao = NOW() WHERE id = ?')
            ->execute([$aprovadoPor, $id]);

        $pdo->commit();
        redirect('/usuarios?msg=' . urlencode('Solicitação aprovada e usuário criado com sucesso!'));
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (($e->getCode() ?? 0) === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) {
            $pdo->prepare('UPDATE solicitacoes_cadastro SET status = "rejeitado", aprovado_por = ?, data_aprovacao = NOW(), motivo_rejeicao = ? WHERE id = ?')
                ->execute([$aprovadoPor, 'Usuário ou e-mail já cadastrado no sistema', $id]);
            redirect('/usuarios?erro=' . urlencode('Usuário ou e-mail já existe no sistema. Solicitação rejeitada automaticamente.'));
        }
        redirect('/usuarios?erro=' . urlencode('Erro ao aprovar solicitação: ' . $e->getMessage()));
    }
}

// ================
// POST /usuarios/solicitacoes/rejeitar/{id}
// ================
if (preg_match('~^/solicitacoes/rejeitar/([0-9]+)$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $rejeitadoPor = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $motivo = trim((string)($_POST['motivo'] ?? ''));
    if ($motivo === '') {
        $motivo = 'Solicitação rejeitada pelo administrador';
    }

    $stmt = $pdo->prepare('SELECT id FROM solicitacoes_cadastro WHERE id = ? AND status = "pendente"');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        redirect('/usuarios?erro=' . urlencode('Solicitação não encontrada ou já processada.'));
    }

    $pdo->prepare('UPDATE solicitacoes_cadastro SET status = "rejeitado", aprovado_por = ?, data_aprovacao = NOW(), motivo_rejeicao = ? WHERE id = ?')
        ->execute([$rejeitadoPor, $motivo, $id]);

    redirect('/usuarios?msg=' . urlencode('Solicitação rejeitada com sucesso!'));
}

// ================
// POST /usuarios/excluir/{id}
// ================
if (preg_match('~^/excluir/([0-9]+)$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    try {
        $stmt = $pdo->prepare('SELECT usuario FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            redirect('/usuarios?erro=' . urlencode('Usuário não encontrado.'));
        }
        if ($row['usuario'] === 'admin') {
            redirect('/usuarios?erro=' . urlencode('Não é permitido excluir o administrador principal.'));
        }

        $stmtDel = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmtDel->execute([$id]);

        redirect('/usuarios?msg=' . urlencode('Usuário excluído com sucesso!'));
    } catch (PDOException $e) {
        redirect('/usuarios?erro=' . urlencode('Erro ao excluir usuário.'));
    }
}

// ================
// Rota não encontrada em /usuarios
// ================
http_response_code(404);
view('404', ['perfil' => $perfil]);

