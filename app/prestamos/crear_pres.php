<?php
include('../session_config.php');
include('../conexion.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?error=Acceso no válido");
    exit();
}

$mensaje = '';
$error = '';

// PROCESAR FORMULARIO
$id_estudiante = trim($_POST['id_estudiante']);
$id_expediente = trim($_POST['id_expediente']);
$fecha_entrega = $_POST['fecha_entrega'];
$fecha_estimada_dev = $_POST['fecha_estimada_dev'];

try {
    // ===== VALIDACIÓN 1: Estudiante debe existir y estar activo =====
    $stmt = $conn->prepare("SELECT e.id_estudiante, e.nombre, e.apellido, e.dpi_estudiante, e.carnetEstudiantil, est.estado
                            FROM estudiantes e
                            INNER JOIN estados est ON e.id_estado = est.id_estado
                            WHERE e.id_estudiante = ?");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        throw new Exception("El estudiante seleccionado no existe.");
    }

    if ($estudiante['estado'] !== 'Activo') {
        throw new Exception("El estudiante {$estudiante['nombre']} {$estudiante['apellido']} está INACTIVO. No se pueden registrar préstamos a estudiantes inactivos.");
    }

    // ===== VALIDACIÓN 2: Estudiante debe tener DPI =====
    if (empty($estudiante['dpi_estudiante'])) {
        throw new Exception("El estudiante {$estudiante['nombre']} {$estudiante['apellido']} no tiene DPI registrado. Es obligatorio para realizar préstamos.");
    }

        // ===== VALIDACIÓN 3: Expediente debe existir =====
        $stmt = $conn->prepare("
            SELECT e.id_expediente, e.ficha_social
            FROM expedientes e
            WHERE e.id_expediente = ?
        ");
        $stmt->execute([$id_expediente]);
        $expediente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$expediente) {
            throw new Exception("El expediente N° $id_expediente no existe.");
        }

    // ===== VALIDACIÓN 4: Expediente debe estar disponible =====
    $stmt = $conn->prepare("
        SELECT COUNT(*) as prestamos_activos
        FROM prestamos
        WHERE id_expediente = ?
        AND id_estado_prest IN (1, 2)
    ");
    $stmt->execute([$id_expediente]);
    $prestamos_activos = $stmt->fetch(PDO::FETCH_ASSOC)['prestamos_activos'];

    if ($prestamos_activos > 0) {
        throw new Exception("El expediente N° $id_expediente ya está prestado y no ha sido devuelto.");
    }

    // ===== VALIDACIÓN 5: Validar fechas =====
    if (strtotime($fecha_estimada_dev) < strtotime($fecha_entrega)) {
        throw new Exception("La fecha estimada de devolución debe ser posterior o igual a la fecha de entrega.");
    }

    // ===== REGISTRAR PRÉSTAMO =====
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO prestamos (id_expediente, id_estudiante, fecha_entrega, fecha_estimada_dev, id_estado_prest)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([$id_expediente, $id_estudiante, $fecha_entrega, $fecha_estimada_dev]);

    $id_prestamo = $conn->lastInsertId();

    // ===== REGISTRAR EN TRANSACCIONES =====
    $ip = $_SERVER['REMOTE_ADDR'];
    $descripcion = "Registro de préstamo ID: $id_prestamo - Expediente: $id_expediente ({$expediente['ficha_social']}), Estudiante: {$estudiante['nombre']} {$estudiante['apellido']} (ID: $id_estudiante, DPI: {$estudiante['dpi_estudiante']})";

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], 'prestamos', $id_prestamo, $descripcion, $ip]);

    $conn->commit();

    header("Location: index.php?mensaje=" . urlencode("Préstamo registrado exitosamente con ID: $id_prestamo"));
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>