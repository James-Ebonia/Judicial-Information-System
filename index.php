<?php
require 'inc/bootstrap.php';
const MAX_ATTEMPTS = 3;
const LOCK_MINUTES = 15;

$R      = require 'inc/roles.php';
$groups = $R['groups'];

// Flat list: value => label
$labels = [];
foreach ($groups as $items) {
    $labels += $items;
}

$err      = '';
$selValue = $_POST['role'] ?? '';
$selLabel = $labels[$selValue] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')) {
        die('Invalid CSRF token');
    }
    $username = substr(trim($_POST['username'] ?? ''), 0, 50);
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'];

    // Split "lawyer|Tax Lawyer" into system role + specialization
    $sysRole = $role;
    $spec    = '';
    if (str_starts_with($role, 'lawyer|')) {
        $sysRole = 'lawyer';
        $spec    = substr($role, 7);
    }
    $validChoice = isset($labels[$role]);

    // Failed attempts since the last successful login, inside the lock window
    $failSql = "SELECT COUNT(*), MAX(at) FROM login_history
                WHERE username = ? AND success = 0
                  AND at > DATE_SUB(NOW(), INTERVAL " . LOCK_MINUTES . " MINUTE)
                  AND id > COALESCE((SELECT MAX(id) FROM login_history
                                 WHERE username = ? AND success = 1), 0)";
    $s = $db->prepare($failSql);
    $s->execute([$username, $username]);
    [$fails, $lastFail] = $s->fetch(PDO::FETCH_NUM);
    $fails = (int) $fails;

    if ($fails >= MAX_ATTEMPTS) {
        $s = $db->prepare('SELECT ' . (LOCK_MINUTES * 60) . ' - TIMESTAMPDIFF(SECOND, ?, NOW())');
        $s->execute([$lastFail]);
        $mins = max(1, (int) ceil(((int) $s->fetchColumn()) / 60));
        $err  = "Too many failed attempts. This account is locked. Try again in about $mins minute(s).";
    } else {
        $s = $db->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
        $s->execute([$username]);
        $user = $s->fetch();

        $passOk = $user && password_verify($password, $user['password_hash']);
        $roleOk = $user && $validChoice
            && $user['role'] === $sysRole
            && ($sysRole !== 'lawyer' || $user['specialization'] === $spec);
        $ok = $passOk && $roleOk;

        $db->prepare('INSERT INTO login_history (username, role, success, ip) VALUES (?, ?, ?, ?)')
           ->execute([$username, substr($role, 0, 100), (int) $ok, $ip]);

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['u'] = [
                'id'        => $user['id'],
                'full_name' => $user['full_name'],
                'role'      => $user['role'],
            ];
            $_SESSION['last'] = time();
            audit('login', null, 'Logged in as ' . ($labels[$role] ?? $role));
            header('Location: dashboard.php');
            exit;
        }

        // ---- Failed attempt ----
        $fails++;
        $left = MAX_ATTEMPTS - $fails;

        if ($passOk && !$roleOk) {
            $why = 'Invalid role. You are not authorized to log in as '
                 . ($labels[$role] ?? 'that role') . '.';
        } else {
            $why = 'Incorrect username or password.';
        }

        if ($left <= 0) {
            $err = 'Too many failed attempts. This account is locked for ' . LOCK_MINUTES
                 . ' minutes and the administrator has been notified.';

            $alert = substr("SECURITY ALERT: $fails failed logins for username '$username' "
                   . "(role tried: " . ($labels[$role] ?? 'none') . ") from IP $ip. "
                   . "Possible unauthorized access attempt.", 0, 255);
            $ins = $db->prepare('INSERT INTO notifications (user_id, case_id, msg) VALUES (?, NULL, ?)');
            foreach ($db->query("SELECT id FROM users WHERE role = 'admin' AND active = 1") as $a) {
                $ins->execute([$a['id'], $alert]);
            }
            audit('security_alert', null, $alert);
        } else {
            $err = "$why You have $left attempt(s) left.";
        }
    }
}

