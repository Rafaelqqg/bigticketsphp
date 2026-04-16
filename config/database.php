<?php

declare(strict_types=1);

/**
 * Cria e retorna uma conexão PDO com o MySQL.
 * Ajuste os dados em `config.php` (produção) e/ou `config.local.php` (localhost).
 */
function createPdoConnection(): PDO
{
    $configFile = __DIR__ . '/config.php';
    $localConfigFile = __DIR__ . '/config.local.php';

    // Prioriza configuração local quando existir (evita subir dados locais para produção).
    if (file_exists($localConfigFile)) {
        $configFile = $localConfigFile;
    }

    $config = require $configFile;

    $host = $config['db_host'] ?? 'localhost';
    $port = (int)($config['db_port'] ?? 3306);
    $db   = $config['db_name'] ?? '';
    $user = $config['db_user'] ?? '';
    $pass = $config['db_pass'] ?? '';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $isLocalHost = in_array(strtolower($host), ['localhost', '127.0.0.1'], true);
    $canTryXamppFallback = $isLocalHost && $user === 'root' && $pass !== '';

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);

        // Garante que todas as operações no banco usem horário de Manaus.
        // Alguns MySQL não têm o timezone "America/Manaus" carregado, então
        // usamos um fallback para o offset equivalente (-04:00) se der erro.
        try {
            $pdo->exec("SET time_zone = 'America/Manaus'");
        } catch (PDOException $eTz) {
            try {
                $pdo->exec("SET time_zone = '-04:00'");
            } catch (PDOException $eTz2) {
                // Se ainda assim falhar, seguimos sem derrubar a conexão.
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        // Fallback para ambiente local com XAMPP: root sem senha.
        if ($canTryXamppFallback) {
            try {
                $pdo = new PDO($dsn, $user, '', $options);

                try {
                    $pdo->exec("SET time_zone = 'America/Manaus'");
                } catch (PDOException $eTz) {
                    try {
                        $pdo->exec("SET time_zone = '-04:00'");
                    } catch (PDOException $eTz2) {
                        // Se ainda assim falhar, seguimos sem derrubar a conexão.
                    }
                }

                return $pdo;
            } catch (PDOException $fallbackError) {
                // Mantém o fluxo de erro original abaixo.
            }
        }

        http_response_code(500);
        echo 'Erro ao conectar ao banco de dados.';
        error_log('Erro PDO BigTicketsPHP: ' . $e->getMessage());
        exit;
    }
}
