<?php
include('../conexion.php');
session_start();

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_asesor = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $telefono = trim($_POST['telefono'] ?? '');

    if ($id_asesor <= 0) {
        header("Location: index.php?mensaje=ID de asesor inválido");
        exit();
    }

    // Validación de teléfono
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
        $sql_update = "UPDATE asesores SET telefono = :telefono WHERE id_asesor = :id";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([
            ':telefono' => !empty($telefono) ? $telefono : null,
            ':id' => $id_asesor
        ]);

        header("Location: index.php?mensaje=Teléfono actualizado exitosamente");
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=Error al actualizar: " . $e->getMessage());
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>