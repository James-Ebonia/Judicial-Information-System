<?php
require 'inc/bootstrap.php';
need('admin');

$R     = require 'inc/roles.php';
$types = array_merge(...array_values($R['lawyer']));
$m     = '';

// Which field (group) each type belongs to
$groupOf = [];
foreach ($R['lawyer'] as $g => $names) {
    foreach ($names as $n) {
        $groupOf[$n] = $g;
    }
}

// Field dropdown + type dropdown, linked by the script at the bottom
function lawyerPicker(array $R, array $groupOf, string $current = '', bool $autoSubmit = false): string
{
    $curGroup = $groupOf[$current] ?? '';
    $out  = '<select class="fld" data-cur="' . e($current) . '">';
    $out .= '<option value="">-- Field of law --</option>';
    foreach (array_keys($R['lawyer']) as $g) {
        $out .= '<option' . ($g === $curGroup ? ' selected' : '') . '>' . e($g) . '</option>';
    }
    $out .= '</select> ';
    $out .= '<select class="typ" name="specialization"' . ($autoSubmit ? ' data-submit="1"' : '') . '></select>';
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'] ?? '';

    // ---- Create user ----
    if ($a === 'add') {
        $pw   = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $spec = $role === 'lawyer' ? ($_POST['specialization'] ?? '') : '';

        if (!preg_match('/^\w{3,50}$/', $_POST['username'] ?? '') || strlen($pw) < 10
            || !in_array($role, ROLES, true)) {
            $m = 'Username (3-50 letters/digits), role and a password of 10+ characters are required.';
        } elseif ($role === 'lawyer' && !in_array($spec, $types, true)) {
            $m = 'Choose the field of law and the lawyer type for this account.';
        } else {
            try {
                $db->prepare('INSERT INTO users (username, full_name, email, role, specialization, password_hash)
                              VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([
                       $_POST['username'],
                       trim($_POST['full_name']),
                       trim($_POST['email']),
                       $role,
                       $spec ?: null,
                       password_hash($pw, PASSWORD_DEFAULT),
                   ]);
                audit('user_created', null, $_POST['username']);
            } catch (Exception $x) {
                $m = 'Username already exists.';
            }
        }
    }

    // ---- Approve / reject a lawyer registration ----
    if ($a === 'approve' || $a === 'reject') {
        $id = (int) $_POST['id'];
        if ($a === 'approve') {
            $db->prepare("UPDATE users SET active = 1, status = 'approved' WHERE id = ? AND status = 'pending'")
               ->execute([$id]);
        } else {
            $db->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'")->execute([$id]);
        }
        audit('registration_' . $a . 'd', null, 'id ' . $id);
    }

    // ---- Activate / deactivate ----
    if ($a === 'toggle' && (int) $_POST['id'] !== me()['id']) {
        $db->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([(int) $_POST['id']]);
        audit('user_toggled', null, 'id ' . (int) $_POST['id']);
    }

    // ---- Delete ----
    if ($a === 'delete') {
        $id = (int) $_POST['id'];

        $s = $db->prepare('SELECT username, role FROM users WHERE id = ?');
        $s->execute([$id]);
        $target = $s->fetch();

        $s = $db->prepare('SELECT COUNT(*) FROM cases
                           WHERE lawyer_id = ? OR prosecutor_id = ? OR judge_id = ? OR investigator_id = ?');
        $s->execute([$id, $id, $id, $id]);
        $assigned = (int) $s->fetchColumn();

        $admins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

        if (!$target) {
            $m = 'User not found.';
        } elseif ($id === me()['id']) {
            $m = 'You cannot delete your own account.';
        } elseif ($target['role'] === 'admin' && $admins <= 1) {
            $m = 'You cannot delete the last administrator.';
        } elseif ($assigned > 0) {
            $m = $target['username'] . ' is assigned to ' . $assigned
               . ' case(s). Reassign those cases first, or deactivate the user instead.';
        } else {
            $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            audit('user_deleted', null, $target['username'] . ' (' . $target['role'] . ')');
        }
    }

    // ---- Change role ----
    if ($a === 'role' && (int) $_POST['id'] !== me()['id'] && in_array($_POST['role'], ROLES, true)) {
        $db->prepare('UPDATE users SET role = ?, specialization = IF(? = "lawyer", specialization, NULL) WHERE id = ?')
           ->execute([$_POST['role'], $_POST['role'], (int) $_POST['id']]);
        audit('role_changed', null, 'id ' . (int) $_POST['id'] . ' -> ' . $_POST['role']);
    }

    // ---- Change lawyer type ----
    if ($a === 'spec' && in_array($_POST['specialization'] ?? '', $types, true)) {
        $db->prepare('UPDATE users SET specialization = ? WHERE id = ? AND role = "lawyer"')
           ->execute([$_POST['specialization'], (int) $_POST['id']]);
        audit('specialization_changed', null, 'id ' . (int) $_POST['id'] . ' -> ' . $_POST['specialization']);
    }
}

$pend = $db->query("SELECT * FROM users WHERE status = 'pending' ORDER BY id")->fetchAll();

top('User management');

if ($m) {
    echo '<p class="err">' . e($m) . '</p>';
}
?>

<?php if ($pend): ?>
<div class="card">
    <h3>Pending lawyer registrations (<?= count($pend) ?>)</h3>
    <p class="muted">Check each Bar roll number against the Supreme Court's official list before approving.</p>
    <table>
        <tr>
            <th>Name</th><th>Username</th><th>Email</th><th>Bar roll no.</th>
            <th>Year</th><th>Lawyer type</th><th>Actions</th>
        </tr>
        <?php foreach ($pend as $x): ?>
            <tr>
                <td><?= e($x['full_name']) ?></td>
                <td><?= e($x['username']) ?></td>
                <td><?= e($x['email']) ?></td>
                <td><?= e($x['bar_roll_no']) ?></td>
                <td><?= e($x['bar_year']) ?></td>
                <td><?= e($x['specialization']) ?></td>
                <td>
                    <div class="acts">
                        <?php foreach (['approve' => 'Approve', 'reject' => 'Reject'] as $act => $label): ?>
                            <form method="post"<?= $act === 'reject'
                                ? ' onsubmit="return confirm(\'Reject and delete this registration?\');"' : '' ?>>
                                <?= csrf() ?>
                                <input type="hidden" name="a" value="<?= $act ?>">
                                <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                                <button class="p<?= $act === 'reject' ? ' danger' : '' ?>"><?= $label ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h3>Create user</h3>
    <form method="post" class="f">
        <?= csrf() ?>
        <input type="hidden" name="a" value="add">

        <div>Username<input name="username" required></div>
        <div>Full name<input name="full_name" required></div>
        <div>Email<input type="email" name="email"></div>
        <div>Role
            <select name="role" id="roleSel">
                <?php foreach (ROLES as $r): ?>
                    <option><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="lawyerOnly" style="display:none">Lawyer type
            <div class="pick"><?= lawyerPicker($R, $groupOf) ?></div>
        </div>
        <div>Password (10+ chars)<input type="password" name="password" required></div>
        <div><br><button class="p">Create user</button></div>
    </form>
</div>

<div class="card">
    <h3>All users</h3>
    <table>
        <tr><th>User</th><th>Name</th><th>Role</th><th>Lawyer type</th><th>Active</th><th>Actions</th></tr>
        <?php foreach ($db->query("SELECT * FROM users WHERE status <> 'pending' ORDER BY id") as $u): ?>
            <tr>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['full_name']) ?></td>
                <td>
                    <form method="post">
                        <?= csrf() ?>
                        <input type="hidden" name="a" value="role">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <select name="role" style="width:auto" onchange="this.form.submit()">
                            <?php foreach (ROLES as $r): ?>
                                <option<?= $r === $u['role'] ? ' selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <?php if ($u['role'] === 'lawyer'): ?>
                        <form method="post">
                            <?= csrf() ?>
                            <input type="hidden" name="a" value="spec">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <div class="pick"><?= lawyerPicker($R, $groupOf, (string) $u['specialization'], true) ?></div>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= $u['active'] ? 'Yes' : 'No' ?></td>
                <td>
                    <div class="acts">
                        <form method="post">
                            <?= csrf() ?>
                            <input type="hidden" name="a" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="p"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>

                        <?php if ((int) $u['id'] !== me()['id']): ?>
                            <form method="post"
                                  onsubmit="return confirm('Delete user <?= e($u['username']) ?>? This cannot be undone.');">
                                <?= csrf() ?>
                                <input type="hidden" name="a" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="p danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
(function () {
    var LAW = <?= json_encode($R['lawyer']) ?>;

    // Fill the type dropdown from the chosen field
    function fill(f, current) {
        var t = f.form.querySelector('select.typ');
        t.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = f.value ? '-- Lawyer type --' : '-- Choose a field first --';
        t.appendChild(ph);
        (LAW[f.value] || []).forEach(function (n) {
            var o = document.createElement('option');
            o.textContent = n;
            o.value = n;
            if (n === current) o.selected = true;
            t.appendChild(o);
        });
    }

    document.querySelectorAll('select.fld').forEach(function (f) {
        fill(f, f.getAttribute('data-cur') || '');
        f.addEventListener('change', function () { fill(f, ''); });
    });

    // In the table, saving happens as soon as a type is picked
    document.querySelectorAll('select.typ[data-submit]').forEach(function (t) {
        t.addEventListener('change', function () {
            if (t.value) t.form.submit();
        });
    });

    // Show the lawyer type picker only when the role is "lawyer"
    var roleSel = document.getElementById('roleSel');
    var box = document.getElementById('lawyerOnly');
    function toggle() { box.style.display = roleSel.value === 'lawyer' ? '' : 'none'; }
    roleSel.addEventListener('change', toggle);
    toggle();
})();
</script>
<?php bottom();