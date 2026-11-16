
<?php
require 'inc/bootstrap.php';
need();

[$w, $p] = scope();
$q = trim($_GET['q'] ?? '');
$st = $_GET['st'] ?? '';
$from = $_GET['from'] ?? '';

// Build Cases Query
$sql = "
    SELECT
        c.*,
        j.full_name jn,
        l.full_name ln
    FROM cases c
    LEFT JOIN users j ON j.id = c.judge_id
    LEFT JOIN users l ON l.id = c.lawyer_id
    WHERE 1=1$w
";

// Search Cases
if ($q !== '') {
    $sql .= '
        AND (
            c.case_ref LIKE ?
            OR c.title LIKE ?
            OR c.complainant LIKE ?
            OR c.respondent LIKE ?
            OR c.classification LIKE ?
            OR l.full_name LIKE ?
            OR j.full_name LIKE ?
            OR c.id IN (
                SELECT case_id
                FROM witnesses
                WHERE full_name LIKE ?
            )
            OR c.id IN (
                SELECT case_id
                FROM blotters
                WHERE blotter_ref LIKE ?
            )
        )
    ';
    for ($i = 0; $i < 9; $i++) {
        $p[] = "%$q%";
    }
}

// Filter by Case Status
if (in_array($st, STATUSES, true)) {
    $sql .= ' AND c.status=?';
    $p[] = $st;
}

// Filter by Filing Date
if (preg_match('/^\d{4}-\d\d-\d\d$/', $from)) {
    $sql .= ' AND c.date_filed>=?';
    $p[] = $from;
}

// Execute Query
$s = $db->prepare(
    $sql . ' ORDER BY c.date_filed DESC LIMIT 200'
);
$s->execute($p);

// Page Header
top('Cases');
?>

<div class=card>
    <form method=get class=f>
        <div>
            Search
            <input
                name=q
                value="<?= e($q) ?>"
                placeholder="Ref, title, party, witness, blotter no., personnel"
            >
        </div>
        <div>
            Status
            <select name=st>
                <option value="">All
                <?php
                foreach (STATUSES as $x) {
                    echo '<option' .
                        ($x == $st ? ' selected' : '') .
                        '>' . e($x);
                }
                ?>
            </select>
        </div>
        <div>
            Filed from
            <input
                type=date
                name=from
                value="<?= e($from) ?>"
            >
        </div>
        <div>
            <br>
            <button class=p>Search</button>
            <?php
            if (in_array(me()['role'], ['admin', 'clerk', 'lawyer'])) {
                echo '<a class=btn href=case_edit.php>New case</a>';
            }
            ?>
        </div>
    </form>
</div>

<div class=card>
    <table>
        <tr>
            <th>Ref</th>
            <th>Title</th>
            <th>Classification</th>
            <th>Filed</th>
            <th>Judge</th>
            <th>Lawyer</th>
            <th>Status</th>
        </tr>

        <?php
        foreach ($s as $c) {
            echo '
                <tr>
                    <td>
                        <a href="case_view.php?id=' . $c['id'] . '">
                            ' . e($c['case_ref']) . '
                        </a>
                    </td>
                    <td>' . e($c['title']) . '</td>
                    <td>' . e($c['classification']) . '</td>
                    <td>' . e($c['date_filed']) . '</td>
                    <td>' . e($c['jn']) . '</td>
                    <td>' . e($c['ln']) . '</td>
                    <td>' . badge($c['status']) . '</td>
                </tr>
            ';
        }
        ?>

    </table>

</div>

<?php

// Page Footer
bottom();
