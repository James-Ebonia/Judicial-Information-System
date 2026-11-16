
<?php

require 'inc/bootstrap.php';

need();

$id = (int)($_GET['id'] ?? 0);
$c = getCase($id);

if (!$c) {
    http_response_code(404);
    die('Case not found or access denied.');
}

// Current User
$r = me()['role'];
$uid = me()['id'];

$all = [
    'admin',
    'lawyer',
    'prosecutor',
    'judge',
    'investigator',
    'clerk'
];

// Role Permissions
$can = [
    'note' => $all,

    'witness' => [
        'admin',
        'lawyer',
        'prosecutor',
        'investigator',
        'clerk'
    ],

    'blotter' => [
        'admin',
        'investigator',
        'clerk'
    ],

    'hearing' => [
        'admin',
        'clerk',
        'judge'
    ],

    'outcome' => [
        'admin',
        'clerk',
        'judge'
    ],

    'status' => [
        'admin',
        'clerk',
        'judge',
        'prosecutor'
    ],

    'upload' => [
        'admin',
        'lawyer',
        'prosecutor',
        'investigator',
        'clerk'
    ]
];

$msg = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $a = $_POST['a'] ?? '';

    if (!isset($can[$a]) || !in_array($r, $can[$a], true)) {
        die('Forbidden');
    }

    $p = fn($k) => trim($_POST[$k] ?? '');

    // Add Legal Note
    if ($a == 'note' && $p('body')) {

        $db->prepare(
            'INSERT INTO notes(case_id,user_id,body) VALUES(?,?,?)'
        )->execute([
            $id,
            $uid,
            $p('body')
        ]);

        audit('note_added', $id);
    }

    // Update Case Status
    if ($a == 'status' && in_array($p('status'), STATUSES, true)) {

        $db->prepare(
            'UPDATE cases SET status=? WHERE id=?'
        )->execute([
            $p('status'),
            $id
        ]);

        audit(
            'status_changed',
            $id,
            $c['status'] . ' -> ' . $p('status')
        );

        notify(
            $c,
            'Status of ' . $c['case_ref'] . ' changed to ' . $p('status')
        );
    }

    // Add Witness or Person Involved
    if ($a == 'witness' && $p('full_name')) {

        $db->prepare(
            'INSERT INTO witnesses(
                case_id,
                full_name,
                type,
                contact,
                relationship,
                statement,
                statement_date,
                created_by
            ) VALUES(?,?,?,?,?,?,?,?)'
        )->execute([
            $id,
            $p('full_name'),
            $p('type'),
            enc($p('contact')),
            $p('relationship'),
            $p('statement'),
            $p('statement_date') ?: null,
            $uid
        ]);

        audit('witness_added', $id, $p('full_name'));
    }

    // Add Police Blotter
    if ($a == 'blotter' && $p('nature')) {

        $ref = 'BLT-' . date('Y') . '-' .
            str_pad(
                (string)(
                    $db->query(
                        'SELECT COALESCE(MAX(id),0)+1 FROM blotters'
                    )->fetchColumn()
                ),
                4,
                '0',
                STR_PAD_LEFT
            );

        $db->prepare(
            'INSERT INTO blotters(
                blotter_ref,
                case_id,
                parent_id,
                reported_at,
                nature,
                location,
                reporter,
                persons,
                narrative,
                officer,
                status,
                created_by
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $ref,
            $id,
            (int)$p('parent_id') ?: null,
            $p('reported_at') ?: date('Y-m-d H:i:s'),
            $p('nature'),
            $p('location'),
            $p('reporter'),
            $p('persons'),
            $p('narrative'),
            $p('officer'),
            $p('bstatus'),
            $uid
        ]);

        audit('blotter_added', $id, $ref);
    }

    // Schedule Hearing
    if ($a == 'hearing' && $p('hearing_at')) {

        $db->prepare(
            'INSERT INTO hearings(
                case_id,
                hearing_at,
                location,
                purpose,
                created_by
            ) VALUES(?,?,?,?,?)'
        )->execute([
            $id,
            $p('hearing_at'),
            $p('location'),
            $p('purpose'),
            $uid
        ]);

        audit('hearing_scheduled', $id, $p('hearing_at'));

        notify(
            $c,
            'Hearing scheduled for ' . $c['case_ref'] .
            ' on ' . $p('hearing_at')
        );
    }

    // Record Hearing Outcome
    if ($a == 'outcome') {

        $db->prepare(
            'UPDATE hearings SET outcome=? WHERE id=? AND case_id=?'
        )->execute([
            $p('outcome'),
            (int)$_POST['hid'],
            $id
        ]);

        audit('hearing_outcome', $id);
    }

    // Upload Documents and Evidence
    if ($a == 'upload' && !empty($_FILES['f']['tmp_name'])) {

        $f = $_FILES['f'];

        $ext = strtolower(
            pathinfo($f['name'], PATHINFO_EXTENSION)
        );

        $ok = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => '',
            'docx' => ''
        ];

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);

        if (
            $f['error'] ||
            $f['size'] > 10485760 ||
            !isset($ok[$ext]) ||
            ($ok[$ext] && $ok[$ext] !== $mime)
        ) {

            $msg = 'File rejected: use PDF, DOC/DOCX, JPG or PNG under 10 MB.';

        } else {

            $st = bin2hex(random_bytes(16)) . '.' . $ext;

            move_uploaded_file(
                $f['tmp_name'],
                __DIR__ . '/storage/' . $st
            );

            $t = $p('title') ?: $f['name'];

            $s = $db->prepare(
                'SELECT COALESCE(MAX(version),0)+1
                 FROM documents
                 WHERE case_id=? AND title=?'
            );

            $s->execute([
                $id,
                $t
            ]);

            $ver = $s->fetchColumn();

            $db->prepare(
                'INSERT INTO documents(
                    case_id,
                    category,
                    title,
                    version,
                    filename,
                    stored,
                    mime,
                    size,
                    description,
                    uploaded_by
                ) VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $id,
                $p('category'),
                $t,
                $ver,
                basename($f['name']),
                $st,
                $mime,
                $f['size'],
                $p('description'),
                $uid
            ]);

            audit(
                'document_uploaded',
                $id,
                $t . ' v' . $ver
            );

            notify(
                $c,
                'Document uploaded to ' . $c['case_ref'] . ': ' . $t
            );
        }
    }

    // Redirect After Successful Submission
    if (!$msg) {
        header("Location: case_view.php?id=$id");
        exit;
    }
}

