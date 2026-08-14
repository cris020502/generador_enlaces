<?php
// conexion.php - Configuración central

// 1. Establecer la zona horaria oficial de México
date_default_timezone_set('America/Mexico_City');

$servername = "localhost:3306";
$username   = "root";
$password   = ""; // Tu contraseña de MySQL
$dbname     = "bd_gestor_documental";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Sincronizar también la zona horaria de la sesión MySQL
    $conn->exec("SET time_zone = '-06:00'");
} catch(PDOException $e) {
    die("Error de conexión con la base de datos: " . $e->getMessage());
}
?>