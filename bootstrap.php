<?php

declare(strict_types=1);

// Fuso horário padrão do sistema: Manaus (Amazonas)
date_default_timezone_set('America/Manaus');

session_start();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/config/database.php';

/**
 * Caminho base do projeto (por ex.: /bigticketsphp).
 * Útil para montar URLs corretas quando o sistema não está na raiz do Apache.
 */
function base_path(): string
{
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scriptDir === '/' ? '' : $scriptDir;
}

/**
 * Retorna uma instância única de PDO para a aplicação.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = createPdoConnection();
    }

    return $pdo;
}

/**
 * Renderiza uma view PHP localizada em `views/{nome}.php`.
 */
function view(string $template, array $data = []): void
{
    // Deixa a base disponível em todas as views
    $data['basePath'] = base_path();
    extract($data, EXTR_SKIP);

    $viewFile = __DIR__ . '/views/' . $template . '.php';

    if (!file_exists($viewFile)) {
        http_response_code(500);
        echo "View '{$template}' não encontrada.";
        return;
    }

    include $viewFile;
}

/**
 * Redireciona para outra rota e encerra a execução.
 * Aceita caminhos relativos (ex.: '/login') e já prefixa com base_path().
 */
function redirect(string $path): void
{
    // Se for URL absoluta (http/https), não mexe
    if (preg_match('~^https?://~i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    $base = base_path();

    if ($path === '' || $path === '/') {
        $target = $base !== '' ? $base . '/' : '/';
    } else {
        // Garante exatamente uma barra entre base e path
        $target = rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    header('Location: ' . $target);
    exit;
}

/**
 * Usuário atual da sessão (mesma ideia do req.session.usuario no Node).
 */
function current_user(): ?array
{
    $usuario = $_SESSION['usuario'] ?? null;
    return is_array($usuario) ? $usuario : null;
}

/**
 * Perfil atual (administrador, moderador, comum, recepcao, manutencao).
 */
function current_profile(): ?string
{
    return $_SESSION['perfil'] ?? null;
}

/**
 * Formata tempo decorrido em texto curto (ex.: "2h atrás", "5 hrs atrás").
 */
function time_ago(?string $dateString): string
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

/**
 * Formata status para exibição (remove underscore de em_andamento).
 */
function status_display(?string $status): string
{
    $s = strtolower(trim((string)$status));
    if ($s === 'em_andamento' || $s === 'em andamento') {
        return 'Em andamento';
    }
    if ($s === 'aberto') {
        return 'Aberto';
    }
    if ($s === 'fechado' || $s === 'resolvido') {
        return 'Fechado';
    }
    return $status ?: 'Aberto';
}

/**
 * Formata nome para exibição na tela de usuários (tipo título: Rafael Albuquerque).
 * No banco o nome permanece em MAIÚSCULAS; use nas views de usuários ao exibir.
 */
/**
 * Valor armazenado em tickets.categoria para a equipe de manutenção (sem acento).
 */
function categoria_manutencao_storage(): string
{
    return 'manutencao';
}

/**
 * Rótulo exibido na interface para a categoria de manutenção.
 */
function categoria_manutencao_label(): string
{
    return 'Manutenção';
}

/**
 * Converte valor do BD (ou legado) para o texto mostrado ao usuário.
 */
function categoria_display(?string $categoria): string
{
    $s = trim((string)$categoria);
    if ($s === '') {
        return '';
    }
    if (strcasecmp($s, 'manutencao') === 0 || strcasecmp($s, 'Manutenção') === 0 || strcasecmp($s, 'Manutencao') === 0) {
        return categoria_manutencao_label();
    }
    return $s;
}

/**
 * Normaliza POST/legado para o valor gravado no BD (manutenção → manutencao).
 */
function categoria_normalize_storage(?string $categoria): string
{
    $s = trim((string)$categoria);
    if ($s === '') {
        return '';
    }
    if (strcasecmp($s, 'Manutenção') === 0 || strcasecmp($s, 'Manutencao') === 0 || strcasecmp($s, 'manutencao') === 0) {
        return categoria_manutencao_storage();
    }
    return $s;
}

function format_nome_pessoa(?string $nome): string
{
    if ($nome === null) {
        return '';
    }
    $nome = trim($nome);
    if ($nome === '') {
        return '';
    }
    if (function_exists('mb_convert_case')) {
        return mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');
    }
    return ucwords(strtolower($nome));
}

/**
 * Sincroniza perfil (e dados básicos) da sessão com o banco, para refletir alterações feitas pelo admin
 * sem exigir novo login.
 */
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['usuario']['id'])) {
    try {
        $pdoSync = db();
        $stmtSync = $pdoSync->prepare('SELECT id, usuario, perfil, nome, email, filial_codigo, ativo FROM usuarios WHERE id = ? LIMIT 1');
        $stmtSync->execute([(int)$_SESSION['usuario']['id']]);
        $rowSync = $stmtSync->fetch(PDO::FETCH_ASSOC);
        if ($rowSync && (int)($rowSync['ativo'] ?? 1) === 1) {
            $pSync = trim((string)($rowSync['perfil'] ?? ''));
            if ($pSync === '') {
                $pSync = 'comum';
            }
            $_SESSION['perfil'] = $pSync;
            $_SESSION['usuario']['perfil'] = $pSync;
            foreach (['usuario', 'nome', 'email', 'filial_codigo'] as $k) {
                if (array_key_exists($k, $rowSync)) {
                    $_SESSION['usuario'][$k] = $rowSync[$k];
                }
            }
        }
    } catch (Throwable $e) {
        // Evita derrubar a página se o banco estiver indisponível
    }
}
