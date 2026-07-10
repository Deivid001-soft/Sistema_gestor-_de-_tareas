<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_error.log');
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php'; // solo funciones, NUNCA HTML

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// ... el resto de tu código