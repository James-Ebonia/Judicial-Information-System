<?php
require 'inc/bootstrap.php';
need();

$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur  = $_POST['current'] ?? '';
    $new  = $_POST['new'] ?? '';
    $new2 = $_POST['new2'] ?? '';

    $s = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $s->execute([me()['id']]);
    $hash = $s->fetchColumn();

    if (!password_verify($cur, $hash)) {
        $msg = 'Your current password is incorrect.';
    } elseif (strlen($new) < 10) {
        $msg = 'The new password must be at least 10 characters.';
    } elseif ($new !== $new2) {
        $msg = 'The new passwords do not match.';
    } elseif ($new === $cur) {
        $msg = 'The new password must be different from the current one.';
    } else {
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($new, PASSWORD_DEFAULT), me()['id']]);
        audit('password_changed');
        session_regenerate_id(true);
        $ok = true;
    }
}

top('Change password');
?>
<div class="card" style="max-width:480px">
    <?php if ($ok): ?>
        <p class="okbox">Password changed.</p>
    <?php elseif ($msg): ?>
        <p class="err"><?= e($msg) ?></p>
    <?php endif; ?>

    <form method="post">
        <?= csrf() ?>
        Current password
        <input type="password" name="current" required autocomplete="current-password">
        New password (10+ characters)
        <input type="password" name="new" required autocomplete="new-password">
        Confirm new password
        <input type="password" name="new2" required autocomplete="new-password">
        <button class="p">Change password</button>
    </form>
</div>
<?php bottom();