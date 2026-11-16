<?php
// Run once, then DELETE this file.

require 'inc/bootstrap.php';

$C = require 'inc/config.php';

$pdo = new PDO(
    "mysql:host={$C['host']};charset=utf8mb4",
    $C['user'],
    $C['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$C['name']}` CHARACTER SET utf8mb4");
$pdo->exec("USE `{$C['name']}`");

foreach (
    array_filter(
        array_map('trim', explode(';', file_get_contents('schema.sql')))
    ) as $q
) {
    $pdo->exec($q);
}

if ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    die('Already installed. Delete install.php.');
}

$pw = password_hash('Passw0rd!Demo', PASSWORD_DEFAULT);

$u = $pdo->prepare(
    'INSERT INTO users(username,full_name,role,password_hash) VALUES(?,?,?,?)'
);

foreach ([
    ['admin', 'Sample Admin', 'admin'],
    ['lawyer', 'Sample Lawyer', 'lawyer'],
    ['prosecutor', 'Sample Prosecutor', 'prosecutor'],
    ['judge', 'Sample Judge', 'judge'],
    ['investigator', 'Sample Investigator', 'investigator'],
    ['clerk', 'Sample Clerk', 'clerk']
] as $r) {
    $u->execute([$r[0], $r[1], $r[2], $pw]);
}

// The sample lawyer needs a lawyer type to be able to log in
$pdo->exec("UPDATE users SET specialization = 'Civil Litigator' WHERE username = 'lawyer'");

$pdo->exec("
    INSERT INTO cases(
        case_ref,
        title,
        category,
        classification,
        date_filed,
        incident_date,
        incident_location,
        complainant,
        respondent,
        court,
        description,
        summary,
        status,
        lawyer_id,
        prosecutor_id,
        judge_id,
        investigator_id,
        created_by
    ) VALUES
    (
        'CASE-2026-0001',
        'SAMPLE: People v. Doe (theft)',
        'Criminal',
        'Theft',
        '2026-09-01',
        '2026-08-28',
        'Sample Street, Sample City',
        'Sample Complainant A',
        'John Doe (sample)',
        'RTC Branch 1 (sample)',
        'Sample test data only.',
        'Fictitious summary for testing.',
        'Under Prosecution',
        2, 3, 4, 5, 1
    ),
    (
        'CASE-2026-0002',
        'SAMPLE: Roe v. Poe (contract)',
        'Civil',
        'Breach of contract',
        '2026-10-05',
        NULL,
        'N/A',
        'Richard Roe (sample)',
        'Paula Poe (sample)',
        'RTC Branch 2 (sample)',
        'Sample civil test data.',
        'Fictitious.',
        'Awaiting Hearing',
        2, NULL, 4, NULL, 1
    )
");

$pdo->prepare("
    INSERT INTO witnesses(
        case_id,
        full_name,
        type,
        contact,
        relationship,
        statement,
        statement_date,
        created_by
    ) VALUES(1,?,?,?,?,?,?,5)
")->execute([
    'Sample Witness One',
    'witness',
    enc('0900-000-0000 (sample)'),
    'Neighbor',
    'Fictitious statement for testing.',
    '2026-09-03'
]);

$pdo->exec("
    INSERT INTO blotters(
        blotter_ref,
        case_id,
        reported_at,
        nature,
        location,
        reporter,
        persons,
        narrative,
        officer,
        status,
        created_by
    ) VALUES(
        'BLT-2026-0001',
        1,
        '2026-08-28 21:15:00',
        'Theft',
        'Sample Street',
        'Sample Complainant A',
        'John Doe (sample)',
        'Fictitious blotter narrative.',
        'PO1 Sample',
        'Referred to prosecutor',
        5
    )
");

$pdo->exec("
    INSERT INTO hearings(
        case_id,
        hearing_at,
        location,
        purpose,
        created_by
    ) VALUES
    (
        1,
        DATE_ADD(NOW(), INTERVAL 7 DAY),
        'Courtroom 1 (sample)',
        'Arraignment',
        6
    ),
    (
        2,
        DATE_ADD(NOW(), INTERVAL 14 DAY),
        'Courtroom 2 (sample)',
        'Pre-trial',
        6
    )
");

echo 'Installed. Log in at index.php (admin / lawyer / prosecutor / judge / investigator / clerk, password Passw0rd!Demo). DELETE install.php now.';