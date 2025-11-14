<?php
/**
 * API de Autenticación - PetZone
 */

// ⚠️ IMPORTANTE: Configurar PHP para evitar salidas HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Iniciar output buffering
ob_start();

require_once __DIR__ . '/../config/database.php';

// Limpiar cualquier salida previa
ob_end_clean();

// Establecer headers ANTES de cualquier salida
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Iniciar sesión solo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ⚠️ CORRECCIÓN: Obtener datos del JSON body
$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $requestData['action'] ?? $_POST['action'] ?? '';

// Log para debug (comentar en producción)
error_log("AUTH.PHP - Action: " . $action);
error_log("AUTH.PHP - Request data: " . print_r($requestData, true));

try {
    switch($action) {
        case 'login':
            login($requestData);
            break;
        case 'logout':
            logout();
            break;
        case 'check':
            checkSession();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida: ' . $action], 400);
    }
} catch (Exception $e) {
    error_log("AUTH.PHP - Exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function login($requestData) {
    $username = sanitize($requestData['username'] ?? '');
    $password = $requestData['password'] ?? '';
    
    error_log("LOGIN - Username: " . $username);
    
    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Usuario y contraseña requeridos'], 400);
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("LOGIN - Usuario no encontrado: " . $username);
            jsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 401);
        }
        
        error_log("LOGIN - Usuario encontrado, verificando password...");
        
        if (password_verify($password, $user['password'])) {
            error_log("LOGIN - Password correcto");
            
            // Actualizar último acceso
            $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Guardar en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['rol'] = $user['rol'];
            
            // Registrar actividad
            registrarActividad($db, $user['id'], 'Inicio de sesión', 'Autenticación', 'Login exitoso');
            
            unset($user['password']); // No enviar password
            
            jsonResponse([
                'success' => true,
                'message' => 'Login exitoso',
                'user' => $user
            ]);
        } else {
            error_log("LOGIN - Password incorrecto");
            jsonResponse(['success' => false, 'message' => 'Contraseña incorrecta'], 401);
        }
    } catch (Exception $e) {
        error_log("LOGIN - Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Error en el servidor'], 500);
    }
}

function logout() {
    if (isset($_SESSION['user_id'])) {
        $db = getDB();
        registrarActividad($db, $_SESSION['user_id'], 'Cierre de sesión', 'Autenticación', 'Logout');
    }
    
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Sesión cerrada']);
}

function checkSession() {
    if (isset($_SESSION['user_id'])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, nombre_completo, email, rol FROM usuarios WHERE id = ? AND activo = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                jsonResponse([
                    'authenticated' => true,
                    'user' => $user
                ]);
            }
        } catch (Exception $e) {
            error_log("CHECK_SESSION - Error: " . $e->getMessage());
        }
    }
    
    jsonResponse(['authenticated' => false]);
}

function registrarActividad($db, $userId, $accion, $modulo, $detalle = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO actividad_admin (usuario_id, accion, modulo, detalle, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $accion,
            $modulo,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}