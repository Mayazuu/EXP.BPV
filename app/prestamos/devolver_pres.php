<?php
include('../session_config.php');
include('../conexion.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$id_prestamo = $_GET['id'] ?? null;

if (!$id_prestamo) {
    header('Location: index.php?error=' . urlencode('ID de préstamo no válido'));
    exit();
}

try {
    // Verificar que el préstamo existe y no está devuelto
    $stmt = $conn->prepare("
        SELECT p.*,
            e.id_estudiante,
            e.nombre as nombre_estudiante,
            e.apellido as apellido_estudiante,
            e.dpi_estudiante,
            exp.ficha_social
        FROM prestamos p
        INNER JOIN estudiantes e ON p.id_estudiante = e.id_estudiante
        INNER JOIN expedientes exp ON p.id_expediente = exp.id_expediente
        WHERE p.id_prestamo = ?
    ");
    $stmt->execute([$id_prestamo]);
    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prestamo) {
        header('Location: index.php?error=' . urlencode('Préstamo no encontrado'));
        exit();
    }

    if ($prestamo['id_estado_prest'] == 3) {
        header('Location: index.php?error=' . urlencode('Este préstamo ya fue devuelto anteriormente'));
        exit();
    }

    // Registrar devolución
    $conn->beginTransaction();

    // Actualizar préstamo
    $stmt = $conn->prepare("
        UPDATE prestamos
        SET fecha_devolucion = NOW(),
            id_estado_prest = 3
        WHERE id_prestamo = ?
    ");
    $stmt->execute([$id_prestamo]);

    // Registrar en transacciones
    $ip = $_SERVER['REMOTE_ADDR'];
    $descripcion = "Devolución del préstamo ID: $id_prestamo - Expediente: {$prestamo['id_expediente']} ({$prestamo['ficha_social']}), Estudiante: {$prestamo['nombre_estudiante']} {$prestamo['apellido_estudiante']} (ID: {$prestamo['id_estudiante']}, DPI: {$prestamo['dpi_estudiante']})";

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], 'prestamos', $id_prestamo, $descripcion, $ip]);

    $conn->commit();

    header('Location: index.php?mensaje=' . urlencode('Devolución registrada exitosamente para el préstamo ID: ' . $id_prestamo));
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header('Location: index.php?error=' . urlencode('Error al registrar la devolución: ' . $e->getMessage()));
    exit();
}
?>