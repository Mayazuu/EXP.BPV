<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Mensaje de timeout
$mensaje_timeout = '';
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $mensaje_timeout = $_GET['mensaje'] ?? 'Su sesión ha expirado por inactividad.';
}

// Mensaje de logout exitoso
$mensaje_logout = '';
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $mensaje_logout = 'Ha cerrado sesión correctamente.';
}

// Mensaje de error general
$error = $_GET['error'] ?? '';
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bufete Popular</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="styleInicio.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="body_login">

<div class="login-wrapper">
    <div class="login">
        <div class="logobufete">
            <img src="../img/logo.png" alt="Logo Bufete Popular">
        </div>
        <h2>Acceso al Sistema</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_timeout): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i>
                <?= htmlspecialchars($mensaje_timeout) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_logout): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($mensaje_logout) ?>
            </div>
        <?php endif; ?>

        <form action="validar.php" method="POST">
            <label for="usuario">Usuario</label>
            <input type="text" name="usuario" id="usuario" required autofocus>
            
            <label for="contrasena">Contraseña</label>
            <input type="password" name="contrasena" id="contrasena" required>
            
            <button type="submit">Ingresar</button>
        </form>

    </div>
</div>

<script>
    // Auto-cerrar mensajes después de 5 segundos
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
</script>

</body>
</html>








