<?php
require 'inc/bootstrap.php';
need('admin');
top('Audit log and login history');

// Recent Activity
echo '
<div class="card">
    <h3>Recent Activity</h3>

    <table>
        <tr>
            <th>Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Case</th>
            <th>Details</th>
            <th>IP</th>
        </tr>
';

foreach (
    $db->query(
        'SELECT
            a.*,
            u.username,
            c.case_ref
         FROM audit_logs a
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN cases c ON c.id = a.case_id
         ORDER BY a.id DESC
         LIMIT 200'
    ) as $a
) {
    echo '
        <tr>
            <td>' . e($a['at']) . '</td>
            <td>' . e($a['username']) . '</td>
            <td>' . e($a['action']) . '</td>
            <td>' . e($a['case_ref']) . '</td>
            <td>' . e($a['details']) . '</td>
            <td>' . e($a['ip']) . '</td>
        </tr>
    ';
}

echo '
    </table>
</div>
';

// Login History
echo '
<div class="card">
    <h3>Login History</h3>

    <table>
        <tr>
            <th>Time</th>
            <th>Username</th>
            <th>Result</th>
            <th>IP</th>
        </tr>
';

foreach (
    $db->query(
        'SELECT *
         FROM login_history
         ORDER BY id DESC
         LIMIT 100'
    ) as $l
) {
    echo '
        <tr>
            <td>' . e($l['at']) . '</td>
            <td>' . e($l['username']) . '</td>
            <td>' . ($l['success'] ? 'Success' : 'Failed') . '</td>
            <td>' . e($l['ip']) . '</td>
        </tr>
    ';
}

echo '
    </table>
</div>
';

bottom();
