<?php
require 'inc/bootstrap.php';
need();

$u = me();
[$w, $p] = scope();

$s = $db->prepare("SELECT status, COUNT(*) n FROM cases c WHERE 1=1$w GROUP BY status");
$s->execute($p);
$cnt   = array_column($s->fetchAll(), 'n', 'status');
$total = array_sum($cnt);

$s = $db->prepare("SELECT c.id, c.case_ref, c.title, c.status, c.updated_at
                   FROM cases c WHERE 1=1$w ORDER BY c.updated_at DESC LIMIT 6");
$s->execute($p);
$recent = $s->fetchAll();

$s = $db->prepare("SELECT h.*, c.case_ref, c.title FROM hearings h
                   JOIN cases c ON c.id = h.case_id
                   WHERE h.hearing_at >= CURDATE()$w ORDER BY h.hearing_at LIMIT 6");
$s->execute($p);
$hearings = $s->fetchAll();

$s = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6');
$s->execute([$u['id']]);
$notes = $s->fetchAll();

$pending = $u['role'] === 'admin'
    ? (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn() : 0;

function when(string $dt): string
{
    $d = (int) floor((strtotime(substr($dt, 0, 10)) - strtotime(date('Y-m-d'))) / 86400);
    return $d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : "In $d days");
}

top('Dashboard');
?>
<div class="welcome">
    <h2>Welcome, <?= e($u['full_name']) ?></h2>
    <p><?= e(ucfirst($u['role'])) ?> &middot; <?= date('l, F j, Y') ?></p>
</div>

<?php if ($pending): ?>
    <div class="alertbar">
        <b><?= $pending ?></b> lawyer registration(s) waiting for approval.
        <a href="users.php">Review now</a>
    </div>
<?php endif; ?>

<div class="quick">
    <?php if (in_array($u['role'], ['admin', 'clerk', 'lawyer'], true)): ?>
        <a href="case_edit.php">+ New case</a>
    <?php endif; ?>
    <a href="cases.php">Search cases</a>
    <a href="hearings.php">Hearing calendar</a>
    <?php if ($u['role'] === 'admin'): ?>
        <a href="users.php">Manage users<?= $pending ? ' <span class="count">' . $pending . '</span>' : '' ?></a>
        <a href="audit.php">Audit log</a>
    <?php endif; ?>
</div>

<div class="stats">
    <a class="stat" href="cases.php"><b><?= $total ?></b><span>All cases</span></a>
    <?php foreach (STATUSES as $st): ?>
        <a class="stat s-<?= preg_replace('/\W+/', '', $st) ?>" href="cases.php?st=<?= urlencode($st) ?>">
            <b><?= (int) ($cnt[$st] ?? 0) ?></b><span><?= e($st) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="two">
    <div>
        <div class="card">
            <h3>Upcoming hearings</h3>
            <?php foreach ($hearings as $h): ?>
                <div class="hrow">
                    <div class="datebox"><b><?= date('d', strtotime($h['hearing_at'])) ?></b><?= date('M', strtotime($h['hearing_at'])) ?></div>
                    <div>
                        <a href="case_view.php?id=<?= (int) $h['case_id'] ?>"><?= e($h['case_ref']) ?></a>
                        <?= e($h['title']) ?><br>
                        <span class="muted"><?= when($h['hearing_at']) ?>, <?= date('g:i A', strtotime($h['hearing_at'])) ?>
                            &middot; <?= e($h['location']) ?> &middot; <?= e($h['purpose']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$hearings): ?><div class="empty">No upcoming hearings.</div><?php endif; ?>
        </div>

        <div class="card">
            <h3>Recently updated cases</h3>
            <table>
                <tr><th>Ref</th><th>Title</th><th>Status</th><th>Updated</th></tr>
                <?php foreach ($recent as $c): ?>
                    <tr>
                        <td><a href="case_view.php?id=<?= (int) $c['id'] ?>"><?= e($c['case_ref']) ?></a></td>
                        <td><?= e($c['title']) ?></td>
                        <td><?= badge($c['status']) ?></td>
                        <td><?= e(substr($c['updated_at'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php if (!$recent): ?><div class="empty">No cases yet. Cases assigned to you will appear here.</div><?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <h3>Notifications</h3>
            <?php foreach ($notes as $n): ?>
                <div class="note<?= str_starts_with($n['msg'], 'SECURITY ALERT') ? ' alert' : '' ?>">
                    <?= e($n['msg']) ?><br><span class="muted"><?= e($n['created_at']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$notes): ?><div class="empty">You're all caught up.</div><?php endif; ?>
        </div>

        <div class="card">
            <h3>Cases by status</h3>
            <?php foreach (STATUSES as $st): $n = (int) ($cnt[$st] ?? 0); ?>
                <div class="muted"><?= e($st) ?> (<?= $n ?>)</div>
                <div class="bar" style="width:<?= $total ? round(100 * $n / $total) : 0 ?>%;margin-bottom:.5rem"></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php bottom();