<?php
/**
 * Configuración de Base de Datos - PetZone
 * Conexión a MySQL usando PDO
 */

// ⚠️ IMPORTANTE: Configurar PHP para evitar salidas HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

class Database {
    /*private $host = 'localhost';
    private $db_name = 'petzone_db';
    private $username = 'root';  // Cambiar según tu configuración
    private $password = '';      // Cambiar según tu configuración
    private $conn;*/
    
    private $host = 'db-us.supercores.host:3306';
    private $db_name = 's2941_Petzone';
    private $username = 'u2941_3ckbdFSUsl';  // Cambiar según tu configuración
    private $password = 'S6L^xcK1QWOv2=NP2+YR5+60';      // Cambiar según tu configuración
    private $conn;

    
    /**
     * Obtener conexión a la base de datos
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    #PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                )
            );
        } catch(PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]);
            exit;
        }
        
        return $this->conn;
    }
    
    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

/**
 * Función helper para obtener conexión rápida
 */
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Función para responder JSON
 */
function jsonResponse($data, $statusCode = 200) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Función para sanitizar datos
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generar o obtener session_id para carrito
 */
function getCartSessionId() {
    // Iniciar sesión solo si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['cart_session_id'])) {
        $_SESSION['cart_session_id'] = session_id();
    }
    return $_SESSION['cart_session_id'];
}