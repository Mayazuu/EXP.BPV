<?php
// renovar_sesion.php
session_start();

if (isset($_SESSION['id_usuario'])) {
    $_SESSION['ultima_actividad'] = time();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'mensaje' => 'Sesión renovada',
        'timestamp' => time()
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'mensaje' => 'No hay sesión activa'
    ]);
}
?>