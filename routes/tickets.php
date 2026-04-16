<?php

declare(strict_types=1);

// Este arquivo recebe controle a partir do index.php quando a URL começa com /tickets
// Variáveis disponíveis: $uri, $method, $pdo

if (!function_exists('current_user') || !function_exists('current_profile')) {
    http_response_code(500);
    echo 'Funções de sessão não encontradas.';
    exit;
}

// Garante que o usuário esteja autenticado para qualquer rota de /tickets
if (!current_user()) {
    redirect('/login');
}

$perfil  = current_profile() ?? 'comum';
$usuario = current_user();
$perfilLowerGlobal = strtolower(trim((string)$perfil));
$loginUsuarioGlobal = is_array($usuario) ? trim((string)($usuario['usuario'] ?? '')) : trim((string)$usuario);
$isPerfilManutencao = $perfilLowerGlobal === 'manutencao';
$isAdminLike = in_array($perfilLowerGlobal, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);
$canAssign   = in_array($perfilLowerGlobal, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);

// Subcaminho após /tickets
$basePrefix = '/tickets';
$subPath = substr($uri, strlen($basePrefix));
$subPath = $subPath === '' ? '/' : $subPath;

// Helpers simples de permissão
$categoriasRecepcao = ['Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
$categoriasManutencao = [categoria_manutencao_storage()];

/**
 * Trecho SQL (condição em usuarios.perfil) para quem pode ser listado como responsável.
 * Recepção só pode atribuir a usuários com perfil recepção; administrador/moderador veem toda a equipe.
 */
function tickets_sql_responsaveis_candidatos(string $perfilQuemAtribui): string
{
    $perfilLower = strtolower(trim($perfilQuemAtribui));
    if ($perfilLower === 'recepcao') {
        return "LOWER(TRIM(COALESCE(perfil, ''))) = 'recepcao'";
    }
    if ($perfilLower === 'manutencao') {
        return "LOWER(TRIM(COALESCE(perfil, ''))) = 'manutencao'";
    }
    return "perfil IN ('administrador','moderador','recepcao','manutencao')";
}

/**
 * Resposta JSON padronizada.
 */
function tickets_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/**
 * Controle simples de "digitando..." para chat live por arquivo temporário.
 */
function tickets_typing_store_path(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bigtickets_typing_live.json';
}

function tickets_typing_read_all(): array
{
    $path = tickets_typing_store_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function tickets_typing_write_all(array $data): void
{
    $path = tickets_typing_store_path();
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function tickets_live_meta_path(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bigtickets_live_meta.json';
}

function tickets_live_meta_read(): array
{
    $path = tickets_live_meta_path();
    if (!is_file($path)) {
        return ['reply' => [], 'reactions' => [], 'seen' => []];
    }
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        return ['reply' => [], 'reactions' => [], 'seen' => []];
    }
    $data['reply'] = is_array($data['reply'] ?? null) ? $data['reply'] : [];
    $data['reactions'] = is_array($data['reactions'] ?? null) ? $data['reactions'] : [];
    $data['seen'] = is_array($data['seen'] ?? null) ? $data['seen'] : [];
    return $data;
}

function tickets_live_meta_write(array $meta): void
{
    $path = tickets_live_meta_path();
    @file_put_contents($path, json_encode($meta, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function tickets_strip_emojis(string $text): string
{
    $clean = @preg_replace('/\p{Extended_Pictographic}|\x{FE0F}|\x{200D}/u', '', $text);
    if (!is_string($clean)) {
        return $text;
    }
    $clean = preg_replace('/\s{2,}/', ' ', $clean);
    return trim((string)$clean);
}

/**
 * Formata tempo decorrido em texto curto (ex.: "2h atrás").
 */
function tickets_time_ago(?string $dateString): string
{
    if (!$dateString) {
        return 'agora';
    }
    $ts = strtotime($dateString);
    if ($ts === false) {
        return 'agora';
    }
    $diff = time() - $ts;
    if ($diff >= 86400) {
        return floor($diff / 86400) . 'd atrás';
    }
    if ($diff >= 3600) {
        $h = floor($diff / 3600);
        return $h . ($h === 1 ? ' hr' : ' hrs') . ' atrás';
    }
    if ($diff >= 60) {
        return floor($diff / 60) . 'min atrás';
    }
    return 'agora';
}

// =========================
// /tickets/api/check-new
// =========================
if ($subPath === '/api/comment-notifications' && $method === 'GET') {
    $lastId = max(0, (int)($_GET['lastId'] ?? 0));
    $params = [$loginUsuarioGlobal, $lastId];
    $where = ["c.autor <> ?", "c.id > ?"];

    if ($perfilLowerGlobal === 'comum') {
        // Solicitante recebe popup somente quando a equipe responder nos próprios tickets
        $where[] = "t.solicitante = ?";
        $params[] = $loginUsuarioGlobal;
        $where[] = "LOWER(TRIM(COALESCE(u.perfil, ''))) IN ('administrador', 'moderador', 'recepcao', 'manutencao')";
    } else {
        // Equipe recebe popup apenas dos tickets onde é responsável
        $where[] = "t.responsavel = ?";
        $params[] = $loginUsuarioGlobal;
    }

    try {
        $sql = "
            SELECT c.id, c.comentario, c.data_criacao, c.autor, c.anexos,
                   t.id AS ticket_id, t.numero_chamado,
                   u.perfil AS autor_perfil, COALESCE(u.nome, u.usuario, c.autor) AS autor_nome
            FROM comentarios c
            INNER JOIN tickets t ON t.id = c.ticket_id
            LEFT JOIN usuarios u ON u.usuario = c.autor
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.id DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            tickets_json(['hasNew' => false, 'latestId' => $lastId]);
            return;
        }
        $texto = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)($row['comentario'] ?? ''))));
        if ($texto === '' && !empty($row['anexos'])) {
            $texto = 'Enviou anexo(s).';
        }
        tickets_json([
            'hasNew' => true,
            'latestId' => (int)($row['id'] ?? $lastId),
            'notification' => [
                'comment_id' => (int)($row['id'] ?? 0),
                'ticket_id' => (int)($row['ticket_id'] ?? 0),
                'numero_chamado' => (string)($row['numero_chamado'] ?? $row['ticket_id'] ?? ''),
                'autor' => (string)($row['autor'] ?? ''),
                'autor_nome' => (string)($row['autor_nome'] ?? ''),
                'autor_perfil' => strtolower((string)($row['autor_perfil'] ?? '')),
                'texto' => $texto,
            ],
        ]);
    } catch (Throwable $e) {
        tickets_json(['hasNew' => false, 'erro' => 'Falha ao buscar notificações.'], 500);
    }
    return;
}

