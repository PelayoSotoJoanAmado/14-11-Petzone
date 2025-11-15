<?php
/**
 * API de Categorías - PetZone
 * Archivo: api/categorias.php
 */

// Configurar errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Output buffering
ob_start();

require_once __DIR__ . '/../config/database.php';

// Limpiar cualquier salida previa
ob_end_clean();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = getDB();
    
    // Obtener todas las categorías activas
    $stmt = $db->query("
        SELECT 
            id, 
            nombre, 
            slug, 
            descripcion, 
            icono, 
            orden, 
            activo
        FROM categorias 
        WHERE activo = 1 
        ORDER BY orden ASC, nombre ASC
    ");
    
    $categorias = $stmt->fetchAll();
    
    // Log para debug
    error_log("CATEGORIAS.PHP - Total categorías: " . count($categorias));
    
    if (empty($categorias)) {
        error_log("CATEGORIAS.PHP - ADVERTENCIA: No hay categorías activas");
    }
    
    // Respuesta exitosa
    jsonResponse([
        'success' => true, 
        'categorias' => $categorias,
        'total' => count($categorias)
    ]);
    
} catch (Exception $e) {
    error_log("CATEGORIAS.PHP - Error: " . $e->getMessage());
    jsonResponse([
        'success' => false, 
        'message' => 'Error al cargar categorías',
        'error' => $e->getMessage()
    ], 500);
}
?>