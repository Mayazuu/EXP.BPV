<?php
include('../conexion.php');
session_start();

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dpi = trim($_POST['dpi_estudiante'] ?? '');
    $carnet = trim($_POST['carnetEstudiantil'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_carrera = $_POST['id_carrera'] ?? '';
    $nueva_carrera = trim($_POST['nueva_carrera'] ?? '');
    $id_estado = $_POST['id_estado'] ?? 1;

    //  SOLO nombre y apellido son obligatorios
    if (empty($nombre) || empty($apellido)) {
        header("Location: index.php?mensaje=" . urlencode("Nombre y apellido son obligatorios"));
        exit();
    }

    //  Validar DPI solo SI se ingresó
    if (!empty($dpi)) {
        if (!preg_match('/^\d{13}$/', $dpi)) {
            header("Location: index.php?mensaje=" . urlencode("El DPI debe tener exactamente 13 dígitos"));
            exit();
        }

        // Verificar si ya existe el DPI
        $stmt_check = $conn->prepare("SELECT dpi_estudiante FROM estudiantes WHERE dpi_estudiante = ?");
        $stmt_check->execute([$dpi]);

        if ($stmt_check->fetch()) {
            header("Location: index.php?mensaje=" . urlencode("Ya existe un estudiante con ese DPI"));
            exit();
        }
    }

    // Validar Carnet solo SI se ingresó
    if (!empty($carnet)) {
        if (!preg_match('/^\d{7}$/', $carnet)) {
            header("Location: index.php?mensaje=" . urlencode("El carnet debe tener exactamente 7 dígitos"));
            exit();
        }

        // Verificar si ya existe el carnet
        $stmt_check2 = $conn->prepare("SELECT carnetEstudiantil FROM estudiantes WHERE carnetEstudiantil = ?");
        $stmt_check2->execute([$carnet]);

        if ($stmt_check2->fetch()) {
            header("Location: index.php?mensaje=" . urlencode("Ya existe un estudiante con ese carnet"));
            exit();
        }
    }

    // Validar teléfono solo si NO está vacío
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

        if (strlen($telefono) > 13) {
            header("Location: index.php?mensaje=" . urlencode("El teléfono no debe exceder 13 caracteres"));
            exit();
        }
    }

    try {
        // MANEJAR NUEVA CARRERA
        if ($id_carrera === 'otros') {
            if (empty($nueva_carrera)) {
                header("Location: index.php?mensaje=" . urlencode("Debe ingresar el nombre de la nueva carrera"));
                exit();
            }

            // Verificar si la carrera ya existe
            $stmt_check_carrera = $conn->prepare("SELECT id_carrera FROM carreras WHERE LOWER(carrera) = LOWER(?)");
            $stmt_check_carrera->execute([$nueva_carrera]);
            $carrera_existente = $stmt_check_carrera->fetch();

            if ($carrera_existente) {
                // Si ya existe, usar ese ID
                $id_carrera = $carrera_existente['id_carrera'];
            } else {
                // Insertar nueva carrera
                $stmt_insert_carrera = $conn->prepare("INSERT INTO carreras (carrera) VALUES (?)");
                $stmt_insert_carrera->execute([$nueva_carrera]);
                $id_carrera = $conn->lastInsertId();
            }
        }

        // Validar que ahora sí tengamos un ID de carrera válido
        if (empty($id_carrera) || !is_numeric($id_carrera)) {
            header("Location: index.php?mensaje=" . urlencode("Debe seleccionar una carrera válida"));
            exit();
        }

        //  Insertar estudiante (DPI y carnet pueden ser NULL)
        $stmt = $conn->prepare("INSERT INTO estudiantes
            (dpi_estudiante, carnetEstudiantil, nombre, apellido, telefono, id_carrera, id_estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            !empty($dpi) ? $dpi : null,
            !empty($carnet) ? $carnet : null,
            $nombre,
            $apellido,
            !empty($telefono) ? $telefono : null,
            $id_carrera,
            $id_estado
        ]);

        //  Obtener el ID generado automáticamente
        $id_estudiante = $conn->lastInsertId();

        header("Location: index.php?mensaje=" . urlencode("Estudiante registrado exitosamente con ID: $id_estudiante"));
        exit();
    } catch (PDOException $e) {
        header("Location: index.php?mensaje=" . urlencode("Error al registrar: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
?>