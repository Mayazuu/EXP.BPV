<?php
// session_config.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);
    session_start();
}

define('TIEMPO_INACTIVIDAD', 1800); // 30 minutos

// Función para obtener la ruta correcta del login según ubicación
function obtener_ruta_login() {
    $ruta_actual = $_SERVER['PHP_SELF'];
    
    // Si estamos en modulo_inicio, la ruta es local
    if (strpos($ruta_actual, '/modulo_inicio/') !== false) {
        return 'login.php';
    } 
    // Si estamos en otro módulo (secretaria, reportes, etc)
    else {
        return '../modulo_inicio/login.php';
    }
}

if (isset($_SESSION['id_usuario'])) {
    if (isset($_SESSION['ultima_actividad'])) {
        $tiempo_transcurrido = time() - $_SESSION['ultima_actividad'];
        
        if ($tiempo_transcurrido > TIEMPO_INACTIVIDAD) {
            $mensaje_timeout = "Su sesión ha expirado por inactividad. Por favor, inicie sesión nuevamente.";
            
            // Limpiar sesión
            session_unset();
            session_destroy();
            
            // Obtener ruta correcta del login
            $ruta_login = obtener_ruta_login();
            
            // Redirigir con mensaje
            header("Location: " . $ruta_login . "?timeout=1&mensaje=" . urlencode($mensaje_timeout));
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