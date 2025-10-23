<?php
include('../conexion.php');
session_start();

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validaciones
    if (empty($nombre) || empty($apellido)) {
        header("Location: index.php?mensaje=El nombre y apellido son obligatorios");
        exit();
    }

    if (strlen($nombre) > 100 || strlen($apellido) > 100) {
        header("Location: index.php?mensaje=Nombre y apellido no deben exceder 100 caracteres");
        exit();
    }

    if (!empty($telefono)) {
        $telefono_limpio = preg_replace('/[\s\-]/', '', $telefono);

        if (!preg_match('/^[\d\s\-+]+$/', $telefono)) {
            header("Location: index.php?mensaje=El teléfono solo puede contener números, espacios, guiones y +");
            exit();
        }

        if (strlen($telefono_limpio) < 8) {
            header("Location: index.php?mensaje=El teléfono debe tener al menos 8 dígitos");
            exit();
        }

        if (strlen($telefono) > 20) {
            header("Location: index.php?mensaje=El teléfono no debe exceder 20 caracteres");
            exit();
        }

        if (!preg_match('/^(\+502\s?)?[\d]{4}[\s\-]?[\d]{4}$/', $telefono)) {
            header("Location: index.php?mensaje=Formato inválido. Use: 1234-5678 o +502 1234-5678");
            exit();
        }
    }

    try {
        // Verificar si ya existe
        $sql_check = "SELECT id_asesor FROM asesores WHERE nombre = :nombre AND apellido = :apellido";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([':nombre' => $nombre, ':apellido' => $apellido]);

        if ($stmt_check->fetch()) {
            header("Location: index.php?mensaje=Ya existe un asesor con ese nombre y apellido");
            exit();
        }

        // Insertar
        $sql = "INSERT INTO asesores (nombre, apellido, telefono) VALUES (:nombre, :apellido, :telefono)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => !empty($telefono) ? $telefono : null
        ]);

        header("Location: index.php?mensaje=Asesor registrado exitosamente");
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=Error al registrar: " . $e->getMessage());
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>