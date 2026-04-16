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

// Administradores, moderadores, recepção e manutenção podem acessar relatórios (manutenção: categoria manutencao no BD, como recepção)
$perfilLower = strtolower((string)$perfil);
if (!in_array($perfilLower, ['administrador', 'moderador', 'recepcao', 'manutencao'], true)) {
    http_response_code(403);
    echo 'Acesso restrito.';
    exit;
}

/** @var PDO $pdo */

$basePrefix = '/relatorio';
$subPath = substr($uri, strlen($basePrefix));
$subPath = $subPath === '' ? '/' : $subPath;

$categoriasRecepcao = ['Tonner', 'Drum', 'Tonner & Drum', 'Uso e Consumo'];
$categoriasManutencao = [categoria_manutencao_storage()];
$buildRelatorioQuery = function () use ($pdo, $perfilLower, $categoriasRecepcao, $categoriasManutencao): array {
    $status        = $_GET['status']        ?? '';
    $categoria     = trim((string)($_GET['categoria'] ?? ''));
    if ($categoria !== '') {
        $categoria = categoria_normalize_storage($categoria);
    }
    $prioridade    = $_GET['prioridade']   ?? '';
    $responsavelF  = $_GET['responsavel']  ?? '';
    $dataIni       = $_GET['data_ini']      ?? '';
    $dataFim       = $_GET['data_fim']      ?? '';
    $numeroChamado = $_GET['numero_chamado']?? '';
    $filialF       = $_GET['filial']        ?? '';
    $avaliacaoF    = $_GET['avaliacao']     ?? '';
    $pendentesAvaliacao = !empty($_GET['pendentes_avaliacao']) || $avaliacaoF === 'pendente';
    $fechadosHoje  = !empty($_GET['fechados_hoje']);
    $criadosHoje   = !empty($_GET['criados_hoje']);
    $urgentesAbertos = !empty($_GET['urgentes_abertos']);
    $csatPeriodo = (string)($_GET['csat_periodo'] ?? '');

    if ($pendentesAvaliacao || $fechadosHoje) {
        $dataIni = $dataFim = '';
        if ($pendentesAvaliacao) $avaliacaoF = 'pendente';
    }
    if ($criadosHoje) {
        $dataIni = date('Y-m-d');
        $dataFim = date('Y-m-d');
    }
    $csatDataIni = '';
    $csatDataFim = '';
    if (in_array($csatPeriodo, ['semanal', 'mensal'], true)) {
        $csatDataIni = $csatPeriodo === 'semanal' ? date('Y-m-d', strtotime('-6 days')) : date('Y-m-01');
        $csatDataFim = date('Y-m-d');
        // Para CSAT, o período deve considerar apenas a data da avaliação.
        $dataIni = '';
        $dataFim = '';
    }

    $sql = "
        SELECT t.*,
               f.codigo AS filial_nome,
               u_solicitante.usuario AS solicitante_usuario,
               COALESCE(u_solicitante.nome, u_solicitante.usuario) AS solicitante_nome,
               u_responsavel.usuario AS responsavel_usuario,
               COALESCE(u_responsavel.nome, u_responsavel.usuario) AS responsavel_nome,
               COALESCE(u_fechado.nome, u_fechado.usuario) AS fechado_por_nome
        FROM tickets t
        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
        LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
        LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
        LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario
    ";
    $where  = [];
    $params = [];
    if ($perfilLower === 'recepcao') {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasRecepcao), '?')) . ")";
        $params = array_merge($params, $categoriasRecepcao);
    }
    if ($perfilLower === 'manutencao') {
        $where[] = "t.categoria IN (" . implode(',', array_fill(0, count($categoriasManutencao), '?')) . ")";
        $params = array_merge($params, $categoriasManutencao);
    }
    if ($status !== '') {
        if ($status === 'Aberto') {
            $where[] = "t.status = 'aberto'";
        } elseif ($status === 'Em andamento') {
            $where[] = "t.status = 'em_andamento'";
        } elseif (in_array($status, ['Fechado', 'Resolvido', 'resolvidos'], true)) {
            $where[] = "(t.status = 'fechado' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'Resolvido')";
        } else {
            $where[]  = "t.status = ?";
            $params[] = $status;
        }
    }
    if ($categoria !== '') {
        if (in_array($categoria, ['Rede/Infraestrutura', 'Rede'], true)) {
            $where[] = "(t.categoria = ? OR t.categoria = ?)";
            $params[] = 'Rede/Infraestrutura';
            $params[] = 'Rede';
        } else {
            $where[] = "t.categoria = ?";
            $params[] = $categoria;
        }
    }
    if ($prioridade !== '' && !$urgentesAbertos) { $where[] = "t.prioridade = ?"; $params[] = $prioridade; }
    if ($responsavelF !== '') { $where[] = "t.responsavel = ?"; $params[] = $responsavelF; }
    if ($dataIni !== '') { $where[] = "DATE(t.created_at) >= ?"; $params[] = $dataIni; }
    if ($dataFim !== '') { $where[] = "DATE(t.created_at) <= ?"; $params[] = $dataFim; }
    if ($numeroChamado !== '') { $where[] = "t.numero_chamado LIKE ?"; $params[] = '%' . $numeroChamado . '%'; }
    if ($filialF !== '') { $where[] = "f.codigo = ?"; $params[] = $filialF; }
    if ($avaliacaoF !== '' && in_array($avaliacaoF, ['1', '2', '3', '4', '5'], true)) {
        $where[] = "t.avaliacao = ?";
        $params[] = (int)$avaliacaoF;
    }
    if (in_array($csatPeriodo, ['semanal', 'mensal'], true)) {
        $where[] = "t.avaliacao IS NOT NULL";
        $where[] = "t.avaliacao BETWEEN 1 AND 5";
        $where[] = "DATE(t.avaliacao_em) >= ?";
        $where[] = "DATE(t.avaliacao_em) <= ?";
        $params[] = $csatDataIni;
        $params[] = $csatDataFim;
    }
    if ($pendentesAvaliacao) {
        $where[] = "(t.status = 'fechado' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'Resolvido')";
        $where[] = "t.avaliacao IS NULL";
        $where[] = "DATE(COALESCE(t.fechado_em, t.updated_at)) = CURDATE()";
    }
    if ($fechadosHoje && !$pendentesAvaliacao) {
        $where[] = "(t.status = 'fechado' OR t.status = 'resolvido' OR t.status = 'Fechado' OR t.status = 'Resolvido')";
        $where[] = "DATE(COALESCE(t.fechado_em, t.updated_at)) = CURDATE()";
    }
    if ($urgentesAbertos) {
        $where[] = "(t.prioridade = 'Alta' OR t.prioridade = 'alta')";
        $where[] = "t.status NOT IN ('fechado', 'resolvido', 'Fechado', 'Resolvido')";
        $where[] = "DATE(t.created_at) = CURDATE()";
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.created_at DESC';
    return [$sql, $params, [
        'status' => $fechadosHoje || $pendentesAvaliacao ? 'Fechado' : $status,
        'categoria' => $categoria, 'prioridade' => $urgentesAbertos ? 'Alta' : $prioridade,
        'responsavel' => $responsavelF,
        'data_ini' => in_array($csatPeriodo, ['semanal', 'mensal'], true) ? $csatDataIni : $dataIni,
        'data_fim' => in_array($csatPeriodo, ['semanal', 'mensal'], true) ? $csatDataFim : $dataFim,
        'numero_chamado' => $numeroChamado, 'filial' => $filialF,
        'avaliacao' => $avaliacaoF,
        'pendentes_avaliacao' => $pendentesAvaliacao, 'fechados_hoje' => $fechadosHoje,
        'criados_hoje' => $criadosHoje, 'urgentes_abertos' => $urgentesAbertos, 'csat_periodo' => $csatPeriodo,
    ]];
};

