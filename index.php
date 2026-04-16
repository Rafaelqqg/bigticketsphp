<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

// Descobre path atual sem query string
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Remove o prefixo do diretório do projeto (ex.: /bigticketsphp)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir));
}

$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Se o usuário da sessão foi inativado no banco, desloga automaticamente.
$sessionUser = current_user();
if (is_array($sessionUser) && isset($sessionUser['id'])) {
    try {
        $stmtSession = $pdo->prepare('SELECT id, ativo FROM usuarios WHERE id = ?');
        $stmtSession->execute([(int)$sessionUser['id']]);
        $sessionRow = $stmtSession->fetch(PDO::FETCH_ASSOC);
        $isInactive = !$sessionRow || (int)($sessionRow['ativo'] ?? 1) !== 1;
        if ($isInactive) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            redirect('/login?erro=' . urlencode('Usuário inativo. Entre em contato com o setor de Tecnologia da Informação.'));
        }
    } catch (Throwable $e) {
        // Em caso de erro na checagem, mantém fluxo normal para não derrubar a aplicação.
    }
}

// Rotas de tickets (delegadas para arquivo dedicado)
if (str_starts_with($uri, '/tickets')) {
    require __DIR__ . '/routes/tickets.php';
    exit;
}

// Rotas de usuários (apenas admin) – delegadas
if (str_starts_with($uri, '/usuarios')) {
    require __DIR__ . '/routes/usuarios.php';
    exit;
}

// Rotas de filiais – delegadas
if (str_starts_with($uri, '/filiais')) {
    require __DIR__ . '/routes/filiais.php';
    exit;
}

// Rotas de cadastro (compatibilidade legado)
if (str_starts_with($uri, '/cadastro')) {
    require __DIR__ . '/routes/cadastro.php';
    exit;
}

// Rotas de relatório – delegadas
if (str_starts_with($uri, '/relatorio')) {
    require __DIR__ . '/routes/relatorio.php';
    exit;
}

