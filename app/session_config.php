<?php
// session_config.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);
    session_start();
}

define('TIEMPO_INACTIVIDAD', 1800); // 30 minutos

// Función para obtener la ruta correcta del login
function obtener_ruta_login() {
    // Obtener la ruta del script actual
    $script_path = $_SERVER['SCRIPT_NAME'];

    // Contar cuántos niveles de profundidad hay
    $niveles = substr_count(dirname($script_path), '/') - substr_count($_SERVER['DOCUMENT_ROOT'], '/');

    // Si estamos en la raíz o en modulo_inicio
    if ($niveles <= 1 || strpos($script_path, '/modulo_inicio/') !== false) {
        return '/bufete/app/modulo_inicio/login.php';
    }

    // Para cualquier otro módulo, construir ruta relativa
    $prefijo = str_repeat('../', $niveles - 1);
    return $prefijo . 'bufete/app/modulo_inicio/login.php';
}

if (isset($_SESSION['id_usuario'])) {
    if (isset($_SESSION['ultima_actividad'])) {
        $tiempo_transcurrido = time() - $_SESSION['ultima_actividad'];

        if ($tiempo_transcurrido > TIEMPO_INACTIVIDAD) {
            $mensaje_timeout = "Su sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.";

            // Limpiar sesión
            session_unset();
            session_destroy();

            // Redirigir con ruta absoluta
            header("Location: /bufete/app/modulo_inicio/login.php?timeout=1&mensaje=" . urlencode($mensaje_timeout));
            exit();
        }
    }

    // Actualizar última actividad
    $_SESSION['ultima_actividad'] = time();
}

// Regenerar ID de sesión periódicamente (seguridad)
if (isset($_SESSION['id_usuario'])) {
    if (!isset($_SESSION['creada'])) {
        $_SESSION['creada'] = time();
    } else if (time() - $_SESSION['creada'] > 300) { // Cada 5 minutos
        session_regenerate_id(true);
        $_SESSION['creada'] = time();
    }
}
?>