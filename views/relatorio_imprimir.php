<?php
// View de fallback quando Dompdf não está instalado: imprimir como PDF pelo navegador.
// Variáveis: $tickets (array), $periodoTexto (string)
$periodoTexto = $periodoTexto ?? 'Todos os períodos';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Tickets - Imprimir</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; padding: 1rem; }
        h1 { font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        .no-print { margin-bottom: 1rem; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
        <button type="button" onclick="window.close()">Fechar</button>
    </div>
    <h1>Relatório de Tickets</h1>
    <p><strong><?= htmlspecialchars($periodoTexto, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p>Total: <?= count($tickets) ?> ticket(s). Gerado em <?= date('d/m/Y H:i') ?>.</p>
    <table>
        <thead>
            <tr>
                <th>Nº</th><th>Título</th><th>Status</th><th>Prioridade</th><th>Categoria</th>
                <th>Solicitante</th><th>Responsável</th><th>Filial</th><th>Fechado por</th><th>Aval.</th>
                <th>Aberto em</th><th>Fechado em</th><th>Tempo resolução</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
                <?php
                $created = $t['created_at'] ?? '';
                if ($created) {
                    try {
                        $d = new DateTime($created);
                        $created = $d->format('d/m/Y H:i');
                    } catch (Throwable $e) {}
                }
                $updated = $t['updated_at'] ?? '';
                if ($updated) {
                    try {
                        $du = new DateTime($updated);
                        $updated = $du->format('d/m/Y H:i');
                    } catch (Throwable $e) { $updated = ''; }
                }
                $isFechado = in_array(strtolower((string)($t['status'] ?? '')), ['fechado', 'resolvido'], true);
                $fechadoEm = ($isFechado && $updated) ? $updated : '-';
                $tempoResolucao = '-';
                if ($isFechado && !empty($t['created_at']) && !empty($t['updated_at'])) {
                    try {
                        $tsA = (new DateTime($t['created_at']))->getTimestamp();
                        $tsF = (new DateTime($t['updated_at']))->getTimestamp();
                        $seg = $tsF - $tsA;
                        if ($seg < 60) $tempoResolucao = $seg . ' s';
                        elseif ($seg < 3600) $tempoResolucao = (int)floor($seg / 60) . ' min';
                        elseif ($seg < 86400) {
                            $h = (int)floor($seg / 3600);
                            $m = (int)floor(($seg % 3600) / 60);
                            $tempoResolucao = $h . 'h' . ($m > 0 ? ' ' . $m . ' min' : '');
                        } else {
                            $d = (int)floor($seg / 86400);
                            $h = (int)floor(($seg % 86400) / 3600);
                            $tempoResolucao = $d . ' dia' . ($d !== 1 ? 's' : '') . ($h > 0 ? ' ' . $h . 'h' : '');
                        }
                    } catch (Throwable $e) {}
                }
                $av = isset($t['avaliacao']) ? (int)$t['avaliacao'] : null;
                $avaliacaoTxt = ($av >= 1 && $av <= 5) ? ($av . '/5') : '-';
                ?>
                <tr>
                    <td><?= htmlspecialchars($t['numero_chamado'] ?? $t['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(function_exists('status_display') ? status_display($t['status'] ?? '') : ($t['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['prioridade'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)($t['categoria'] ?? '')) : (string)($t['categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['solicitante_nome'] ?? $t['solicitante'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não atribuído', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['filial_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($t['fechado_por_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($avaliacaoTxt, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($created ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($fechadoEm, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($tempoResolucao, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
