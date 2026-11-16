<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

$C = require __DIR__ . '/config.php';

const STATUSES = ['Pending', 'Under Investigation', 'Under Prosecution', 'Awaiting Hearing', 'Ongoing', 'Resolved', 'Archived'];
const ROLES    = ['admin', 'lawyer', 'prosecutor', 'judge', 'investigator', 'clerk'];

try {
    $db = new PDO(
        "mysql:host={$C['host']};dbname={$C['name']};charset=utf8mb4",
        $C['user'],
        $C['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Exception $x) {
    if (basename($_SERVER['SCRIPT_NAME']) != 'install.php') {
        die('Database not ready. Edit inc/config.php and open install.php');
    }
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf()
{
    if (empty($_SESSION['t'])) {
        $_SESSION['t'] = bin2hex(random_bytes(32));
    }
    return '<input type=hidden name=t value="' . $_SESSION['t'] . '">';
}

function me()
{
    return $_SESSION['u'] ?? null;
}

function need(...$roles)
{
    $u = me();
    if (!$u) {
        header('Location: index.php');
        exit;
    }
    if (time() - ($_SESSION['last'] ?? 0) > 1800) {
        session_destroy();
        header('Location: index.php?exp=1');
        exit;
    }
    $_SESSION['last'] = time();
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('Forbidden');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')) {
        die('Invalid CSRF token');
    }
}

function audit($act, $case = null, $d = '')
{
    global $db;
    $db->prepare('INSERT INTO audit_logs(user_id,case_id,action,details,ip) VALUES(?,?,?,?,?)')
       ->execute([me()['id'] ?? null, $case, $act, $d, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function scope()
{
    $m = [
        'judge'        => 'judge_id',
        'lawyer'       => 'lawyer_id',
        'prosecutor'   => 'prosecutor_id',
        'investigator' => 'investigator_id',
    ];
    $r = me()['role'];
    return isset($m[$r]) ? [" AND c.{$m[$r]}=?", [me()['id']]] : ['', []];
}

function getCase($id)
{
    global $db;
    [$w, $p] = scope();
    $s = $db->prepare("SELECT c.* FROM cases c WHERE c.id=?$w");
    $s->execute(array_merge([$id], $p));
    return $s->fetch();
}

function notify($c, $msg)
{
    global $db;
    $s = $db->prepare('INSERT INTO notifications(user_id,case_id,msg) VALUES(?,?,?)');
    foreach (['lawyer_id', 'prosecutor_id', 'judge_id', 'investigator_id'] as $k) {
        if ($c[$k] && $c[$k] != me()['id']) {
            $s->execute([$c[$k], $c['id'], $msg]);
        }
    }
}

function enc($v)
{
    global $C;
    if ($v === '') {
        return '';
    }
    $iv = random_bytes(12);
    $ct = openssl_encrypt($v, 'aes-256-gcm', hash('sha256', $C['key'], true), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}

function dec($v)
{
    global $C;
    if ($v === '') {
        return '';
    }
    $b = base64_decode($v);
    return (string) openssl_decrypt(
        substr($b, 28),
        'aes-256-gcm',
        hash('sha256', $C['key'], true),
        OPENSSL_RAW_DATA,
        substr($b, 0, 12),
        substr($b, 12, 16)
    );
}

function badge($s)
{
    return '<span class="b b-' . preg_replace('/\W+/', '', $s) . '">' . e($s) . '</span>';
}

function top($t)
{
    global $db;
    $u   = me();
    $cur = basename($_SERVER['SCRIPT_NAME']);
    if (in_array($cur, ['case_view.php', 'case_edit.php'], true)) {
        $cur = 'cases.php';
    }

    $nav = ['dashboard.php' => 'Dashboard', 'cases.php' => 'Cases', 'hearings.php' => 'Hearings'];
    if ($u['role'] === 'admin') {
        $nav += ['users.php' => 'Users', 'audit.php' => 'Audit log'];
    }

    $pend = $u['role'] === 'admin'
        ? (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn()
        : 0;

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($t) . ' | Judicial Information System</title>'
       . '<link rel="stylesheet" href="assets/style.css"></head><body>'
       . '<aside><div class="logo">JIS<small>Judicial Information System</small></div>';

    foreach ($nav as $h => $l) {
        echo '<a href="' . $h . '"' . ($h === $cur ? ' class="active"' : '') . '>' . $l
           . ($h === 'users.php' && $pend ? '<span class="count">' . $pend . '</span>' : '')
           . '</a>';
    }

    echo '<form method="post" action="logout.php">' . csrf() . '<button class="lo">Log out</button></form></aside>'
       . '<main><header><button onclick="document.body.classList.toggle(\'c\')" aria-label="Toggle menu">Menu</button>'
       . '<h1>' . e($t) . '</h1><span>' . e($u['full_name']) . ' (' . e($u['role']) . ')</span></header><section>';
}

function bottom()
{
    echo '</section></main></body></html>';
}