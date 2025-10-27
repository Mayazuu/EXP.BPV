<?php
// logout.php
include('../session_config.php');

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir con RUTA ABSOLUTA (consistente con session_config.php)
header("Location: /bufete/app/modulo_inicio/login.php?logout=1");
exit();
?>