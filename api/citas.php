<?php
/**
 * API de Citas - PetZone
 * Maneja las solicitudes de citas del formulario de reserva
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/database.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
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
            // Solo admin puede listar
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            listCitas();
            break;
            
        case 'get':
            // Solo admin puede ver detalle
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            getCita();
            break;
            
        case 'create':
            // Público - crear cita desde formulario
            createCita();
            break;
            
        case 'update':
            // Solo admin puede actualizar
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            updateCita();
            break;
            
        case 'delete':
            // Solo admin puede eliminar
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            deleteCita();
            break;
            
        case 'update_estado':
            // Solo admin puede cambiar estado
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            updateEstado();
            break;
            
        case 'stats':
            // Estadísticas para dashboard
            if (!isset($_SESSION['user_id'])) {
                jsonResponse(['success' => false, 'message' => 'No autenticado'], 401);
            }
            getStats();
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log("CITAS.PHP - Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
}

/**
 * Listar todas las citas (Admin)
 */
function listCitas() {
    $db = getDB();
    
    $filtro = $_GET['filtro'] ?? 'todas';
    $busqueda = $_GET['busqueda'] ?? '';
    $limite = (int)($_GET['limite'] ?? 50);
    $pagina = (int)($_GET['pagina'] ?? 1);
    $offset = ($pagina - 1) * $limite;
    
    $query = "SELECT * FROM vista_citas WHERE 1=1";
    $params = [];
    
    // Filtro por estado
    if ($filtro !== 'todas') {
        $query .= " AND estado = ?";
        $params[] = $filtro;
    }
    
    // Búsqueda
    if (!empty($busqueda)) {
        $query .= " AND (nombre LIKE ? OR correo LIKE ? OR telefono LIKE ? OR codigo_cita LIKE ?)";
        $busquedaParam = "%{$busqueda}%";
        $params[] = $busquedaParam;
        $params[] = $busquedaParam;
        $params[] = $busquedaParam;
        $params[] = $busquedaParam;
    }
    
    // Contar total
    $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM citas WHERE 1=1" . 
        ($filtro !== 'todas' ? " AND estado = ?" : "") .
        (!empty($busqueda) ? " AND (nombre LIKE ? OR correo LIKE ? OR telefono LIKE ? OR codigo_cita LIKE ?)" : "")
    );
    $stmtCount->execute($params);
    $total = $stmtCount->fetch()['total'];
    
    // Obtener citas
    $query .= " ORDER BY fecha_solicitud DESC LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $citas = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true, 
        'citas' => $citas,
        'total' => $total,
        'pagina' => $pagina,
        'totalPaginas' => ceil($total / $limite)
    ]);
}

/**
 * Obtener una cita específica (Admin)
 */
function getCita() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID requerido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM citas WHERE id = ?");
    $stmt->execute([$id]);
    $cita = $stmt->fetch();
    
    if ($cita) {
        jsonResponse(['success' => true, 'cita' => $cita]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Cita no encontrada'], 404);
    }
}

/**
 * Crear nueva cita (Público - Formulario)
 */
