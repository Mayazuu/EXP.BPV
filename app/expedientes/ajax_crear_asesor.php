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
    $telefono = trim($_POST['telefono'] ?? '');
    
    // Validaciones
    if (empty($nombre) || empty($apellido)) {
        throw new Exception('Nombre y apellido son obligatorios');
    }
    
    if (strlen($nombre) < 2 || strlen($apellido) < 2) {
        throw new Exception('Nombre y apellido deben tener al menos 2 caracteres');
    }
    
    // Validar que no sea duplicado (mismo nombre y apellido)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM asesores WHERE LOWER(nombre) = LOWER(?) AND LOWER(apellido) = LOWER(?)");
    $stmt->execute([$nombre, $apellido]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Ya existe un asesor con ese nombre y apellido');
    }
    
    $conn->beginTransaction();
    
    // Insertar asesor
    $stmt = $conn->prepare("
        INSERT INTO asesores (nombre, apellido, telefono)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $nombre,
        $apellido,
        $telefono ?: null
    ]);
    
    $id_asesor = $conn->lastInsertId();
    
    // Registrar transacción
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $descripcion = "Creó asesor desde expedientes: $nombre $apellido (ID: $id_asesor)";
    
    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'asesores', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id_asesor, $descripcion, $ip]);
    
    $conn->commit();
    
    // Devolver datos del asesor creado
    echo json_encode([
        'success' => true,
        'mensaje' => 'Asesor creado exitosamente',
        'asesor' => [
            'id' => $id_asesor,
            'nombre' => $nombre,
            'apellido' => $apellido
        ]
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en ajax_crear_asesor.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'mensaje' => 'Error en BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
?>