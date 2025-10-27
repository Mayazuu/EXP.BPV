<?php
include('../session_config.php');
include('../conexion.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$id_logueado = $_SESSION['id_usuario'];

if (!$id) {
    header("Location: index.php?mensaje=" . urlencode("ID de usuario inválido") . "&tipo=error");
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

    if ($usuario['estado'] === 'Activo') {
        throw new Exception("El usuario ya está activo");
    }

    $stmt_update = $conn->prepare("UPDATE usuarios SET id_estado = 1 WHERE id_usuario = ?");
    $stmt_update->execute([$id]);

    // Registrar en transacciones
    $ip = $_SERVER['REMOTE_ADDR'];
    $descripcion = "Activó usuario ID: $id - Nombre: {$usuario['nombre']} {$usuario['apellido']}, Usuario: {$usuario['usuario']}, Rol: {$usuario['rol']}";
    
    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$id_logueado, 'usuarios', $id, $descripcion, $ip]);

    $conn->commit();

    header("Location: index.php?mensaje=" . urlencode("Usuario activado exitosamente") . "&tipo=success");
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: index.php?mensaje=" . urlencode("Error: " . $e->getMessage()) . "&tipo=error");
    exit();
}
?>