if ($subPath === '/api/check-new' && $method === 'GET') {
    $lastCheck = trim((string)($_GET['lastCheck'] ?? $_GET['lastId'] ?? ''));
    $params = [];
    try {
        if ($lastCheck !== '') {
            // Compatível com Node: aceita timestamp em ms ou ID.
            if (ctype_digit($lastCheck) && (int)$lastCheck > 9999999999) {
                $date = date('Y-m-d H:i:s', (int)floor(((int)$lastCheck) / 1000));
                $sql = "SELECT id, numero_chamado, titulo, created_at FROM tickets WHERE created_at > ?";
                $params[] = $date;
            } else {
                $sql = "SELECT id, numero_chamado, titulo, created_at FROM tickets WHERE id > ?";
                $params[] = (int)$lastCheck;
            }
            if ($perfilLowerGlobal === 'recepcao') {
                $sql .= " AND categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
                $params = array_merge($params, $categoriasRecepcao);
            } elseif ($isPerfilManutencao) {
                $sql .= " AND categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
                $params = array_merge($params, $categoriasManutencao);
            }
            $sql .= " ORDER BY id DESC LIMIT 10";
        } else {
            $sql = "SELECT id, numero_chamado, titulo, created_at FROM tickets";
            if ($perfilLowerGlobal === 'recepcao') {
                $sql .= " WHERE categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
                $params = $categoriasRecepcao;
            } elseif ($isPerfilManutencao) {
                $sql .= " WHERE categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
                $params = $categoriasManutencao;
            }
            $sql .= " ORDER BY id DESC LIMIT 1";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $latest = $rows[0] ?? null;
        $hasNew = ($lastCheck !== '') && !empty($rows) && (int)$lastCheck > 0;
        tickets_json([
            'hasNew' => $hasNew,
            'count' => $hasNew ? count($rows) : 0,
            'latestTicket' => $latest,
            'latestId' => $latest['id'] ?? null,
            'latestTimestamp' => isset($latest['created_at']) ? (strtotime((string)$latest['created_at']) * 1000) : null,
        ]);
    } catch (Throwable $e) {
        tickets_json(['erro' => 'Erro ao verificar novos tickets', 'hasNew' => false], 500);
    }
    return;
}

// =========================
// /tickets/api/recentes
// =========================
if ($subPath === '/api/recentes' && $method === 'GET') {
    $tickets = [];
    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $isDashboardUser = in_array(strtolower(trim((string)$loginUsuario)), ['dashboard', 'dash'], true);
    $sqlSolicitanteComentouApi = ",
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
    try {
        $sql = "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.created_at, t.avaliacao,
                       COALESCE(u_responsavel.nome, u_responsavel.usuario, 'Não atribuído') AS responsavel_nome,
                       COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                       COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome,
                       f.codigo AS filial_nome
                       {$sqlSolicitanteComentouApi}
                FROM tickets t
                LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario
                LEFT JOIN filiais f ON f.codigo = t.filial_codigo";
        $params = [];
        // Usuário Dashboard/Dash (painel TV) vê todos os últimos tickets; recepção vê só categorias específicas
        if ($perfilLowerGlobal === 'recepcao' && !$isDashboardUser) {
            $sql .= " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
            $params = $categoriasRecepcao;
        } elseif ($isPerfilManutencao && !$isDashboardUser) {
            $sql .= " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
            $params = $categoriasManutencao;
        }
        $sql .= " ORDER BY t.created_at DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $colMissingFechado = str_contains((string)$e->getMessage(), 'fechado_por');
        $tickets = [];
        $runRecentes = static function (string $sqlBody, array $paramsIn) use ($pdo, &$tickets, $perfilLowerGlobal, $isDashboardUser, $categoriasRecepcao, $categoriasManutencao, $isPerfilManutencao): void {
            $sql = $sqlBody;
            $params = $paramsIn;
            if ($perfilLowerGlobal === 'recepcao' && !$isDashboardUser) {
                $sql .= " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
                $params = $categoriasRecepcao;
            } elseif ($isPerfilManutencao && !$isDashboardUser) {
                $sql .= " WHERE t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
                $params = $categoriasManutencao;
            }
            $sql .= " ORDER BY t.created_at DESC LIMIT 5";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        };
        try {
            if ($colMissingFechado) {
                $runRecentes(
                    "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.created_at,
                           COALESCE(u_responsavel.nome, u_responsavel.usuario, 'Não atribuído') AS responsavel_nome,
                           COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                           f.codigo AS filial_nome
                           {$sqlSolicitanteComentouApi}
                    FROM tickets t
                    LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                    LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                    LEFT JOIN filiais f ON f.codigo = t.filial_codigo",
                    []
                );
            } else {
                $runRecentes(
                    "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.created_at, t.avaliacao,
                           COALESCE(u_responsavel.nome, u_responsavel.usuario, 'Não atribuído') AS responsavel_nome,
                           COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                           COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome,
                           f.codigo AS filial_nome
                    FROM tickets t
                    LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                    LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                    LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario
                    LEFT JOIN filiais f ON f.codigo = t.filial_codigo",
                    []
                );
            }
        } catch (Throwable $e2) {
            try {
                if ($colMissingFechado) {
                    $runRecentes(
                        "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.created_at,
                           COALESCE(u_responsavel.nome, u_responsavel.usuario, 'Não atribuído') AS responsavel_nome,
                           COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                           f.codigo AS filial_nome
                    FROM tickets t
                    LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                    LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                    LEFT JOIN filiais f ON f.codigo = t.filial_codigo",
                        []
                    );
                } else {
                    $runRecentes(
                        "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.created_at, t.avaliacao,
                           COALESCE(u_responsavel.nome, u_responsavel.usuario, 'Não atribuído') AS responsavel_nome,
                           COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                           COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome,
                           f.codigo AS filial_nome
                    FROM tickets t
                    LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                    LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                    LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario
                    LEFT JOIN filiais f ON f.codigo = t.filial_codigo",
                        []
                    );
                }
            } catch (Throwable $e3) {
                tickets_json(['erro' => 'Erro ao buscar tickets recentes', 'tickets' => []], 500);
                return;
            }
        }
    }
    $out = array_map(static function (array $t): array {
        $createdAt = $t['created_at'] ?? null;
        $createdTs = null;
        if ($createdAt !== null && $createdAt !== '') {
            $ts = strtotime((string)$createdAt);
            $createdTs = $ts !== false ? $ts : null;
        }
        return [
            'id' => $t['id'] ?? null,
            'numero_chamado' => $t['numero_chamado'] ?? null,
            'titulo' => $t['titulo'] ?: 'Sem título',
            'status' => $t['status'] ?? '',
            'statusDisplay' => (function ($raw) {
                $s = strtolower(trim((string)$raw));
                if ($s === 'em_andamento' || $s === 'em andamento') return 'Em andamento';
                if ($s === 'aberto') return 'Aberto';
                if ($s === 'fechado' || $s === 'resolvido') return 'Fechado';
                return $raw ?: 'Aberto';
            })($t['status'] ?? ''),
            'prioridade' => $t['prioridade'] ?? '',
            'responsavel' => $t['responsavel_nome'] ?? 'Não atribuído',
            'solicitante_nome' => $t['solicitante_nome'] ?? 'N/A',
            'filial_nome' => $t['filial_nome'] ?? 'N/A',
            'fechado_por_nome' => $t['fechado_por_nome'] ?? null,
            'avaliacao' => isset($t['avaliacao']) && $t['avaliacao'] >= 1 && $t['avaliacao'] <= 5 ? (int)$t['avaliacao'] : null,
            'created_at' => $createdAt,
            'created_at_ts' => $createdTs,
            'timeAgo' => tickets_time_ago($createdAt),
            'solicitante_comentou' => (int)($t['solicitante_comentou'] ?? 0) === 1,
        ];
    }, $tickets);
    tickets_json(['tickets' => $out]);
    return;
}

// =========================
// /tickets/api/grafico-semana
// =========================
if ($subPath === '/api/grafico-semana' && $method === 'GET') {
    $abertos = array_fill(0, 7, 0);
    $andamento = array_fill(0, 7, 0);
    $resolvidos = array_fill(0, 7, 0);
    try {
        $inicio = date('Y-m-d', strtotime('-6 days'));
        $fim = date('Y-m-d');
        $sql = "SELECT DATE(created_at) AS data, status, COUNT(*) AS total
                FROM tickets
                WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
        $params = [$inicio, $fim];
        if ($perfilLowerGlobal === 'recepcao') {
            $sql .= " AND categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
            $params = array_merge($params, $categoriasRecepcao);
        } elseif ($isPerfilManutencao) {
            $sql .= " AND categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
            $params = array_merge($params, $categoriasManutencao);
        }
        $sql .= " GROUP BY DATE(created_at), status ORDER BY DATE(created_at), status";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $startTs = strtotime($inicio);
        foreach ($rows as $row) {
            $d = strtotime((string)$row['data']);
            if ($d === false) {
                continue;
            }
            $idx = (int)floor(($d - $startTs) / 86400);
            if ($idx < 0 || $idx > 6) {
                continue;
            }
            $status = strtolower(str_replace(' ', '_', (string)($row['status'] ?? '')));
            $total = (int)($row['total'] ?? 0);
            if ($status === 'aberto') {
                $abertos[$idx] = $total;
            } elseif ($status === 'em_andamento') {
                $andamento[$idx] = $total;
            } elseif ($status === 'resolvido' || $status === 'fechado') {
                $resolvidos[$idx] += $total;
            }
        }
        tickets_json([
            'dadosAbertos' => $abertos,
            'dadosEmAndamento' => $andamento,
            'dadosResolvidos' => $resolvidos,
        ]);
    } catch (Throwable $e) {
        tickets_json([
            'erro' => 'Erro ao buscar dados do gráfico',
            'dadosAbertos' => $abertos,
            'dadosEmAndamento' => $andamento,
            'dadosResolvidos' => $resolvidos,
        ], 500);
    }
    return;
}

// =========================
// /tickets/buscar-responsaveis
// =========================
if ($subPath === '/buscar-responsaveis' && $method === 'GET') {
    if (!$isAdminLike) {
        tickets_json(['erro' => 'Acesso restrito a administradores, moderadores, recepção ou manutenção.'], 403);
        return;
    }
    try {
        $wherePerfil = tickets_sql_responsaveis_candidatos((string)$perfil);
        $stmt = $pdo->query(
            "SELECT id, usuario, perfil, nome FROM usuarios WHERE {$wherePerfil} AND ativo = 1 AND LOWER(usuario) <> 'dashboard' AND LOWER(usuario) <> 'dash' ORDER BY usuario"
        );
        tickets_json($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        tickets_json(['erro' => 'Erro ao buscar responsáveis'], 500);
    }
    return;
}

// =========================
// /tickets/buscar/{numeroChamado}
// =========================
if (preg_match('~^/buscar/([^/]+)$~', $subPath, $m) && $method === 'GET') {
    $numeroChamado = (string)$m[1];
    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    try {
        $stmt = $pdo->prepare("SELECT t.*, f.codigo AS filial_nome,
                                      COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
                                      u_solicitante.cargo AS solicitante_cargo,
                                      u_responsavel.usuario AS responsavel_usuario,
                                      COALESCE(u_responsavel.nome, u_responsavel.usuario) AS responsavel_nome
                               FROM tickets t
                               LEFT JOIN filiais f ON f.codigo = t.filial_codigo
                               LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
                               LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
                               WHERE t.numero_chamado = ?");
        $stmt->execute([$numeroChamado]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$ticket) {
            tickets_json(['erro' => 'Ticket não encontrado', 'numeroChamado' => $numeroChamado], 404);
            return;
        }
        if (strtolower((string)$perfil) === 'comum' && (string)($ticket['solicitante'] ?? '') !== $loginUsuario) {
            tickets_json(['erro' => 'Acesso negado', 'numeroChamado' => $numeroChamado], 403);
            return;
        }
        if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticket['categoria'] ?? ''), $categoriasRecepcao, true)) {
            tickets_json(['erro' => 'Acesso negado', 'numeroChamado' => $numeroChamado], 403);
            return;
        }
        if ($isPerfilManutencao && categoria_normalize_storage((string)($ticket['categoria'] ?? '')) !== categoria_manutencao_storage()) {
            tickets_json(['erro' => 'Acesso negado', 'numeroChamado' => $numeroChamado], 403);
            return;
        }
        $ticket['timeAgo'] = tickets_time_ago($ticket['created_at'] ?? null);
        tickets_json(['sucesso' => true, 'ticket' => $ticket]);
    } catch (Throwable $e) {
        tickets_json(['erro' => 'Erro interno do servidor', 'numeroChamado' => $numeroChamado], 500);
    }
    return;
}

