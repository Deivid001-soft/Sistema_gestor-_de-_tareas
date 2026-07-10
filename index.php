<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
} else {
    header('Location: pages/login.php');
    exit;
}
?>