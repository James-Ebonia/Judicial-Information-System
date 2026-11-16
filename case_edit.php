
<?php

require 'inc/bootstrap.php';

need('admin', 'clerk', 'lawyer');

// Get Case ID
$id = (int)($_GET['id'] ?? 0);
$c = $id ? getCase($id) : null;

if ($id && !$c) {
    die('Not found');
}

// Get Active Users
$us = [];

foreach ($db->query('SELECT id, full_name, role FROM users WHERE active=1') as $u) {
    $us[$u['role']][] = $u;
}

// Case Fields
$F = [
    'title',
    'category',
    'classification',
    'date_filed',
    'incident_date',
    'incident_location',
    'complainant',
    'respondent',
    'court',
    'description',
    'summary'
];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $d = [];

    foreach ($F as $f) {
        $d[$f] = trim($_POST[$f] ?? '');
    }

    // Validate Required Fields
    if (
        $d['title'] === '' ||
        !preg_match('/^\d{4}-\d\d-\d\d$/', $d['date_filed'])
    ) {
        $err = 'Title and a valid filing date are required.';
    } else {

        // Assigned Personnel
        $a = [];

        foreach (
            ['lawyer_id', 'prosecutor_id', 'judge_id', 'investigator_id']
            as $k
        ) {
            $a[$k] = (int)($_POST[$k] ?? 0) ?: null;
        }

        // Update Existing Case
        if ($c) {

            $set = implode(
                ',',
                array_map(
                    fn($k) => "$k=?",
                    array_merge($F, array_keys($a))
                )
            );

            $db->prepare(
                "UPDATE cases SET $set WHERE id=?"
            )->execute(
                array_merge(
                    array_values($d),
                    array_values($a),
                    [$id]
                )
            );

            audit('case_updated', $id);

        } else {

            // Create New Case
            $d['incident_date'] = $d['incident_date'] ?: null;

            $ref = 'CASE-' . date('Y') . '-' .
                str_pad(
                    (string)(
                        $db->query(
                            'SELECT COALESCE(MAX(id),0)+1 FROM cases'
                        )->fetchColumn()
                    ),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            $cols = array_merge(
                ['case_ref', 'status', 'created_by'],
                $F,
                array_keys($a)
            );

            $vals = array_merge(
                [$ref, 'Pending', me()['id']],
                array_values($d),
                array_values($a)
            );

            $db->prepare(
                'INSERT INTO cases(' .
                implode(',', $cols) .
                ') VALUES(' .
                rtrim(str_repeat('?,', count($cols)), ',') .
                ')'
            )->execute($vals);

            $id = $db->lastInsertId();

            // Record Case Creation
            audit('case_created', $id, $ref);

            // Notify Assigned Personnel
            $n = getCase($id) ?: $db->query(
                "SELECT * FROM cases WHERE id=$id"
            )->fetch();

            notify($n, "New case assigned: $ref");
        }

        // Redirect to Case Details
        header("Location: case_view.php?id=$id");
        exit;
    }
}

// Page Header
top($c ? 'Edit case' : 'New case');

// Display Validation Error
if (!empty($err)) {
    echo '<p class=err>' . e($err) . '</p>';
}

// Retrieve Form Values
$v = fn($k) => e($_POST[$k] ?? $c[$k] ?? '');

// Case Form
echo '<div class=card>
<form method=post class=f>' . csrf();

// Generate Case Input Fields
foreach ($F as $f) {

    $t = str_contains($f, 'date') ? 'date' : 'text';

    $l = ucwords(str_replace('_', ' ', $f));

    if (in_array($f, ['description', 'summary'])) {

        echo "<div style='grid-column:1/-1'>
            $l
            <textarea name=$f rows=4>" .
            $v($f) .
            '</textarea>
        </div>';

    } else {

        echo "<div>
            $l
            <input type=$t name=$f value=\"" .
            $v($f) .
            '">
        </div>';
    }
}

// Assign Case Personnel
foreach (
    [
        'lawyer' => 'lawyer',
        'prosecutor' => 'prosecutor',
        'judge' => 'judge',
        'investigator' => 'investigator'
    ] as $r => $_
) {

    echo '<div>Assigned ' . $r .
        '<select name=' . $r . '_id>
            <option value=0>None';

    foreach ($us[$r] ?? [] as $u) {

        echo '<option value=' . $u['id'] .
            (($c[$r . '_id'] ?? 0) == $u['id'] ? ' selected' : '') .
            '>' . e($u['full_name']);
    }

    echo '</select></div>';
}

// Save Button
echo '
    <div>
        <br>
        <button class=p>Save case</button>
    </div>

</form>
</div>
';

// Page Footer
bottom();