// ================
// GET /relatorio
// ================
if ($subPath === '/' && $method === 'GET') {
    $stmtResp = $pdo->query("
        SELECT id, usuario, perfil, nome
        FROM usuarios
        WHERE perfil IN ('administrador', 'moderador', 'recepcao', 'manutencao') AND ativo = 1 AND LOWER(usuario) <> 'dashboard' AND LOWER(usuario) <> 'dash'
        ORDER BY nome
    ");
    $responsaveis = $stmtResp->fetchAll(PDO::FETCH_ASSOC);
    $stmtFil = $pdo->query("SELECT codigo FROM filiais ORDER BY CAST(codigo AS UNSIGNED)");
    $filiais = $stmtFil->fetchAll(PDO::FETCH_ASSOC);

    [$sql, $params, $filtros] = $buildRelatorioQuery();
    $ticketsPage = max(1, (int)($_GET['ticketsPage'] ?? 1));
    $ticketsLimit = 20;
    $ticketsOffset = ($ticketsPage - 1) * $ticketsLimit;

    $countSql = "
        SELECT COUNT(*) AS total
        FROM tickets t
        LEFT JOIN filiais f ON f.codigo = t.filial_codigo
        LEFT JOIN usuarios u_solicitante ON t.solicitante = u_solicitante.usuario
        LEFT JOIN usuarios u_responsavel ON t.responsavel = u_responsavel.usuario
        LEFT JOIN usuarios u_fechado ON t.fechado_por = u_fechado.usuario
    ";
    $wherePos = stripos($sql, ' WHERE ');
    if ($wherePos !== false) {
        $countSql .= substr($sql, $wherePos, stripos($sql, ' ORDER BY ') - $wherePos);
    }
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalTickets = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $totalTicketsPages = max(1, (int)ceil($totalTickets / $ticketsLimit));
    if ($ticketsPage > $totalTicketsPages) {
        $ticketsPage = $totalTicketsPages;
        $ticketsOffset = ($ticketsPage - 1) * $ticketsLimit;
    }

    $sql .= ' LIMIT ? OFFSET ?';
    $stmtTickets = $pdo->prepare($sql);
    $stmtTickets->execute(array_merge($params, [$ticketsLimit, $ticketsOffset]));
    $tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

    view('relatorio', [
        'perfil'  => $perfil,
        'usuario' => $usuario,
        'tickets' => $tickets,
        'responsaveis' => $responsaveis,
        'filiais' => $filiais,
        'filtros' => $filtros,
        'paginacao' => [
            'tickets' => [
                'paginaAtual' => $ticketsPage,
                'totalPaginas' => $totalTicketsPages,
                'totalItens' => $totalTickets,
                'limite' => $ticketsLimit,
            ],
        ],
    ]);
    return;
}

// ================
// GET /relatorio/exportar/excel (CSV para Excel)
// ================
if ($subPath === '/exportar/excel' && $method === 'GET') {
    [$sql, $params] = $buildRelatorioQuery();
    $sql .= ' LIMIT 5000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    // Mesmas colunas da tela/PDF: inclui Avaliado em
    fputcsv($out, ['Número', 'Título', 'Status', 'Prioridade', 'Categoria', 'Solicitante', 'Responsável', 'Filial', 'Fechado por', 'Avaliação', 'Avaliado em', 'Aberto em', 'Fechado em', 'Tempo resolução'], ';');
    foreach ($tickets as $t) {
        $solicitanteNome = $t['solicitante_nome'] ?? $t['solicitante'] ?? '';
        $responsavelNome = $t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não atribuído';
        $createdRaw = $t['created_at'] ?? '';
        $updatedRaw = $t['updated_at'] ?? '';
        $fechadoRaw = $t['fechado_em'] ?? $updatedRaw;
        $created = $createdRaw;
        $updated = $fechadoRaw;
        if ($created) {
            try {
                $d = new DateTime($created);
                $created = $d->format('d/m/Y H:i');
            } catch (Throwable $e) {}
        }
        if ($updated) {
            try {
                $d = new DateTime($updated);
                $updated = $d->format('d/m/Y H:i');
            } catch (Throwable $e) { $updated = ''; }
        }
        $isFechado = in_array(strtolower((string)($t['status'] ?? '')), ['fechado', 'resolvido'], true);
        $fechadoEm = ($isFechado && $updated) ? $updated : '-';
        $tempoResolucao = '-';
        if ($isFechado && !empty($createdRaw) && !empty($fechadoRaw)) {
            try {
                $tsA = (new DateTime($createdRaw))->getTimestamp();
                $tsF = (new DateTime($fechadoRaw))->getTimestamp();
                $seg = $tsF - $tsA;
                if ($seg < 60) {
                    $tempoResolucao = $seg . ' s';
                } elseif ($seg < 3600) {
                    $tempoResolucao = (int)floor($seg / 60) . ' min';
                } elseif ($seg < 86400) {
                    $h = (int)floor($seg / 3600);
                    $m = (int)floor(($seg % 3600) / 60);
                    $tempoResolucao = $h . 'h' . ($m > 0 ? ' ' . $m . ' min' : '');
                } else {
                    $dDias = (int)floor($seg / 86400);
                    $h = (int)floor(($seg % 86400) / 3600);
                    $tempoResolucao = $dDias . ' dia' . ($dDias !== 1 ? 's' : '') . ($h > 0 ? ' ' . $h . 'h' : '');
                }
            } catch (Throwable $e) {}
        }
        $av = isset($t['avaliacao']) ? (int)$t['avaliacao'] : null;
        $avaliacaoCsv = ($av >= 1 && $av <= 5) ? ($av . '/5') : '-';
        $avaliadoEmCsv = '-';
        if (!empty($t['avaliacao_em'])) {
            try {
                $avaliadoEmCsv = (new DateTime((string)$t['avaliacao_em']))->format('d/m/Y H:i');
            } catch (Throwable $e) {}
        }
        fputcsv($out, [
            $t['numero_chamado'] ?? $t['id'],
            $t['titulo'] ?? '',
            $t['status'] ?? '',
            $t['prioridade'] ?? '',
            function_exists('categoria_display') ? categoria_display((string)($t['categoria'] ?? '')) : (string)($t['categoria'] ?? ''),
            $solicitanteNome,
            $responsavelNome,
            $t['filial_nome'] ?? '',
            $t['fechado_por_nome'] ?? '-',
            $avaliacaoCsv,
            $avaliadoEmCsv,
            $created ?: '-',
            $fechadoEm,
            $tempoResolucao,
        ], ';');
    }
    fclose($out);
    exit;
}

// ================
// GET /relatorio/exportar/pdf
// ================
if ($subPath === '/exportar/pdf' && $method === 'GET') {
    [$sql, $params, $filtros] = $buildRelatorioQuery();
    $sql .= ' LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataIni = $filtros['data_ini'] ?? '';
    $dataFim = $filtros['data_fim'] ?? '';
    $periodoTexto = '';
    if ($dataIni !== '' && $dataFim !== '') {
        try {
            $d1 = (new DateTime($dataIni))->format('d/m/Y');
            $d2 = (new DateTime($dataFim))->format('d/m/Y');
            $periodoTexto = 'Período filtrado: ' . $d1 . ' a ' . $d2;
        } catch (Throwable $e) {
            $periodoTexto = 'Período: ' . $dataIni . ' a ' . $dataFim;
        }
    } elseif ($dataIni !== '') {
        try {
            $d1 = (new DateTime($dataIni))->format('d/m/Y');
            $periodoTexto = 'A partir de: ' . $d1;
        } catch (Throwable $e) {
            $periodoTexto = 'A partir de: ' . $dataIni;
        }
    } elseif ($dataFim !== '') {
        try {
            $d2 = (new DateTime($dataFim))->format('d/m/Y');
            $periodoTexto = 'Até: ' . $d2;
        } catch (Throwable $e) {
            $periodoTexto = 'Até: ' . $dataFim;
        }
    } else {
        $periodoTexto = 'Todos os períodos';
    }

    if (class_exists(\Dompdf\Dompdf::class)) {
        $abrev = static function (string $texto, int $maxLen = 35): string {
            $t = trim($texto);
            if (mb_strlen($t) > $maxLen) {
                return mb_substr($t, 0, $maxLen - 2) . '..';
            }
            return $t;
        };
        $abrevStatus = static function (string $s): string {
            $s = strtolower(trim($s));
            if ($s === 'em_andamento' || $s === 'em andamento') return 'Em and.';
            if ($s === 'aberto') return 'Aberto';
            if (in_array($s, ['fechado', 'resolvido'], true)) return 'Fechado';
            return $s;
        };
        $abrevCategoria = static function (string $c) use ($abrev): string {
            $c = function_exists('categoria_display') ? categoria_display($c) : $c;
            if (stripos($c, 'Rede/Infraestrutura') !== false || stripos($c, 'Rede') === 0) return 'Rede/Infra.';
            if (stripos($c, 'Uso e Consumo') !== false) return 'Uso/Consumo';
            if (stripos($c, 'Tonner & Drum') !== false) return 'Tonner&Drum';
            return $abrev($c, 15);
        };
        $abrevPessoa = static function (string $p) use ($abrev): string {
            $p = trim($p);
            if (stripos($p, 'Administrador') !== false) return 'Admin';
            if (stripos($p, 'Não atribuído') !== false || $p === '') return 'N/A';
            return $abrev($p, 18);
        };

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:9px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #333;padding:3px;text-align:left;} th{background:#eee;}</style></head><body>';
        $html .= '<h1>Relatório de Tickets</h1>';
        $html .= '<p><strong>' . htmlspecialchars($periodoTexto) . '</strong></p>';
        $html .= '<p>Total: ' . count($tickets) . ' ticket(s). Gerado em ' . date('d/m/Y H:i') . '</p><table>';
        $html .= '<tr><th>Nº</th><th>Título</th><th>Status</th><th>Prior.</th><th>Categ.</th><th>Solic.</th><th>Resp.</th><th>Filial</th><th>Fechado por</th><th>Aval.</th><th>Avaliado em</th><th>Aberto em</th><th>Fechado em</th><th>Tempo resol.</th></tr>';
        foreach ($tickets as $t) {
            $createdRaw = $t['created_at'] ?? '';
            $updatedRaw = $t['updated_at'] ?? '';
            $fechadoRaw = $t['fechado_em'] ?? $updatedRaw;
            $created = $createdRaw;
            $updated = $fechadoRaw;
            if ($created) {
                try {
                    $d = new DateTime($created);
                    $created = $d->format('d/m/Y H:i');
                } catch (Throwable $e) {}
            }
            if ($updated) {
                try {
                    $d = new DateTime($updated);
                    $updated = $d->format('d/m/Y H:i');
                } catch (Throwable $e) { $updated = ''; }
            }
            $isFechado = in_array(strtolower((string)($t['status'] ?? '')), ['fechado', 'resolvido'], true);
            $fechadoEm = ($isFechado && $updated) ? $updated : '-';
            $tempoResolucao = '-';
            if ($isFechado && !empty($createdRaw) && !empty($fechadoRaw)) {
                try {
                    $tsA = (new DateTime($createdRaw))->getTimestamp();
                    $tsF = (new DateTime($fechadoRaw))->getTimestamp();
                    $seg = $tsF - $tsA;
                    if ($seg < 60) {
                        $tempoResolucao = $seg . ' s';
                    } elseif ($seg < 3600) {
                        $tempoResolucao = (int)floor($seg / 60) . ' min';
                    } elseif ($seg < 86400) {
                        $h = (int)floor($seg / 3600);
                        $m = (int)floor(($seg % 3600) / 60);
                        $tempoResolucao = $h . 'h' . ($m > 0 ? ' ' . $m . ' min' : '');
                    } else {
                        $dDias = (int)floor($seg / 86400);
                        $h = (int)floor(($seg % 86400) / 3600);
                        $tempoResolucao = $dDias . ' dia' . ($dDias !== 1 ? 's' : '') . ($h > 0 ? ' ' . $h . 'h' : '');
                    }
                } catch (Throwable $e) {}
            }
            $titulo = $abrev($t['titulo'] ?? '', 40);
            $status = $abrevStatus($t['status'] ?? '');
            $categoria = $abrevCategoria((string)($t['categoria'] ?? ''));
            $solicitante = $abrevPessoa($t['solicitante_nome'] ?? $t['solicitante'] ?? '');
            $responsavel = $abrevPessoa($t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não atribuído');
            $fechadoPor = $abrevPessoa($t['fechado_por_nome'] ?? '-');
            $prioridade = $abrev($t['prioridade'] ?? '', 8);

            $av = isset($t['avaliacao']) ? (int)$t['avaliacao'] : null;
            $avaliacaoTxt = ($av >= 1 && $av <= 5) ? ($av . '/5') : '-';
            $avaliadoEmTxt = '-';
            if (!empty($t['avaliacao_em'])) {
                try {
                    $avaliadoEmTxt = (new DateTime((string)$t['avaliacao_em']))->format('d/m/Y H:i');
                } catch (Throwable $e) {}
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($t['numero_chamado'] ?? $t['id'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($titulo) . '</td>';
            $html .= '<td>' . htmlspecialchars($status) . '</td>';
            $html .= '<td>' . htmlspecialchars($prioridade) . '</td>';
            $html .= '<td>' . htmlspecialchars($categoria) . '</td>';
            $html .= '<td>' . htmlspecialchars($solicitante) . '</td>';
            $html .= '<td>' . htmlspecialchars($responsavel) . '</td>';
            $html .= '<td>' . htmlspecialchars($t['filial_nome'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($fechadoPor) . '</td>';
            $html .= '<td>' . htmlspecialchars($avaliacaoTxt) . '</td>';
            $html .= '<td>' . htmlspecialchars($avaliadoEmTxt) . '</td>';
            $html .= '<td>' . htmlspecialchars($created ?: '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($fechadoEm) . '</td>';
            $html .= '<td>' . htmlspecialchars($tempoResolucao) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'tickets_' . date('Y-m-d_His') . '.pdf';
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $dompdf->output();
        exit;
    }

    // Fallback: página para imprimir como PDF
    view('relatorio_imprimir', ['tickets' => $tickets, 'periodoTexto' => $periodoTexto]);
    return;
}

// ================
// Rota não encontrada em /relatorio
// ================
http_response_code(404);
view('404', ['perfil' => $perfil]);

