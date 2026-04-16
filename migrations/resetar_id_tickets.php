<?php
/**
 * Renumera os IDs dos tickets para ficarem em sequência (1, 2, 3...).
 * Preserva a ordem por data de criação.
 * Execute: php migrations/resetar_id_tickets.php
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$pdo = db();

echo "Renumerando IDs dos tickets...\n";

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Busca tickets ordenados por created_at (ou id) para manter a ordem
    $stmt = $pdo->query('SELECT id FROM tickets ORDER BY created_at ASC, id ASC');
    $tickets = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $total = count($tickets);

    if ($total === 0) {
        $pdo->exec('ALTER TABLE tickets AUTO_INCREMENT = 1');
        echo "Nenhum ticket. AUTO_INCREMENT resetado para 1.\n";
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        exit(0);
    }

    // Cria mapeamento old_id -> new_id
    $map = [];
    foreach ($tickets as $i => $oldId) {
        $map[(int)$oldId] = $i + 1;
    }

    // Passo 1: tickets id -> negativo (libera 1, 2, 3...)
    foreach ($map as $oldId => $newId) {
        $pdo->prepare('UPDATE tickets SET id = ? WHERE id = ?')
            ->execute([-$newId, $oldId]);
    }

    // Passo 2: comentarios ticket_id -> negativo (mantém vínculo)
    foreach ($map as $oldId => $newId) {
        $pdo->prepare('UPDATE comentarios SET ticket_id = ? WHERE ticket_id = ?')
            ->execute([-$newId, $oldId]);
    }

    // Passo 3: tickets e comentarios negativos -> positivos
    foreach ($map as $oldId => $newId) {
        $pdo->prepare('UPDATE tickets SET id = ? WHERE id = ?')
            ->execute([$newId, -$newId]);
        $pdo->prepare('UPDATE comentarios SET ticket_id = ? WHERE ticket_id = ?')
            ->execute([$newId, -$newId]);
    }

    $proximo = $total + 1;
    $pdo->exec("ALTER TABLE tickets AUTO_INCREMENT = $proximo");

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "Concluído! $total tickets renumerados (id 1 a $total).\n";
    echo "Próximo ticket terá id = $proximo.\n";
} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
