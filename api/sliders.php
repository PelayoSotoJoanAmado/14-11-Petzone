<?php
/**
 * API de Sliders - PetZone
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/database.php';
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

$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $requestData['action'] ?? $_POST['action'] ?? 'list';

try {
    switch($action) {
        case 'list':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            listSliders();
            break;
        case 'get':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            getSlider();
            break;
        case 'create':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            createSlider();
            break;
        case 'update':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            updateSlider();
            break;
        case 'delete':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            deleteSlider();
            break;
        case 'activos':
            getSlidersActivos();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("SLIDERS.PHP - Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function listSliders() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sliders ORDER BY orden ASC, id DESC");
    $stmt->execute();
    $sliders = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'sliders' => $sliders]);
}

function getSlider() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sliders WHERE id = ?");
    $stmt->execute([$id]);
    $slider = $stmt->fetch();
    
    if ($slider) {
        jsonResponse(['success' => true, 'slider' => $slider]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Slider no encontrado'], 404);
    }
}

function createSlider() {
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    $enlace = sanitize($_POST['enlace'] ?? '');
    $posicion = sanitize($_POST['posicion'] ?? 'principal');
    $orden = (int)($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($titulo)) {
        jsonResponse(['success' => false, 'message' => 'Título requerido'], 400);
    }
    
    // Procesar imagen
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen = uploadImagen($_FILES['imagen'], 'sliders');
    } else {
        jsonResponse(['success' => false, 'message' => 'Imagen requerida'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO sliders (titulo, descripcion, imagen, enlace, posicion, orden, activo) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([$titulo, $descripcion, $imagen, $enlace, $posicion, $orden, $activo]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Slider creado', 'Sliders', "Slider: {$titulo}");
        jsonResponse(['success' => true, 'message' => 'Slider creado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al crear slider'], 500);
    }
}

function updateSlider() {
    $id = (int)($_POST['id'] ?? 0);
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    $enlace = sanitize($_POST['enlace'] ?? '');
    $posicion = sanitize($_POST['posicion'] ?? 'principal');
    $orden = (int)($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $db = getDB();
    
    // Obtener imagen actual
    $stmt = $db->prepare("SELECT imagen FROM sliders WHERE id = ?");
    $stmt->execute([$id]);
    $sliderActual = $stmt->fetch();
    $imagen = $sliderActual['imagen'];
    
    // Procesar nueva imagen si se subió
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && $_FILES['imagen']['size'] > 0) {
        // Eliminar imagen anterior solo si se sube una nueva
        if ($imagen && file_exists("../{$imagen}")) {
            @unlink("../{$imagen}");
        }
        $imagen = uploadImagen($_FILES['imagen'], 'sliders');
    }
    
    $stmt = $db->prepare("
        UPDATE sliders 
        SET titulo = ?, descripcion = ?, imagen = ?, enlace = ?, 
            posicion = ?, orden = ?, activo = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$titulo, $descripcion, $imagen, $enlace, $posicion, $orden, $activo, $id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Slider actualizado', 'Sliders', "Slider ID: {$id}");
        jsonResponse(['success' => true, 'message' => 'Slider actualizado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al actualizar slider'], 500);
    }
}

function deleteSlider() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT imagen, titulo FROM sliders WHERE id = ?");
    $stmt->execute([$id]);
    $slider = $stmt->fetch();
    
    if (!$slider) {
        jsonResponse(['success' => false, 'message' => 'Slider no encontrado'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM sliders WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        if ($slider['imagen'] && file_exists("../{$slider['imagen']}")) {
            @unlink("../{$slider['imagen']}");
        }
        
        registrarActividad($db, $_SESSION['user_id'], 'Slider eliminado', 'Sliders', "Slider: {$slider['titulo']}");
        jsonResponse(['success' => true, 'message' => 'Slider eliminado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al eliminar slider'], 500);
    }
}

function getSlidersActivos() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM sliders WHERE activo = 1 ORDER BY orden ASC LIMIT 5");
    $stmt->execute();
    $sliders = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'sliders' => $sliders]);
}

function uploadImagen($file, $carpeta) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaDestino = __DIR__ . "/../IMG/{$carpeta}/";
    
    if (!file_exists($rutaDestino)) {
        mkdir($rutaDestino, 0777, true);
    }
    
    $rutaCompleta = $rutaDestino . $nombreArchivo;
    
    if (move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
        return "IMG/{$carpeta}/{$nombreArchivo}";
    } else {
        throw new Exception('Error al subir imagen');
    }
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