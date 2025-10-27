<?php
include('../session_config.php');
include('../conexion.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    echo json_encode(['success' => false, 'mensaje' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$dpi = trim($_POST['dpi'] ?? '');
$carnet = trim($_POST['carnet'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

try {
    // Validaciones
    if (empty($nombre) || empty($apellido)) {
        throw new Exception("Nombre y apellido son obligatorios");
    }

    if (empty($dpi) && empty($carnet)) {
        throw new Exception("Debe ingresar al menos DPI o Carnet Estudiantil");
    }

    if (!empty($dpi)) {
        if (strlen($dpi) !== 13 || !ctype_digit($dpi)) {
            throw new Exception("El DPI debe tener exactamente 13 dígitos");
        }

        // Verificar DPI único
        $stmt = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE dpi_estudiante = ?");
        $stmt->execute([$dpi]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ya existe un estudiante con ese DPI");
        }
    }

    // Insertar estudiante (id_estado = 1 para Activo)
    $stmt = $conn->prepare("
        INSERT INTO estudiantes (nombre, apellido, dpi_estudiante, carnetEstudiantil, telefono, id_estado)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $nombre,
        $apellido,
        !empty($dpi) ? $dpi : null,
        !empty($carnet) ? $carnet : null,
        !empty($telefono) ? $telefono : null
    ]);

    $id_estudiante = $conn->lastInsertId();

    // Registrar transacción
    $dpi_texto = !empty($dpi) ? $dpi : 'No registrado';
    $carnet_texto = !empty($carnet) ? $carnet : 'No registrado';
    $descripcion = "Registro de estudiante ID: $id_estudiante - $nombre $apellido (DPI: $dpi_texto, Carnet: $carnet_texto)";
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt_log = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->execute([$_SESSION['id_usuario'], 'estudiantes', $id_estudiante, $descripcion, $ip]);

    echo json_encode([
        'success' => true,
        'mensaje' => 'Estudiante registrado exitosamente',
        'estudiante' => [
            'id' => $id_estudiante,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dpi' => $dpi,
            'carnet' => $carnet
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
?>