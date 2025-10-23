<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol - SOLO DIRECTORA puede eliminar
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Directora') {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=ID de préstamo inválido");
    exit();
}

$id_prestamo = (int)$_GET['id'];

try {
    // Verificar que el préstamo existe y está devuelto (estado 3)
    $stmt = $conn->prepare("
        SELECT
            p.id_prestamo,
            p.id_expediente,
            p.id_estudiante,
            p.id_estado_prest,
            e.nombre as nombre_estudiante,
            e.apellido as apellido_estudiante,
            exp.ficha_social
        FROM prestamos p
        INNER JOIN estudiantes e ON p.id_estudiante = e.id_estudiante
        LEFT JOIN expedientes exp ON p.id_expediente = exp.id_expediente
        WHERE p.id_prestamo = ?
    ");
    $stmt->execute([$id_prestamo]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prestamo) {
        header("Location: index.php?error=Préstamo no encontrado");
        exit();
    }

    // Verificar que el préstamo esté devuelto
    if ($prestamo['id_estado_prest'] != 3) {
        header("Location: index.php?error=Solo se pueden eliminar préstamos devueltos");
        exit();
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Capturar IP del usuario
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Registrar la acción en transacciones
    $descripcion = "Eliminó el préstamo #{$id_prestamo} del estudiante {$prestamo['nombre_estudiante']} {$prestamo['apellido_estudiante']} (Expediente: {$prestamo['id_expediente']})";

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'prestamos', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id_prestamo, $descripcion, $ip]);

    // Eliminar el préstamo
    $stmt = $conn->prepare("DELETE FROM prestamos WHERE id_prestamo = ?");
    $stmt->execute([$id_prestamo]);

    // Confirmar transacción
    $conn->commit();

    header("Location: index.php?mensaje=Préstamo eliminado exitosamente");
    exit();

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Error al eliminar préstamo: " . $e->getMessage());
    header("Location: index.php?error=Error al eliminar el préstamo: " . $e->getMessage());
    exit();
}
?>