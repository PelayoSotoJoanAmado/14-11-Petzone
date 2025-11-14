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

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    switch($action) {
        case 'get':
            getContenido();
            break;
        case 'update':
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            updateContenido();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function getContenido() {
    $seccion = $_GET['seccion'] ?? '';
    
    $db = getDB();
    $sql = "SELECT * FROM contenido_general WHERE 1=1";
    $params = [];
    
    if (!empty($seccion)) {
        $sql .= " AND seccion = ?";
        $params[] = $seccion;
    }
    
    $sql .= " ORDER BY seccion, clave";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $contenidos = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'contenidos' => $contenidos]);
}

function updateContenido() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $seccion = sanitize($data['seccion'] ?? '');
    $contenidos = $data['contenidos'] ?? [];
    
    if (empty($seccion) || empty($contenidos)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        foreach ($contenidos as $clave => $valor) {
            $stmt = $db->prepare("
                UPDATE contenido_general 
                SET valor = ? 
                WHERE seccion = ? AND clave = ? AND editable = 1
            ");
            $stmt->execute([sanitize($valor), $seccion, $clave]);
        }
        
        $db->commit();
        
        registrarActividad($db, $_SESSION['user_id'], 'Contenido actualizado', 'Contenido', "Sección: {$seccion}");
        
        jsonResponse(['success' => true, 'message' => 'Contenido actualizado exitosamente']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Error al actualizar contenido'], 500);
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
?>

<?php
/**
 * API de Pedidos - PetZone
 * Archivo: api/pedidos.php
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
}

$action = $_GET['action'] ?? 'list';

try {
    switch($action) {
        case 'list':
            listPedidos();
            break;
        case 'get':
            getPedido();
            break;
        case 'update-estado':
            updateEstado();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function listPedidos() {
    $estado = $_GET['estado'] ?? '';
    $fecha_desde = $_GET['fecha_desde'] ?? '';
    $fecha_hasta = $_GET['fecha_hasta'] ?? '';
    
    $db = getDB();
    $sql = "SELECT * FROM pedidos WHERE 1=1";
    $params = [];
    
    if (!empty($estado)) {
        $sql .= " AND estado = ?";
        $params[] = $estado;
    }
    
    if (!empty($fecha_desde)) {
        $sql .= " AND DATE(fecha_pedido) >= ?";
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $sql .= " AND DATE(fecha_pedido) <= ?";
        $params[] = $fecha_hasta;
    }
    
    $sql .= " ORDER BY fecha_pedido DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'pedidos' => $pedidos]);
}

function getPedido() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
    }
    
    $db = getDB();
    
    // Obtener pedido
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        jsonResponse(['success' => false, 'message' => 'Pedido no encontrado'], 404);
    }
    
    // Obtener detalles del pedido
    $stmt = $db->prepare("SELECT * FROM detalle_pedidos WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll();
    
    $pedido['detalles'] = $detalles;
    
    jsonResponse(['success' => true, 'pedido' => $pedido]);
}

function updateEstado() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = (int)($data['id'] ?? 0);
    $estado = sanitize($data['estado'] ?? '');
    
    if ($id == 0 || empty($estado)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    $estados_validos = ['pendiente', 'procesando', 'enviado', 'entregado', 'cancelado'];
    if (!in_array($estado, $estados_validos)) {
        jsonResponse(['success' => false, 'message' => 'Estado inválido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $result = $stmt->execute([$estado, $id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Estado de pedido actualizado', 'Pedidos', "Pedido ID: {$id} - Estado: {$estado}");
        jsonResponse(['success' => true, 'message' => 'Estado actualizado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al actualizar estado'], 500);
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
?>