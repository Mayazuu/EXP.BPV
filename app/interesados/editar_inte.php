<?php
include('../session_config.php');
include('../conexion.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_interesado = $_POST['id_interesado'] ?? '';
    $dpi = trim($_POST['dpi_interesado'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion_exacta'] ?? '');
    $id_lugar = $_POST['id_lugar'] ?? '';
    $otro_lugar = trim($_POST['otro_lugar'] ?? '');
    $id_usuario = $_SESSION['id_usuario'];

    // Validación básica
    if (empty($id_interesado)) {
        header("Location: index.php?mensaje=" . urlencode("ID de interesado no especificado"));
        exit();
    }

    if (empty($nombre) || empty($apellido)) {
        header("Location: index.php?mensaje=" . urlencode("Nombre y apellidos son obligatorios"));
        exit();
    }

    if (empty($direccion)) {
        header("Location: index.php?mensaje=" . urlencode("La dirección es obligatoria"));
        exit();
    }

    // Validar DPI solo si se ingresó
    if (!empty($dpi)) {
        // Verificar que no exista en otro interesado
        $stmt_check_dpi = $conn->prepare("SELECT id_interesado FROM interesados WHERE dpi_interesado = ? AND id_interesado != ?");
        $stmt_check_dpi->execute([$dpi, $id_interesado]);
        if ($stmt_check_dpi->fetch()) {
            header("Location: index.php?mensaje=" . urlencode("Ya existe otro interesado con ese DPI/Cédula"));
            exit();
        }
    }

    // Validar teléfono solo si NO está vacío
    if (!empty($telefono)) {
        if (!preg_match('/^[0-9+\-\s\(\)]{8,20}$/', $telefono)) {
            header("Location: index.php?mensaje=" . urlencode("Formato de teléfono inválido"));
            exit();
        }
    }

    try {
        $nombre_lugar_final = '';

        // Si seleccionó "Otros", insertar nuevo municipio
        if ($id_lugar === 'otros') {
            if (empty($otro_lugar)) {
                header("Location: index.php?mensaje=" . urlencode("Debe ingresar el nombre del municipio"));
                exit();
            }

            $stmt_check_lugar = $conn->prepare("SELECT id_lugar, municipio FROM lugares WHERE LOWER(municipio) = LOWER(?)");
            $stmt_check_lugar->execute([$otro_lugar]);
            $lugar_existente = $stmt_check_lugar->fetch();

            if ($lugar_existente) {
                $id_lugar = $lugar_existente['id_lugar'];
                $nombre_lugar_final = $lugar_existente['municipio'];
            } else {
                $stmt_insert_lugar = $conn->prepare("INSERT INTO lugares (municipio) VALUES (?)");
                $stmt_insert_lugar->execute([$otro_lugar]);
                $id_lugar = $conn->lastInsertId();
                $nombre_lugar_final = $otro_lugar;
            }
        } else {
            $stmt_get_lugar = $conn->prepare("SELECT municipio FROM lugares WHERE id_lugar = ?");
            $stmt_get_lugar->execute([$id_lugar]);
            $lug = $stmt_get_lugar->fetch();
            $nombre_lugar_final = $lug ? $lug['municipio'] : 'Desconocido';
        }

        if (empty($id_lugar) || !is_numeric($id_lugar)) {
            header("Location: index.php?mensaje=" . urlencode("Debe seleccionar un municipio válido"));
            exit();
        }

        // Actualizar interesado
        $stmt = $conn->prepare("UPDATE interesados
                                SET dpi_interesado = ?,
                                    nombre = ?,
                                    apellido = ?,
                                    telefono = ?,
                                    direccion_exacta = ?,
                                    id_lugar = ?
                                WHERE id_interesado = ?");

        $stmt->execute([
            !empty($dpi) ? $dpi : null,
            $nombre,
            $apellido,
            !empty($telefono) ? $telefono : null,
            $direccion,
            $id_lugar,
            $id_interesado
        ]);

        // Registrar transacción
        $dpi_texto = !empty($dpi) ? $dpi : 'No registrado';
        $telefono_texto = !empty($telefono) ? $telefono : 'No registrado';
        $descripcion = "Actualización del interesado ID: $id_interesado - $nombre $apellido (DPI: $dpi_texto, Teléfono: $telefono_texto, Municipio: $nombre_lugar_final)";
        $ip = $_SERVER['REMOTE_ADDR'];

        $stmt_log = $conn->prepare("INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip)
                                    VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt_log->execute([$id_usuario, 'interesados', $id_interesado, $descripcion, $ip]);

        header("Location: index.php?mensaje=" . urlencode("Interesado actualizado exitosamente"));
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
