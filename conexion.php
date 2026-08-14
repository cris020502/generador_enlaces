<?php
// conexion.php - Configuración central de la Base de Datos
$servername = "localhost:3306";
$username   = "root";
$password   = ""; // Tu contraseña de MySQL
$dbname     = "bd_gestor_documental";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión con la base de datos: " . $e->getMessage());
}
?>