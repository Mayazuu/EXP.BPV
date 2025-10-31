<?php
include('../session_config.php');
include('../conexion.php');

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
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion_exacta = trim($_POST['direccion'] ?? '');
    $id_lugar = $_POST['id_lugar'] ?? '';
    $otro_lugar = trim($_POST['otro_lugar'] ?? '');

    // Validaciones básicas
    if (empty($nombre) || empty($apellido)) {
        throw new Exception('Nombre y apellido son obligatorios');
    }

    if (strlen($nombre) < 2 || strlen($apellido) < 2) {
        throw new Exception('Nombre y apellido deben tener al menos 2 caracteres');
    }

    if (empty($direccion_exacta)) {
        throw new Exception('La dirección es obligatoria');
    }

    // Validar que DPI o cédula sea obligatorio
    if (empty($dpi)) {
        throw new Exception('Debe ingresar un número de DPI o cédula');
    }

    // Validar formato y longitud (máximo 13 dígitos)
    if (!ctype_digit($dpi) || strlen($dpi) > 13) {
        throw new Exception('El DPI o cédula debe contener solo números y un máximo de 13 dígitos');
    }

    // Validar que no se repita en la base de datos
    $stmt = $conn->prepare("SELECT COUNT(*) FROM interesados WHERE dpi_interesado = ?");
    $stmt->execute([$dpi]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('El DPI o cédula ya está registrado');
    }

    // Validar teléfono si se proporciona
    if (!empty($telefono)) {
        if (!preg_match('/^[0-9+\-\s\(\)]{8,20}$/', $telefono)) {
            throw new Exception('Formato de teléfono inválido');
        }
    }

    $conn->beginTransaction();

    $nombre_lugar_final = '';

    // Manejo de lugar (municipio)
    if ($id_lugar === 'otros') {
        if (empty($otro_lugar)) {
            throw new Exception('Debe ingresar el nombre del municipio');
        }

        // Verificar si el municipio ya existe
        $stmt = $conn->prepare("SELECT id_lugar, municipio FROM lugares WHERE LOWER(municipio) = LOWER(?)");
        $stmt->execute([$otro_lugar]);
        $lugar_existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lugar_existente) {
            $id_lugar = $lugar_existente['id_lugar'];
            $nombre_lugar_final = $lugar_existente['municipio'];
        } else {
            // Crear nuevo municipio
            $stmt = $conn->prepare("INSERT INTO lugares (municipio) VALUES (?)");
            $stmt->execute([$otro_lugar]);
            $id_lugar = $conn->lastInsertId();
            $nombre_lugar_final = $otro_lugar;
        }
    } else {
        // Obtener nombre del lugar seleccionado
        $stmt = $conn->prepare("SELECT municipio FROM lugares WHERE id_lugar = ?");
        $stmt->execute([$id_lugar]);
        $lugar = $stmt->fetch(PDO::FETCH_ASSOC);
        $nombre_lugar_final = $lugar ? $lugar['municipio'] : 'Desconocido';
    }

    // Validar que tengamos un ID de lugar válido
    if (empty($id_lugar) || !is_numeric($id_lugar)) {
        throw new Exception('Debe seleccionar un municipio válido');
    }

    // ================================================================
    // INSERTAR INTERESADO
    // ================================================================
    $stmt = $conn->prepare("
        INSERT INTO interesados (dpi_interesado, nombre, apellido, telefono, direccion_exacta, id_lugar)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $dpi ?: null,
        $nombre,
        $apellido,
        $telefono ?: null,
        $direccion_exacta,
        $id_lugar
    ]);

    $id_interesado = $conn->lastInsertId();

    // Registrar transacción
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $dpi_texto = !empty($dpi) ? $dpi : 'No registrado';
    $telefono_texto = !empty($telefono) ? $telefono : 'No registrado';
    $descripcion = "Creó interesado desde expedientes: $nombre $apellido (ID: $id_interesado, DPI: $dpi_texto, Tel: $telefono_texto, Municipio: $nombre_lugar_final)";

    $stmt = $conn->prepare("
        INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
        VALUES (?, 'interesados', ?, ?, NOW(), ?)
    ");
    $stmt->execute([$_SESSION['id_usuario'], $id_interesado, $descripcion, $ip]);

    $conn->commit();

    // Devolver datos del interesado creado
    echo json_encode([
        'success' => true,
        'mensaje' => 'Cliente creado exitosamente',
        'interesado' => [
            'id' => $id_interesado,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dpi' => $dpi,
            'municipio' => $nombre_lugar_final
        ]
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en ajax_crear_interesado.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'mensaje' => 'Error en BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
}
?>