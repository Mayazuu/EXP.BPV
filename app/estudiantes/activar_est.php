<?php
include('../session_config.php');
include('../conexion.php');

// ============================================================
// VALIDACIÓN DE PERMISOS - SOLO DIRECTORA PUEDE ACTIVAR
// ============================================================
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SESSION['rol'] !== 'Directora') {
    header("Location: index.php?mensaje=" . urlencode("❌ No tiene permisos para activar estudiantes. Solo la Directora puede realizar esta acción."));
    exit();
}

// ============================================================
// VALIDAR MÉTODO Y DATOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Location: index.php");
    exit();
}

$id_estudiante = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_estudiante <= 0) {
    header("Location: index.php?mensaje=" . urlencode("❌ ID de estudiante inválido"));
    exit();
}

try {
    $conn->beginTransaction();

    // Verificar que el estudiante existe y está inactivo
    $sql_check = "SELECT e.id_estudiante, e.nombre, e.apellido, e.id_estado, es.estado
                FROM estudiantes e
                INNER JOIN estados es ON e.id_estado = es.id_estado
                WHERE e.id_estudiante = :id";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':id' => $id_estudiante]);
    $estudiante = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        throw new Exception('Estudiante no encontrado');
    }

    if ($estudiante['id_estado'] != 2) {
        throw new Exception('El estudiante ya está activo');
    }

    // Obtener IP del usuario
    $ip_usuario = $_SERVER['REMOTE_ADDR'];

    // Registrar en transacciones
    $sql_trans = "INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
                VALUES (:id_usuario, :tabla, :id_registro, :descripcion, NOW(), :ip)";
    $stmt_trans = $conn->prepare($sql_trans);
    $stmt_trans->execute([
        ':id_usuario' => $_SESSION['id_usuario'],
        ':tabla' => 'estudiantes',
        ':id_registro' => $id_estudiante,
        ':descripcion' => "Activado: {$estudiante['nombre']} {$estudiante['apellido']}",
        ':ip' => $ip_usuario
    ]);

    // Activar estudiante (cambiar estado a 1 = Activo)
    $sql_update = "UPDATE estudiantes SET id_estado = 1 WHERE id_estudiante = :id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([':id' => $id_estudiante]);

    $conn->commit();

    header("Location: index.php?mensaje=" . urlencode("✅ Estudiante activado exitosamente: {$estudiante['nombre']} {$estudiante['apellido']}"));
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: index.php?mensaje=" . urlencode("❌ Error: " . $e->getMessage()));
    exit();
}
?>