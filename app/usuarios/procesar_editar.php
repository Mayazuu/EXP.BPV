<?php
include('../session_config.php');
include('../conexion.php');

header('Content-Type: application/json');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Directora'])) {
    echo json_encode(['success' => false, 'mensaje' => 'No tiene permisos para realizar esta acción']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

// Obtener y validar datos
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$id_rol = filter_var($_POST['rol'] ?? null, FILTER_VALIDATE_INT);
$id_estado = filter_var($_POST['estado'] ?? null, FILTER_VALIDATE_INT);

// Validaciones básicas
if (!$id) {
    echo json_encode(['success' => false, 'mensaje' => 'ID de usuario inválido']);
    exit();
}

if (empty($nombre) || empty($apellido)) {
    echo json_encode(['success' => false, 'mensaje' => 'Nombre y apellido son obligatorios']);
    exit();
}

if (strlen($nombre) < 2 || strlen($nombre) > 100) {
    echo json_encode(['success' => false, 'mensaje' => 'El nombre debe tener entre 2 y 100 caracteres']);
    exit();
}

if (strlen($apellido) < 2 || strlen($apellido) > 100) {
    echo json_encode(['success' => false, 'mensaje' => 'El apellido debe tener entre 2 y 100 caracteres']);
    exit();
}

if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $nombre)) {
    echo json_encode(['success' => false, 'mensaje' => 'El nombre solo debe contener letras']);
    exit();
}

if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellido)) {
    echo json_encode(['success' => false, 'mensaje' => 'El apellido solo debe contener letras']);
    exit();
}

if (!$id_rol || !$id_estado) {
    echo json_encode(['success' => false, 'mensaje' => 'Debe seleccionar rol y estado válidos']);
    exit();
}

try {
    // Obtener datos actuales del usuario
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
        echo json_encode(['success' => false, 'mensaje' => 'Usuario no encontrado']);
        exit();
    }

    // Verificar que el rol existe
    $stmt = $conn->prepare("SELECT rol FROM roles WHERE id_rol = ?");
    $stmt->execute([$id_rol]);
    $rolNombre = $stmt->fetchColumn();
    
    if (!$rolNombre) {
        echo json_encode(['success' => false, 'mensaje' => 'El rol seleccionado no es válido']);
        exit();
    }

    // Verificar que el estado existe
    $stmt = $conn->prepare("SELECT estado FROM estados WHERE id_estado = ?");
    $stmt->execute([$id_estado]);
    $estadoNombre = $stmt->fetchColumn();
    
    if (!$estadoNombre) {
        echo json_encode(['success' => false, 'mensaje' => 'El estado seleccionado no es válido']);
        exit();
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Actualizar usuario (SIN TOCAR LA CONTRASEÑA)
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET nombre = ?, apellido = ?, id_rol = ?, id_estado = ?
        WHERE id_usuario = ?
    ");
    $resultado = $stmt->execute([$nombre, $apellido, $id_rol, $id_estado, $id]);

    if (!$resultado) {
        throw new Exception('Error al actualizar el usuario');
    }

    // Registrar cambios realizados
    $cambios = [];
    if ($nombre !== $usuario['nombre']) {
        $cambios[] = "Nombre: {$usuario['nombre']} → $nombre";
    }
    if ($apellido !== $usuario['apellido']) {
        $cambios[] = "Apellido: {$usuario['apellido']} → $apellido";
    }
    if ($id_rol != $usuario['id_rol']) {
        $cambios[] = "Rol: {$usuario['rol']} → $rolNombre";
    }
    if ($id_estado != $usuario['id_estado']) {
        $cambios[] = "Estado: {$usuario['estado']} → $estadoNombre";
    }

    // Si no hay cambios
    if (empty($cambios)) {
        $conn->rollBack();
        echo json_encode([
            'success' => true,
            'mensaje' => 'No se realizaron cambios'
        ]);
        exit();
    }

    // Registrar en transacciones
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $descripcion = "Editó usuario ID: $id - Usuario: {$usuario['usuario']}, Cambios: " . implode(', ', $cambios);

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'usuarios', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id, $descripcion, $ip]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'mensaje' => 'Usuario actualizado exitosamente.'
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en procesar_editar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al actualizar usuario: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>