// Record Case View
audit('case_viewed', $id);

$c = getCase($id);

// Database Query Helper
$q = fn($sql, $p = []) => (function () use ($db, $sql, $p) {

    $s = $db->prepare($sql);
    $s->execute($p);

    return $s->fetchAll();

})();

// Retrieve Case Records
$wit = $q(
    'SELECT * FROM witnesses WHERE case_id=? ORDER BY id',
    [$id]
);

$bl = $q(
    'SELECT * FROM blotters WHERE case_id=? ORDER BY id',
    [$id]
);

$dc = $q(
    'SELECT d.*,u.full_name un
     FROM documents d
     LEFT JOIN users u ON u.id=d.uploaded_by
     WHERE case_id=?
     ORDER BY title,version DESC',
    [$id]
);

$hr = $q(
    'SELECT * FROM hearings WHERE case_id=? ORDER BY hearing_at',
    [$id]
);

$nt = $q(
    'SELECT n.*,u.full_name un
     FROM notes n
     LEFT JOIN users u ON u.id=n.user_id
     WHERE case_id=?
     ORDER BY n.id DESC',
    [$id]
);

$tl = $q(
    'SELECT a.*,u.full_name un
     FROM audit_logs a
     LEFT JOIN users u ON u.id=a.user_id
     WHERE case_id=?
     AND action<>"case_viewed"
     ORDER BY a.id DESC
     LIMIT 50',
    [$id]
);

// Retrieve Assigned Personnel
$staff = [];

foreach (
    [
        'lawyer_id', 'notary_id',
        'prosecutor_id',
        'judge_id',
        'investigator_id'
    ] as $k
) {

    if ($c[$k]) {
        $s = $db->prepare(
            'SELECT full_name FROM users WHERE id=?'
        );
        $s->execute([$c[$k]]);
        $staff[$k] = $s->fetchColumn();
    }
}

// Check User Permissions
$in = fn($k) => in_array($r, $can[$k], true);
// Page Header
top($c['case_ref']);
if ($msg) {
    echo '<p class=err>' . e($msg) . '</p>';
}