// =========================
// /tickets/export (CSV) - mesmos filtros da listagem
// =========================
if ($subPath === '/export' && $method === 'GET') {
    if (!$isAdminLike) {
        http_response_code(403);
        echo 'Acesso restrito.';
        return;
    }
    $statusF       = trim($_GET['status'] ?? '');
    $categoriaF    = categoria_normalize_storage(trim($_GET['categoria'] ?? ''));
    $prioridadeF   = trim($_GET['prioridade'] ?? '');
    $responsavelF  = trim($_GET['responsavel'] ?? '');
    $dataIni       = trim($_GET['data_ini'] ?? '');
    $dataFim       = trim($_GET['data_fim'] ?? '');
    $numeroChamado = trim($_GET['numero_chamado'] ?? '');
    $filialF       = trim($_GET['filial'] ?? '');

    $sql = "SELECT t.id, t.numero_chamado, t.titulo, t.status, t.prioridade, t.categoria, t.solicitante, t.created_at,
                   f.codigo AS filial_nome,
                   COALESCE(u_responsavel.nome, u_responsavel.usuario) AS responsavel_nome
            FROM tickets t
            LEFT JOIN filiais f ON f.codigo = t.filial_codigo
            LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario";
    $where = [];
    $params = [];
    if ($numeroChamado !== '') { $where[] = 't.numero_chamado LIKE ?'; $params[] = '%' . $numeroChamado . '%'; }
    if ($perfilLowerGlobal === 'recepcao') {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
        $params = array_merge($params, $categoriasRecepcao);
    } elseif ($isPerfilManutencao) {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
        $params = array_merge($params, $categoriasManutencao);
    }
    if ($statusF !== '') {
        if ($statusF === 'Aberto') { $where[] = "t.status = 'aberto'"; }
        elseif ($statusF === 'Em andamento') { $where[] = "t.status = 'em_andamento'"; }
        elseif (in_array($statusF, ['Fechado', 'Resolvido'], true)) { $where[] = "(t.status = 'fechado' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'Resolvido')"; }
        else { $where[] = 't.status = ?'; $params[] = strtolower(str_replace(' ', '_', $statusF)); }
    }
    if ($categoriaF !== '') {
        if (in_array($categoriaF, ['Rede/Infraestrutura', 'Rede'], true)) {
            $where[] = '(t.categoria = ? OR t.categoria = ?)';
            $params[] = 'Rede/Infraestrutura';
            $params[] = 'Rede';
        } else {
            $where[] = 't.categoria = ?';
            $params[] = $categoriaF;
        }
    }
    if ($prioridadeF !== '') { $where[] = 't.prioridade = ?'; $params[] = $prioridadeF; }
    if ($responsavelF !== '') { $where[] = 't.responsavel = ?'; $params[] = $responsavelF; }
    if ($dataIni !== '') { $where[] = 'DATE(t.created_at) >= ?'; $params[] = $dataIni; }
    if ($dataFim !== '') { $where[] = 'DATE(t.created_at) <= ?'; $params[] = $dataFim; }
    if ($filialF !== '') { $where[] = 'f.codigo = ?'; $params[] = $filialF; }
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY t.created_at DESC';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $escape = static function ($v): string {
            $s = str_replace('"', '""', (string)($v ?? ''));
            return '"' . $s . '"';
        };
        $header = ['Nº Chamado', 'Título', 'Status', 'Prioridade', 'Categoria', 'Solicitante', 'Filial', 'Responsável', 'Data Criação'];
        $lines = [implode(';', array_map($escape, $header))];
        foreach ($tickets as $t) {
            $lines[] = implode(';', array_map($escape, [
                $t['numero_chamado'] ?? '',
                preg_replace('/\r?\n/', ' ', (string)($t['titulo'] ?? '')),
                $t['status'] ?? '',
                $t['prioridade'] ?? '',
                function_exists('categoria_display') ? categoria_display((string)($t['categoria'] ?? '')) : (string)($t['categoria'] ?? ''),
                $t['solicitante'] ?? '',
                $t['filial_nome'] ?? '',
                $t['responsavel_nome'] ?? '',
                !empty($t['created_at']) ? date('d/m/Y H:i:s', strtotime((string)$t['created_at'])) : '',
            ]));
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets.csv"');
        echo $csv;
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Erro ao exportar tickets.';
    }
    return;
}

