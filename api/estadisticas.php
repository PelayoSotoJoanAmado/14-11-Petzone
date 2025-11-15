<?php
// Evitar que se muestren errores como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Iniciar output buffering para capturar cualquier salida inesperada
ob_start();

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
}

try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM estadisticas_dashboard");
    $stats = $stmt->fetch();
    
    jsonResponse(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>

<?php
/**
 * API de Categorías - PetZone
 * Archivo: api/categorias.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY orden ASC");
    $categorias = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'categorias' => $categorias]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>

