<?php
include('../conexion.php');
session_start();

header('Content-Type: application/json');

// Validar sesión
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No tiene permisos']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

try {
    // Obtener datos
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $dpi = trim($_POST['dpi'] ?? '');
    $carnet = trim($_POST['carnet'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_carrera = $_POST['id_carrera'] ?? '';
    $nueva_carrera = trim($_POST['nueva_carrera'] ?? '');
    
    // Validaciones
    if (empty($nombre) || empty($apellido)) {
        throw new Exception('Nombre y apellido son obligatorios');
    }
    
    if (strlen($nombre) < 2 || strlen($apellido) < 2) {
        throw new Exception('Nombre y apellido deben tener al menos 2 caracteres');
    }
    
    // Si seleccionó "Otros", crear nueva carrera
    if ($id_carrera === 'otros') {
        if (empty($nueva_carrera)) {
            throw new Exception('Debe ingresar el nombre de la carrera');
        }
        
        // Verificar si ya existe
        $stmt = $conn->prepare("SELECT id_carrera FROM carreras WHERE LOWER(carrera) = LOWER(?)");
        $stmt->execute([$nueva_carrera]);
        $carreraExistente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($carreraExistente) {
            $id_carrera = $carreraExistente['id_carrera'];
        } else {
            $stmt = $conn->prepare("INSERT INTO carreras (carrera) VALUES (?)");
            $stmt->execute([$nueva_carrera]);
            $id_carrera = $conn->lastInsertId();
        }
    }
    
    if (empty($id_carrera)) {
        throw new Exception('Debe seleccionar una carrera');
    }
    
    // Validar DPI único si se proporciona
    if (!empty($dpi)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE dpi_estudiante = ?");
        $stmt->execute([$dpi]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El DPI ya está registrado');
        }
    }
    
    // Validar Carnet único si se proporciona
    if (!empty($carnet)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE carnetEstudiantil = ?");
        $stmt->execute([$carnet]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El carnet ya está registrado');
        }
    }
    
    $conn->beginTransaction();
    
    // Insertar estudiante (id_estado = 1 = Activo)
    $stmt = $conn->prepare("
        INSERT INTO estudiantes (nombre, apellido, dpi_estudiante, carnetEstudiantil, telefono, id_carrera, id_estado)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->execute([
        $nombre,
        $apellido,
        $dpi ?: null,
        $carnet ?: null,
        $telefono ?: null,
        $id_carrera
    ]);
    
    $id_estudiante = $conn->lastInsertId();
    
    // Registrar transacción
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $descripcion = "Creó estudiante desde expedientes: $nombre $apellido (ID: $id_estudiante)";
    
    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'estudiantes', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id_estudiante, $descripcion, $ip]);
    
    $conn->commit();
    
    // Devolver datos del estudiante creado
    echo json_encode([
        'success' => true,
        'mensaje' => 'Estudiante creado exitosamente',
        'estudiante' => [
            'id' => $id_estudiante,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dpi' => $dpi,
            'carnet' => $carnet
        ]
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en ajax_crear_estudiante.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'mensaje' => 'Error en BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
?>