// =========================
// /tickets (lista geral) - apenas perfis de equipe, com filtros e paginação
// =========================
if ($subPath === '/' && $method === 'GET') {
    if (!$isAdminLike) {
        redirect('/tickets/novo');
    }

    $statusF       = trim($_GET['status'] ?? '');
    $categoriaF    = categoria_normalize_storage(trim($_GET['categoria'] ?? ''));
    $prioridadeF   = trim($_GET['prioridade'] ?? '');
    $responsavelF  = trim($_GET['responsavel'] ?? '');
    $dataIni       = trim($_GET['data_ini'] ?? '');
    $dataFim       = trim($_GET['data_fim'] ?? '');
    $numeroChamado = trim($_GET['numero_chamado'] ?? '');
    $filialF       = trim($_GET['filial'] ?? '');
    $perPage = max(10, min((int)($_GET['perPage'] ?? $_GET['limit'] ?? 15), 100));
    $page = max(1, (int)($_GET['page'] ?? $_GET['ticketsPage'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $sqlSolicitanteComentouLista = ",
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
    $sql = "
        SELECT t.*, f.codigo AS filial_nome,
               COALESCE(u_resp.nome, u_resp.usuario) AS responsavel_nome,
               COALESCE(u_sol.nome, u_sol.usuario) AS solicitante_nome
               {$sqlSolicitanteComentouLista}
        FROM tickets t
        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario
        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
    ";
    $where  = [];
    $params = [];

    if ($numeroChamado !== '') {
        $where[]  = 't.numero_chamado LIKE ?';
        $params[] = '%' . $numeroChamado . '%';
    }
    if ($perfilLowerGlobal === 'recepcao') {
        $where[]  = "t.categoria IN ('Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo')";
    } elseif ($isPerfilManutencao) {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
        $params = array_merge($params, $categoriasManutencao);
    }
    // Por padrão exclui fechados; só mostra fechados quando o usuário filtra por status Fechado
    if ($statusF === '') {
        $where[] = "(t.status != 'fechado' AND t.status != 'resolvido')";
    }
    if ($statusF !== '') {
        if ($statusF === 'Aberto') {
            $where[] = "t.status = 'aberto'";
        } elseif ($statusF === 'Em andamento') {
            $where[] = "t.status = 'em_andamento'";
        } elseif (in_array($statusF, ['Fechado', 'Resolvido'], true)) {
            $where[] = "(t.status = 'fechado' OR t.status = 'resolvido')";
        } else {
            $where[]  = 't.status = ?';
            $params[] = $statusF;
        }
    }
    if ($categoriaF !== '') {
        if (in_array($categoriaF, ['Rede/Infraestrutura', 'Rede'], true)) {
            $where[]  = '(t.categoria = ? OR t.categoria = ?)';
            $params[] = 'Rede/Infraestrutura';
            $params[] = 'Rede';
        } else {
            $where[]  = 't.categoria = ?';
            $params[] = $categoriaF;
        }
    }
    if ($prioridadeF !== '') {
        $where[]  = 't.prioridade = ?';
        $params[] = $prioridadeF;
    }
    if ($responsavelF !== '') {
        $where[]  = 't.responsavel = ?';
        $params[] = $responsavelF;
    }
    if ($dataIni !== '') {
        $where[]  = 'DATE(t.created_at) >= ?';
        $params[] = $dataIni;
    }
    if ($dataFim !== '') {
        $where[]  = 'DATE(t.created_at) <= ?';
        $params[] = $dataFim;
    }
    if ($filialF !== '') {
        $where[]  = 'f.codigo = ?';
        $params[] = $filialF;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.created_at DESC';

    $countSql = "SELECT COUNT(*) AS total FROM tickets t LEFT JOIN filiais f ON f.codigo = t.filial_codigo LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario";
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalItens = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    $totalPaginas = max(1, (int)ceil($totalItens / $perPage));
    if ($page > $totalPaginas) {
        $page = $totalPaginas;
        $offset = ($page - 1) * $perPage;
    }

    try {
        $stmt = $pdo->prepare($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $sqlFallback = str_replace($sqlSolicitanteComentouLista, '', $sql);
        $stmt = $pdo->prepare($sqlFallback . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tickets as &$row) {
            $row['solicitante_comentou'] = 0;
        }
        unset($row);
    }

    $whereRespCand = tickets_sql_responsaveis_candidatos((string)$perfil);
    $stmtResp = $pdo->query(
        "SELECT usuario, nome FROM usuarios WHERE {$whereRespCand} AND ativo = 1 AND LOWER(usuario) <> 'dashboard' AND LOWER(usuario) <> 'dash' ORDER BY usuario"
    );
    $responsaveis = $stmtResp->fetchAll(PDO::FETCH_ASSOC);
    $stmtFil = $pdo->query("SELECT codigo FROM filiais ORDER BY CAST(codigo AS UNSIGNED)");
    $filiais = $stmtFil->fetchAll(PDO::FETCH_ASSOC);

    $filtros = [
        'status' => $statusF, 'categoria' => $categoriaF, 'prioridade' => $prioridadeF,
        'responsavel' => $responsavelF, 'data_ini' => $dataIni, 'data_fim' => $dataFim,
        'numero_chamado' => $numeroChamado, 'filial' => $filialF,
    ];
    $paginacao = [
        'totalItens' => $totalItens,
        'limit' => $perPage,
        'paginaAtual' => $page,
        'totalPaginas' => $totalPaginas,
    ];

    view('tickets_list', [
        'tickets'     => $tickets,
        'perfil'      => $perfil,
        'usuario'     => $usuario,
        'responsaveis'=> $responsaveis,
        'filiais'     => $filiais,
        'filtros'     => $filtros,
        'paginacao'   => $paginacao,
        'msg'         => $_GET['msg'] ?? null,
        'erro'        => $_GET['erro'] ?? null,
    ]);
    return;
}

// =========================
// /tickets/novo  (tela de novo + acompanhamento)
// =========================
if ($subPath === '/novo' && $method === 'GET') {
    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $filtroStatus = strtolower(trim((string)($_GET['status'] ?? '')));
    $dataIni = trim((string)($_GET['data_ini'] ?? ''));
    $dataFim = trim((string)($_GET['data_fim'] ?? ''));
    $perPage = max(1, min((int)($_GET['perPage'] ?? 15), 100));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $hasSpecificParams = !empty($_GET);
    $showAcompanhamento = $hasSpecificParams && (($_GET['acompanhamento'] ?? '') === 'true' || $filtroStatus !== '' || $dataIni !== '' || $dataFim !== '');

    $filialAtual = is_array($usuario) ? trim((string)($usuario['filial_codigo'] ?? '')) : '';
    $filiais = [];
    if (in_array($perfilLowerGlobal, ['administrador', 'moderador', 'recepcao'], true)) {
        $stmtFil = $pdo->query("SELECT codigo FROM filiais ORDER BY CAST(codigo AS UNSIGNED)");
        $filiais = $stmtFil ? $stmtFil->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    $sql = "
        SELECT t.*, f.codigo AS filial_nome,
               COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
               u_responsavel.usuario AS responsavel_usuario,
               COALESCE(u_responsavel.nome, u_responsavel.usuario) AS responsavel_nome,
               (SELECT c.autor FROM comentarios c WHERE c.ticket_id = t.id ORDER BY c.id DESC LIMIT 1) AS ultimo_autor,
               (
                 SELECT LOWER(u.perfil)
                 FROM comentarios c2
                 LEFT JOIN usuarios u ON u.usuario = c2.autor
                 WHERE c2.ticket_id = t.id
                 ORDER BY c2.id DESC
                 LIMIT 1
               ) AS ultimo_autor_perfil
        FROM tickets t
        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
        LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
        LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
    ";
    $where = ["(t.solicitante = ? OR t.responsavel = ?)"];
    $params = [$loginUsuario, $loginUsuario];

    if ($perfilLowerGlobal === 'recepcao') {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
        $params = array_merge($params, $categoriasRecepcao);
    } elseif ($isPerfilManutencao) {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
        $params = array_merge($params, $categoriasManutencao);
    }
    if ($filtroStatus !== '' && $filtroStatus !== 'todos') {
        if (in_array($filtroStatus, ['resolvidos', 'fechados'], true)) {
            $where[] = "(t.status = 'Resolvido' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'fechado')";
        } elseif ($filtroStatus === 'em-andamento') {
            $where[] = "(t.status = 'Em andamento' OR t.status = 'em_andamento')";
        } elseif ($filtroStatus === 'aberto') {
            $where[] = "(t.status = 'Aberto' OR t.status = 'aberto')";
        } elseif ($filtroStatus === 'respondidos') {
            // Filtrado após mapear último autor.
        } elseif ($filtroStatus === 'nao-avaliados') {
            $where[] = "(t.status = 'Resolvido' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'fechado')";
            $where[] = "t.avaliacao IS NULL";
        } else {
            $where[] = "t.status = ?";
            $params[] = ucfirst($filtroStatus);
        }
    }
    if ($dataIni !== '') {
        $where[] = "DATE(t.created_at) >= ?";
        $params[] = $dataIni;
    }
    if ($dataFim !== '') {
        $where[] = "DATE(t.created_at) <= ?";
        $params[] = $dataFim;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticketsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ticketsRaw = array_values(array_filter($ticketsRaw, static fn(array $t): bool => !empty($t['id'])));

    // Verifica anexos de comentário em lote (sem N+1)
    $comentariosComAnexos = [];
    if (!empty($ticketsRaw)) {
        try {
            $ids = array_map(static fn(array $t): int => (int)$t['id'], $ticketsRaw);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtAnexos = $pdo->prepare("
                SELECT DISTINCT ticket_id
                FROM comentarios
                WHERE ticket_id IN ({$placeholders})
                  AND anexos IS NOT NULL
                  AND anexos != '[]'
                  AND anexos != ''
            ");
            $stmtAnexos->execute($ids);
            foreach ($stmtAnexos->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!empty($row['ticket_id'])) {
                    $comentariosComAnexos[(int)$row['ticket_id']] = true;
                }
            }
        } catch (Throwable $e) {
            $comentariosComAnexos = [];
        }
    }

    $ticketsComIndicadores = array_map(
        static function (array $ticket) use ($comentariosComAnexos): array {
            $hasAttachments = false;
            if (!empty($ticket['anexos'])) {
                $raw = $ticket['anexos'];
                if (is_string($raw)) {
                    $parsed = json_decode($raw, true);
                    if (is_array($parsed)) {
                        $hasAttachments = count($parsed) > 0;
                    } else {
                        $hasAttachments = trim($raw) !== '';
                    }
                } elseif (is_array($raw)) {
                    $hasAttachments = count($raw) > 0;
                }
            }
            if (!$hasAttachments && !empty($ticket['id']) && !empty($comentariosComAnexos[(int)$ticket['id']])) {
                $hasAttachments = true;
            }

            $ticketTs = !empty($ticket['created_at']) ? strtotime((string)$ticket['created_at']) : false;
            $isNew = $ticketTs ? ((time() - $ticketTs) <= (15 * 60)) : false;
            $ultimoAutorPerfil = strtolower((string)($ticket['ultimo_autor_perfil'] ?? ''));
            $hasTiReply = in_array($ultimoAutorPerfil, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);

            $ticket['hasAttachments'] = $hasAttachments;
            $ticket['isNew'] = $isNew;
            $ticket['timeAgo'] = tickets_time_ago($ticket['created_at'] ?? null);
            $ticket['hasTiReply'] = $hasTiReply;
            return $ticket;
        },
        $ticketsRaw
    );

    // Solicitante (perfil comum): chamados em que a última mensagem é do suporte — popup no acompanhamento
    $ticketsAcompanhamentoPopupSuporte = [];
    if (strtolower((string)$perfil) === 'comum') {
        $rowsPopup = [];
        foreach ($ticketsComIndicadores as $tk) {
            if (empty($tk['hasTiReply'])) {
                continue;
            }
            if ((string)($tk['solicitante'] ?? '') !== $loginUsuario) {
                continue;
            }
            $rowsPopup[] = $tk;
        }
        usort($rowsPopup, static function (array $a, array $b): int {
            $ua = strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? '')) ?: 0;
            $ub = strtotime((string)($b['updated_at'] ?? $b['created_at'] ?? '')) ?: 0;
            return $ub <=> $ua;
        });
        foreach ($rowsPopup as $tk) {
            $ticketsAcompanhamentoPopupSuporte[] = [
                'id' => (int)($tk['id'] ?? 0),
                'numero_chamado' => (string)($tk['numero_chamado'] ?? $tk['id'] ?? ''),
                'titulo' => (string)($tk['titulo'] ?? ''),
            ];
        }
    }

    $ticketsFiltrados = $ticketsComIndicadores;
    if ($filtroStatus === 'respondidos') {
        $ticketsFiltrados = array_values(array_filter($ticketsComIndicadores, static fn(array $t): bool => !empty($t['hasTiReply'])));
    }
    $totalTickets = count($ticketsFiltrados);
    $totalPages = max(1, (int)ceil($totalTickets / $perPage));
    $currentPage = min($page, $totalPages);
    $startIndex = ($currentPage - 1) * $perPage;
    $tickets = array_slice($ticketsFiltrados, $startIndex, $perPage);

    $countAbertos = 0;
    $countAndamento = 0;
    $countFechados = 0;
    foreach ($ticketsFiltrados as $tk) {
        $st = strtolower((string)($tk['status'] ?? ''));
        if ($st === 'aberto') $countAbertos++;
        if ($st === 'em_andamento' || $st === 'em andamento') $countAndamento++;
        if ($st === 'fechado' || $st === 'resolvido') $countFechados++;
    }

    view('ticket_novo', [
        'tickets' => $tickets,
        'perfil'  => $perfil,
        'usuario' => $usuario,
        'msg'     => $_GET['msg'] ?? null,
        'erro'    => $_GET['erro'] ?? null,
        'acompanhamentoAtivo' => $showAcompanhamento,
        'currentStatus' => $filtroStatus,
        'dataIni' => $dataIni,
        'dataFim' => $dataFim,
        'currentAcompanhamento' => (string)($_GET['acompanhamento'] ?? ''),
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'perPage' => $perPage,
        'totalTickets' => $totalTickets,
        'countAbertos' => $countAbertos,
        'countAndamento' => $countAndamento,
        'countFechados' => $countFechados,
        'ticketsAcompanhamentoPopupSuporte' => $ticketsAcompanhamentoPopupSuporte,
        'filiais' => $filiais,
        'filialAtual' => $filialAtual,
    ]);
    return;
}

// =========================
// POST /tickets/novo  (criação de ticket)
// =========================
if ($subPath === '/novo' && $method === 'POST') {
    $titulo     = trim($_POST['titulo'] ?? '');
    $categoria  = categoria_normalize_storage(trim($_POST['categoria'] ?? ''));
    $prioridade = trim($_POST['prioridade'] ?? '');
    $descricao  = trim($_POST['descricao'] ?? '');
    $responsavel = trim($_POST['responsavel_id'] ?? '');
    $ipAnyDesk = trim((string)($_POST['ip_anydesk'] ?? ''));

    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $filialCodigoUsuario = is_array($usuario) ? trim((string)($usuario['filial_codigo'] ?? '')) : '';
    $filialCodigo = $filialCodigoUsuario;
    if (in_array($perfilLowerGlobal, ['administrador', 'moderador', 'recepcao'], true)) {
        $filialCodigo = trim((string)($_POST['filial_codigo'] ?? ''));
        if ($filialCodigo === '') {
            redirect('/tickets/novo?erro=Selecione a filial.');
        }
        $stmtFil = $pdo->prepare('SELECT 1 FROM filiais WHERE codigo = ?');
        $stmtFil->execute([$filialCodigo]);
        if (!$stmtFil->fetch(PDO::FETCH_ASSOC)) {
            redirect('/tickets/novo?erro=Filial inválida.');
        }
    }

    if ($titulo === '' || $descricao === '' || $categoria === '' || $prioridade === '') {
        redirect('/tickets/novo?erro=Preencha todos os campos obrigatórios.');
    }
    $categoriasPermitidasNovo = ['Suporte', 'Hardware', 'Software', 'Rede/Infraestrutura', 'Desenvolvimento', categoria_manutencao_storage(), 'Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
    if (!in_array($categoria, $categoriasPermitidasNovo, true)) {
        redirect('/tickets/novo?erro=Categoria inválida.');
    }
    if (mb_strlen($titulo) > 30) {
        redirect('/tickets/novo?erro=O título deve ter no máximo 30 caracteres.');
    }

    if ($filialCodigo === '') {
        redirect('/tickets/novo?erro=Usuário não possui filial vinculada. Contate o administrador.');
    }

    // Geração simples do número de chamado (similar ao TicketService.gerarNumeroChamado)
    $ultimoStmt = $pdo->query("SELECT numero_chamado FROM tickets WHERE numero_chamado REGEXP '^[0-9]+$' ORDER BY CAST(numero_chamado AS UNSIGNED) DESC LIMIT 1");
    $ultimo = $ultimoStmt->fetch(PDO::FETCH_ASSOC);
    $proximoNumero = 1;
    if ($ultimo && isset($ultimo['numero_chamado']) && ctype_digit($ultimo['numero_chamado'])) {
        $proximoNumero = (int)$ultimo['numero_chamado'] + 1;
    }
    $numeroChamado = (string)$proximoNumero;

    // =========================
    // Upload de anexos (arquivos no ticket)
    // =========================
    $anexosJson = null;
    if (!empty($_FILES['anexos']) && is_array($_FILES['anexos']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/tickets';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $anexos = [];
        $names   = $_FILES['anexos']['name'];
        $tmpNames= $_FILES['anexos']['tmp_name'];
        $errors  = $_FILES['anexos']['error'];

        foreach ($names as $idx => $nomeOriginal) {
            $nomeOriginal = (string)$nomeOriginal;
            $error = $errors[$idx] ?? UPLOAD_ERR_NO_FILE;
            $tmp   = $tmpNames[$idx] ?? null;

            if ($error === UPLOAD_ERR_NO_FILE || !$tmp) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $ext = $ext ? preg_replace('~[^a-zA-Z0-9]~', '', $ext) : '';
            $safeName = 'ticket_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            if ($ext !== '') {
                $safeName .= '.' . strtolower($ext);
            }

            $destinoFs = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
            if (@move_uploaded_file($tmp, $destinoFs)) {
                $anexos[] = [
                    'nome_original' => $nomeOriginal,
                    'arquivo'       => '/uploads/tickets/' . $safeName,
                ];
            }
        }

        if (!empty($anexos)) {
            $anexosJson = json_encode($anexos, JSON_UNESCAPED_UNICODE);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO tickets
            (numero_chamado, titulo, descricao, anexos, status, prioridade, categoria, ip_anydesk, solicitante, responsavel, filial_codigo, created_at, updated_at)
        VALUES
            (:num, :titulo, :descricao, :anexos, 'aberto', :prioridade, :categoria, :ip_anydesk, :solicitante, :responsavel, :filial_codigo, NOW(), NOW())
    ");

    $stmt->execute([
        'num'         => $numeroChamado,
        'titulo'      => $titulo,
        'descricao'   => $descricao,
        'anexos'      => $anexosJson,
        'prioridade'  => $prioridade,
        'categoria'   => $categoria,
        'ip_anydesk'  => $ipAnyDesk !== '' ? $ipAnyDesk : null,
        'solicitante' => $loginUsuario,
        'responsavel' => $responsavel !== '' ? $responsavel : null,
        'filial_codigo' => $filialCodigo,
    ]);

    redirect('/tickets/novo?msg=Ticket ' . urlencode($numeroChamado) . ' criado com sucesso!');
}

// Helpers de retorno para manter usuário na mesma tela (detalhe/lista com filtros/paginação)
$ticketsNormalizeReturnPath = static function (string $pathIn): string {
    $pathIn = trim($pathIn);
    if ($pathIn === '') {
        return '';
    }
    if (str_starts_with($pathIn, '/tickets')) {
        return $pathIn;
    }
    $bp = function_exists('base_path') ? rtrim(base_path(), '/') : '';
    if ($bp !== '' && str_starts_with($pathIn, $bp . '/tickets')) {
        $normalized = substr($pathIn, strlen($bp));
        return $normalized !== '' ? $normalized : '/tickets';
    }
    return '';
};
$ticketsRedirectBaseFromContext = static function (string $default = '/tickets', string $returnTo = '') use ($ticketsNormalizeReturnPath): string {
    $base = $ticketsNormalizeReturnPath($returnTo);
    if ($base !== '') {
        return $base;
    }
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refParts = parse_url((string)$_SERVER['HTTP_REFERER']);
        $refPath = (string)($refParts['path'] ?? '');
        $refQuery = (string)($refParts['query'] ?? '');
        $refPathNorm = $ticketsNormalizeReturnPath($refPath);
        if ($refPathNorm !== '') {
            return $refPathNorm . ($refQuery !== '' ? '?' . $refQuery : '');
        }
    }
    return $default;
};
$ticketsRedirectWithNotice = static function (string $base, string $key, string $message, array $extra = []): string {
    $parts = parse_url($base);
    $path = (string)($parts['path'] ?? $base);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
    }
    foreach ($extra as $k => $v) {
        $query[(string)$k] = (string)$v;
    }
    $query[$key] = $message;
    return $path . (!empty($query) ? ('?' . http_build_query($query)) : '');
};

// =========================
// /tickets/{id}  (detalhes)
// =========================
if (preg_match('~^/([0-9]+)$~', $subPath, $m) && $method === 'GET') {
    $id = (int)$m[1];

    $stmt = $pdo->prepare("
        SELECT t.*, f.codigo AS filial_nome,
               COALESCE(u_resp.nome, u_resp.usuario) AS responsavel_nome,
               COALESCE(u_sol.nome, u_sol.usuario) AS solicitante_nome,
               u_sol.cargo AS solicitante_cargo
        FROM tickets t
        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
        LEFT JOIN usuarios u_resp ON t.responsavel = u_resp.usuario
        LEFT JOIN usuarios u_sol ON t.solicitante = u_sol.usuario
        WHERE t.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        view('404', ['perfil' => $perfil]);
        return;
    }

    // SLA por prioridade (simples)
    $prioridade = trim((string)($ticket['prioridade'] ?? ''));
    $prioridadeNorm = strtolower($prioridade);
    $slaSegundos = 24 * 60 * 60; // padrão 24h
    if ($prioridadeNorm === 'alta') {
        $slaSegundos = 4 * 60 * 60; // 4h
    } elseif (in_array($prioridadeNorm, ['média', 'media'], true)) {
        $slaSegundos = 24 * 60 * 60; // 24h
    } elseif ($prioridadeNorm === 'baixa') {
        $slaSegundos = 3 * 24 * 60 * 60; // 3 dias
    }

    $createdTs = !empty($ticket['created_at']) ? @strtotime((string)$ticket['created_at']) : false;
    $slaPrazoTs = $createdTs ? $createdTs + $slaSegundos : null;
    $slaStatus = null;
    if ($slaPrazoTs) {
        $statusLower = strtolower((string)($ticket['status'] ?? ''));
        $isFechado = in_array($statusLower, ['fechado', 'resolvido'], true);
        $referenciaTs = $isFechado && !empty($ticket['updated_at'])
            ? (@strtotime((string)$ticket['updated_at']) ?: time())
            : time();
        $slaStatus = ($referenciaTs <= $slaPrazoTs) ? 'dentro' : 'atrasado';
    }
    $ticket['sla_prazo_ts'] = $slaPrazoTs;
    $ticket['sla_status'] = $slaStatus;

    // Controle de acesso semelhante ao Node:
    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    if (strtolower((string)$perfil) === 'comum' && $ticket['solicitante'] !== $loginUsuario) {
        http_response_code(403);
        echo 'Acesso negado a este ticket.';
        return;
    }
    if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticket['categoria'] ?? ''), $categoriasRecepcao, true)) {
        http_response_code(403);
        echo 'Acesso negado a este ticket.';
        return;
    }
    if ($isPerfilManutencao && categoria_normalize_storage((string)($ticket['categoria'] ?? '')) !== categoria_manutencao_storage()) {
        http_response_code(403);
        echo 'Acesso negado a este ticket.';
        return;
    }

    // Comentários básicos (se tabela existir)
    try {
        $cStmt = $pdo->prepare("
            SELECT c.*, u.perfil AS autor_perfil,
                   COALESCE(u.nome, u.usuario) AS autor_nome
            FROM comentarios c
            LEFT JOIN usuarios u ON u.usuario = c.autor
            WHERE c.ticket_id = :id
            ORDER BY c.id DESC
        ");
        $cStmt->execute(['id' => $id]);
        $comentarios = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $comentarios = [];
    }

    // Lista de possíveis responsáveis (recepção/manutenção só enxergam perfis equivalentes)
    $responsaveis = [];
    if ($canAssign) {
        try {
            $whereDetCand = tickets_sql_responsaveis_candidatos((string)$perfil);
            $rStmt = $pdo->query("
                SELECT id, usuario, nome, perfil
                FROM usuarios
                WHERE {$whereDetCand} AND ativo = 1 AND LOWER(usuario) <> 'dashboard' AND LOWER(usuario) <> 'dash'
                ORDER BY nome
            ");
            $responsaveis = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $responsaveis = [];
        }
    }

    view('ticket_detalhes', [
        'ticket'           => $ticket,
        'comentarios'      => $comentarios,
        'responsaveis'     => $responsaveis,
        'perfil'           => $perfil,
        'usuario'          => $usuario,
        'msg'              => $_GET['msg'] ?? null,
        'erro'             => $_GET['erro'] ?? null,
        'focus_avaliacao'  => !empty($_GET['focus_avaliacao']),
    ]);
    return;
}

// =========================
// GET /tickets/{id}/comentarios-live (chat em tempo real)
// =========================
if (preg_match('~^/([0-9]+)/comentarios-live$~', $subPath, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $stmtTicket = $pdo->prepare("SELECT id, solicitante, categoria, status, avaliacao FROM tickets WHERE id = :id");
    $stmtTicket->execute(['id' => $id]);
    $ticketInfo = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    if (!$ticketInfo) {
        tickets_json(['erro' => 'Ticket não encontrado.'], 404);
        return;
    }
    if (strtolower((string)$perfil) === 'comum' && (string)($ticketInfo['solicitante'] ?? '') !== $loginUsuarioGlobal) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticketInfo['categoria'] ?? ''), $categoriasRecepcao, true)) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($isPerfilManutencao && categoria_normalize_storage((string)($ticketInfo['categoria'] ?? '')) !== categoria_manutencao_storage()) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.autor, c.comentario, c.anexos, c.data_criacao, u.perfil AS autor_perfil, COALESCE(u.nome, u.usuario, c.autor) AS autor_nome
            FROM comentarios c
            LEFT JOIN usuarios u ON u.usuario = c.autor
            WHERE c.ticket_id = :id
            ORDER BY c.id ASC
        ");
        $stmt->execute(['id' => $id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $meta = tickets_live_meta_read();
        $ticketKey = (string)$id;
        $replyMap = is_array($meta['reply'][$ticketKey] ?? null) ? $meta['reply'][$ticketKey] : [];
        $reactionsMap = is_array($meta['reactions'][$ticketKey] ?? null) ? $meta['reactions'][$ticketKey] : [];
        $seenMap = is_array($meta['seen'][$ticketKey] ?? null) ? $meta['seen'][$ticketKey] : [];
        $maxSeenOther = 0;
        foreach ($seenMap as $seenUser => $seenId) {
            if ((string)$seenUser === $loginUsuarioGlobal) {
                continue;
            }
            $n = (int)$seenId;
            if ($n > $maxSeenOther) {
                $maxSeenOther = $n;
            }
        }

        $isPrivilegedLive = in_array($perfilLowerGlobal, ['administrador', 'moderador'], true);
        $comentarios = array_map(static function (array $row) use ($replyMap, $reactionsMap, $loginUsuarioGlobal, $maxSeenOther, $isPrivilegedLive): array {
            $textoLimpo = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)($row['comentario'] ?? ''))));
            $anexosOut = [];
            if (!empty($row['anexos'])) {
                try {
                    $tmp = json_decode((string)$row['anexos'], true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($tmp)) {
                        foreach ($tmp as $ax) {
                            if (!is_array($ax)) {
                                continue;
                            }
                            $arquivo = (string)($ax['arquivo'] ?? '');
                            if ($arquivo === '') {
                                continue;
                            }
                            $anexosOut[] = [
                                'arquivo' => $arquivo,
                                'nome_original' => (string)($ax['nome_original'] ?? basename($arquivo)),
                            ];
                        }
                    }
                } catch (Throwable $e) {
                }
            }
            return [
                'id' => (int)($row['id'] ?? 0),
                'autor' => (string)($row['autor'] ?? ''),
                'autor_nome' => (string)($row['autor_nome'] ?? ''),
                'autor_perfil' => strtolower((string)($row['autor_perfil'] ?? '')),
                'comentario' => $textoLimpo,
                'anexos' => $anexosOut,
                'reply_to' => isset($replyMap[(string)((int)($row['id'] ?? 0))]) ? (int)$replyMap[(string)((int)($row['id'] ?? 0))] : null,
                'reactions' => is_array($reactionsMap[(string)((int)($row['id'] ?? 0))] ?? null) ? $reactionsMap[(string)((int)($row['id'] ?? 0))] : [],
                'can_edit' => ((string)($row['autor'] ?? '') === $loginUsuarioGlobal) || $isPrivilegedLive,
                'status' => ((string)($row['autor'] ?? '') === $loginUsuarioGlobal)
                    ? ($maxSeenOther >= (int)($row['id'] ?? 0) ? 'visto' : 'enviado')
                    : null,
                'data_criacao' => (string)($row['data_criacao'] ?? ''),
            ];
        }, $rows);
        tickets_json(['comentarios' => $comentarios]);
    } catch (Throwable $e) {
        tickets_json(['erro' => 'Erro ao carregar comentários.'], 500);
    }
    return;
}

