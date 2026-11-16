<?php
require 'inc/bootstrap.php';

if (me()) {
    header('Location: dashboard.php');
    exit;
}

$R     = require 'inc/roles.php';
$types = array_merge(...array_values($R['lawyer']));
$groupOf = [];
foreach ($R['lawyer'] as $g => $names) {
    foreach ($names as $n) {
        $groupOf[$n] = $g;
    }
}

$errs = [];
$done = false;
$v = ['full_name' => '', 'email' => '', 'username' => '', 'roll' => '', 'year' => '', 'spec' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')) {
        die('Invalid CSRF token');
    }
    if (!empty($_POST['website'])) {   // honeypot field: real people leave it empty
        die('Rejected');
    }

    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';
    $yr  = (int) $v['year'];

    if ($v['full_name'] === '' || strlen($v['full_name']) > 120) $errs[] = 'Enter your full name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL))         $errs[] = 'Enter a valid email address.';
    if (!preg_match('/^\w{3,50}$/', $v['username']))              $errs[] = 'Username must be 3-50 letters, numbers or underscores.';
    if (!preg_match('/^[\w\-\/ ]{1,30}$/', $v['roll']))           $errs[] = 'Enter your Bar roll number.';
    if ($yr < 1950 || $yr > (int) date('Y'))                      $errs[] = 'Enter the year you passed the Bar.';
    if (!in_array($v['spec'], $types, true))                      $errs[] = 'Choose your field of law and lawyer type.';
    if (strlen($pw) < 10)                                         $errs[] = 'Password must be at least 10 characters.';
    if ($pw !== $pw2)                                             $errs[] = 'Passwords do not match.';
    if (empty($_POST['certify']))                                 $errs[] = 'You must certify that your details are true.';

    if (!$errs) {
        $s = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $s->execute([$v['username']]);
        if ($s->fetchColumn()) $errs[] = 'That username is already taken.';

        $s = $db->prepare('SELECT COUNT(*) FROM users WHERE bar_roll_no = ?');
        $s->execute([$v['roll']]);
        if ($s->fetchColumn()) $errs[] = 'That Bar roll number is already registered.';
    }

    if (!$errs) {
        $db->prepare("INSERT INTO users (username, full_name, email, role, specialization, password_hash,
                                         active, status, bar_roll_no, bar_year)
                      VALUES (?, ?, ?, 'lawyer', ?, ?, 0, 'pending', ?, ?)")
           ->execute([
               $v['username'], $v['full_name'], $v['email'], $v['spec'],
               password_hash($pw, PASSWORD_DEFAULT), $v['roll'], $yr,
           ]);

        $msg = substr("New lawyer registration awaiting approval: {$v['full_name']} (Bar roll no. {$v['roll']})", 0, 255);
        $ins = $db->prepare('INSERT INTO notifications (user_id, case_id, msg) VALUES (?, NULL, ?)');
        foreach ($db->query("SELECT id FROM users WHERE role = 'admin' AND active = 1") as $a) {
            $ins->execute([$a['id'], $msg]);
        }
        audit('registration_submitted', null, $v['username']);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register | Judicial Information System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth">
<div class="login-box wide">
    <div class="brand">
        <div class="brand-mark">J.I.S</div>
        <div class="brand-sub">Lawyer registration</div>
    </div>

<?php if ($done): ?>
    <p class="okbox"><b>Registration received.</b><br>
       An administrator will check your Bar details and activate your account.
       You can log in only after it is approved.</p>
    <a class="btn login-btn" href="index.php" style="text-align:center">Back to log in</a>

<?php else: ?>
    <?php if ($errs): ?>
        <div class="err" role="alert"><?php foreach ($errs as $x) echo e($x) . '<br>'; ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?= csrf() ?>
        <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

        <div class="rg">
            <div><label for="full_name">Full name</label>
                 <input id="full_name" name="full_name" value="<?= e($v['full_name']) ?>" required></div>
            <div><label for="email">Email</label>
                 <input id="email" type="email" name="email" value="<?= e($v['email']) ?>" required></div>
            <div><label for="roll">Bar roll number</label>
                 <input id="roll" name="roll" value="<?= e($v['roll']) ?>" required></div>
            <div><label for="year">Year you passed the Bar</label>
                 <input id="year" type="number" name="year" min="1950" max="<?= date('Y') ?>" value="<?= e($v['year']) ?>" required></div>
            <div><label for="fld">Field of law</label>
                 <select id="fld" data-cur="<?= e($v['spec']) ?>" required>
                     <option value="">-- Select field --</option>
                     <?php foreach (array_keys($R['lawyer']) as $g): ?>
                         <option<?= ($groupOf[$v['spec']] ?? '') === $g ? ' selected' : '' ?>><?= e($g) ?></option>
                     <?php endforeach; ?>
                 </select></div>
            <div><label for="typ">Lawyer type</label>
                 <select id="typ" name="spec" required></select></div>
            <div><label for="username">Username</label>
                 <input id="username" name="username" value="<?= e($v['username']) ?>" required></div>
            <div></div>
            <div><label for="password">Password (10+ characters)</label>
                 <input id="password" type="password" name="password" required></div>
            <div><label for="password2">Confirm password</label>
                 <input id="password2" type="password" name="password2" required></div>
        </div>

        <label class="chk"><input type="checkbox" id="showpw"> Show passwords</label>
        <label class="chk"><input type="checkbox" name="certify" value="1" required>
            I certify that I passed the Philippine Bar Examination and that these details are true.</label>

        <button class="p login-btn">Submit registration</button>
    </form>

    <p class="notice"><a href="index.php">Already approved? Log in</a></p>
<?php endif; ?>
</div>

<script>
(function () {
    var LAW = <?= json_encode($R['lawyer']) ?>;
    var fld = document.getElementById('fld'), typ = document.getElementById('typ');
    if (!fld) return;

    function fill(cur) {
        typ.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = fld.value ? '-- Select type --' : '-- Choose a field first --';
        typ.appendChild(ph);
        (LAW[fld.value] || []).forEach(function (n) {
            var o = document.createElement('option');
            o.value = o.textContent = n;
            if (n === cur) o.selected = true;
            typ.appendChild(o);
        });
    }
    fld.addEventListener('change', function () { fill(''); });
    fill(fld.getAttribute('data-cur') || '');

    document.getElementById('showpw').addEventListener('change', function () {
        var t = this.checked ? 'text' : 'password';
        document.getElementById('password').type = t;
        document.getElementById('password2').type = t;
    });
})();
</script>
</body>
</html>