// Health check
if ($uri === '/health' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo->query('SELECT 1');
        echo json_encode(['ok' => true, 'database' => 'ok'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'database' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// API (ex.: último ticket)
if ($uri === '/api/tickets/ultimo' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmt = $pdo->query('SELECT id FROM tickets ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: (object)[]);
    } catch (Throwable $e) {
        echo json_encode((object)[]);
    }
    exit;
}

// API CSAT (média de avaliação para dashboard)
if ($uri === '/api/dashboard/csat' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $usuario = current_user();
    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }
    $perfil = current_profile() ?? 'comum';
    if ($perfil === 'comum') {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso restrito']);
        exit;
    }
    $categoriasRecepcao = ['Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
    $categoriasManutencao = [categoria_manutencao_storage()];
    $whereRecepcao = '';
    $paramsRecepcao = [];
    $plC = strtolower((string)$perfil);
    if ($plC === 'recepcao') {
        $placeholders = implode(',', array_fill(0, count($categoriasRecepcao), '?'));
        $whereRecepcao = " WHERE categoria IN ($placeholders)";
        $paramsRecepcao = $categoriasRecepcao;
    } elseif ($plC === 'manutencao') {
        $placeholders = implode(',', array_fill(0, count($categoriasManutencao), '?'));
        $whereRecepcao = " WHERE categoria IN ($placeholders)";
        $paramsRecepcao = $categoriasManutencao;
    }
    $inicio = date('Y-m-d', strtotime('-6 days'));
    $fim = date('Y-m-d');
    $csat = [
        'csat_semanal' => null, 'csat_mensal' => null,
        'csat_semanal_qtd' => 0, 'csat_mensal_qtd' => 0,
        'periodo_semanal' => date('d/m', strtotime($inicio)) . ' a ' . date('d/m', strtotime($fim)),
    ];
    try {
        $sqlS = "SELECT AVG(avaliacao) AS media, COUNT(*) AS qtd FROM tickets" . ($whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao) . " AND avaliacao IS NOT NULL AND avaliacao BETWEEN 1 AND 5 AND DATE(avaliacao_em) >= ? AND DATE(avaliacao_em) <= ?";
        $stmtS = $pdo->prepare($sqlS);
        $paramsS = $whereRecepcao !== '' ? array_merge($paramsRecepcao, [$inicio, $fim]) : [$inicio, $fim];
        $stmtS->execute($paramsS);
        $rowS = $stmtS->fetch(PDO::FETCH_ASSOC);
        if ($rowS && (int)($rowS['qtd'] ?? 0) > 0) {
            $csat['csat_semanal'] = round((float)($rowS['media'] ?? 0), 1);
            $csat['csat_semanal_qtd'] = (int)($rowS['qtd'] ?? 0);
        }
        $sqlM = "SELECT AVG(avaliacao) AS media, COUNT(*) AS qtd FROM tickets" . ($whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao) . " AND avaliacao IS NOT NULL AND avaliacao BETWEEN 1 AND 5 AND avaliacao_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $stmtM = $whereRecepcao === '' ? $pdo->query($sqlM) : $pdo->prepare($sqlM);
        if ($whereRecepcao !== '') $stmtM->execute($paramsRecepcao);
        $rowM = $stmtM->fetch(PDO::FETCH_ASSOC);
        if ($rowM && (int)($rowM['qtd'] ?? 0) > 0) {
            $csat['csat_mensal'] = round((float)($rowM['media'] ?? 0), 1);
            $csat['csat_mensal_qtd'] = (int)($rowM['qtd'] ?? 0);
        }
    } catch (Throwable $e) {}
    echo json_encode($csat, JSON_UNESCAPED_UNICODE);
    exit;
}

// API Stats (KPIs e Resumo do dia)
if ($uri === '/api/dashboard/stats' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $usuario = current_user();
    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }
    $perfil = current_profile() ?? 'comum';
    if ($perfil === 'comum') {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso restrito']);
        exit;
    }

    $categoriasRecepcao = ['Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
    $categoriasManutencao = [categoria_manutencao_storage()];
    $whereRecepcao = '';
    $paramsRecepcao = [];
    $plS = strtolower((string)$perfil);
    if ($plS === 'recepcao') {
        $placeholders = implode(',', array_fill(0, count($categoriasRecepcao), '?'));
        $whereRecepcao = " WHERE categoria IN ($placeholders)";
        $paramsRecepcao = $categoriasRecepcao;
    } elseif ($plS === 'manutencao') {
        $placeholders = implode(',', array_fill(0, count($categoriasManutencao), '?'));
        $whereRecepcao = " WHERE categoria IN ($placeholders)";
        $paramsRecepcao = $categoriasManutencao;
    }

    $baseWhere = $whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao;

    $stats = [
        'total' => 0,
        'abertos' => 0,
        'em_andamento' => 0,
        'fechados' => 0,
        'novos_hoje' => 0,
        'fechados_hoje' => 0,
        'urgentes_abertos' => 0,
        'pendentes_avaliacao' => 0,
        'abertos_3dias' => 0,
        'usuarios_pendentes_aprovacao' => 0,
    ];

    // Totais por status (histórico geral)
    try {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM tickets" . $baseWhere . " GROUP BY status");
        $stmt->execute($paramsRecepcao);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = strtolower((string)($row['status'] ?? ''));
            $qtd = (int)($row['total'] ?? 0);
            $stats['total'] += $qtd;
            if ($status === 'aberto') {
                $stats['abertos'] = $qtd;
            } elseif ($status === 'em_andamento' || $status === 'em andamento') {
                $stats['em_andamento'] = $qtd;
            }
        }
    } catch (Throwable $e) {}

    // Fechados (histórico geral)
    try {
        $whereFechados = " AND (status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido')";
        $sqlFechados = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE 1=1" . $whereFechados
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . $whereFechados;
        $stmtFechados = $pdo->prepare($sqlFechados);
        $stmtFechados->execute($paramsRecepcao);
        $rowFechados = $stmtFechados->fetch(PDO::FETCH_ASSOC);
        $stats['fechados'] = $rowFechados ? (int)($rowFechados['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    // Tickets criados hoje
    try {
        $sqlHoje = "SELECT COUNT(*) AS total FROM tickets" . $baseWhere . " AND DATE(created_at) = CURDATE()";
        $stmtHoje = $pdo->prepare($sqlHoje);
        $stmtHoje->execute($paramsRecepcao);
        $rowHoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);
        if ($rowHoje) {
            $stats['novos_hoje'] = (int)($rowHoje['total'] ?? 0);
        }
    } catch (Throwable $e) {}

    // Resumo do dia: fechados hoje
    try {
        $whereFechado = "(status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido') AND fechado_em IS NOT NULL AND DATE(fechado_em) = CURDATE()";
        $sqlFechadosHoje = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereFechado
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereFechado;
        $stmtFechados = $pdo->prepare($sqlFechadosHoje);
        $stmtFechados->execute($paramsRecepcao);
        $rowFechados = $stmtFechados->fetch(PDO::FETCH_ASSOC);
        $stats['fechados_hoje'] = $rowFechados ? (int)($rowFechados['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    // Resumo do dia: urgentes (Alta prioridade, ainda abertos, criados hoje)
    try {
        $whereUrgentes = "(prioridade = 'Alta' OR prioridade = 'alta') AND status NOT IN ('fechado', 'resolvido', 'Fechado', 'Resolvido') AND DATE(created_at) = CURDATE()";
        $sqlUrgentes = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereUrgentes
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereUrgentes;
        $stmtUrgentes = $pdo->prepare($sqlUrgentes);
        $stmtUrgentes->execute($paramsRecepcao);
        $rowUrgentes = $stmtUrgentes->fetch(PDO::FETCH_ASSOC);
        $stats['urgentes_abertos'] = $rowUrgentes ? (int)($rowUrgentes['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    // Resumo do dia: pendentes de avaliação (fechados hoje sem CSAT)
    try {
        $wherePendentes = "(status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido') AND avaliacao IS NULL AND fechado_em IS NOT NULL AND DATE(fechado_em) = CURDATE()";
        $sqlPendentes = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $wherePendentes
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $wherePendentes;
        $stmtPendentes = $pdo->prepare($sqlPendentes);
        $stmtPendentes->execute($paramsRecepcao);
        $rowPendentes = $stmtPendentes->fetch(PDO::FETCH_ASSOC);
        $stats['pendentes_avaliacao'] = $rowPendentes ? (int)($rowPendentes['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    // Tickets abertos há mais de 3 dias (para o indicador do card ABERTOS)
    try {
        $whereAbertos3d = "(status = 'aberto' OR status = 'Aberto') AND created_at < (NOW() - INTERVAL 3 DAY)";
        $sqlAbertos3d = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereAbertos3d
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereAbertos3d;
        $stmtAbertos3d = $pdo->prepare($sqlAbertos3d);
        $stmtAbertos3d->execute($paramsRecepcao);
        $rowAbertos3d = $stmtAbertos3d->fetch(PDO::FETCH_ASSOC);
        $stats['abertos_3dias'] = $rowAbertos3d ? (int)($rowAbertos3d['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    // Usuários com solicitação de cadastro pendente de aprovação
    try {
        $stmtPendUsuarios = $pdo->query("SELECT COUNT(*) AS total FROM solicitacoes_cadastro WHERE status = 'pendente'");
        $rowPendUsuarios = $stmtPendUsuarios ? $stmtPendUsuarios->fetch(PDO::FETCH_ASSOC) : null;
        $stats['usuarios_pendentes_aprovacao'] = $rowPendUsuarios ? (int)($rowPendUsuarios['total'] ?? 0) : 0;
    } catch (Throwable $e) {}

    echo json_encode($stats, JSON_UNESCAPED_UNICODE);
    exit;
}

// Rotas básicas (equivalentes às de auth.js)
switch ($uri) {
    case '/':
        $usuario = current_user();
        if (!$usuario) {
            redirect('/login');
        }

        $perfil = current_profile() ?? 'comum';

        // Perfil "comum" não usa dashboard executivo
        if ($perfil === 'comum') {
            redirect('/tickets/novo');
        }

        // Dashboard para perfis administrativos
        // Recepção: categorias Tonner/Drum/...; Manutenção: BD grava manutencao; exibição com acento nas views
        $categoriasRecepcao = ['Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
        $categoriasManutencao = [categoria_manutencao_storage()];
        $whereRecepcao = '';
        $paramsRecepcao = [];
        $perfilLowerDash = strtolower((string)$perfil);
        if ($perfilLowerDash === 'recepcao') {
            $placeholders = implode(',', array_fill(0, count($categoriasRecepcao), '?'));
            $whereRecepcao = " WHERE categoria IN ($placeholders)";
            $paramsRecepcao = $categoriasRecepcao;
        } elseif ($perfilLowerDash === 'manutencao') {
            $placeholders = implode(',', array_fill(0, count($categoriasManutencao), '?'));
            $whereRecepcao = " WHERE categoria IN ($placeholders)";
            $paramsRecepcao = $categoriasManutencao;
        }

        $baseWhere = $whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao;

        // Estatísticas do mês atual
        $stats = [
            'total'        => 0,
            'abertos'      => 0,
            'em_andamento' => 0,
            'fechados'     => 0,
            'novos_hoje'   => 0,
            'usuarios_pendentes_aprovacao' => 0,
        ];

        // Totais por status (histórico geral)
        $stmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM tickets" . $baseWhere . " GROUP BY status");
        $stmt->execute($paramsRecepcao);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = strtolower((string)($row['status'] ?? ''));
            $qtd    = (int)($row['total'] ?? 0);
            $stats['total'] += $qtd;
            if ($status === 'aberto') {
                $stats['abertos'] = $qtd;
            } elseif ($status === 'em_andamento' || $status === 'em andamento') {
                $stats['em_andamento'] = $qtd;
            }
            // fechados: contamos separadamente (tickets fechados no mês)
        }

        // Fechados (histórico geral)
        $whereFechados = " AND (status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido')";
        $sqlFechados = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE 1=1" . $whereFechados
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . $whereFechados;
        $stmtFechados = $pdo->prepare($sqlFechados);
        $stmtFechados->execute($paramsRecepcao);
        $rowFechados = $stmtFechados->fetch(PDO::FETCH_ASSOC);
        $stats['fechados'] = $rowFechados ? (int)($rowFechados['total'] ?? 0) : 0;

        // Tickets criados hoje
        $sqlHoje = "SELECT COUNT(*) AS total FROM tickets" . $baseWhere . " AND DATE(created_at) = CURDATE()";
        $stmtHoje = $pdo->prepare($sqlHoje);
        $stmtHoje->execute($paramsRecepcao);
        $rowHoje  = $stmtHoje->fetch(PDO::FETCH_ASSOC);
        if ($rowHoje) {
            $stats['novos_hoje'] = (int)($rowHoje['total'] ?? 0);
        }

        // Resumo do dia: fechados hoje (não usa filtro de data - é sempre "hoje")
        $whereFechado = "(status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido') AND fechado_em IS NOT NULL AND DATE(fechado_em) = CURDATE()";
        $sqlFechadosHoje = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereFechado
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereFechado;
        $stmtFechados = $pdo->prepare($sqlFechadosHoje);
        $stmtFechados->execute($paramsRecepcao);
        $rowFechados = $stmtFechados->fetch(PDO::FETCH_ASSOC);
        $stats['fechados_hoje'] = $rowFechados ? (int)($rowFechados['total'] ?? 0) : 0;

        // Resumo do dia: urgentes (Alta prioridade, ainda abertos, criados hoje)
        $whereUrgentes = "(prioridade = 'Alta' OR prioridade = 'alta') AND status NOT IN ('fechado', 'resolvido', 'Fechado', 'Resolvido') AND DATE(created_at) = CURDATE()";
        $sqlUrgentes = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereUrgentes
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereUrgentes;
        $stmtUrgentes = $pdo->prepare($sqlUrgentes);
        $stmtUrgentes->execute($paramsRecepcao);
        $rowUrgentes = $stmtUrgentes->fetch(PDO::FETCH_ASSOC);
        $stats['urgentes_abertos'] = $rowUrgentes ? (int)($rowUrgentes['total'] ?? 0) : 0;

        // Resumo do dia: pendentes de avaliação (fechados hoje sem CSAT)
        $wherePendentes = "(status = 'fechado' OR status = 'resolvido' OR status = 'Fechado' OR status = 'Resolvido') AND avaliacao IS NULL AND fechado_em IS NOT NULL AND DATE(fechado_em) = CURDATE()";
        $sqlPendentes = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $wherePendentes
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $wherePendentes;
        try {
        $stmtPendentes = $pdo->prepare($sqlPendentes);
        $stmtPendentes->execute($paramsRecepcao);
        $rowPendentes = $stmtPendentes->fetch(PDO::FETCH_ASSOC);
        $stats['pendentes_avaliacao'] = $rowPendentes ? (int)($rowPendentes['total'] ?? 0) : 0;

        // Tickets abertos há mais de 3 dias (qualquer mês)
        $whereAbertos3d = "(status = 'aberto' OR status = 'Aberto') AND created_at < (NOW() - INTERVAL 3 DAY)";
        $sqlAbertos3d = $whereRecepcao === ''
            ? "SELECT COUNT(*) AS total FROM tickets WHERE " . $whereAbertos3d
            : "SELECT COUNT(*) AS total FROM tickets" . $whereRecepcao . " AND " . $whereAbertos3d;
        $stmtAbertos3d = $pdo->prepare($sqlAbertos3d);
        $stmtAbertos3d->execute($paramsRecepcao);
        $rowAbertos3d = $stmtAbertos3d->fetch(PDO::FETCH_ASSOC);
        $stats['abertos_3dias'] = $rowAbertos3d ? (int)($rowAbertos3d['total'] ?? 0) : 0;
        } catch (Throwable $e) {
            $stats['pendentes_avaliacao'] = 0;
        }

        // Solicitações de novo usuário pendentes (somente para admin)
        if (strtolower((string)$perfil) === 'administrador') {
            try {
                $stmtPendUsuarios = $pdo->query("SELECT COUNT(*) AS total FROM solicitacoes_cadastro WHERE status = 'pendente'");
                $rowPendUsuarios = $stmtPendUsuarios ? $stmtPendUsuarios->fetch(PDO::FETCH_ASSOC) : null;
                $stats['usuarios_pendentes_aprovacao'] = $rowPendUsuarios ? (int)($rowPendUsuarios['total'] ?? 0) : 0;
            } catch (Throwable $e) {
                $stats['usuarios_pendentes_aprovacao'] = 0;
            }
        }

        // Média de avaliação (CSAT) - semanal (mesmo período do gráfico: -6 dias a hoje) e mensal
        $inicioGrafico = date('Y-m-d', strtotime('-6 days'));
        $fimGrafico = date('Y-m-d');
        $stats['csat_semanal'] = null;
        $stats['csat_mensal'] = null;
        $stats['csat_semanal_qtd'] = 0;
        $stats['csat_mensal_qtd'] = 0;
        $stats['periodo_semanal'] = date('d/m', strtotime($inicioGrafico)) . ' a ' . date('d/m', strtotime($fimGrafico));
        try {
            $sqlCsatSemanal = "SELECT AVG(avaliacao) AS media, COUNT(*) AS qtd FROM tickets" . ($whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao) . " AND avaliacao IS NOT NULL AND avaliacao BETWEEN 1 AND 5 AND DATE(avaliacao_em) >= ? AND DATE(avaliacao_em) <= ?";
            $stmtCsatS = $pdo->prepare($sqlCsatSemanal);
            $paramsCsatS = $whereRecepcao !== '' ? array_merge($paramsRecepcao, [$inicioGrafico, $fimGrafico]) : [$inicioGrafico, $fimGrafico];
            $stmtCsatS->execute($paramsCsatS);
            $rowCsatS = $stmtCsatS->fetch(PDO::FETCH_ASSOC);
            if ($rowCsatS && (int)($rowCsatS['qtd'] ?? 0) > 0) {
                $stats['csat_semanal'] = round((float)($rowCsatS['media'] ?? 0), 1);
                $stats['csat_semanal_qtd'] = (int)($rowCsatS['qtd'] ?? 0);
            }
            $sqlCsatMensal = "SELECT AVG(avaliacao) AS media, COUNT(*) AS qtd FROM tickets" . ($whereRecepcao === '' ? " WHERE 1=1" : $whereRecepcao) . " AND avaliacao IS NOT NULL AND avaliacao BETWEEN 1 AND 5 AND avaliacao_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
            $stmtCsatM = $whereRecepcao === '' ? $pdo->query($sqlCsatMensal) : $pdo->prepare($sqlCsatMensal);
            if ($whereRecepcao !== '') $stmtCsatM->execute($paramsRecepcao);
            $rowCsatM = $stmtCsatM->fetch(PDO::FETCH_ASSOC);
            if ($rowCsatM && (int)($rowCsatM['qtd'] ?? 0) > 0) {
                $stats['csat_mensal'] = round((float)($rowCsatM['media'] ?? 0), 1);
                $stats['csat_mensal_qtd'] = (int)($rowCsatM['qtd'] ?? 0);
            }
        } catch (Throwable $e) {
            // Coluna avaliacao pode não existir
        }

        // Últimos 5 tickets
        // Selo: comentário do solicitante (login = solicitante) e que não é perfil de equipe.
        // Inclui perfil NULL/vazio no BD (legado), que na prática é usuário comum.
        $sqlSolicitanteComentou = ",
                   CASE WHEN EXISTS (
                       SELECT 1 FROM comentarios c
                       LEFT JOIN usuarios u ON LOWER(TRIM(COALESCE(u.usuario, ''))) = LOWER(TRIM(COALESCE(c.autor, '')))
                       WHERE c.ticket_id = t.id
                         AND TRIM(COALESCE(c.autor, '')) <> ''
                         AND LOWER(TRIM(COALESCE(c.autor, ''))) = LOWER(TRIM(COALESCE(t.solicitante, '')))
                         AND (
                           u.usuario IS NULL
                           OR LOWER(TRIM(COALESCE(u.perfil, ''))) NOT IN ('administrador', 'moderador', 'recepcao', 'manutencao')
                         )
                   ) THEN 1 ELSE 0 END AS solicitante_comentou";
        if ($perfilLowerDash === 'recepcao') {
            $sqlRecentWhere = " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
            $paramsRecent = $categoriasRecepcao;
        } elseif ($perfilLowerDash === 'manutencao') {
            $sqlRecentWhere = " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
            $paramsRecent = $categoriasManutencao;
        } else {
            $sqlRecentWhere = " WHERE 1=1";
            $paramsRecent = [];
        }
        $sqlRecentSuffix = " ORDER BY t.created_at DESC LIMIT 5";
        $recentTickets = [];
        try {
            $sqlRecentBase = "
                SELECT t.*, f.codigo AS filial_nome,
                       COALESCE(u_sol.nome, u_sol.usuario, t.solicitante) AS solicitante_nome,
                       COALESCE(u_resp.nome, u_resp.usuario, 'Não atribuído') AS responsavel_nome,
                       COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome
                       {$sqlSolicitanteComentou}
                FROM tickets t
                LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
                LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario
                LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario";
            $stmtRecent = $pdo->prepare($sqlRecentBase . $sqlRecentWhere . $sqlRecentSuffix);
            $stmtRecent->execute($paramsRecent);
            $recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $colMissing = str_contains((string)$e->getMessage(), 'fechado_por');
            try {
                if ($colMissing) {
                    $sqlRecentBase = "
                        SELECT t.*, f.codigo AS filial_nome,
                               COALESCE(u_sol.nome, u_sol.usuario, t.solicitante) AS solicitante_nome,
                               COALESCE(u_resp.nome, u_resp.usuario, 'Não atribuído') AS responsavel_nome
                               {$sqlSolicitanteComentou}
                        FROM tickets t
                        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
                        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario";
                } else {
                    $sqlRecentBase = "
                        SELECT t.*, f.codigo AS filial_nome,
                               COALESCE(u_sol.nome, u_sol.usuario, t.solicitante) AS solicitante_nome,
                               COALESCE(u_resp.nome, u_resp.usuario, 'Não atribuído') AS responsavel_nome,
                               COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome
                        FROM tickets t
                        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
                        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario
                        LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario";
                }
                $stmtRecent = $pdo->prepare($sqlRecentBase . $sqlRecentWhere . $sqlRecentSuffix);
                $stmtRecent->execute($paramsRecent);
                $recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                try {
                    $sqlRecentBase = "
                        SELECT t.*, f.codigo AS filial_nome,
                               COALESCE(u_sol.nome, u_sol.usuario, t.solicitante) AS solicitante_nome,
                               COALESCE(u_resp.nome, u_resp.usuario, 'Não atribuído') AS responsavel_nome,
                               COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome
                        FROM tickets t
                        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
                        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario
                        LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario";
                    $stmtRecent = $pdo->prepare($sqlRecentBase . $sqlRecentWhere . $sqlRecentSuffix);
                    $stmtRecent->execute($paramsRecent);
                    $recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $e3) {
                    $sqlRecentBase = "
                        SELECT t.*, f.codigo AS filial_nome,
                               COALESCE(u_sol.nome, u_sol.usuario, t.solicitante) AS solicitante_nome,
                               COALESCE(u_resp.nome, u_resp.usuario, 'Não atribuído') AS responsavel_nome
                        FROM tickets t
                        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
                        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario";
                    $stmtRecent = $pdo->prepare($sqlRecentBase . $sqlRecentWhere . $sqlRecentSuffix);
                    $stmtRecent->execute($paramsRecent);
                    $recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }

        view('index', [
            'perfil'        => $perfil,
            'usuario'       => $usuario,
            'stats'         => $stats,
            'recentTickets' => $recentTickets,
        ]);
        break;

    case '/login':
        if ($method === 'GET') {
            $erro = $_GET['erro'] ?? null;
            $msg  = $_GET['msg'] ?? null;
            view('login', ['erro' => $erro, 'msg' => $msg]);
            break;
        }

        if ($method === 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $senha   = trim($_POST['senha'] ?? '');

            if ($usuario === '' || $senha === '') {
                view('login', ['erro' => 'Preencha todos os campos.', 'msg' => null]);
                break;
            }

            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ?');
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $senhaValida = false;
            if ($user && isset($user['senha'])) {
                if (password_verify($senha, $user['senha'])) {
                    $senhaValida = true;
                } elseif (strlen($user['senha']) < 60 && $user['senha'] === $senha) {
                    // Compatibilidade: senha antiga em texto puro - re-hash e atualiza
                    $senhaValida = true;
                    $stmtUp = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
                    $stmtUp->execute([password_hash($senha, PASSWORD_DEFAULT), (int)$user['id']]);
                }
            }
            if (!$senhaValida) {
                view('login', ['erro' => 'Usuário ou senha inválidos.', 'msg' => null]);
                break;
            }

            if ((int)($user['ativo'] ?? 1) !== 1) {
                view('login', ['erro' => 'Usuário inativo. Entre em contato com o setor de Tecnologia da Informação.', 'msg' => null]);
                break;
            }

            $_SESSION['usuario'] = $user;
            $_SESSION['perfil']  = $user['perfil'] ?? 'comum';

            if ($_SESSION['perfil'] === 'comum') {
                redirect('/tickets/novo');
            }

            redirect('/');
        }

        http_response_code(405);
        echo 'Método não permitido.';
        break;

    case '/logout':
        session_destroy();
        redirect('/login');
        break;

    case '/criar-conta':
        $getFiliais = function () use ($pdo) {
            $stmt = $pdo->query('SELECT codigo FROM filiais ORDER BY CAST(codigo AS UNSIGNED)');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        };
        if ($method === 'GET') {
            view('criar_conta', ['erro' => null, 'msg' => null, 'filiais' => $getFiliais()]);
            break;
        }
        if ($method === 'POST') {
            $usuarioInput = trim($_POST['usuario'] ?? '');
            $senhaInput  = $_POST['senha'] ?? '';
            $confirmarSenha = $_POST['confirmarSenha'] ?? '';
            $nome  = trim($_POST['nome'] ?? '');
            if ($nome !== '') {
                $nome = function_exists('mb_strtoupper') ? mb_strtoupper($nome, 'UTF-8') : strtoupper($nome);
            }
            $email = trim($_POST['email'] ?? '');
            $filialCodigo = trim((string)($_POST['filial_codigo'] ?? ''));
            $filiais = $getFiliais();

            // Nome de usuário sem acentuação (normalização backend)
            $removeAccents = static function (string $value): string {
                if (function_exists('transliterator_transliterate')) {
                    $normalized = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC;', $value);
                    if (is_string($normalized)) {
                        return $normalized;
                    }
                }
                if (function_exists('iconv')) {
                    $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
                    if ($iconv !== false) {
                        return (string)$iconv;
                    }
                }
                return $value;
            };
            $usuarioInput = trim($removeAccents($usuarioInput));

            if ($usuarioInput === '') {
                view('criar_conta', ['erro' => 'Nome de usuário é obrigatório.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            if ($senhaInput === '') {
                view('criar_conta', ['erro' => 'Senha é obrigatória.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            if ($senhaInput !== $confirmarSenha) {
                view('criar_conta', ['erro' => 'As senhas não coincidem.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            if (strlen($senhaInput) < 3) {
                view('criar_conta', ['erro' => 'A senha deve ter pelo menos 3 caracteres.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            if ($email === '') {
                view('criar_conta', ['erro' => 'E-mail é obrigatório.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
                view('criar_conta', ['erro' => 'E-mail inválido.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?)');
            $stmt->execute([$usuarioInput]);
            if ($stmt->fetch()) {
                view('criar_conta', ['erro' => 'Já existe usuário com esse nome.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                view('criar_conta', ['erro' => 'Este e-mail já está cadastrado.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            try {
                // Compatível com o fluxo do Node:
                // - bloqueia pendente
                // - se rejeitado/aprovado, reabre para pendente atualizando dados
                $stmt = $pdo->prepare('SELECT id, status FROM solicitacoes_cadastro WHERE (LOWER(usuario) = LOWER(?) OR email = ?) ORDER BY id DESC LIMIT 1');
                $stmt->execute([$usuarioInput, $email]);
                $solExistente = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($solExistente && ($solExistente['status'] ?? '') === 'pendente') {
                    view('criar_conta', ['erro' => 'Já existe uma solicitação de cadastro pendente para este usuário ou e-mail. Aguarde a aprovação.', 'msg' => null, 'filiais' => $filiais]);
                    break;
                }
            } catch (Throwable $e) {
                // tabela pode não existir
                $solExistente = null;
            }
            if ($filialCodigo === '') {
                view('criar_conta', ['erro' => 'Por favor, selecione uma filial.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            $stmtFil = $pdo->prepare('SELECT 1 FROM filiais WHERE codigo = ?');
            $stmtFil->execute([$filialCodigo]);
            if (!$stmtFil->fetch()) {
                view('criar_conta', ['erro' => 'Filial selecionada não encontrada.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            $senhaHash = password_hash($senhaInput, PASSWORD_DEFAULT);
            try {
                if (!empty($solExistente['id'])) {
                    $up = $pdo->prepare('
                        UPDATE solicitacoes_cadastro
                        SET status = "pendente",
                            senha = ?,
                            nome = ?,
                            filial_codigo = ?,
                            data_solicitacao = NOW(),
                            aprovado_por = NULL,
                            data_aprovacao = NULL,
                            motivo_rejeicao = NULL
                        WHERE id = ?
                    ');
                    $up->execute([$senhaHash, $nome ?: null, $filialCodigo, (int)$solExistente['id']]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO solicitacoes_cadastro (usuario, senha, nome, email, filial_codigo, status) VALUES (?, ?, ?, ?, ?, "pendente")');
                    $ins->execute([$usuarioInput, $senhaHash, $nome ?: null, $email, $filialCodigo]);
                }
            } catch (PDOException $e) {
                view('criar_conta', ['erro' => 'Erro ao criar conta. Tente novamente ou contate o suporte.', 'msg' => null, 'filiais' => $filiais]);
                break;
            }
            view('criar_conta', ['erro' => null, 'msg' => 'Solicitação de cadastro enviada com sucesso! Aguarde a aprovação de um administrador.', 'filiais' => $filiais]);
        }
        break;

    default:
        http_response_code(404);
        view('404');
        break;
}