// =========================
// POST /tickets/{id}/comentario-live (envio rápido em tempo real)
// =========================
if (preg_match('~^/([0-9]+)/comentario-live$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $texto = tickets_strip_emojis(trim((string)($_POST['comentario'] ?? '')));
    $replyTo = (int)($_POST['reply_to'] ?? 0);
    if ($texto === '') {
        // permite envio somente com anexo
        $temArquivo = !empty($_FILES['anexos']) && is_array($_FILES['anexos']['name']);
        if (!$temArquivo) {
            tickets_json(['erro' => 'Comentário obrigatório.'], 422);
            return;
        }
    }
    $stmtTicket = $pdo->prepare("SELECT id, solicitante, categoria, status, avaliacao FROM tickets WHERE id = :id");
    $stmtTicket->execute(['id' => $id]);
    $ticketInfo = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    if (!$ticketInfo) {
        tickets_json(['erro' => 'Ticket não encontrado.'], 404);
        return;
    }
    if (strtolower((string)$perfil) === 'comum' && (string)($ticketInfo['solicitante'] ?? '') !== $loginUsuarioGlobal) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticketInfo['categoria'] ?? ''), $categoriasRecepcao, true)) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($isPerfilManutencao && categoria_normalize_storage((string)($ticketInfo['categoria'] ?? '')) !== categoria_manutencao_storage()) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }

    $stInfo = strtolower((string)($ticketInfo['status'] ?? ''));
    $isFechadoInfo = in_array($stInfo, ['fechado', 'resolvido'], true);
    $avaliacaoInfo = isset($ticketInfo['avaliacao']) ? (int)$ticketInfo['avaliacao'] : null;
    $temAvaliacaoValida = $avaliacaoInfo !== null && $avaliacaoInfo >= 1 && $avaliacaoInfo <= 5;
    if ($isFechadoInfo && $temAvaliacaoValida) {
        tickets_json(['erro' => 'Ticket já avaliado e encerrado.'], 409);
        return;
    }

    $anexosJson = null;
    if (!empty($_FILES['anexos']) && is_array($_FILES['anexos']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/comentarios';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $anexos = [];
        $names = $_FILES['anexos']['name'];
        $tmpNames = $_FILES['anexos']['tmp_name'];
        $errors = $_FILES['anexos']['error'];
        foreach ($names as $idx => $nomeOriginal) {
            $nomeOriginal = (string)$nomeOriginal;
            $error = $errors[$idx] ?? UPLOAD_ERR_NO_FILE;
            $tmp = $tmpNames[$idx] ?? null;
            if ($error === UPLOAD_ERR_NO_FILE || !$tmp) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }
            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $ext = $ext ? preg_replace('~[^a-zA-Z0-9]~', '', $ext) : '';
            $safeName = 'coment_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            if ($ext !== '') {
                $safeName .= '.' . strtolower($ext);
            }
            $destinoFs = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
            if (@move_uploaded_file($tmp, $destinoFs)) {
                $anexos[] = [
                    'nome_original' => $nomeOriginal,
                    'arquivo' => '/uploads/comentarios/' . $safeName,
                ];
            }
        }
        if (!empty($anexos)) {
            $anexosJson = json_encode($anexos, JSON_UNESCAPED_UNICODE);
        }
    }

    if ($texto === '' && $anexosJson === null) {
        tickets_json(['erro' => 'Mensagem ou anexo é obrigatório.'], 422);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO comentarios (ticket_id, autor, comentario, anexos, data_criacao)
            VALUES (:id, :autor, :comentario, :anexos, NOW())
        ");
        $stmt->execute([
            'id' => $id,
            'autor' => $loginUsuarioGlobal,
            'comentario' => $texto,
            'anexos' => $anexosJson,
        ]);
        $newCommentId = (int)$pdo->lastInsertId();
        if ($replyTo > 0 && $newCommentId > 0) {
            $meta = tickets_live_meta_read();
            $tk = (string)$id;
            if (!isset($meta['reply'][$tk]) || !is_array($meta['reply'][$tk])) {
                $meta['reply'][$tk] = [];
            }
            $meta['reply'][$tk][(string)$newCommentId] = $replyTo;
            tickets_live_meta_write($meta);
        }

        if ($perfilLowerGlobal !== 'comum') {
            $upStmt = $pdo->prepare("
                UPDATE tickets SET responsavel = :r, status = IF(status = 'aberto', 'em_andamento', status), updated_at = NOW()
                WHERE id = :id
            ");
            $upStmt->execute(['r' => $loginUsuarioGlobal, 'id' => $id]);
        } else {
            $ehSolicitante = (string)($ticketInfo['solicitante'] ?? '') === $loginUsuarioGlobal;
            if ($ehSolicitante && $isFechadoInfo && !$temAvaliacaoValida) {
                $upStmt = $pdo->prepare("UPDATE tickets SET status = 'em_andamento', updated_at = NOW() WHERE id = :id");
                $upStmt->execute(['id' => $id]);
            }
        }

        tickets_json(['ok' => true]);
    } catch (Throwable $e) {
        tickets_json(['erro' => 'Erro ao enviar comentário.'], 500);
    }
    return;
}

