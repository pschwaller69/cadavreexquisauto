<?php
declare(strict_types=1);

define('APP_DIR', dirname(__DIR__));
define('DATA_DIR', APP_DIR . '/data');
define('PHOTO_DIR', DATA_DIR . '/photos');

if (!is_file(APP_DIR . '/config.php')) {
    http_response_code(500);
    exit('config.php manquant : copiez config.sample.php en config.php puis lancez install.php');
}
$GLOBALS['CFG'] = require APP_DIR . '/config.php';
date_default_timezone_set(cfg('timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db');
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'], $c['pass']
        );
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function q1(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r ?: null;
}
function qv(string $sql, array $p = [])
{
    return q($sql, $p)->fetchColumn();
}
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    q("INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($data));
    return (int) db()->lastInsertId();
}
function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}

function now(): string { return date('Y-m-d H:i:s'); }
function plus_jours(string $date, int $j): string { return date('Y-m-d H:i:s', strtotime($date . ' ' . sprintf('%+d', $j) . ' days')); }
function fdate(?string $d, bool $heure = false): string
{
    if (!$d) return '';
    return date($heure ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d));
}
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function token(int $bytes = 16): string { return bin2hex(random_bytes($bytes)); }
function url(string $path = ''): string { return rtrim(cfg('base_url'), '/') . '/' . ltrim($path, '/'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function post(string $k, $d = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $d; }
function get(string $k, $d = '') { return isset($_GET[$k]) ? trim((string) $_GET[$k]) : $d; }

function nom_propre(string $s): string { return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $s))); }
function prenom_propre(string $s): string
{
    $s = mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    return preg_replace_callback('/(^|[\s\-])(\p{L})/u', fn($m) => $m[1] . mb_strtoupper($m[2]), $s);
}
function club_propre(string $s): string { return trim(preg_replace('/\s+/', ' ', trim($s, " \t\n\r\"'"))); }

// ---------- Session / sécurité ----------
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Sessions de 30 jours, dans un dossier propre à l'appli : on reste connecté dans l'appli installée sur le téléphone
    $dir = DATA_DIR . '/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string) (30 * 86400));
    session_name('cadavreauto');
    session_set_cookie_params(['lifetime' => 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}

/** Message si base_url (config.php) ne correspond pas à l'adresse utilisée. */
function alerte_base_url(): string
{
    $hote_cfg = parse_url((string) cfg('base_url'), PHP_URL_HOST);
    $hote = $_SERVER['HTTP_HOST'] ?? '';
    if (!$hote || !$hote_cfg || strcasecmp($hote_cfg, preg_replace('/:\d+$/', '', $hote)) === 0) return '';
    return '<p class="alerte ko"><strong>config.php :</strong> base_url vaut « ' . h(cfg('base_url')) . ' » alors que le site est ouvert sur « '
        . h($hote) . ' ». Les liens des mails et les redirections sont faux : corrigez base_url (ex. https://' . h($hote) . ').</p>';
}

/** Balises <head> pour l'application installable (PWA). */
function pwa_head(string $manifest = 'manifest.webmanifest', string $titre = 'Cadavre Auto'): string
{
    $b = h(rtrim(cfg('base_url'), '/'));
    $m = h($manifest);
    $t = h($titre);
    return <<<HTML
<link rel="manifest" href="$b/$m">
<meta name="theme-color" content="#17171f">
<link rel="icon" type="image/png" href="$b/assets/icons/favicon.png">
<link rel="apple-touch-icon" href="$b/assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="$t">
<script>if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('$b/sw.js', {scope: '$b/'}));</script>
HTML;
}
function csrf(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token();
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf(): void
{
    start_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Jeton de sécurité invalide, rechargez la page.');
    }
}
function flash(?string $msg = null, string $type = 'ok')
{
    start_session();
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
function admin(): ?array
{
    start_session();
    if (empty($_SESSION['admin_id'])) return null;
    static $a = null;
    return $a ??= q1('SELECT * FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
}
function require_admin(): array
{
    $a = admin();
    if (!$a) redirect('login.php'); // relatif : fonctionne même si base_url est faux
    $v = @filemtime(__DIR__ . '/schema.php');
    if (($_SESSION['schema_v'] ?? 0) !== $v) {
        require_once __DIR__ . '/schema.php';
        assurer_schema();
        $_SESSION['schema_v'] = $v;
    }
    return $a;
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/logic.php';
maj_schema_si_besoin();
