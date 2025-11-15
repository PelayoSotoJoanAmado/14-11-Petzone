<?php
/**
 * API de Servicios - PetZone
 * Archivo: api/servicios.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch($action) {
        case 'list':
            listServicios();
            break;
        case 'get':
            getServicio();
            break;
        case 'disponibles':
            getServiciosDisponibles();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("SERVICIOS.PHP - Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function listServicios() {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT * FROM servicios 
        WHERE disponible = 1 
        ORDER BY orden ASC, nombre ASC
    ");
    
    $servicios = $stmt->fetchAll();
    
    // Decodificar características JSON
    foreach ($servicios as &$servicio) {
        if ($servicio['caracteristicas']) {
            $servicio['caracteristicas'] = json_decode($servicio['caracteristicas'], true);
        }
    }
    
    jsonResponse(['success' => true, 'servicios' => $servicios]);
}

function getServicio() {
    $id = $_GET['id'] ?? null;
    $slug = $_GET['slug'] ?? null;
    
    if (!$id && !$slug) {
        jsonResponse(['success' => false, 'message' => 'ID o slug requerido'], 400);
    }
    
    $db = getDB();
    
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM servicios WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM servicios WHERE slug = ?");
        $stmt->execute([$slug]);
    }
    
    $servicio = $stmt->fetch();
    
    if ($servicio) {
        // Decodificar características JSON
        if ($servicio['caracteristicas']) {
            $servicio['caracteristicas'] = json_decode($servicio['caracteristicas'], true);
        }
        
        jsonResponse(['success' => true, 'servicio' => $servicio]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Servicio no encontrado'], 404);
    }
}

function getServiciosDisponibles() {
    $db = getDB();
    
    $stmt = $db->query("SELECT * FROM vista_servicios_disponibles");
    $servicios = $stmt->fetchAll();
    
    // Decodificar características JSON
    foreach ($servicios as &$servicio) {
        if ($servicio['caracteristicas']) {
            $servicio['caracteristicas'] = json_decode($servicio['caracteristicas'], true);
        }
    }
    
    jsonResponse(['success' => true, 'servicios' => $servicios]);
}
?>