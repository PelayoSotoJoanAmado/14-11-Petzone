<?php
/**
 * API de Reservas - PetZone
 * Archivo: api/reservas.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $requestData['action'] ?? $_GET['action'] ?? '';

error_log("RESERVAS.PHP - Action: " . $action);
error_log("RESERVAS.PHP - Request data: " . print_r($requestData, true));

try {
    switch($action) {
        case 'crear':
            crearReserva($requestData);
            break;
        case 'list':
            listReservas();
            break;
        case 'verificar-disponibilidad':
            verificarDisponibilidad($requestData);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("RESERVAS.PHP - Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function crearReserva($data) {
    // Validar datos requeridos
    $servicio_id = (int)($data['servicio_id'] ?? 0);
    $nombre = sanitize($data['nombre'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $telefono = sanitize($data['telefono'] ?? '');
    $nombre_mascota = sanitize($data['nombre_mascota'] ?? '');
    $tipo_mascota = sanitize($data['tipo_mascota'] ?? 'perro');
    $fecha_reserva = sanitize($data['fecha_reserva'] ?? '');
    $hora_reserva = sanitize($data['hora_reserva'] ?? '');
    $notas = sanitize($data['notas'] ?? '');
    
    // Validaciones
    if ($servicio_id == 0) {
        jsonResponse(['success' => false, 'message' => 'Servicio no seleccionado'], 400);
    }
    
    if (empty($nombre) || empty($email) || empty($telefono) || empty($nombre_mascota)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    if (empty($fecha_reserva) || empty($hora_reserva)) {
        jsonResponse(['success' => false, 'message' => 'Fecha y hora requeridas'], 400);
    }
    
    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Email inválido'], 400);
    }
    
    // Validar que la fecha no sea en el pasado
    if (strtotime($fecha_reserva) < strtotime(date('Y-m-d'))) {
        jsonResponse(['success' => false, 'message' => 'No se pueden hacer reservas en el pasado'], 400);
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Verificar que el servicio existe y está disponible
        $stmt = $db->prepare("SELECT * FROM servicios WHERE id = ? AND disponible = 1");
        $stmt->execute([$servicio_id]);
        $servicio = $stmt->fetch();
        
        if (!$servicio) {
            jsonResponse(['success' => false, 'message' => 'Servicio no disponible'], 404);
        }
        
        // Verificar disponibilidad en esa fecha/hora (opcional - puedes agregar lógica de horarios)
        $stmt = $db->prepare("
            SELECT COUNT(*) as reservas_simultaneas 
            FROM reservas 
            WHERE servicio_id = ? 
            AND fecha_reserva = ? 
            AND hora_reserva = ?
            AND estado NOT IN ('cancelada')
        ");
        $stmt->execute([$servicio_id, $fecha_reserva, $hora_reserva]);
        $result = $stmt->fetch();
        
        if ($result['reservas_simultaneas'] >= 3) { // Límite de 3 reservas por horario
            jsonResponse(['success' => false, 'message' => 'Este horario ya no está disponible'], 400);
        }
        
        // Generar código de reserva único
        $codigo_reserva = 'RES-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insertar reserva
        $stmt = $db->prepare("
            INSERT INTO reservas (
                codigo_reserva, servicio_id, nombre_cliente, email_cliente, 
                telefono_cliente, nombre_mascota, tipo_mascota, fecha_reserva, 
                hora_reserva, notas, subtotal, total, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        
        $precio = $servicio['precio'];
        
        $stmt->execute([
            $codigo_reserva,
            $servicio_id,
            $nombre,
            $email,
            $telefono,
            $nombre_mascota,
            $tipo_mascota,
            $fecha_reserva,
            $hora_reserva,
            $notas,
            $precio,
            $precio
        ]);
        
        $db->commit();
        
        error_log("RESERVA CREADA - Código: " . $codigo_reserva);
        
        jsonResponse([
            'success' => true,
            'message' => 'Reserva creada exitosamente',
            'codigo_reserva' => $codigo_reserva,
            'servicio' => $servicio['nombre'],
            'fecha' => $fecha_reserva,
            'hora' => $hora_reserva,
            'total' => $precio
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("ERROR AL CREAR RESERVA: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Error al crear reserva: ' . $e->getMessage()], 500);
    }
}

function listReservas() {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT r.*, s.nombre as servicio_nombre
        FROM reservas r
        INNER JOIN servicios s ON r.servicio_id = s.id
        ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC
        LIMIT 100
    ");
    
    $reservas = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'reservas' => $reservas]);
}

function verificarDisponibilidad($data) {
    $servicio_id = (int)($data['servicio_id'] ?? 0);
    $fecha = sanitize($data['fecha'] ?? '');
    $hora = sanitize($data['hora'] ?? '');
    
    if ($servicio_id == 0 || empty($fecha) || empty($hora)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as reservas_simultaneas 
        FROM reservas 
        WHERE servicio_id = ? 
        AND fecha_reserva = ? 
        AND hora_reserva = ?
        AND estado NOT IN ('cancelada')
    ");
    $stmt->execute([$servicio_id, $fecha, $hora]);
    $result = $stmt->fetch();
    
    $disponible = $result['reservas_simultaneas'] < 3;
    
    jsonResponse([
        'success' => true,
        'disponible' => $disponible,
        'reservas_actuales' => (int)$result['reservas_simultaneas'],
        'limite' => 3
    ]);
}
?>