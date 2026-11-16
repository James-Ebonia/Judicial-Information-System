
<?php

require 'inc/bootstrap.php';

need();

// Retrieve Document
$s = $db->prepare(
    'SELECT * FROM documents WHERE id=?'
);

$s->execute([
    (int)($_GET['id'] ?? 0)
]);

$d = $s->fetch();

// Check Document Access
if (!$d || !getCase($d['case_id'])) {

    http_response_code(403);

    die('Access denied');
}

// Get File Path
$p = __DIR__ . '/storage/' . basename($d['stored']);

// Check if File Exists
if (!is_file($p)) {
    die('File missing');
}

// Record Document Download
audit(
    'document_downloaded',
    $d['case_id'],
    $d['title']
);

// Set Response Headers
header(
    'Content-Type: ' .
    ($d['mime'] ?: 'application/octet-stream')
);

header('X-Content-Type-Options: nosniff');

header(
    'Content-Disposition: attachment; filename="' .
    str_replace(['"', "\r", "\n"], '', $d['filename']) .
    '"'
);

header(
    'Content-Length: ' . filesize($p)
);

// Download File
readfile($p);
