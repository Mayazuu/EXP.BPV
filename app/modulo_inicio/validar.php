<?php
include('../session_config.php');
include('../conexion.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_ingresado = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha_hora = date("Y-m-d H:i:s");

    // Consulta SQL: incluye id_rol para control de acceso
    $sql = "SELECT u.id_usuario, u.usuario, u.nombre, u.apellido, u.contrasena, u.id_rol,
                r.rol, e.estado
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            INNER JOIN estados e ON u.id_estado = e.id_estado
            WHERE u.usuario = :usuario";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $usuario_ingresado);
    $stmt->execute();
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila) {
        // Verifica la contraseña
        if (password_verify($contrasena, $fila['contrasena'])) {

            // Verifica que el usuario esté activo
            if ($fila['estado'] === 'Activo') {
                // Guardar datos en sesión
                $_SESSION['id_usuario'] = $fila['id_usuario'];
                $_SESSION['usuario'] = $fila['usuario'];
                $_SESSION['rol'] = $fila['rol'];
                $_SESSION['id_rol'] = $fila['id_rol'];
                $_SESSION['nombre'] = $fila['nombre'] . ' ' . $fila['apellido'];


                header("Location: dashboard.php");
                exit();

            } else {
                header("Location: login.php?error=Cuenta inactiva, contacte al administrador.");
                exit();
            }

        } else {
            // ═══════════════════════════════════════════════════════════
            // CONTRASEÑA INCORRECTA (usuario SÍ existe)
            // ═══════════════════════════════════════════════════════════
            try {
                $log = $conn->prepare("
                    INSERT INTO inicios_fallidos 
                    (id_usuario, usuario_ingresado, fecha_hora, ip)
                    VALUES (:id_usuario, :usuario_ingresado, :fecha_hora, :ip)
                ");
                $log->bindParam(':id_usuario', $fila['id_usuario']);
                $log->bindParam(':usuario_ingresado', $usuario_ingresado);  // ← NUEVO
                $log->bindParam(':fecha_hora', $fecha_hora);
                $log->bindParam(':ip', $ip);
                $log->execute();
            } catch (Exception $e) {
                // Ignorar error de log
            }

            header("Location: login.php?error=Contraseña incorrecta.");
            exit();
        }
    } else {
        // ═══════════════════════════════════════════════════════════
        // USUARIO NO ENCONTRADO (usuario NO existe)
        // ═══════════════════════════════════════════════════════════
        try {
            $log = $conn->prepare("
                INSERT INTO inicios_fallidos 
                (id_usuario, usuario_ingresado, fecha_hora, ip)
                VALUES (NULL, :usuario_ingresado, :fecha_hora, :ip)
            ");
            $log->bindParam(':usuario_ingresado', $usuario_ingresado);  
            $log->bindParam(':fecha_hora', $fecha_hora);
            $log->bindParam(':ip', $ip);
            $log->execute();
        } catch (Exception $e) {
            // Ignorar error de log
        }

        header("Location: login.php?error=Usuario no encontrado.");
        exit();
    }
}
?>