// =========================
// POST /tickets/{id}/comentario-live/{cid}/editar
// =========================
if (preg_match('~^/([0-9]+)/comentario-live/([0-9]+)/editar$~', $subPath, $m) && $method === 'POST') {
    $ticketId = (int)$m[1];
    $commentId = (int)$m[2];
    $texto = tickets_strip_emojis(trim((string)($_POST['comentario'] ?? '')));
    if ($texto === '') {
        tickets_json(['erro' => 'Mensagem obrigatória.'], 422);
        return;
    }
    $stmt = $pdo->prepare("SELECT id, autor FROM comentarios WHERE id = :cid AND ticket_id = :tid");
    $stmt->execute(['cid' => $commentId, 'tid' => $ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        tickets_json(['erro' => 'Comentário não encontrado.'], 404);
        return;
    }
    $isPrivileged = in_array($perfilLowerGlobal, ['administrador', 'moderador'], true);
    if ((string)($row['autor'] ?? '') !== $loginUsuarioGlobal && !$isPrivileged) {
        tickets_json(['erro' => 'Sem permissão.'], 403);
        return;
    }
    $up = $pdo->prepare("UPDATE comentarios SET comentario = :c WHERE id = :cid");
    $up->execute(['c' => $texto, 'cid' => $commentId]);
    tickets_json(['ok' => true]);
    return;
}

// =========================
// POST /tickets/{id}/comentario-live/{cid}/excluir
// =========================
if (preg_match('~^/([0-9]+)/comentario-live/([0-9]+)/excluir$~', $subPath, $m) && $method === 'POST') {
    $ticketId = (int)$m[1];
    $commentId = (int)$m[2];
    $stmt = $pdo->prepare("SELECT id, autor FROM comentarios WHERE id = :cid AND ticket_id = :tid");
    $stmt->execute(['cid' => $commentId, 'tid' => $ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        tickets_json(['erro' => 'Comentário não encontrado.'], 404);
        return;
    }
    $isPrivileged = in_array($perfilLowerGlobal, ['administrador', 'moderador'], true);
    if ((string)($row['autor'] ?? '') !== $loginUsuarioGlobal && !$isPrivileged) {
        tickets_json(['erro' => 'Sem permissão.'], 403);
        return;
    }
    $del = $pdo->prepare("DELETE FROM comentarios WHERE id = :cid");
    $del->execute(['cid' => $commentId]);
    $meta = tickets_live_meta_read();
    $tk = (string)$ticketId;
    if (isset($meta['reply'][$tk][(string)$commentId])) unset($meta['reply'][$tk][(string)$commentId]);
    if (isset($meta['reactions'][$tk][(string)$commentId])) unset($meta['reactions'][$tk][(string)$commentId]);
    tickets_live_meta_write($meta);
    tickets_json(['ok' => true]);
    return;
}

// =========================
// POST /tickets/{id}/comentario-live/{cid}/reaction
// =========================
if (preg_match('~^/([0-9]+)/comentario-live/([0-9]+)/reaction$~', $subPath, $m) && $method === 'POST') {
    $ticketId = (int)$m[1];
    $commentId = (int)$m[2];
    $emoji = trim((string)($_POST['emoji'] ?? ''));
    if ($emoji === '') {
        tickets_json(['erro' => 'Emoji obrigatório.'], 422);
        return;
    }
    $meta = tickets_live_meta_read();
    $tk = (string)$ticketId;
    $ck = (string)$commentId;
    if (!isset($meta['reactions'][$tk]) || !is_array($meta['reactions'][$tk])) $meta['reactions'][$tk] = [];
    if (!isset($meta['reactions'][$tk][$ck]) || !is_array($meta['reactions'][$tk][$ck])) $meta['reactions'][$tk][$ck] = [];
    if (!isset($meta['reactions'][$tk][$ck][$emoji]) || !is_array($meta['reactions'][$tk][$ck][$emoji])) $meta['reactions'][$tk][$ck][$emoji] = [];
    $users = $meta['reactions'][$tk][$ck][$emoji];
    $idx = array_search($loginUsuarioGlobal, $users, true);
    if ($idx === false) {
        $users[] = $loginUsuarioGlobal;
    } else {
        array_splice($users, $idx, 1);
    }
    if (empty($users)) {
        unset($meta['reactions'][$tk][$ck][$emoji]);
    } else {
        $meta['reactions'][$tk][$ck][$emoji] = array_values($users);
    }
    tickets_live_meta_write($meta);
    tickets_json(['ok' => true]);
    return;
}

// =========================
// POST /tickets/{id}/seen-live
// =========================
if (preg_match('~^/([0-9]+)/seen-live$~', $subPath, $m) && $method === 'POST') {
    $ticketId = (int)$m[1];
    $lastSeen = (int)($_POST['last_seen_id'] ?? 0);
    $meta = tickets_live_meta_read();
    $tk = (string)$ticketId;
    if (!isset($meta['seen'][$tk]) || !is_array($meta['seen'][$tk])) $meta['seen'][$tk] = [];
    $meta['seen'][$tk][$loginUsuarioGlobal] = $lastSeen;
    tickets_live_meta_write($meta);
    tickets_json(['ok' => true]);
    return;
}

// =========================
// POST /tickets/{id}/typing-live (atualiza digitando)
// =========================
if (preg_match('~^/([0-9]+)/typing-live$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $isTyping = (int)($_POST['typing'] ?? 0) === 1;
    $stmtTicket = $pdo->prepare("SELECT id, solicitante, categoria FROM tickets WHERE id = :id");
    $stmtTicket->execute(['id' => $id]);
    $ticketInfo = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    if (!$ticketInfo) {
        tickets_json(['erro' => 'Ticket não encontrado.'], 404);
        return;
    }
    if (strtolower((string)$perfil) === 'comum' && (string)($ticketInfo['solicitante'] ?? '') !== $loginUsuarioGlobal) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticketInfo['categoria'] ?? ''), $categoriasRecepcao, true)) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($isPerfilManutencao && categoria_normalize_storage((string)($ticketInfo['categoria'] ?? '')) !== categoria_manutencao_storage()) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    $key = $id . '::' . $loginUsuarioGlobal;
    $all = tickets_typing_read_all();
    $now = time();
    foreach ($all as $k => $v) {
        if (!is_array($v)) {
            unset($all[$k]);
            continue;
        }
        $ts = (int)($v['ts'] ?? 0);
        if (($now - $ts) > 10) {
            unset($all[$k]);
        }
    }
    if ($isTyping) {
        $all[$key] = [
            'ticket_id' => $id,
            'usuario' => $loginUsuarioGlobal,
            'nome' => is_array($usuario) ? (string)($usuario['nome'] ?? $usuario['usuario'] ?? $loginUsuarioGlobal) : $loginUsuarioGlobal,
            'ts' => $now,
        ];
    } else {
        unset($all[$key]);
    }
    tickets_typing_write_all($all);
    tickets_json(['ok' => true]);
    return;
}