// Case Information
echo '
<div class=card>
    <h3>' . e($c['title']) . ' ' . badge($c['status']) . '</h3>
    <div class=f>
        <div>
            <b>Category</b><br>
            ' . e($c['category']) . '<br>
            <b>Classification</b><br>
            ' . e($c['classification']) . '<br>
            <b>Court</b><br>
            ' . e($c['court']) . '
        </div>
        <div>
            <b>Filed</b> ' . e($c['date_filed']) . '<br>
            <b>Incident</b> ' . e($c['incident_date']) .
            ' at ' . e($c['incident_location']) . '<br>
            <b>Complainant</b> ' . e($c['complainant']) . '<br>
            <b>Respondent / Accused</b> ' . e($c['respondent']) . '
        </div>
        <div>
            <b>Judge</b> ' . e($staff['judge_id'] ?? '-') . '<br>
            <b>Prosecutor</b> ' . e($staff['prosecutor_id'] ?? '-') . '<br>
            <b>Lawyer</b> ' . e($staff['lawyer_id'] ?? '-') . '<br>
            <b>Investigator</b> ' . e($staff['investigator_id'] ?? '-') . '
        </div>
    </div>
    <p>
        <b>Description</b><br>
        ' . nl2br(e($c['description'])) . '
    </p>
    <p>
        <b>Summary</b><br>
        ' . nl2br(e($c['summary'])) . '
    </p>
';

// Edit Case and Print Report
if (in_array($r, ['admin', 'clerk', 'lawyer'])) {
    echo '<a class=btn href="case_edit.php?id=' . $id . '">Edit case</a> ';
}

echo '<button class=p onclick="print()">Print report</button>';

// Update Case Status
if ($in('status')) {
    echo '
    <form method=post style="margin-top:.6rem">
        ' . csrf() . '
        <input type=hidden name=a value=status>
        <select name=status style="width:auto">
    ';
    foreach (STATUSES as $s) {
        echo '<option' .
            ($s == $c['status'] ? ' selected' : '') .
            '>' . e($s);
    }
    echo '
        </select>
        <button class=p>Update status</button>
    </form>
    ';
}

echo '</div>';

// Hearings Section
echo '
<div class=card>
    <h3>Hearings</h3>
    <table>
        <tr>
            <th>When</th>
            <th>Location</th>
            <th>Purpose</th>
            <th>Outcome</th>
        </tr>
';

foreach ($hr as $h) {
    echo '
        <tr>
            <td>' . e($h['hearing_at']) . '</td>
            <td>' . e($h['location']) . '</td>
            <td>' . e($h['purpose']) . '</td>
            <td>' . nl2br(e($h['outcome'])) . '</td>
        </tr>
    ';

    if ($in('outcome')) {
        echo '
        <form method=post>
            ' . csrf() . '
            <input type=hidden name=a value=outcome>
            <input type=hidden name=hid value=' . $h['id'] . '>
            <input name=outcome placeholder="Record outcome">
            <button class=p>Save</button>
        </form>
        ';
    }
}

echo '</table>';

// Schedule Hearing Form
if ($in('hearing')) {
    echo '
    <form method=post class=f>
        ' . csrf() . '
        <input type=hidden name=a value=hearing>
        <div>
            Date and time
            <input type=datetime-local name=hearing_at required>
        </div>
        <div>
            Location
            <input name=location>
        </div>
        <div>
            Purpose
            <input name=purpose>
        </div>
        <div>
            <br>
            <button class=p>Schedule hearing</button>
        </div>
    </form>
    ';
}
echo '</div>';

// Witnesses and Persons Involved
echo '
<div class=card>
    <h3>Witnesses and persons involved</h3>
    <table>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Relationship</th>
            <th>Contact</th>
            <th>Statement</th>
        </tr>
';

foreach ($wit as $w) {
    echo '
        <tr>
            <td>' . e($w['full_name']) . '</td>
            <td>' . e($w['type']) . '</td>
            <td>' . e($w['relationship']) . '</td>
            <td>' .
                ($r == 'clerk' ? 'Restricted' : e(dec($w['contact']))) .
            '</td>
            <td>
                ' . nl2br(e($w['statement'])) . '
                <i>' . e($w['statement_date']) . '</i>
            </td>
        </tr>
    ';
}

echo '</table>';

// Add Witness Form
if ($in('witness')) {
    echo '
    <form method=post class=f>
        ' . csrf() . '
        <input type=hidden name=a value=witness>
        <div>
            Full name
            <input name=full_name required>
        </div>
        <div>
            Type
            <select name=type>
    ';

    foreach (
        [
            'witness',
            'complainant',
            'respondent',
            'accused',
            'victim',
            'other'
        ] as $t
    ) {
        echo "<option>$t";
    }

    echo '
            </select>
        </div>
        <div>
            Contact
            <input name=contact>
        </div>
        <div>
            Relationship
            <input name=relationship>
        </div>
        <div>
            Statement date
            <input type=date name=statement_date>
        </div>

        <div style="grid-column:1/-1">
            Statement
            <textarea name=statement></textarea>
        </div>
        <div>
            <button class=p>Add person</button>
        </div>
    </form>
    ';
}

echo '</div>';