function createCita() {
    // Obtener datos del POST o JSON
    $data = $_POST;
    if (empty($data)) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    
    $nombre = sanitize($data['nombre'] ?? '');
    $correo = sanitize($data['correo'] ?? '');
    $telefono = sanitize($data['telefono'] ?? '');
    $servicio = sanitize($data['servicio'] ?? '');
    $mensaje = sanitize($data['mensaje'] ?? '');
    
    // Validaciones
    if (empty($nombre) || empty($correo) || empty($telefono) || empty($servicio)) {
        jsonResponse(['success' => false, 'message' => 'Todos los campos son requeridos excepto el mensaje'], 400);
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Correo electrónico inválido'], 400);
    }
    
    // Generar código único
    $codigoCita = generarCodigoCita();
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO citas (codigo_cita, nombre, correo, telefono, servicio, mensaje, estado, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)
        ");
        
        $result = $stmt->execute([
            $codigoCita,
            $nombre,
            $correo,
            $telefono,
            $servicio,
            $mensaje,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        if ($result) {
            $citaId = $db->lastInsertId();
            
            // Opcional: Enviar email de confirmación
            // enviarEmailConfirmacion($correo, $nombre, $codigoCita);
            
            jsonResponse([
                'success' => true, 
                'message' => 'Cita registrada exitosamente. Te contactaremos pronto.',
                'codigo_cita' => $codigoCita,
                'id' => $citaId
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Error al registrar la cita'], 500);
        }
    } catch (Exception $e) {
        error_log("Error al crear cita: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Error al procesar la solicitud'], 500);
    }
}

/**
 * Actualizar cita (Admin)
 */
function updateCita() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_POST;
    }
    
    $id = (int)($data['id'] ?? 0);
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $nombre = sanitize($data['nombre'] ?? '');
    $correo = sanitize($data['correo'] ?? '');
    $telefono = sanitize($data['telefono'] ?? '');
    $servicio = sanitize($data['servicio'] ?? '');
    $mensaje = sanitize($data['mensaje'] ?? '');
    $estado = sanitize($data['estado'] ?? 'pendiente');
    
    $db = getDB();
    
    $stmt = $db->prepare("
        UPDATE citas 
        SET nombre = ?, correo = ?, telefono = ?, servicio = ?, mensaje = ?, estado = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$nombre, $correo, $telefono, $servicio, $mensaje, $estado, $id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Cita actualizada', 'Citas', "Cita ID: {$id} - Cliente: {$nombre}");
        jsonResponse(['success' => true, 'message' => 'Cita actualizada exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al actualizar cita'], 500);
    }
}

/**
 * Eliminar cita (Admin)
 */
function deleteCita() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT codigo_cita, nombre FROM citas WHERE id = ?");
    $stmt->execute([$id]);
    $cita = $stmt->fetch();
    
    if (!$cita) {
        jsonResponse(['success' => false, 'message' => 'Cita no encontrada'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM citas WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Cita eliminada', 'Citas', "Código: {$cita['codigo_cita']} - Cliente: {$cita['nombre']}");
        jsonResponse(['success' => true, 'message' => 'Cita eliminada exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al eliminar cita'], 500);
    }
}

/**
 * Actualizar solo el estado de la cita (Admin)
 */
function updateEstado() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $estado = sanitize($data['estado'] ?? '');
    
    $estadosValidos = ['pendiente', 'confirmada', 'completada', 'cancelada'];
    
    if ($id == 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    if (!in_array($estado, $estadosValidos)) {
        jsonResponse(['success' => false, 'message' => 'Estado inválido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE citas SET estado = ? WHERE id = ?");
    $result = $stmt->execute([$estado, $id]);
    
    if ($result) {
        registrarActividad($db, $_SESSION['user_id'], 'Estado de cita actualizado', 'Citas', "Cita ID: {$id} - Nuevo estado: {$estado}");
        jsonResponse(['success' => true, 'message' => 'Estado actualizado exitosamente']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Error al actualizar estado'], 500);
    }
}

/**
 * Obtener estadísticas de citas (Admin)
 */
function getStats() {
    $db = getDB();
    
    // Estadísticas generales
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
            SUM(CASE WHEN DATE(fecha_solicitud) = CURDATE() THEN 1 ELSE 0 END) as hoy,
            SUM(CASE WHEN WEEK(fecha_solicitud) = WEEK(CURDATE()) THEN 1 ELSE 0 END) as esta_semana,
            SUM(CASE WHEN MONTH(fecha_solicitud) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as este_mes
        FROM citas
    ");
    $stats = $stmt->fetch();
    
    // Servicios más solicitados
    $stmt = $db->query("
        SELECT servicio, COUNT(*) as total 
        FROM citas 
        GROUP BY servicio 
        ORDER BY total DESC 
        LIMIT 5
    ");
    $serviciosTop = $stmt->fetchAll();
    
    // Citas recientes
    $stmt = $db->query("
        SELECT * FROM vista_citas 
        WHERE estado = 'pendiente' 
        ORDER BY fecha_solicitud DESC 
        LIMIT 10
    ");
    $citasRecientes = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'servicios_top' => $serviciosTop,
        'citas_recientes' => $citasRecientes
    ]);
}

/**
 * Generar código único para la cita
 */
function generarCodigoCita() {
    return 'CITA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

/**
 * Registrar actividad del admin
 */
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

/**
 * Función opcional para enviar email de confirmación
 */
function enviarEmailConfirmacion($correo, $nombre, $codigoCita) {
    // Implementar según tu sistema de emails
    // Ejemplo con PHPMailer o mail() de PHP
    $asunto = "Confirmación de Cita - PetZone";
    $mensaje = "
        Hola {$nombre},
        
        Tu solicitud de cita ha sido recibida exitosamente.
        Código de cita: {$codigoCita}
        
        Nos pondremos en contacto contigo pronto para confirmar la fecha y hora.
        
        Saludos,
        Equipo PetZone
    ";
    
    // mail($correo, $asunto, $mensaje);
}
?>