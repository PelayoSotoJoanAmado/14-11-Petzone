<?php
// Evitar que se muestren errores como HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Iniciar output buffering para capturar cualquier salida inesperada
ob_start();

require_once '../config/database.php';

header('Content-Type: application/json');

$requestData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $requestData['action'] ?? $_GET['action'] ?? '';
$sessionId = getCartSessionId();

try {
    switch($action) {
        case 'add':
            addToCart($sessionId);
            break;
        case 'update':
            updateCart($sessionId);
            break;
        case 'remove':
            removeFromCart($sessionId);
            break;
        case 'get':
            getCart($sessionId);
            break;
        case 'clear':
            clearCart($sessionId);
            break;
        case 'checkout':
            processCheckout($sessionId);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function addToCart($sessionId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $productoId = (int)($data['producto_id'] ?? 0);
    $cantidad = (int)($data['cantidad'] ?? 1);
    
    if ($productoId <= 0 || $cantidad <= 0) {
        jsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
    }
    
    $db = getDB();
    
    // Verificar existencia y stock del producto
    $stmt = $db->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
    $stmt->execute([$productoId]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        jsonResponse(['success' => false, 'message' => 'Producto no encontrado'], 404);
    }
    
    if ($producto['stock'] < $cantidad) {
        jsonResponse(['success' => false, 'message' => 'Stock insuficiente'], 400);
    }
    
    // Verificar si ya existe en el carrito
    $stmt = $db->prepare("SELECT * FROM carrito WHERE session_id = ? AND producto_id = ?");
    $stmt->execute([$sessionId, $productoId]);
    $itemExistente = $stmt->fetch();
    
    if ($itemExistente) {
        // Actualizar cantidad
        $nuevaCantidad = $itemExistente['cantidad'] + $cantidad;
        
        if ($producto['stock'] < $nuevaCantidad) {
            jsonResponse(['success' => false, 'message' => 'Stock insuficiente'], 400);
        }
        
        $stmt = $db->prepare("
            UPDATE carrito 
            SET cantidad = ?, precio_unitario = ? 
            WHERE session_id = ? AND producto_id = ?
        ");
        $stmt->execute([$nuevaCantidad, $producto['precio'], $sessionId, $productoId]);
    } else {
        // Insertar nuevo item
        $stmt = $db->prepare("
            INSERT INTO carrito (session_id, producto_id, cantidad, precio_unitario) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $productoId, $cantidad, $producto['precio']]);
    }
    
    // Obtener totales actualizados
    $totales = getCartTotals($db, $sessionId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Producto agregado al carrito',
        'cart' => $totales
    ]);
}

function updateCart($sessionId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $productoId = (int)($data['producto_id'] ?? 0);
    $cantidad = (int)($data['cantidad'] ?? 1);
    
    if ($productoId <= 0 || $cantidad < 0) {
        jsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
    }
    
    $db = getDB();
    
    if ($cantidad == 0) {
        // Eliminar del carrito
        $stmt = $db->prepare("DELETE FROM carrito WHERE session_id = ? AND producto_id = ?");
        $stmt->execute([$sessionId, $productoId]);
    } else {
        // Verificar stock
        $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ?");
        $stmt->execute([$productoId]);
        $producto = $stmt->fetch();
        
        if (!$producto || $producto['stock'] < $cantidad) {
            jsonResponse(['success' => false, 'message' => 'Stock insuficiente'], 400);
        }
        
        // Actualizar cantidad
        $stmt = $db->prepare("
            UPDATE carrito 
            SET cantidad = ? 
            WHERE session_id = ? AND producto_id = ?
        ");
        $stmt->execute([$cantidad, $sessionId, $productoId]);
    }
    
    $totales = getCartTotals($db, $sessionId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Carrito actualizado',
        'cart' => $totales
    ]);
}

function removeFromCart($sessionId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $productoId = (int)($data['producto_id'] ?? 0);
    
    if ($productoId <= 0) {
        jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM carrito WHERE session_id = ? AND producto_id = ?");
    $stmt->execute([$sessionId, $productoId]);
    
    $totales = getCartTotals($db, $sessionId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Producto eliminado',
        'cart' => $totales
    ]);
}

function getCart($sessionId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT c.*, p.nombre, p.imagen, p.descripcion, p.stock
        FROM carrito c
        INNER JOIN productos p ON c.producto_id = p.id
        WHERE c.session_id = ?
        ORDER BY c.fecha_agregado DESC
    ");
    $stmt->execute([$sessionId]);
    $items = $stmt->fetchAll();
    
    // Calcular subtotal por item
    foreach ($items as &$item) {
        $item['subtotal'] = $item['cantidad'] * $item['precio_unitario'];
    }
    
    $totales = getCartTotals($db, $sessionId);
    
    jsonResponse([
        'success' => true,
        'items' => $items,
        'totales' => $totales
    ]);
}

function clearCart($sessionId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM carrito WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Carrito vaciado',
        'cart' => ['count' => 0, 'subtotal' => 0, 'total' => 0]
    ]);
}

// function processCheckout($sessionId) {
//     $data = json_decode(file_get_contents('php://input'), true);
    
