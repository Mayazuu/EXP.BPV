<?php
include('../conexion.php');
session_start();

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
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';
$confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
$id_rol = filter_var($_POST['rol'] ?? null, FILTER_VALIDATE_INT);
$id_estado = filter_var($_POST['estado'] ?? null, FILTER_VALIDATE_INT);

// Validaciones básicas
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

if (strlen($contrasena) < 6) {
    echo json_encode(['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres']);
    exit();
}

if ($contrasena !== $confirmar_contrasena) {
    echo json_encode(['success' => false, 'mensaje' => 'Las contraseñas no coinciden']);
    exit();
}

if (!$id_rol || !$id_estado) {
    echo json_encode(['success' => false, 'mensaje' => 'Debe seleccionar rol y estado válidos']);
    exit();
}

try {
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

    // ========================================
    // GENERAR USUARIO ÚNICO CON MANEJO DE DUPLICADOS
    // ========================================
    
    // Función para limpiar caracteres especiales y convertir a minúsculas
    function limpiarTexto($texto) {
        $texto = mb_strtolower($texto, 'UTF-8');
        
        // Reemplazar caracteres especiales
        $caracteres = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n'
        ];
        
        $texto = strtr($texto, $caracteres);
        
        // Eliminar espacios y caracteres no alfanuméricos
        $texto = preg_replace('/[^a-z0-9]/', '', $texto);
        
        return $texto;
    }
    
    // Obtener primera letra del nombre y apellido completo
    $primeraLetraNombre = mb_substr(limpiarTexto($nombre), 0, 1);
    $apellidoLimpio = limpiarTexto($apellido);
    
    // Generar usuario base
    $usuarioBase = $primeraLetraNombre . $apellidoLimpio;
    
    // Limitar longitud máxima del usuario base a 20 caracteres
    if (strlen($usuarioBase) > 20) {
        $usuarioBase = substr($usuarioBase, 0, 20);
    }
    
    // Verificar si el usuario ya existe y generar uno único
    $usuario = $usuarioBase;
    $contador = 1;
    
    while (true) {
        // Verificar si el usuario existe
        $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $existe = (int)$stmt->fetchColumn();
        
        if ($existe === 0) {
            // Usuario disponible
            break;
        }
        
        // Usuario existe, generar variante con número
        $usuario = $usuarioBase . $contador;
        $contador++;
        
        // Seguridad: evitar loop infinito (máximo 99 usuarios con mismo patrón)
        if ($contador > 99) {
            // En caso extremo, usar timestamp
            $usuario = $usuarioBase . '_' . time();
            break;
        }
    }
    
    // ========================================
    // FIN DE GENERACIÓN DE USUARIO ÚNICO
    // ========================================

    // Hashear contraseña
    $password_hash = password_hash($contrasena, PASSWORD_BCRYPT);

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar usuario
    $stmt = $conn->prepare("
        INSERT INTO usuarios (usuario, contrasena, nombre, apellido, id_rol, id_estado)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $resultado = $stmt->execute([$usuario, $password_hash, $nombre, $apellido, $id_rol, $id_estado]);

    if (!$resultado) {
        throw new Exception('Error al crear el usuario');
    }

    $id_nuevo_usuario = $conn->lastInsertId();

    // Registrar en transacciones
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $descripcion = "Creó nuevo usuario ID: $id_nuevo_usuario - Usuario: $usuario, Nombre: $nombre $apellido, Rol: $rolNombre";

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'usuarios', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id_nuevo_usuario, $descripcion, $ip]);

    // Confirmar transacción
    $conn->commit();

    // Preparar respuesta con información del usuario creado
    $mensaje = "Usuario creado exitosamente";
    if ($contador > 1) {
        $mensaje .= " (se generó usuario único: $usuario)";
    }

    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'nombre' => "$nombre $apellido",
        'usuario' => $usuario,
        'password' => $contrasena,
        'es_variante' => $contador > 1,
        'numero_variante' => $contador > 1 ? ($contador - 1) : 0
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en procesar_crear.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al crear usuario: ' . $e->getMessage()
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
