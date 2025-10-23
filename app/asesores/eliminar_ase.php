<?php
session_start();
include('../conexion.php');

// Validar sesión y rol de Directora
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['id_rol'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// Verificar que sea Directora (id_rol = 1)
if ($_SESSION['id_rol'] != 1) {
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$id_asesor = isset($_POST['id_asesor']) ? (int)$_POST['id_asesor'] : 0;
$razon = isset($_POST['razon']) ? trim($_POST['razon']) : '';

if ($id_asesor <= 0 || empty($razon)) {
    header("Location: index.php?mensaje=Datos incompletos para eliminación");
    exit();
}

try {
    $conn->beginTransaction();

    // Verificar que el asesor existe
    $sql_check = "SELECT a.id_asesor, a.nombre, a.apellido,
                COUNT(e.id_expediente) as expedientes_vinculados
                FROM asesores a
                LEFT JOIN expedientes e ON a.id_asesor = e.id_asesor
                WHERE a.id_asesor = :id
                GROUP BY a.id_asesor";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':id' => $id_asesor]);
    $asesor = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$asesor) {
        throw new Exception('Asesor no encontrado');
    }

    if ($asesor['expedientes_vinculados'] > 0) {
        throw new Exception('No se puede eliminar. El asesor tiene expedientes vinculados');
    }

    // Obtener IP del usuario
    $ip_usuario = $_SERVER['REMOTE_ADDR'];

    // Registrar en transacciones
    $sql_trans = "INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
                VALUES (:id_usuario, :tabla, :id_registro, :descripcion, NOW(), :ip)";
    $stmt_trans = $conn->prepare($sql_trans);
    $stmt_trans->execute([
        ':id_usuario' => $_SESSION['id_usuario'],
        ':tabla' => 'asesores',
        ':id_registro' => $id_asesor,
        ':descripcion' => "Eliminado: {$asesor['nombre']} {$asesor['apellido']}. Motivo: {$razon}",
        ':ip' => $ip_usuario
    ]);

    // Eliminar asesor
    $sql_delete = "DELETE FROM asesores WHERE id_asesor = :id";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([':id' => $id_asesor]);

    $conn->commit();

    header("Location: index.php?mensaje=Asesor eliminado exitosamente");
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: index.php?mensaje=" . urlencode("Error: " . $e->getMessage()));
    exit();
}
?>