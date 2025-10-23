<?php
$host = 'localhost';
$usuario = 'root';
$contrasena = '';
$base = 'bufete_popular';

try {
    $conn = new PDO("mysql:host=$host;dbname=$base;charset=utf8", $usuario, $contrasena);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>