//     $nombre = sanitize($data['nombre'] ?? '');
//     $email = sanitize($data['email'] ?? '');
//     $telefono = sanitize($data['telefono'] ?? '');
//     $direccion = sanitize($data['direccion'] ?? '');
//     $metodo_pago = sanitize($data['metodo_pago'] ?? '');
//     $notas = sanitize($data['notas'] ?? '');
    
//     if (empty($nombre) || empty($email) || empty($telefono) || empty($direccion) || empty($metodo_pago)) {
//         jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
//     }
    
//     $db = getDB();
//     $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");


//     // Verificar que hay items en el carrito
//     $stmt = $db->prepare("SELECT COUNT(*) as count FROM carrito WHERE session_id = ?");
//     $stmt->execute([$sessionId]);
//     $result = $stmt->fetch();
    
//     if ($result['count'] == 0) {
//         jsonResponse(['success' => false, 'message' => 'Carrito vacío'], 400);
//     }
    
//     try {
//         $db->beginTransaction();
        
//         // Llamar al stored procedure para crear pedido
//         $stmt = $db->prepare("CALL sp_crear_pedido(?, ?, ?, ?, ?, ?, ?, @codigo)");
//         $stmt->execute([
//             $sessionId,
//             $nombre,
//             $email,
//             $telefono,
//             $direccion,
//             $metodo_pago,
//             $notas
//         ]);
        
//         // Obtener código del pedido
//         $result = $db->query("SELECT @codigo as codigo")->fetch();
//         $codigoPedido = $result['codigo'];
        
//         $db->commit();
        
//         jsonResponse([
//             'success' => true,
//             'message' => 'Pedido creado exitosamente',
//             'codigo_pedido' => $codigoPedido
//         ]);
        
//     } catch (Exception $e) {
//         $db->rollBack();
//         jsonResponse(['success' => false, 'message' => 'Error al procesar pedido: ' . $e->getMessage()], 500);
//     }
// }

function processCheckout($sessionId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $nombre = sanitize($data['nombre'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $telefono = sanitize($data['telefono'] ?? '');
    $direccion = sanitize($data['direccion'] ?? '');
    $metodo_pago = sanitize($data['metodo_pago'] ?? '');
    $notas = sanitize($data['notas'] ?? '');
    
    if (empty($nombre) || empty($email) || empty($telefono) || empty($direccion) || empty($metodo_pago)) {
        jsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();

        // 1. Obtener items del carrito
        $stmt = $db->prepare("
            SELECT c.*, p.nombre, p.stock 
            FROM carrito c 
            INNER JOIN productos p ON c.producto_id = p.id 
            WHERE c.session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $carritoItems = $stmt->fetchAll();

        if (empty($carritoItems)) {
            jsonResponse(['success' => false, 'message' => 'Carrito vacío'], 400);
        }

        // 2. Verificar stock
        foreach ($carritoItems as $item) {
            if ($item['stock'] < $item['cantidad']) {
                throw new Exception("Stock insuficiente para: " . $item['nombre']);
            }
        }

        // 3. Calcular subtotal
        $subtotal = 0;
        foreach ($carritoItems as $item) {
            $subtotal += $item['cantidad'] * $item['precio_unitario'];
        }

        // 4. Crear pedido (SIN session_id)
        $codigo_pedido = 'PZ-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("
            INSERT INTO pedidos 
            (codigo_pedido, nombre_cliente, email_cliente, telefono_cliente, direccion_envio, 
             subtotal, total, metodo_pago, notas, estado) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");
        $stmt->execute([
            $codigo_pedido,
            $nombre,
            $email,
            $telefono,
            $direccion,
            $subtotal,
            $subtotal, // total = subtotal (sin descuentos/impuestos)
            $metodo_pago,
            $notas
        ]);
        
        $pedido_id = $db->lastInsertId();

        // 5. Crear items del detalle_pedidos
        $stmt = $db->prepare("
            INSERT INTO detalle_pedidos 
            (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($carritoItems as $item) {
            $subtotal_item = $item['cantidad'] * $item['precio_unitario'];
            
            $stmt->execute([
                $pedido_id,
                $item['producto_id'],
                $item['nombre'],
                $item['cantidad'],
                $item['precio_unitario'],
                $subtotal_item
            ]);

            // 6. Actualizar stock
            $updateStmt = $db->prepare("
                UPDATE productos 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$item['cantidad'], $item['producto_id']]);
        }

        // 7. Vaciar carrito
        $stmt = $db->prepare("DELETE FROM carrito WHERE session_id = ?");
        $stmt->execute([$sessionId]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'codigo_pedido' => $codigo_pedido
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Error al procesar pedido: ' . $e->getMessage()], 500);
    }
}

function getCartTotals($db, $sessionId) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(cantidad) as total_items,
            SUM(cantidad * precio_unitario) as subtotal
        FROM carrito
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $totales = $stmt->fetch();
    
    return [
        'count' => (int)$totales['count'],
        'total_items' => (int)$totales['total_items'],
        'subtotal' => (float)$totales['subtotal'],
        'total' => (float)$totales['subtotal'] // Puedes agregar cálculos de envío, impuestos, etc.
    ];
}
?>