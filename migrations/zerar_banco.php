<?php
/**
 * Script para zerar o banco de dados e reiniciar a numeração dos chamados.
 * Execute: php migrations/zerar_banco.php
 *
 * ATENÇÃO: Remove TODOS os dados. Mantém apenas admin e filial 1 para poder logar.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$pdo = db();

echo "Zerando banco de dados...\n";

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Ordem: tabelas dependentes primeiro
    $tabelas = ['comentarios', 'tickets', 'solicitacoes_cadastro', 'usuarios', 'filiais'];
    foreach ($tabelas as $tabela) {
        try {
            $pdo->exec("TRUNCATE TABLE `$tabela`");
            echo "  - $tabela: zerada\n";
        } catch (PDOException $e) {
            echo "  - $tabela: " . ($e->getMessage()) . "\n";
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Reinserir dados mínimos para o sistema funcionar
    $senhaAdmin = password_hash('admin', PASSWORD_DEFAULT);

    $pdo->exec("INSERT INTO filiais (codigo, cnpj) VALUES ('1', '00.000.000/0001-00')");
    echo "  - filiais: filial 1 criada\n";

    $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, senha, perfil, filial_codigo, nome, email) VALUES (?, ?, 'administrador', '1', 'Administrador', 'admin@localhost')");
    $stmt->execute(['admin', $senhaAdmin]);
    echo "  - usuarios: admin criado (senha: admin)\n";

    echo "\nBanco zerado com sucesso!\n";
    echo "Próximo ticket terá numero_chamado = 1\n";
    echo "Login: admin / admin\n";
} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