// =========================
// GET /tickets/{id}/typing-live (lista quem está digitando)
// =========================
if (preg_match('~^/([0-9]+)/typing-live$~', $subPath, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $stmtTicket = $pdo->prepare("SELECT id, solicitante, categoria FROM tickets WHERE id = :id");
    $stmtTicket->execute(['id' => $id]);
    $ticketInfo = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    if (!$ticketInfo) {
        tickets_json(['erro' => 'Ticket não encontrado.'], 404);
        return;
    }
    if (strtolower((string)$perfil) === 'comum' && (string)($ticketInfo['solicitante'] ?? '') !== $loginUsuarioGlobal) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($perfilLowerGlobal === 'recepcao' && !in_array((string)($ticketInfo['categoria'] ?? ''), $categoriasRecepcao, true)) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    if ($isPerfilManutencao && categoria_normalize_storage((string)($ticketInfo['categoria'] ?? '')) !== categoria_manutencao_storage()) {
        tickets_json(['erro' => 'Acesso negado.'], 403);
        return;
    }
    $all = tickets_typing_read_all();
    $now = time();
    $out = [];
    foreach ($all as $k => $v) {
        if (!is_array($v)) {
            continue;
        }
        $ts = (int)($v['ts'] ?? 0);
        if (($now - $ts) > 10) {
            continue;
        }
        if ((int)($v['ticket_id'] ?? 0) !== $id) {
            continue;
        }
        $userTyping = (string)($v['usuario'] ?? '');
        if ($userTyping === $loginUsuarioGlobal) {
            continue;
        }
        $out[] = [
            'usuario' => $userTyping,
            'nome' => (string)($v['nome'] ?? $userTyping),
        ];
    }
    tickets_json(['typing' => $out]);
    return;
}

// =========================
// POST /tickets/{id}/comentario  (adiciona comentário com possível upload)
// =========================
if (preg_match('~^/([0-9]+)/comentario$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $texto = trim($_POST['comentario'] ?? '');
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets/' . $id, $returnTo);

    if ($texto === '') {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Comentário obrigatório.'));
    }

    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $perfilLower  = strtolower((string)$perfil);

    // Regras de bloqueio/reabertura
    $stmtTicketInfo = $pdo->prepare("SELECT solicitante, status, avaliacao FROM tickets WHERE id = :id");
    $stmtTicketInfo->execute(['id' => $id]);
    $ticketInfo = $stmtTicketInfo->fetch(PDO::FETCH_ASSOC);
    if (!$ticketInfo) {
        redirect($ticketsRedirectWithNotice('/tickets', 'erro', 'Ticket não encontrado.'));
    }
    $stInfo = strtolower((string)($ticketInfo['status'] ?? ''));
    $isFechadoInfo = in_array($stInfo, ['fechado', 'resolvido'], true);
    $avaliacaoInfo = isset($ticketInfo['avaliacao']) ? (int)$ticketInfo['avaliacao'] : null;
    $temAvaliacaoValida = $avaliacaoInfo !== null && $avaliacaoInfo >= 1 && $avaliacaoInfo <= 5;

    // 1) Se ticket está fechado e já tem avaliação, ninguém mais pode comentar
    if ($isFechadoInfo && $temAvaliacaoValida) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Ticket já avaliado e encerrado. Não é possível adicionar novos comentários.'));
    }

    // Upload de anexos do comentário
    $anexosJson = null;
    if (!empty($_FILES['anexos']) && is_array($_FILES['anexos']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/comentarios';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $anexos = [];
        $names   = $_FILES['anexos']['name'];
        $tmpNames= $_FILES['anexos']['tmp_name'];
        $errors  = $_FILES['anexos']['error'];

        foreach ($names as $idx => $nomeOriginal) {
            $nomeOriginal = (string)$nomeOriginal;
            $error = $errors[$idx] ?? UPLOAD_ERR_NO_FILE;
            $tmp   = $tmpNames[$idx] ?? null;

            if ($error === UPLOAD_ERR_NO_FILE || !$tmp) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $ext = $ext ? preg_replace('~[^a-zA-Z0-9]~', '', $ext) : '';
            $safeName = 'coment_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            if ($ext !== '') {
                $safeName .= '.' . strtolower($ext);
            }

            $destinoFs = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
            if (@move_uploaded_file($tmp, $destinoFs)) {
                $anexos[] = [
                    'nome_original' => $nomeOriginal,
                    'arquivo'       => '/uploads/comentarios/' . $safeName,
                ];
            }
        }

        if (!empty($anexos)) {
            $anexosJson = json_encode($anexos, JSON_UNESCAPED_UNICODE);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO comentarios (ticket_id, autor, comentario, anexos, data_criacao)
        VALUES (:id, :autor, :comentario, :anexos, NOW())
    ");
    $stmt->execute([
        'id'        => $id,
        'autor'     => $loginUsuario,
        'comentario'=> $texto,
        'anexos'    => $anexosJson,
    ]);

    // Quem comentar é atribuído automaticamente (exceto perfil comum)
    if ($perfilLower !== 'comum') {
        $upStmt = $pdo->prepare("
            UPDATE tickets SET responsavel = :r, status = IF(status = 'aberto', 'em_andamento', status), updated_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute(['r' => $loginUsuario, 'id' => $id]);
    } else {
        // 2) Se for solicitante comum comentando em ticket fechado ainda não avaliado, reabrir para em_andamento
        $ehSolicitante = (string)($ticketInfo['solicitante'] ?? '') === $loginUsuario;
        if ($ehSolicitante && $isFechadoInfo && !$temAvaliacaoValida) {
            $upStmt = $pdo->prepare("
                UPDATE tickets
                SET status = 'em_andamento', updated_at = NOW()
                WHERE id = :id
            ");
            $upStmt->execute(['id' => $id]);
        }
    }

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Comentário adicionado!'));
}

// =========================
// POST /tickets/{id}/fechar-solicitante  (solicitante encerra e segue para avaliação CSAT)
// =========================
if (preg_match('~^/([0-9]+)/fechar-solicitante$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $perfilLowerFs = strtolower((string)$perfil);
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets/' . $id, $returnTo);

    if ($perfilLowerFs !== 'comum') {
        http_response_code(403);
        echo 'Apenas o solicitante pode encerrar o chamado por esta ação.';
        return;
    }

    $stmt = $pdo->prepare('SELECT id, solicitante, status, responsavel FROM tickets WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $tkFs = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tkFs) {
        redirect($ticketsRedirectWithNotice('/tickets/novo', 'erro', 'Ticket não encontrado.'));
    }
    if ((string)($tkFs['solicitante'] ?? '') !== $loginUsuario) {
        redirect($ticketsRedirectWithNotice('/tickets/novo', 'erro', 'Você não pode encerrar este chamado.'));
    }
    if (trim((string)($tkFs['responsavel'] ?? '')) === '') {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'É necessário haver um responsável atribuído ao chamado antes de encerrar.'));
    }

    $stFs = strtolower((string)($tkFs['status'] ?? ''));
    if (in_array($stFs, ['fechado', 'resolvido'], true)) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Este chamado já está encerrado.'));
    }

    try {
        $upFs = $pdo->prepare("UPDATE tickets SET status = 'fechado', fechado_por = :fechado_por, fechado_em = NOW(), updated_at = NOW() WHERE id = :id");
        $upFs->execute(['fechado_por' => $loginUsuario, 'id' => $id]);
    } catch (Throwable $e) {
        if (str_contains((string)$e->getMessage(), 'fechado_por') || str_contains((string)$e->getMessage(), 'Unknown column')) {
            $upFs = $pdo->prepare("UPDATE tickets SET status = 'fechado', fechado_em = NOW(), updated_at = NOW() WHERE id = :id");
            $upFs->execute(['id' => $id]);
        } else {
            throw $e;
        }
    }

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Chamado encerrado. Avalie o atendimento abaixo.', ['focus_avaliacao' => '1']));
}

// =========================
// POST /tickets/{id}/status  (altera status)
// =========================
if (preg_match('~^/([0-9]+)/status$~', $subPath, $m) && $method === 'POST') {
    if (!$isAdminLike) {
        http_response_code(403);
        echo 'Apenas administrador/moderador/recepção/manutenção podem alterar status.';
        return;
    }

    $id = (int)$m[1];
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets/' . $id, $returnTo);
    $stmtTicket = $pdo->prepare("SELECT status, responsavel FROM tickets WHERE id = :id");
    $stmtTicket->execute(['id' => $id]);
    $ticketAtual = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    if (!$ticketAtual) {
        redirect($ticketsRedirectWithNotice('/tickets', 'erro', 'Ticket não encontrado.'));
    }
    $stAtual = strtolower((string)($ticketAtual['status'] ?? ''));
    $estaFechado = in_array($stAtual, ['fechado', 'resolvido'], true);
    if ($estaFechado && strtolower((string)$perfil) !== 'administrador') {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Apenas o administrador pode alterar o status de tickets fechados.'));
    }

    $novoStatus = trim($_POST['status'] ?? '');
    $permitidos = ['aberto', 'em_andamento', 'fechado'];
    if (!in_array($novoStatus, $permitidos, true)) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Status inválido.'));
    }

    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;

    // Administrador, moderador e recepção: só podem fechar se houver responsável atribuído
    if ($novoStatus === 'fechado') {
        $respAtual = trim((string)($ticketAtual['responsavel'] ?? ''));
        if ($respAtual === '') {
            redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Para fechar o chamado, atribua um responsável antes.'));
        }
    }

    try {
        if ($novoStatus === 'fechado') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = :s, fechado_por = :fechado_por, fechado_em = NOW(), updated_at = NOW() WHERE id = :id");
            $stmt->execute(['s' => $novoStatus, 'fechado_por' => $loginUsuario, 'id' => $id]);
        } else {
            // Ao reabrir/colocar em andamento, removemos a avaliação (volta a ficar pendente)
            $stmt = $pdo->prepare("UPDATE tickets SET status = :s, fechado_por = NULL, fechado_em = NULL, avaliacao = NULL, avaliacao_em = NULL, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['s' => $novoStatus, 'id' => $id]);
        }
    } catch (Throwable $e) {
        // Coluna fechado_por ou avaliacao*_ pode não existir ainda; fallback para update simples
        $stmt = $pdo->prepare("UPDATE tickets SET status = :s, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['s' => $novoStatus, 'id' => $id]);
    }

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Status atualizado com sucesso!'));
}

// =========================
// POST /tickets/{id}/avaliar  (avalia ticket fechado: 1 a 5, 5 = ótimo)
// =========================
if (preg_match('~^/([0-9]+)/avaliar$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets/' . $id, $returnTo);
    $avaliacao = (int)($_POST['avaliacao'] ?? 0);
    if ($avaliacao < 1 || $avaliacao > 5) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Avaliação inválida. Escolha de 1 a 5.'));
    }

    $stmt = $pdo->prepare("SELECT id, solicitante, status, avaliacao FROM tickets WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        redirect($ticketsRedirectWithNotice('/tickets', 'erro', 'Ticket não encontrado.'));
    }

    $loginUsuario = is_array($usuario) ? ($usuario['usuario'] ?? '') : (string)$usuario;
    $perfilLower = strtolower((string)$perfil);
    $st = strtolower((string)($ticket['status'] ?? ''));
    $isFechado = in_array($st, ['fechado', 'resolvido'], true);

    if (!$isFechado) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Apenas tickets fechados podem ser avaliados.'));
    }

    $ehAdminUsuario = (strtolower((string)$loginUsuario) === 'admin');
    $ehSolicitante = (string)($ticket['solicitante'] ?? '') === $loginUsuario;
    $avaliacaoAtual = isset($ticket['avaliacao']) && $ticket['avaliacao'] >= 1 && $ticket['avaliacao'] <= 5;
    $semAvaliacao = !$avaliacaoAtual;

    // Regra: usuário admin pode tudo (avaliar e alterar). Perfil comum (como solicitante) só pode avaliar (não alterar).
    $podeAvaliar = false;
    if ($ehAdminUsuario) {
        $podeAvaliar = true;
    } elseif ($semAvaliacao && $ehSolicitante && $perfilLower === 'comum') {
        $podeAvaliar = true; // Solicitante com perfil comum pode fazer a avaliação inicial
    }

    if (!$podeAvaliar) {
        if ($avaliacaoAtual) {
            redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Apenas o usuário admin pode alterar a avaliação.'));
        }
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Apenas o solicitante (perfil comum) pode avaliar. Para alterar, somente o usuário admin.'));
    }

    try {
        $upStmt = $pdo->prepare("UPDATE tickets SET avaliacao = :a, avaliacao_em = NOW(), updated_at = NOW() WHERE id = :id");
        $upStmt->execute(['a' => $avaliacao, 'id' => $id]);
    } catch (Throwable $e) {
        if (str_contains((string)$e->getMessage(), 'avaliacao') || str_contains((string)$e->getMessage(), 'Unknown column')) {
            redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Coluna de avaliação não encontrada. Execute a migration add_avaliacao.sql.'));
        }
        throw $e;
    }

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Avaliação registrada com sucesso!'));
}

// =========================
// POST /tickets/{id}/atribuir  (atribui responsável)
// =========================
if (preg_match('~^/([0-9]+)/atribuir$~', $subPath, $m) && $method === 'POST') {
    if (!$canAssign) {
        http_response_code(403);
        echo 'Apenas administrador, moderador, recepção e manutenção podem atribuir responsáveis.';
        return;
    }

    $id = (int)$m[1];
    $novoResponsavel = trim($_POST['responsavel'] ?? '');
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets', $returnTo);

    // Permite limpar responsável
    if ($novoResponsavel !== '') {
        $perfilAtribuidor = strtolower(trim((string)$perfil));
        if ($perfilAtribuidor === 'recepcao') {
            $chk = $pdo->prepare(
                "SELECT usuario FROM usuarios WHERE usuario = :u AND ativo = 1 AND LOWER(TRIM(COALESCE(perfil, ''))) = 'recepcao'"
            );
        } elseif ($perfilAtribuidor === 'manutencao') {
            $chk = $pdo->prepare(
                "SELECT usuario FROM usuarios WHERE usuario = :u AND ativo = 1 AND LOWER(TRIM(COALESCE(perfil, ''))) = 'manutencao'"
            );
        } else {
            $chk = $pdo->prepare("SELECT usuario FROM usuarios WHERE usuario = :u AND ativo = 1");
        }
        $chk->execute(['u' => $novoResponsavel]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            $msgErro = $perfilAtribuidor === 'recepcao'
                ? 'Recepção só pode atribuir o chamado a usuários com perfil Recepção.'
                : ($perfilAtribuidor === 'manutencao'
                    ? 'Manutenção só pode atribuir o chamado a usuários com perfil Manutenção.'
                    : 'Responsável inválido ou inativo.');
            redirect($ticketsRedirectWithNotice($redirectBase, 'erro', $msgErro));
        }
    }

    // Ao atribuir responsável, status passa para em_andamento (se estiver aberto)
    if ($novoResponsavel !== '') {
        $stmt = $pdo->prepare("UPDATE tickets SET responsavel = :r, status = 'em_andamento', updated_at = NOW() WHERE id = :id AND status = 'aberto'");
        $stmt->execute(['r' => $novoResponsavel, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("UPDATE tickets SET responsavel = :r, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['r' => $novoResponsavel, 'id' => $id]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE tickets SET responsavel = :r, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['r' => null, 'id' => $id]);
    }

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Responsável atualizado com sucesso!'));
}

// =========================
// POST /tickets/{id}/categoria  (altera categoria do ticket) - perfis de equipe
// =========================
if (preg_match('~^/([0-9]+)/categoria$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets/' . $id, $returnTo);
    if (!$isAdminLike) {
        redirect($ticketsRedirectWithNotice('/tickets', 'erro', 'Apenas administrador, moderador, recepção ou manutenção podem alterar a categoria.'));
    }

    $novaCategoria = categoria_normalize_storage(trim($_POST['categoria'] ?? ''));
    $categoriasValidas = ['Suporte', 'Hardware', 'Software', 'Rede/Infraestrutura', 'Desenvolvimento', categoria_manutencao_storage(), 'Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];

    if ($novaCategoria === '' || !in_array($novaCategoria, $categoriasValidas, true)) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'erro', 'Categoria inválida.'));
    }

    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = :id");
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        redirect($ticketsRedirectWithNotice('/tickets', 'erro', 'Ticket não encontrado.'));
    }

    $upStmt = $pdo->prepare("UPDATE tickets SET categoria = :c, updated_at = NOW() WHERE id = :id");
    $upStmt->execute(['c' => $novaCategoria, 'id' => $id]);

    $perfilAtual = strtolower((string)$perfil);
    $categoriaManutencao = categoria_manutencao_storage();
    $categoriaVaiParaManutencao = categoria_normalize_storage($novaCategoria) === $categoriaManutencao;

    // Se a pessoa transferiu para uma categoria fora do seu escopo, redireciona para lista com aviso amigável.
    // Isso evita cair na tela de detalhes e receber 403 logo após a transferência.
    if ($perfilAtual === 'recepcao' && !in_array($novaCategoria, $categoriasRecepcao, true)) {
        $msgTransferencia = $categoriaVaiParaManutencao
            ? 'Chamado transferido para o setor de Manutenção.'
            : 'Chamado transferido para a equipe de Suporte Técnico.';
        redirect($ticketsRedirectWithNotice('/tickets', 'msg', $msgTransferencia));
    }
    if ($perfilAtual === 'manutencao' && !$categoriaVaiParaManutencao) {
        $msgTransferencia = in_array($novaCategoria, $categoriasRecepcao, true)
            ? 'Chamado transferido para o setor de Recepção.'
            : 'Chamado transferido para a equipe de Suporte Técnico.';
        redirect($ticketsRedirectWithNotice('/tickets', 'msg', $msgTransferencia));
    }

    // Mensagem padrão para quem permanece com acesso ao ticket
    if ($categoriaVaiParaManutencao) {
        redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Chamado transferido para o setor de Manutenção.'));
    }
    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Categoria atualizada com sucesso!'));
}

// =========================
// POST /tickets/{id}/excluir  (exclui ticket) - apenas usuário admin
// =========================
if (preg_match('~^/([0-9]+)/excluir$~', $subPath, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $returnTo = trim($_POST['return_to'] ?? '');
    $redirectBase = $ticketsRedirectBaseFromContext('/tickets', $returnTo);
    $loginUsuario = is_array($usuario) ? strtolower((string)($usuario['usuario'] ?? '')) : strtolower((string)$usuario);
    if ($loginUsuario !== 'admin') {
        http_response_code(403);
        echo 'Apenas o usuário admin pode excluir tickets.';
        return;
    }

    // Remove comentários primeiro, se existir tabela
    try {
        $cDel = $pdo->prepare("DELETE FROM comentarios WHERE ticket_id = :id");
        $cDel->execute(['id' => $id]);
    } catch (Throwable $e) {
        // ignora
    }

    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
    $stmt->execute(['id' => $id]);

    redirect($ticketsRedirectWithNotice($redirectBase, 'msg', 'Ticket excluído com sucesso!'));
}

// =========================
// Rota não encontrada dentro de /tickets
// =========================
http_response_code(404);
view('404', ['perfil' => $perfil]);

