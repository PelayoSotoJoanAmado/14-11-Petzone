<?php
// Evitar que se muestren errores como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Iniciar output buffering para capturar cualquier salida inesperada
ob_start();

header('Content-Type: application/json');

try {
    require_once '../config/database.php';
    $db = getDB();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Conexión a BD exitosa',
        'session_status' => session_status(),
        'session_id' => session_id()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>