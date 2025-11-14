<?php
/**
 * API de Anuncios - PetZone
 * Archivo: api/anuncios.php
 * 🔥 VERSIÓN CORREGIDA - Problema del checkbox resuelto
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

ob_start();

require_once __DIR__ . '/../config/database.php';

// Limpiar cualquier salida previa
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// Permitir 'activos' sin autenticación
if (!isset($_SESSION['user_id']) && !in_array($_GET['action'] ?? '', ['activos'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
}

$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $requestData['action'] ?? '';

// Log para debug
error_log("ANUNCIOS.PHP - Action: " . $action);
error_log("ANUNCIOS.PHP - Request data: " . print_r($requestData, true));

try {
    switch($action) {
        case 'list':
            listAnuncios();
            break;
        case 'get':
            getAnuncio();
            break;
        case 'create':
            createAnuncio($requestData);
            break;
        case 'update':
            updateAnuncio($requestData);
            break;
        case 'delete':
            deleteAnuncio($requestData);
            break;
        case 'activos':
            getAnunciosActivos();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida: ' . $action], 400);
    }
} catch (Exception $e) {
    error_log("ANUNCIOS.PHP - Exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function listAnuncios() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM anuncios ORDER BY prioridad DESC, id DESC");
    $stmt->execute();
    $anuncios = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'anuncios' => $anuncios]);
}

function getAnuncio() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM anuncios WHERE id = ?");
    $stmt->execute([$id]);
    $anuncio = $stmt->fetch();
    
    if ($anuncio) {
        // 🔥 ASEGURAR QUE 'activo' sea un entero
        $anuncio['activo'] = (int)$anuncio['activo'];
        
        jsonResponse(['success' => true, 'anuncio' => $anuncio]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Anuncio no encontrado'], 404);
    }
}

function createAnuncio($data) {
    $mensaje = sanitize($data['mensaje'] ?? '');
    $tipo = sanitize($data['tipo'] ?? 'aviso_general');
    $color_fondo = sanitize($data['color_fondo'] ?? '#23906F');
    $color_texto = sanitize($data['color_texto'] ?? '#FFFFFF');
    $icono = sanitize($data['icono'] ?? '');
    $velocidad = (int)($data['velocidad'] ?? 30);
    $prioridad = (int)($data['prioridad'] ?? 0);
    
    // 🔥 CORRECCIÓN CRÍTICA: Convertir explícitamente a entero
    $activo = isset($data['activo']) ? (int)$data['activo'] : 1;
    
    error_log("CREATE - activo recibido: " . var_export($data['activo'], true));
    error_log("CREATE - activo convertido: " . $activo);
    
    if (empty($mensaje)) {
        jsonResponse(['success' => false, 'message' => 'Mensaje requerido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO anuncios 
        (mensaje, tipo, color_fondo, color_texto, icono, velocidad, prioridad, activo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $mensaje, 
        $tipo, 
        $color_fondo, 
        $color_texto, 
        $icono, 
        $velocidad, 
        $prioridad, 
        $activo
    ]);
    
    if ($result) {
        $nuevoId = $db->lastInsertId();
        registrarActividad($db, $_SESSION['user_id'], 'Anuncio creado', 'Anuncios', "ID: {$nuevoId} - " . substr($mensaje, 0, 50));
        
        jsonResponse([
            'success' => true, 
            'message' => 'Anuncio creado exitosamente',
            'id' => $nuevoId
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al crear anuncio'], 500);
    }
}

function updateAnuncio($data) {
    $id = (int)($data['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $mensaje = sanitize($data['mensaje'] ?? '');
    $tipo = sanitize($data['tipo'] ?? 'aviso_general');
    $color_fondo = sanitize($data['color_fondo'] ?? '#23906F');
    $color_texto = sanitize($data['color_texto'] ?? '#FFFFFF');
    $icono = sanitize($data['icono'] ?? '');
    $velocidad = (int)($data['velocidad'] ?? 30);
    $prioridad = (int)($data['prioridad'] ?? 0);
    
    // 🔥 CORRECCIÓN CRÍTICA: Manejar correctamente el valor booleano/entero
    // Verificar si la clave existe y convertir a entero
    $activo = isset($data['activo']) ? (int)$data['activo'] : 0;
    
    // Log detallado para debug
    error_log("UPDATE - ID: " . $id);
    error_log("UPDATE - activo recibido (raw): " . var_export($data['activo'], true));
    error_log("UPDATE - activo tipo: " . gettype($data['activo']));
    error_log("UPDATE - activo convertido: " . $activo);
    
    if (empty($mensaje)) {
        jsonResponse(['success' => false, 'message' => 'Mensaje requerido'], 400);
    }
    
    $db = getDB();
    
    // 🔥 VERIFICAR ESTADO ACTUAL ANTES DE ACTUALIZAR
    $stmtCheck = $db->prepare("SELECT activo FROM anuncios WHERE id = ?");
    $stmtCheck->execute([$id]);
    $estadoActual = $stmtCheck->fetch();
    error_log("UPDATE - Estado actual en BD: " . var_export($estadoActual['activo'], true));
    
    $stmt = $db->prepare("
        UPDATE anuncios 
        SET mensaje = ?, 
            tipo = ?, 
            color_fondo = ?, 
            color_texto = ?, 
            icono = ?, 
            velocidad = ?, 
            prioridad = ?, 
            activo = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $mensaje, 
        $tipo, 
        $color_fondo, 
        $color_texto, 
        $icono, 
        $velocidad, 
        $prioridad, 
        $activo,
        $id
    ]);
    
    // 🔥 VERIFICAR QUE SE ACTUALIZÓ CORRECTAMENTE
    if ($result) {
        $stmtVerify = $db->prepare("SELECT activo FROM anuncios WHERE id = ?");
        $stmtVerify->execute([$id]);
        $nuevoEstado = $stmtVerify->fetch();
        error_log("UPDATE - Nuevo estado en BD: " . var_export($nuevoEstado['activo'], true));
        
        registrarActividad($db, $_SESSION['user_id'], 'Anuncio actualizado', 'Anuncios', "ID: {$id} - Activo: {$activo}");
        
        jsonResponse([
            'success' => true, 
            'message' => 'Anuncio actualizado exitosamente',
            'debug' => [
                'id' => $id,
                'activo_enviado' => $activo,
                'activo_guardado' => (int)$nuevoEstado['activo']
            ]
        ]);
    } else {
        error_log("UPDATE - Error en execute: " . print_r($stmt->errorInfo(), true));
        jsonResponse(['success' => false, 'message' => 'Error al actualizar anuncio'], 500);
    }
}

function deleteAnuncio($data) {
    $id = (int)($data['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM anuncios WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Anuncio eliminado', 'Anuncios', "ID: {$id}");
        jsonResponse(['success' => true, 'message' => 'Anuncio eliminado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al eliminar anuncio'], 500);
    }
}

function getAnunciosActivos() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM anuncios 
        WHERE activo = 1 
        AND (fecha_inicio IS NULL OR fecha_inicio <= NOW())
        AND (fecha_fin IS NULL OR fecha_fin >= NOW())
        ORDER BY prioridad DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $anuncios = $stmt->fetchAll();
    
    // Incrementar visualizaciones
    foreach ($anuncios as $anuncio) {
        $updateStmt = $db->prepare("UPDATE anuncios SET visualizaciones = visualizaciones + 1 WHERE id = ?");
        $updateStmt->execute([$anuncio['id']]);
    }
    
    jsonResponse(['success' => true, 'anuncios' => $anuncios]);
}

function registrarActividad($db, $userId, $accion, $modulo, $detalle = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO actividad_admin (usuario_id, accion, modulo, detalle, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $accion, $modulo, $detalle, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}
?>