if (isset($_GET['exp'])) {
    $err = 'Your session expired. Log in again.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Log in | Judicial Information System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth">
<div class="login-box">
    <div class="brand">
        <div class="brand-mark">J.I.S</div>
        <div class="brand-sub">Judicial Information System</div>
    </div>

    <?php if ($err): ?>
        <p class="err" role="alert"><?= e($err) ?></p>
    <?php endif; ?>

    <form method="post" id="loginForm" autocomplete="off">
        <?= csrf() ?>
        <label for="username">Username</label>
        <input id="username" name="username" required autofocus autocomplete="username">
        <label for="password">Password</label>
        <div class="pw-wrap">
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <button type="button" id="pwToggle" class="pw-toggle" aria-pressed="false">Show</button>
        </div>
        <label for="roleSearch" class="center">Select role</label>
        <div class="combo" id="combo">
            <input type="text" id="roleSearch" placeholder="Search or select role"
                   value="<?= e($selLabel) ?>" autocomplete="off" role="combobox" aria-expanded="false">
            <input type="hidden" name="role" id="role" value="<?= e($selLabel ? $selValue : '') ?>">
            <div class="combo-list" id="roleList">
                <?php $first = true; foreach ($groups as $groupName => $items): ?>
                    <div class="grp<?= $first ? ' open' : '' ?>"><?= e($groupName) ?></div>
                    <div class="grpbody">
                        <?php foreach ($items as $value => $label): ?>
                            <div class="opt" data-v="<?= e($value) ?>"><?= e($label) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php $first = false; endforeach; ?>
                <div class="none">No matching role found</div>
            </div>
        </div>
        <button class="p login-btn">Log in</button>
    </form>
        <p class="notice">New lawyer? <a href="register.php">Register here</a>
    </p>
    <p class="notice">Authorized personnel only. All activity is logged.</p>
</div>

<script>
(function () {
    // ---- Show / hide password ----
    var pw = document.getElementById('password');
    var tg = document.getElementById('pwToggle');
    tg.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        tg.textContent = show ? 'Hide' : 'Show';
        tg.setAttribute('aria-pressed', show ? 'true' : 'false');
    });

    // ---- Role dropdown: collapsible fields + search ----
    var combo = document.getElementById('combo');
    var inp   = document.getElementById('roleSearch');
    var hid   = document.getElementById('role');
    var list  = document.getElementById('roleList');
    var opts  = [].slice.call(list.querySelectorAll('.opt'));
    var grps  = [].slice.call(list.querySelectorAll('.grp'));
    var none  = list.querySelector('.none');

    function filter(ignoreText) {
        var q = ignoreText ? '' : inp.value.trim().toLowerCase();
        var shown = 0;
        opts.forEach(function (o) {
            var match = o.textContent.toLowerCase().indexOf(q) > -1;
            o.style.display = match ? '' : 'none';
            if (match) shown++;
        });

        // While searching, every field with a match opens by itself
        list.classList.toggle('searching', q !== '');
        grps.forEach(function (g) {
            var any = [].slice.call(g.nextElementSibling.children)
                        .some(function (o) { return o.style.display !== 'none'; });
            g.style.display = any ? '' : 'none';
        });
        none.style.display = shown ? 'none' : 'block';
    }

    function openList()  { combo.classList.add('open'); inp.setAttribute('aria-expanded', 'true'); }
    function closeList() { combo.classList.remove('open'); inp.setAttribute('aria-expanded', 'false'); }

    function pick(o) {
        hid.value = o.getAttribute('data-v');
        inp.value = o.textContent;
        closeList();
    }

    inp.addEventListener('focus', function () { inp.select(); openList(); filter(true); });
    inp.addEventListener('input', function () { hid.value = ''; openList(); filter(false); });

    inp.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeList(); return; }
        if (e.key === 'Enter' && combo.classList.contains('open')) {
            var first = opts.filter(function (o) { return o.style.display !== 'none'; })[0];
            e.preventDefault();
            if (first && inp.value.trim() !== '') pick(first);
        }
    });

    list.addEventListener('mousedown', function (e) {
        var o = e.target.closest('.opt');
        if (o) { e.preventDefault(); pick(o); return; }
        var g = e.target.closest('.grp');
        if (g) { e.preventDefault(); g.classList.toggle('open'); }
    });

    document.addEventListener('click', function (e) {
        if (!combo.contains(e.target)) closeList();
    });

    document.getElementById('loginForm').addEventListener('submit', function (e) {
        if (!hid.value) {
            e.preventDefault();
            inp.focus();
            alert('Please choose a role from the list.');
        }
    });
})();
</script>

</body>
</html>