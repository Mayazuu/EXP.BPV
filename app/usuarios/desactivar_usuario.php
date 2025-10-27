<?php
include('../session_config.php');
include('../conexion.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $razon = trim($_POST['razon']);
    $id_logueado = $_SESSION['id_usuario'];

    // Validaciones
    if (!$id || empty($razon)) {
        header("Location: index.php?mensaje=" . urlencode("Datos incompletos") . "&tipo=error");
        exit();
    }

    if (strlen($razon) < 10) {
        header("Location: index.php?mensaje=" . urlencode("La razón debe tener al menos 10 caracteres") . "&tipo=error");
        exit();
    }

    if ($id == $id_logueado) {
        header("Location: index.php?mensaje=" . urlencode("No puede desactivar su propio usuario") . "&tipo=error");
        exit();
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            SELECT u.*, r.rol, e.estado
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            INNER JOIN estados e ON u.id_estado = e.id_estado
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new Exception("Usuario no encontrado");
        }

        if ($usuario['estado'] === 'Inactivo') {
            throw new Exception("El usuario ya está desactivado");
        }

        $stmt_update = $conn->prepare("UPDATE usuarios SET id_estado = 2 WHERE id_usuario = ?");
        $stmt_update->execute([$id]);

        // Registrar en transacciones
        $ip = $_SERVER['REMOTE_ADDR'];
        $descripcion = "Desactivó usuario ID: $id - Nombre: {$usuario['nombre']} {$usuario['apellido']}, Usuario: {$usuario['usuario']}, Rol: {$usuario['rol']}, Razón: $razon";

        $stmt = $conn->prepare("
            INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$id_logueado, 'usuarios', $id, $descripcion, $ip]);

        $conn->commit();

        header("Location: index.php?mensaje=" . urlencode("Usuario desactivado exitosamente") . "&tipo=success");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: index.php?mensaje=" . urlencode("Error: " . $e->getMessage()) . "&tipo=error");
        exit();
    }
} else {
    header("Location: index.php?mensaje=" . urlencode("Método no permitido") . "&tipo=error");
    exit();
}
?>