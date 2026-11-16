
<?php
require 'inc/bootstrap.php';
need();
[$w, $p] = scope();

// Retrieve Hearing Records
$s = $db->prepare(
    "SELECT
        h.*,
        c.case_ref,
        c.title
     FROM hearings h
     JOIN cases c ON c.id = h.case_id
     WHERE h.hearing_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)$w
     ORDER BY h.hearing_at"
);

$s->execute($p);

// Page Header
top('Hearing calendar');

// Track Current Date
$day = '';

// Display Hearing Calendar
foreach ($s as $h) {
    $d = substr($h['hearing_at'], 0, 10);
    // Start a New Day Section
    if ($d != $day) {
        if ($day) {
            echo '</table></div>';
        }

        $day = $d;

        echo '
        <div class=card>
            <h3>' .
            e(date('l, F j, Y', strtotime($d))) .
            ($d < date('Y-m-d') ? ' (past)' : '') .
            '</h3>
            <table>
        ';
    }

    // Display Hearing Details
    echo '
        <tr>
            <td width=90>
                ' . e(substr($h['hearing_at'], 11, 5)) . '
            </td>
            <td>
                <a href="case_view.php?id=' . $h['case_id'] . '">
                    ' . e($h['case_ref']) . '
                </a>
                ' . e($h['title']) . '
            </td>
            <td>' . e($h['location']) . '</td>
            <td>' . e($h['purpose']) . '</td>
        </tr>
    ';
}

// Close Calendar or Display Empty Message
echo $day
    ? '</table></div>'
    : '<p>No hearings scheduled.</p>';

// Page Footer
bottom();