// Police Blotter Section
echo '
<div class=card>
    <h3>Police blotter</h3>
    <table>
        <tr>
            <th>Ref</th>
            <th>Reported</th>
            <th>Nature</th>
            <th>Narrative</th>
            <th>Officer</th>
            <th>Status</th>
        </tr>
';

foreach ($bl as $b) {
    echo '
        <tr>
            <td>
                ' . e($b['blotter_ref']) .
                ($b['parent_id']
                    ? ' (correction of #' . $b['parent_id'] . ')'
                    : '') . '
            </td>
            <td>' . e($b['reported_at']) . '</td>
            <td>' . e($b['nature']) . '</td>
            <td>' . nl2br(e($b['narrative'])) . '</td>
            <td>' . e($b['officer']) . '</td>
            <td>' . e($b['status']) . '</td>
        </tr>
    ';
}

echo '</table>';

// Add Police Blotter Form
if ($in('blotter')) {
    echo '
    <form method=post class=f>
        ' . csrf() . '
        <input type=hidden name=a value=blotter>
        <div>
            Nature
            <input name=nature required>
        </div>
        <div>
            Reported at
            <input type=datetime-local name=reported_at>
        </div>
        <div>
            Location
            <input name=location>
        </div>
        <div>
            Reporter
            <input name=reporter>
        </div>
        <div>
            Officer
            <input name=officer>
        </div>
        <div>
            Status
            <input name=bstatus>
        </div>
        <div>
            Correction of blotter record ID (optional)
            <input type=number name=parent_id>
        </div>
        <div style="grid-column:1/-1">
            Persons involved
            <input name=persons>
            Narrative
            <textarea name=narrative></textarea>
        </div>
        <div>
            <button class=p>Add blotter entry</button>
        </div>
    </form>
    <small>
        Blotter entries are never overwritten;
        corrections are saved as new linked entries.
    </small>
    ';
}

echo '</div>';
// Documents and Evidence Section
echo '
<div class=card>
    <h3>Documents and evidence</h3>

    <table>
        <tr>
            <th>Title</th>
            <th>Ver</th>
            <th>Category</th>
            <th>Uploaded by</th>
            <th>Date</th>
            <th></th>
        </tr>
';

foreach ($dc as $d) {
    echo '
        <tr>
            <td>
                ' . e($d['title']) . '<br>
                <small>' . e($d['description']) . '</small>
            </td>
            <td>' . $d['version'] . '</td>
            <td>' . e($d['category']) . '</td>
            <td>' . e($d['un']) . '</td>
            <td>' . e($d['created_at']) . '</td>
            <td>
                <a href="download.php?id=' . $d['id'] . '">
                    Download
                </a>
            </td>
        </tr>
    ';
}

echo '</table>';

// Upload Documents Form
if ($in('upload')) {
    echo '
    <form method=post enctype=multipart/form-data class=f>
        ' . csrf() . '
        <input type=hidden name=a value=upload>
        <div>
            File
            <input type=file name=f required>
        </div>
        <div>
            Title (same title = new version)
            <input name=title>
        </div>
        <div>
            Category
            <select name=category>
    ';

    foreach (
        [
            'Affidavit',
            'Complaint',
            'Motion',
            'Order',
            'Decision',
            'Evidence',
            'Other'
        ] as $t
    ) {
        echo "<option>$t";
    }
    echo '
            </select>
        </div>
        <div>
            Description
            <input name=description>
        </div>
        <div>
            <br>
            <button class=p>Upload</button>
        </div>
    </form>
    ';
}

echo '</div>';

// Legal Notes and Updates
echo '
<div class=card>
    <h3>Legal notes and updates</h3>
    <form method=post>
        ' . csrf() . '
        <input type=hidden name=a value=note>
        <textarea
            name=body
            required
            placeholder="Add a note visible to personnel assigned to this case"
        ></textarea>
        <button class=p>Add note</button>
    </form>
';

// Display Existing Notes
foreach ($nt as $n) {
    echo '
        <p>
            <b>' . e($n['un']) . '</b>
            <small>' . e($n['created_at']) . '</small>
            <br>
            ' . nl2br(e($n['body'])) . '
        </p>
    ';
}

echo '</div>';

// Activity Timeline
echo '
<div class=card>
    <h3>Activity timeline</h3>
    <table>
';

foreach ($tl as $t) {
    echo '
        <tr>
            <td>' . e($t['at']) . '</td>
            <td>' . e($t['un']) . '</td>
            <td>' . e($t['action']) . '</td>
            <td>' . e($t['details']) . '</td>
        </tr>
    ';
}

echo '
    </table>
</div>
';

// Page Footer
bottom();
