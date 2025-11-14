<?php
/**
 * API de Pedidos - PetZone
 * Archivo: api/pedidos.php
 * VERSIÓN CORREGIDA
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

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
}

$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $requestData['action'] ?? 'list';

// LOG para debug
error_log("PEDIDOS.PHP - Action: " . $action);
error_log("PEDIDOS.PHP - Request data: " . print_r($requestData, true));

try {
    switch($action) {
        case 'list':
            listPedidos();
            break;
        case 'get':
            getPedido();
            break;
        case 'update-estado':
            updateEstado($requestData);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("PEDIDOS.PHP - Error: " . $e->getMessage());
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
    
    // LOG para verificar
    error_log("LIST PEDIDOS - Total encontrados: " . count($pedidos));
    
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

function updateEstado($data) {
    $id = (int)($data['id'] ?? 0);
    $estado = sanitize($data['estado'] ?? '');
    
    // LOG para debug
    error_log("UPDATE ESTADO - ID: " . $id);
    error_log("UPDATE ESTADO - Nuevo estado: " . $estado);
    
    if ($id == 0 || empty($estado)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    $estados_validos = ['pendiente', 'procesando', 'enviado', 'entregado', 'cancelado'];
    if (!in_array($estado, $estados_validos)) {
        jsonResponse(['success' => false, 'message' => 'Estado inválido'], 400);
    }
    
    $db = getDB();
    
    // VERIFICAR ESTADO ACTUAL
    $stmtCheck = $db->prepare("SELECT estado FROM pedidos WHERE id = ?");
    $stmtCheck->execute([$id]);
    $pedidoActual = $stmtCheck->fetch();
    
    if (!$pedidoActual) {
        jsonResponse(['success' => false, 'message' => 'Pedido no encontrado'], 404);
    }
    
    error_log("UPDATE ESTADO - Estado actual en BD: " . $pedidoActual['estado']);
    
    // ACTUALIZAR ESTADO
    $stmt = $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $result = $stmt->execute([$estado, $id]);
    
    // VERIFICAR QUE SE ACTUALIZÓ
    $stmtVerify = $db->prepare("SELECT estado FROM pedidos WHERE id = ?");
    $stmtVerify->execute([$id]);
    $pedidoNuevo = $stmtVerify->fetch();
    
    error_log("UPDATE ESTADO - Nuevo estado en BD: " . $pedidoNuevo['estado']);
    
    if ($result && $pedidoNuevo['estado'] === $estado) {
        registrarActividad($db, $_SESSION['user_id'], 'Estado de pedido actualizado', 'Pedidos', "Pedido ID: {$id} - De '{$pedidoActual['estado']}' a '{$estado}'");
        
        jsonResponse([
            'success' => true, 
            'message' => 'Estado actualizado exitosamente',
            'nuevo_estado' => $estado,
            'debug' => [
                'id' => $id,
                'estado_anterior' => $pedidoActual['estado'],
                'estado_nuevo' => $pedidoNuevo['estado']
            ]
        ]);
    } else {
        error_log("UPDATE ESTADO - ERROR: No se pudo actualizar");
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