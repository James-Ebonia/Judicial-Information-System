
<?php

require 'inc/bootstrap.php';

// Record Logout Activity
if (
    me() &&
    hash_equals($_SESSION['t'] ?? '', $_POST['t'] ?? '')
) {
    audit('logout');
}

// Destroy User Session
session_destroy();

// Redirect to Login Page
header('Location: index.php');
