<?php
include('../session_config.php');
include('../conexion.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ahora usamos id_estudiante como identificador
    $id_estudiante = $_POST['id_estudiante'] ?? '';
    $dpi = trim($_POST['dpi_estudiante'] ?? '');
    $carnet = trim($_POST['carnetEstudiantil'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $id_carrera = $_POST['id_carrera'] ?? '';
    $nueva_carrera = trim($_POST['nueva_carrera'] ?? '');
    $id_usuario = $_SESSION['id_usuario'];

    // Validación básica
    if (empty($id_estudiante)) {
        header("Location: index.php?mensaje=" . urlencode("ID de estudiante no especificado"));
        exit();
    }

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

        // Verificar que no exista en otro estudiante
        $stmt_check_dpi = $conn->prepare("SELECT id_estudiante FROM estudiantes WHERE dpi_estudiante = ? AND id_estudiante != ?");
        $stmt_check_dpi->execute([$dpi, $id_estudiante]);
        if ($stmt_check_dpi->fetch()) {
            header("Location: index.php?mensaje=" . urlencode("Ya existe otro estudiante con ese DPI"));
            exit();
        }
    }

    // Validar Carnet solo SI se ingresó
    if (!empty($carnet)) {
        if (!preg_match('/^\d{7}$/', $carnet)) {
            header("Location: index.php?mensaje=" . urlencode("El carnet debe tener exactamente 7 dígitos"));
            exit();
        }

        // Verificar que no exista en otro estudiante
        $stmt_check_carnet = $conn->prepare("SELECT id_estudiante FROM estudiantes WHERE carnetEstudiantil = ? AND id_estudiante != ?");
        $stmt_check_carnet->execute([$carnet, $id_estudiante]);
        if ($stmt_check_carnet->fetch()) {
            header("Location: index.php?mensaje=" . urlencode("Ya existe otro estudiante con ese carnet"));
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

        if (strlen($telefono) > 20) {
            header("Location: index.php?mensaje=" . urlencode("El teléfono no debe exceder 20 caracteres"));
            exit();
        }
    }

    try {
        $nombre_carrera_final = '';

        // MANEJAR NUEVA CARRERA
        if ($id_carrera === 'otros') {
            if (empty($nueva_carrera)) {
                header("Location: index.php?mensaje=" . urlencode("Debe ingresar el nombre de la nueva carrera"));
                exit();
            }

            // Verificar si la carrera ya existe
            $stmt_check_carrera = $conn->prepare("SELECT id_carrera, carrera FROM carreras WHERE LOWER(carrera) = LOWER(?)");
            $stmt_check_carrera->execute([$nueva_carrera]);
            $carrera_existente = $stmt_check_carrera->fetch();

            if ($carrera_existente) {
                $id_carrera = $carrera_existente['id_carrera'];
                $nombre_carrera_final = $carrera_existente['carrera'];
            } else {
                $stmt_insert_carrera = $conn->prepare("INSERT INTO carreras (carrera) VALUES (?)");
                $stmt_insert_carrera->execute([$nueva_carrera]);
                $id_carrera = $conn->lastInsertId();
                $nombre_carrera_final = $nueva_carrera;
            }
        } else {
            // Obtener nombre de la carrera seleccionada
            $stmt_get_carrera = $conn->prepare("SELECT carrera FROM carreras WHERE id_carrera = ?");
            $stmt_get_carrera->execute([$id_carrera]);
            $carr = $stmt_get_carrera->fetch();
            $nombre_carrera_final = $carr ? $carr['carrera'] : 'Desconocida';
        }

        // Validar que ahora sí tengamos un ID de carrera válido
        if (empty($id_carrera) || !is_numeric($id_carrera)) {
            header("Location: index.php?mensaje=" . urlencode("Debe seleccionar una carrera válida"));
            exit();
        }

        // Actualizar estudiante (ahora incluye DPI y carnet)
        $stmt = $conn->prepare("UPDATE estudiantes
                                SET dpi_estudiante = ?,
                                    carnetEstudiantil = ?,
                                    nombre = ?,
                                    apellido = ?,
                                    telefono = ?,
                                    id_carrera = ?
                                WHERE id_estudiante = ?");
        $stmt->execute([
            !empty($dpi) ? $dpi : null,
            !empty($carnet) ? $carnet : null,
            $nombre,
            $apellido,
            !empty($telefono) ? $telefono : null,
            $id_carrera,
            $id_estudiante
        ]);

        //  REGISTRAR EN TRANSACCIONES
        $dpi_texto = !empty($dpi) ? $dpi : 'No registrado';
        $carnet_texto = !empty($carnet) ? $carnet : 'No registrado';
        $telefono_texto = !empty($telefono) ? $telefono : 'No registrado';

        $descripcion = "Se editó al estudiante ID: $id_estudiante - $nombre $apellido (DPI: $dpi_texto, Carnet: $carnet_texto, Teléfono: $telefono_texto, Carrera: $nombre_carrera_final)";
        $ip = $_SERVER['REMOTE_ADDR'];

        $stmt_log = $conn->prepare("INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
                                    VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt_log->execute([$id_usuario, 'estudiantes', $id_estudiante, $descripcion, $ip]);

        header("Location: index.php?mensaje=" . urlencode("Estudiante actualizado exitosamente"));
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