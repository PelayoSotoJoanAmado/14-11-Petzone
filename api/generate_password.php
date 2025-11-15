<?php
/**
 * Generador de contraseñas para PetZone
 * Ejecuta este archivo SOLO UNA VEZ y luego ELIMÍNALO por seguridad
 */

// Tu contraseña deseada
$password = 'admin123'; // Cambia esto

// Generar hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Generador de Contraseñas - PetZone</h2>";
echo "<p><strong>Contraseña:</strong> {$password}</p>";
echo "<p><strong>Hash:</strong> {$hash}</p>";
echo "<hr>";
echo "<h3>SQL para insertar en phpMyAdmin:</h3>";
echo "<textarea style='width:100%; height:200px; font-family:monospace;'>";
echo "INSERT INTO usuarios (username, password, nombre_completo, email, rol, activo, fecha_creacion) \n";
echo "VALUES (\n";
echo "    'admin',\n";
echo "    '{$hash}',\n";
echo "    'Administrador Principal',\n";
echo "    'admin@petzone.com',\n";
echo "    'admin',\n";
echo "    1,\n";
echo "    NOW()\n";
echo ");";
echo "</textarea>";
echo "<hr>";
echo "<p style='color:red;'><strong>⚠️ IMPORTANTE: ELIMINA ESTE ARCHIVO después de usarlo</strong></p>";
?>