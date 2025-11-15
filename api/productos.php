<?php
/**
 * API de Productos - PetZone
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
            listProductos();
            break;
        case 'get':
            getProducto();
            break;
        case 'create':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            createProducto();
            break;
        case 'update':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            updateProducto();
            break;
        case 'delete':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            deleteProducto();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("PRODUCTOS.PHP - Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function listProductos() {
    $categoria = $_GET['categoria'] ?? '';
    $busqueda = $_GET['busqueda'] ?? '';
    
    $db = getDB();
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
            FROM productos p 
            INNER JOIN categorias c ON p.categoria_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($categoria)) {
        $sql .= " AND p.categoria_id = ?";
        $params[] = $categoria;
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $params[] = "%{$busqueda}%";
        $params[] = "%{$busqueda}%";
    }
    
    $sql .= " ORDER BY p.destacado DESC, p.id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'productos' => $productos]);
}

function getProducto() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    if ($producto) {
        jsonResponse(['success' => true, 'producto' => $producto]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Producto no encontrado'], 404);
    }
}

function createProducto() {
    $nombre = sanitize($_POST['nombre'] ?? '');
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $precio = (float)($_POST['precio'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $codigo_sku = sanitize($_POST['codigo_sku'] ?? '');
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    
    if (empty($nombre) || $categoria_id == 0 || $precio <= 0) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    // Procesar imagen
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen = uploadImagen($_FILES['imagen'], 'productos');
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO productos (nombre, descripcion, categoria_id, precio, stock, imagen, codigo_sku, destacado, activo) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $result = $stmt->execute([$nombre, $descripcion, $categoria_id, $precio, $stock, $imagen, $codigo_sku, $destacado]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Producto creado', 'Productos', "Producto: {$nombre}");
        jsonResponse(['success' => true, 'message' => 'Producto creado exitosamente', 'id' => $db->lastInsertId()]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al crear producto'], 500);
    }
}

function updateProducto() {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $nombre = sanitize($_POST['nombre'] ?? '');
    $descripcion = sanitize($_POST['descripcion'] ?? '');
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $precio = (float)($_POST['precio'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $codigo_sku = sanitize($_POST['codigo_sku'] ?? '');
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    
    $db = getDB();
    
    // Obtener imagen actual
    $stmt = $db->prepare("SELECT imagen FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $productoActual = $stmt->fetch();
    $imagen = $productoActual['imagen'];
    
    // Procesar nueva imagen si se subió
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        if ($imagen && file_exists("../{$imagen}")) {
            @unlink("../{$imagen}");
        }
        $imagen = uploadImagen($_FILES['imagen'], 'productos');
    }
    
    $stmt = $db->prepare("
        UPDATE productos 
        SET nombre = ?, descripcion = ?, categoria_id = ?, precio = ?, 
            stock = ?, imagen = ?, codigo_sku = ?, destacado = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$nombre, $descripcion, $categoria_id, $precio, $stock, $imagen, $codigo_sku, $destacado, $id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Producto actualizado', 'Productos', "ID: {$id}");
        jsonResponse(['success' => true, 'message' => 'Producto actualizado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al actualizar producto'], 500);
    }
}

function deleteProducto() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $db = getDB();
    
    // Obtener datos del producto
    $stmt = $db->prepare("SELECT imagen, nombre FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        jsonResponse(['success' => false, 'message' => 'Producto no encontrado'], 404);
    }
    
    // Eliminar imagen
    if ($producto['imagen'] && file_exists("../{$producto['imagen']}")) {
        @unlink("../{$producto['imagen']}");
    }
    
    // Eliminar producto
    $stmt = $db->prepare("DELETE FROM productos WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Producto eliminado', 'Productos', "Producto: {$producto['nombre']}");
        jsonResponse(['success' => true, 'message' => 'Producto eliminado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al eliminar producto'], 500);
    }
}

function uploadImagen($file, $carpeta) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande (máx 5MB)');
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