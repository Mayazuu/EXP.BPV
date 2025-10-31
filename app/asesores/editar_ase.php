<?php
include('../session_config.php');
include('../conexion.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_asesor = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_usuario = $_SESSION['id_usuario'];

    if ($id_asesor <= 0) {
        header("Location: index.php?mensaje=" . urlencode("ID de asesor inválido"));
        exit();
    }

    // Validación de campos obligatorios
    if (empty($nombre) || empty($apellido)) {
        header("Location: index.php?mensaje=" . urlencode("Nombre y apellido son obligatorios"));
        exit();
    }

    // Validación de teléfono (opcional)
    if (!empty($telefono)) {
        $telefono_limpio = preg_replace('/[\s\-]/', '', $telefono);

        if (!preg_match('/^[\d\s\-+]+$/', $telefono)) {
            header("Location: index.php?mensaje=" . urlencode("El teléfono solo puede contener números, espacios, guiones y +"));
            exit();
        }

        if (strlen($telefono_limpio) < 8) {
            header("Location: index.php?mensaje=" . urlencode("El teléfono debe tener al menos 8 dígitos"));
            exit();
        }

        if (strlen($telefono) > 20) {
            header("Location: index.php?mensaje=" . urlencode("El teléfono no debe exceder 20 caracteres"));
            exit();
        }

        if (!preg_match('/^(\+502\s?)?[\d]{4}[\s\-]?[\d]{4}$/', $telefono)) {
            header("Location: index.php?mensaje=" . urlencode("Formato inválido. Use: 1234-5678 o +502 1234-5678"));
            exit();
        }
    }

    try {
        // Actualizar nombre, apellido y teléfono
        $sql_update = "UPDATE asesores
                    SET nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono
                    WHERE id_asesor = :id";

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => !empty($telefono) ? $telefono : null,
            ':id' => $id_asesor
        ]);

        // Registrar transacción
        $telefono_texto = !empty($telefono) ? $telefono : 'No registrado';
        $descripcion = "Actualización del asesor ID: $id_asesor - $nombre $apellido (Teléfono: $telefono_texto)";
        $ip = $_SERVER['REMOTE_ADDR'];

        $stmt_log = $conn->prepare("INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
                                    VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt_log->execute([$id_usuario, 'asesores', $id_asesor, $descripcion, $ip]);

        header("Location: index.php?mensaje=" . urlencode("Asesor actualizado exitosamente"));
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=" . urlencode("Error al actualizar: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>