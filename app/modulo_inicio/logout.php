<?php
// logout.php
include('../session_config.php');

$usuario = $_SESSION['usuario'] ?? 'Desconocido';
$id_usuario = $_SESSION['id_usuario'] ?? null;

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje de logout exitoso
header("Location: login.php?logout=1");
exit();
?>