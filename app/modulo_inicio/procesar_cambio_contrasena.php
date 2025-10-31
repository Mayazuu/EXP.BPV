<?php
session_start();
include('../conexion.php');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = $_SESSION['id_usuario'];
    $contrasena_actual = trim($_POST['contrasena_actual']);
    $contrasena_nueva = trim($_POST['contrasena_nueva']);
    $contrasena_confirmar = trim($_POST['contrasena_confirmar']);

    // Validar que las contraseñas nuevas coincidan
    if ($contrasena_nueva !== $contrasena_confirmar) {
        header("Location: cambiar_contrasena.php?error=Las contraseñas no coinciden");
        exit();
    }

    // Validar contraseña segura en el backend
    if (!validarContrasenaSegura($contrasena_nueva)) {
        header("Location: cambiar_contrasena.php?error=La contraseña no cumple con los requisitos de seguridad");
        exit();
    }

    // Obtener la contraseña actual de la base de datos
    $sql = "SELECT contrasena FROM usuarios WHERE id_usuario = :id_usuario";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_usuario', $id_usuario);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Verificar que la contraseña actual sea correcta
        if (password_verify($contrasena_actual, $usuario['contrasena'])) {

            // Hashear la nueva contraseña
            $nueva_hash = password_hash($contrasena_nueva, PASSWORD_DEFAULT);

            // Actualizar la contraseña en la base de datos
            $sql_update = "UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id_usuario";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindParam(':contrasena', $nueva_hash);
            $stmt_update->bindParam(':id_usuario', $id_usuario);

            if ($stmt_update->execute()) {


                header("Location: cambiar_contrasena.php?mensaje=Contraseña cambiada exitosamente");
                exit();
            } else {
                header("Location: cambiar_contrasena.php?error=Error al actualizar la contraseña");
                exit();
            }

        } else {
            header("Location: cambiar_contrasena.php?error=La contraseña actual es incorrecta");
            exit();
        }
    } else {
        header("Location: cambiar_contrasena.php?error=Usuario no encontrado");
        exit();
    }
}

/**
 * Valida que una contraseña cumpla con los requisitos de seguridad
 * - Mínimo 8 caracteres
 * - Al menos una letra mayúscula
 * - Al menos una letra minúscula
 * - Al menos un número
 * - Al menos un símbolo especial
 */
function validarContrasenaSegura($password) {
    // Mínimo 8 caracteres
    if (strlen($password) < 8) {
        return false;
    }

    // Al menos una letra mayúscula
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Al menos una letra minúscula
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Al menos un número
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Al menos un símbolo especial
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        return false;
    }

    return true;
}
?>