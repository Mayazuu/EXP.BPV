<?php
include('../session_config.php');
include('../conexion.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$error = '';
$mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Datos básicos del expediente
        $ficha_social = trim($_POST['ficha_social'] ?? '');
        $numero_caso = trim($_POST['numero_caso'] ?? '');
        $anio = trim($_POST['anio'] ?? '');
        $num_proceso = trim($_POST['num_proceso'] ?? '');
        $id_estado_exp = $_POST['id_estado_exp'] ?? '';
        $folios = $_POST['folios'] ?? 0;

        // Validar folios
        if (!is_numeric($folios) || $folios < 1) {
            throw new Exception("El número de folios debe ser mayor a 0.");
        }

        // ===== VALIDACIÓN: FICHA SOCIAL ÚNICA =====
        $stmt = $conn->prepare("SELECT COUNT(*) FROM expedientes WHERE ficha_social = ?");
        $stmt->execute([$ficha_social]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("❌ La ficha social '$ficha_social' ya está registrada. Debe ser única.");
        }

        if (isset($_POST['numero_caso']) && !empty(trim($_POST['numero_caso']))) {
            $numero_caso = trim($_POST['numero_caso']);

            // Validar que sea numérico
            if (!is_numeric($numero_caso)) {
                $error = "El número de caso debe ser un número";
            }
            // Validar que sea entero positivo
            elseif (!ctype_digit($numero_caso)) {
                $error = "El número de caso debe ser un número entero";
            }
            // Validar que sea mayor a 0
            elseif ((int)$numero_caso < 1) {
                $error = "El número de caso debe ser un número positivo mayor a 0";
            }
            else {
                // Validación exitosa
                $numero_caso = (int)$numero_caso;
            }
        } else {
            // Si está vacío, asignar NULL
            $numero_caso = null;
        }

// Validar que no exista el mismo número de caso en el mismo año (solo si se ingresó)
        if ($numero_caso !== null) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM expedientes 
                                        WHERE numero_caso = ? AND YEAR(anio) = ?");
            $stmt_check->execute([$numero_caso, $anio]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "Ya existe un expediente con ese número en el año $anio";
            }
        }

        // Datos de interesados, estudiantes y asesores
        $id_interesado = $_POST['id_interesado'] ?? '';
        $id_estudiante = $_POST['id_estudiante'] ?? '';
        $id_asesor = $_POST['id_asesor'] ?? '';

        // Juzgado
        $id_juzgado = $_POST['id_juzgado'] ?? '';
        if ($id_juzgado === 'otro') {
            $nuevo_nombre_juzgado = trim($_POST['nuevo_nombre_juzgado'] ?? '');
            if (empty($nuevo_nombre_juzgado)) {
                throw new Exception("Debe ingresar un nombre para el nuevo juzgado.");
            }

            $stmt = $conn->prepare("SELECT id_juzgado FROM juzgados WHERE LOWER(nombre) = LOWER(?)");
            $stmt->execute([$nuevo_nombre_juzgado]);
            $juzgadoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($juzgadoExistente) {
                $id_juzgado = $juzgadoExistente['id_juzgado'];
            } else {
                $stmt = $conn->prepare("INSERT INTO juzgados (nombre, id_estado) VALUES (?, 1)");
                $stmt->execute([$nuevo_nombre_juzgado]);
                $id_juzgado = $conn->lastInsertId();
            }
        }

        // Tipo de expediente
        $id_tipo_exp = $_POST['id_tipo_exp'] ?? '';
        if ($id_tipo_exp === 'otro') {
            $nuevo_nombre_tipo = trim($_POST['nuevo_nombre_tipo'] ?? '');
            $nuevo_id_area = $_POST['nuevo_id_area'] ?? '';

            if (empty($nuevo_nombre_tipo) || empty($nuevo_id_area)) {
                throw new Exception("Debe ingresar el nombre del tipo y seleccionar un área legal.");
            }

            $stmt = $conn->prepare("SELECT id_tipo_exp FROM tipo_caso WHERE LOWER(caso) = LOWER(?)");
            $stmt->execute([$nuevo_nombre_tipo]);
            $tipoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tipoExistente) {
                $id_tipo_exp = $tipoExistente['id_tipo_exp'];
            } else {
                $stmt = $conn->prepare("INSERT INTO tipo_caso (caso, id_area) VALUES (?, ?)");
                $stmt->execute([$nuevo_nombre_tipo, $nuevo_id_area]);
                $id_tipo_exp = $conn->lastInsertId();
            }
        }

        // Fechas
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_audiencia1 = $_POST['fecha_audiencia1'] ?: null;
        $fecha_audiencia2 = $_POST['fecha_audiencia2'] ?: null;
        $fecha_finalizacion = $_POST['fecha_finalizacion'] ?: null;

        // Estante
        $id_estante = $_POST['id_estante'] ?? '';
        if ($id_estante === 'otro') {
            $nuevo_numero_estante = trim($_POST['nuevo_numero_estante'] ?? '');
            if (empty($nuevo_numero_estante)) {
                throw new Exception("Debe ingresar un número de estante.");
            }

            $stmt = $conn->prepare("SELECT id_estante FROM estantes WHERE estante = ?");
            $stmt->execute([$nuevo_numero_estante]);
            $estanteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($estanteExistente) {
                $id_estante = $estanteExistente['id_estante'];
            } else {
                $stmt = $conn->prepare("INSERT INTO estantes (estante) VALUES (?)");
                $stmt->execute([$nuevo_numero_estante]);
                $id_estante = $conn->lastInsertId();
            }
        }

        // Observaciones
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validaciones de existencia
        $validaciones = [
            ["estudiantes", "id_estudiante", $id_estudiante, "Estudiante no existe."],
            ["interesados", "id_interesado", $id_interesado, "Interesado no existe."],
            ["asesores", "id_asesor", $id_asesor, "Asesor no existe."],
            ["juzgados", "id_juzgado", $id_juzgado, "Juzgado no existe."],
            ["tipo_caso", "id_tipo_exp", $id_tipo_exp, "Tipo de expediente no existe."],
            ["estados_exp", "id_estado_exp", $id_estado_exp, "Estado de expediente no existe."],
            ["estantes", "id_estante", $id_estante, "Estante no existe."]
        ];

        foreach ($validaciones as $v) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM {$v[0]} WHERE {$v[1]} = ?");
            $stmt->execute([$v[2]]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception($v[3]);
            }
        }

        // Validar coherencia de fechas
        if ($fecha_finalizacion && $fecha_inicio > $fecha_finalizacion) {
            throw new Exception("La fecha de inicio no puede ser mayor a la de finalización.");
        }
        if ($fecha_audiencia1 && ($fecha_audiencia1 < $fecha_inicio || ($fecha_finalizacion && $fecha_audiencia1 > $fecha_finalizacion))) {
            throw new Exception("La fecha de audiencia 1 debe estar entre inicio y finalización.");
        }
        if ($fecha_audiencia2 && ($fecha_audiencia2 < $fecha_inicio || ($fecha_finalizacion && $fecha_audiencia2 > $fecha_finalizacion))) {
            throw new Exception("La fecha de audiencia 2 debe estar entre inicio y finalización.");
        }

        // ===== INSERTAR EXPEDIENTE =====
        $conn->beginTransaction();

        // Obtener los DPIs si existen (opcionales)
        $stmt_get_dpi_int = $conn->prepare("SELECT dpi_interesado FROM interesados WHERE id_interesado = ?");
        $stmt_get_dpi_int->execute([$id_interesado]);
        $dpi_interesado_valor = $stmt_get_dpi_int->fetchColumn() ?: null;

        $stmt_get_dpi_est = $conn->prepare("SELECT dpi_estudiante FROM estudiantes WHERE id_estudiante = ?");
        $stmt_get_dpi_est->execute([$id_estudiante]);
        $dpi_estudiante_valor = $stmt_get_dpi_est->fetchColumn() ?: null;

        // Insertar expediente
        $stmt = $conn->prepare("INSERT INTO expedientes
            (ficha_social, numero_caso, anio, num_proceso, id_estado_exp, folios,
            id_interesado, dpi_interesado_exp, id_estudiante, dpi_estudiante_exp,
            id_asesor, id_juzgado, id_tipo_exp, fecha_inicio, fecha_audiencia1,
            fecha_audiencia2, fecha_finalizacion, id_estante, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $ficha_social,
            $numero_caso,
            $anio,
            $num_proceso,
            $id_estado_exp,
            $folios,
            $id_interesado,
            $dpi_interesado_valor,
            $id_estudiante,
            $dpi_estudiante_valor,
            $id_asesor,
            $id_juzgado,
            $id_tipo_exp,
            $fecha_inicio,
            $fecha_audiencia1,
            $fecha_audiencia2,
            $fecha_finalizacion,
            $id_estante,
            $observaciones
        ]);

        // Validar nuevo estante
        if (isset($_POST['id_estante']) && $_POST['id_estante'] === 'otro') {
            $nuevo_estante = trim($_POST['nuevo_numero_estante'] ?? '');

            // Validar que no esté vacío
            if (empty($nuevo_estante)) {
                $error = "El número de estante es obligatorio";
            }
            // Validar que sea numérico
            elseif (!is_numeric($nuevo_estante)) {
                $error = "El estante debe ser un número";
            }
            // Validar que sea entero positivo
            elseif (!ctype_digit($nuevo_estante)) {
                $error = "El estante debe ser un número entero";
            }
            // Validar que sea mayor a 0
            elseif ((int)$nuevo_estante < 1) {
                $error = "El estante debe ser un número positivo mayor a 0";
            }
            else {
                // Validación exitosa
                $numero_estante = (int)$nuevo_estante;

                // Verificar si ya existe (opcional)
                // $existe = verificarEstanteExistente($numero_estante);
                // if ($existe) {
                //     $error = "El estante $numero_estante ya existe";
                // }
            }
        }
        $id_expediente = $conn->lastInsertId();

        // Registrar en transacciones
        $ip = $_SERVER['REMOTE_ADDR'];
        $descripcion = "Creó expediente ID: $id_expediente - Ficha: $ficha_social, Cliente: ID $id_interesado" . 
                    ($dpi_interesado_valor ? " (DPI: $dpi_interesado_valor)" : "") . 
                    ", Estudiante: ID $id_estudiante" . 
                    ($dpi_estudiante_valor ? " (DPI: $dpi_estudiante_valor)" : "");

        $stmt_trans = $conn->prepare("INSERT INTO transacciones (id_usuario, tabla, id_registro, descripcion, fecha_hora, ip) VALUES (?, 'expedientes', ?, ?, NOW(), ?)");
        $stmt_trans->execute([$_SESSION['id_usuario'], $id_expediente, $descripcion, $ip]);

        $conn->commit();

        header("Location: index.php?mensaje=" . urlencode("✅ Expediente creado exitosamente con ID: $id_expediente"));
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Consultas para selects
$estudiantes = $conn->query("SELECT id_estudiante, dpi_estudiante, nombre, apellido, carnetEstudiantil, est.estado 
                            FROM estudiantes e
                            INNER JOIN estados est ON e.id_estado = est.id_estado
                            ORDER BY e.id_estado DESC, e.nombre, e.apellido")->fetchAll(PDO::FETCH_ASSOC);

$interesados = $conn->query("SELECT id_interesado, dpi_interesado, nombre, apellido
                            FROM interesados
                            ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);

$asesores = $conn->query("SELECT id_asesor, nombre, apellido 
                        FROM asesores
                        ORDER BY nombre, apellido")->fetchAll(PDO::FETCH_ASSOC);

$juzgados = $conn->query("SELECT id_juzgado, nombre
                        FROM juzgados
                        ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

$tipos = $conn->query("
    SELECT t.id_tipo_exp, t.caso, t.id_area, a.area
    FROM tipo_caso t
    LEFT JOIN areas a ON t.id_area = a.id_area
    ORDER BY a.area, t.caso
")->fetchAll(PDO::FETCH_ASSOC);

$estados = $conn->query("SELECT id_estado_exp, estado_exp
                        FROM estados_exp
                        ORDER BY estado_exp")->fetchAll(PDO::FETCH_ASSOC);

$estantes = $conn->query("SELECT id_estante, estante
                        FROM estantes
                        ORDER BY estante")->fetchAll(PDO::FETCH_ASSOC);

$areas = $conn->query("SELECT id_area, area
                        FROM areas
                        ORDER BY area")->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Expediente - Bufete Popular</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="styleExp.css">

    <!-- Select2 para búsqueda mejorada -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body class="form-body">

<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main>
    <div class="form-container">
        <h2 class="form-title">📝 Registrar Nuevo Expediente</h2>

        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($mensaje): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <!-- COLUMNA IZQUIERDA -->
            <div class="form-column">
                <div>
                    <label class="form-label campo-obligatorio">Ficha Social (Única)</label>
                    <input type="text" name="ficha_social" class="form-input" required 
                        value="<?= htmlspecialchars($_POST['ficha_social'] ?? '') ?>"
                        placeholder="Ingrese ficha social única">
                    <small style="color: #6B7280;">⚠️ La ficha social debe ser única en el sistema</small>
                </div>

                <div>
                    <label class="form-label">Número de caso en el año <span style="color: #6B7280; font-weight: normal;"></span></label>
                    <input type="text" name="numero_caso" id="numero-caso" class="form-input" 
                        value="<?= htmlspecialchars($_POST['numero_caso'] ?? '') ?>" 
                        placeholder="Dejar vacío si no aplica">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Año</label>
                    <input type="number" name="anio" class="form-input" required min="1900" max="2100"
                        value="<?= htmlspecialchars($_POST['anio'] ?? date('Y')) ?>">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Número de Proceso</label>
                    <input type="text" name="num_proceso" class="form-input" required
                        value="<?= htmlspecialchars($_POST['num_proceso'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Estado del Expediente</label>
                    <select name="id_estado_exp" class="form-input" required>
                        <option value="">-- Seleccionar Estado --</option>
                        <?php foreach($estados as $estado): ?>
                            <option value="<?= $estado['id_estado_exp'] ?>"
                                    <?= (($_POST['id_estado_exp'] ?? '') == $estado['id_estado_exp']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['estado_exp']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Número de Folios</label>
                    <input type="number" name="folios" class="form-input" min="1" required
                        value="<?= htmlspecialchars($_POST['folios'] ?? 0) ?>">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Cliente (Interesado)</label>
                    <select name="id_interesado" id="select-interesado" class="form-input select2" required style="width: 100%;">
                        <option value="">-- Buscar y seleccionar cliente --</option>
                        <?php foreach($interesados as $i): ?>
                            <option value="<?= $i['id_interesado'] ?>"
                                    <?= (($_POST['id_interesado'] ?? '') == $i['id_interesado']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($i['nombre'].' '.$i['apellido']) ?>
                                <?php if($i['dpi_interesado']): ?>
                                    - DPI: <?= htmlspecialchars($i['dpi_interesado']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="abrirModalInteresado()" class="btn-modal-agregar">
                        ➕ ¿No encuentras al cliente? Regístralo aquí
                    </button>
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Estudiante</label>
                    <select name="id_estudiante" id="select-estudiante" class="form-input select2" required style="width: 100%;">
                        <option value="">-- Buscar y seleccionar estudiante --</option>
                        <?php foreach($estudiantes as $e): ?>
                            <option value="<?= $e['id_estudiante'] ?>"
                                    <?= (($_POST['id_estudiante'] ?? '') == $e['id_estudiante']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['nombre'].' '.$e['apellido']) ?>
                                <?php if($e['dpi_estudiante']): ?>
                                    - DPI: <?= htmlspecialchars($e['dpi_estudiante']) ?>
                                <?php endif; ?>
                                <?= $e['carnetEstudiantil'] ? ' - Carnet: '.htmlspecialchars($e['carnetEstudiantil']) : '' ?>
                                <?php if($e['estado'] === 'Inactivo'): ?>
                                    - ⚠️ INACTIVO
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="abrirModalEstudiante()" class="btn-modal-agregar">
                        ➕ ¿No encuentras al estudiante? Regístralo aquí
                    </button>
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Asesor</label>
                    <select name="id_asesor" id="select-asesor" class="form-input select2" required style="width: 100%;">
                        <option value="">-- Buscar y seleccionar asesor --</option>
                        <?php foreach($asesores as $a): ?>
                            <option value="<?= $a['id_asesor'] ?>"
                                    <?= (($_POST['id_asesor'] ?? '') == $a['id_asesor']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        <button type="button" onclick="abrirModalAsesor()" class="btn-modal-agregar">
                            ➕ ¿No encuentras al asesor? Regístralo aquí
                        </button>
                </div>
            </div>

            <!-- COLUMNA DERECHA -->
            <div class="form-column">
                <div>
                    <label class="form-label campo-obligatorio">Juzgado</label>
                    <select name="id_juzgado" id="select-juzgado" class="form-input" required>
                        <option value="">-- Seleccionar Juzgado --</option>
                        <?php foreach($juzgados as $j): ?>
                            <option value="<?= $j['id_juzgado'] ?>"
                                    <?= (($_POST['id_juzgado'] ?? '') == $j['id_juzgado']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="otro">➕ Agregar Nuevo Juzgado</option>
                    </select>
                    <input type="text" id="nuevo-juzgado" name="nuevo_nombre_juzgado" 
                        class="form-input otro" placeholder="Nombre del nuevo juzgado" style="display: none;">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Tipo de Expediente</label>
                    <select name="id_tipo_exp" id="select-tipo" class="form-input" required>
                        <option value="">-- Seleccionar Tipo --</option>
                        <?php foreach($tipos as $t): ?>
                            <option value="<?= $t['id_tipo_exp'] ?>"
                                    <?= (($_POST['id_tipo_exp'] ?? '') == $t['id_tipo_exp']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['caso']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="otro">➕ Agregar Nuevo Tipo</option>
                    </select>
                    <input type="text" id="nuevo-tipo" name="nuevo_nombre_tipo" 
                        class="form-input otro" placeholder="Nombre del nuevo tipo de caso" style="display: none;">
                    <select name="nuevo_id_area" id="nueva-area" class="form-input otro" style="display: none;">
                        <option value="">-- Seleccionar Área Legal --</option>
                        <?php foreach($areas as $area): ?>
                            <option value="<?= $area['id_area'] ?>">
                                <?= htmlspecialchars($area['area']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-input" required
                        value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? date('Y-m-d')) ?>">
                </div>

                <div>
                    <label class="form-label campo-opcional">Fecha Audiencia 1</label>
                    <input type="date" name="fecha_audiencia1" class="form-input"
                        value="<?= htmlspecialchars($_POST['fecha_audiencia1'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label campo-opcional">Fecha Audiencia 2</label>
                    <input type="date" name="fecha_audiencia2" class="form-input"
                        value="<?= htmlspecialchars($_POST['fecha_audiencia2'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label campo-opcional">Fecha de Finalización</label>
                    <input type="date" name="fecha_finalizacion" class="form-input"
                        value="<?= htmlspecialchars($_POST['fecha_finalizacion'] ?? '') ?>">
                </div>

                <div>
                    <label class="form-label campo-obligatorio">Estante</label>
                    <select name="id_estante" id="select-estante" class="form-input" required>
                        <option value="">-- Seleccionar Estante --</option>
                        <?php foreach($estantes as $est): ?>
                            <option value="<?= $est['id_estante'] ?>"
                                    <?= (($_POST['id_estante'] ?? '') == $est['id_estante']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($est['estante']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="otro">➕ Agregar Nuevo Estante</option>
                    </select>
                    <input type="number" id="nuevo-estante" name="nuevo_numero_estante" class="form-input otro" placeholder="Número del nuevo estante" style="display: none;"min="1"step="1"pattern="[0-9]+"
                        oninput="validarEstante(this)">
                </div>

                <div>
                    <label class="form-label campo-opcional">Observaciones</label>
                    <textarea name="observaciones" class="form-input" rows="4"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- BOTONES -->
            <div class="form-buttons">
                <button type="submit" class="form-btn btn-registrar">💾 Registrar Expediente</button>
                <a href="index.php" class="form-btn btn-volver">❌ Volver</a>
            </div>
        </form>
    </div>
</main>


<!-- MODAL: AGREGAR ESTUDIANTE -->
<div id="modalEstudiante" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>➕ Agregar Nuevo Estudiante</h3>
            <button class="modal-close" onclick="cerrarModalEstudiante()">✕</button>
        </div>
        <form id="formEstudiante" onsubmit="guardarEstudiante(event)">
            <div class="modal-form-group">
                <label>Nombre *</label>
                <input type="text" id="est_nombre" required>
            </div>
            <div class="modal-form-group">
                <label>Apellido *</label>
                <input type="text" id="est_apellido" required>
            </div>
            <div class="modal-form-group">
                <label>DPI <span class="campo-opcional-modal"></span></label>
                <input type="text" id="est_dpi" maxlength="13" pattern="[0-9]{13}">
            </div>
            <div class="modal-form-group">
                <label>Carnet Estudiantil <span class="campo-opcional-modal"></span></label>
                <input type="text" id="est_carnet">
            </div>
            <div class="modal-form-group">
                <label>Teléfono <span class="campo-opcional-modal">(Opcional)</span></label>
                <input type="text" id="est_telefono">
            </div>
            <div class="modal-form-group">
                <label>Carrera *</label>
                <select id="est_carrera" required onchange="toggleNuevaCarrera()">
                    <option value="">-- Seleccionar --</option>
                    <?php
                    $carreras = $conn->query("SELECT * FROM carreras ORDER BY carrera")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($carreras as $c):
                    ?>
                        <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['carrera']) ?></option>
                    <?php endforeach; ?>
                    <option value="otros">➕ Otra carrera...</option>
                </select>
            </div>
            <div class="modal-form-group" id="nuevaCarreraDiv" style="display: none;">
                <label>Nombre de la nueva carrera *</label>
                <input type="text" id="est_nueva_carrera">
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn-modal-submit">💾 Guardar</button>
                <button type="button" class="btn-modal-cancel" onclick="cerrarModalEstudiante()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AGREGAR INTERESADO -->
<div id="modalInteresado" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>➕ Agregar Nuevo Cliente</h3>
            <button class="modal-close" onclick="cerrarModalInteresado()">✕</button>
        </div>
        <form id="formInteresado" onsubmit="guardarInteresado(event)">
            <div class="modal-form-group">
                <label>Nombre *</label>
                <input type="text" id="int_nombre" required>
            </div>

            <div class="modal-form-group">
                <label>Apellido *</label>
                <input type="text" id="int_apellido" required>
            </div>

            <div class="modal-form-group">
                <label>DPI/Cédula</label>
                <input type="text" id="int_dpi" maxlength="13"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    placeholder="Ej: 2345678901234 o 12345678">
                <small style="color: #6B7280; display: block; margin-top: 4px;">
                    8 a 13 dígitos (para cédulas antiguas o DPI nuevo)
                </small>
            </div>

            <div class="modal-form-group">
                <label>Teléfono <span class="campo-opcional-modal">(Opcional)</span></label>
                <input type="text" id="int_telefono" placeholder="Ej: 1234-5678">
            </div>

            <div class="modal-form-group">
                <label>Dirección Exacta *</label>
                <textarea id="int_direccion" rows="3" required 
                        placeholder="Ej: 5ta calle 3-25 zona 1"></textarea>
            </div>

            <div class="modal-form-group">
                <label>Municipio *</label>
                <select id="int_lugar" required onchange="toggleOtroLugar()">
                    <option value="">-- Seleccionar municipio --</option>
                    <?php
                    $lugares = $conn->query("SELECT id_lugar, municipio FROM lugares ORDER BY municipio")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($lugares as $lugar):
                    ?>
                        <option value="<?= $lugar['id_lugar'] ?>"><?= htmlspecialchars($lugar['municipio']) ?></option>
                    <?php endforeach; ?>
                    <option value="otros">➕ Otro municipio...</option>
                </select>
            </div>

            <!-- CAMPO DE NUEVO MUNICIPIO -->
            <div id="otroLugarDiv" style="display: none; margin-top: 16px; padding: 16px; background: #F3F4F6; border-radius: 8px; border: 2px solid #3B82F6;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1F2937;">
                    📍 Nombre del nuevo municipio *
                </label>
                <input type="text" id="int_otro_lugar" 
                    tyle="width: 100%; padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 14px;"
                <small style="color: #6B7280; display: block; margin-top: 8px;">
                    💡 Este municipio se agregará automáticamente a la lista
                </small>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn-modal-submit">💾 Guardar Cliente</button>
                <button type="button" class="btn-modal-cancel" onclick="cerrarModalInteresado()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: AGREGAR ASESOR -->
<div id="modalAsesor" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>➕ Agregar Nuevo Asesor</h3>
            <button class="modal-close" onclick="cerrarModalAsesor()">✕</button>
        </div>
        <form id="formAsesor" onsubmit="guardarAsesor(event)">
            <div class="modal-form-group">
                <label>Nombre *</label>
                <input type="text" id="ase_nombre" required>
            </div>
            <div class="modal-form-group">
                <label>Apellido *</label>
                <input type="text" id="ase_apellido" required>
            </div>
            <div class="modal-form-group">
                <label>Teléfono <span class="campo-opcional-modal">(Opcional)</span></label>
                <input type="text" id="ase_telefono">
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn-modal-submit">💾 Guardar</button>
                <button type="button" class="btn-modal-cancel" onclick="cerrarModalAsesor()">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php include('../footer.php'); ?>


<script>
// ========================================
// CONFIGURACIÓN DE SELECT2
// ========================================
$(document).ready(function() {
    // Select2 normal para los demás campos
    $('.select2').select2({
        placeholder: 'Buscar...',
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Select2 especial para tipo de expediente con búsqueda por área
    $('.select2-tipo').select2({
        placeholder: 'Buscar por nombre o área legal...',
        allowClear: true,
        matcher: function(params, data) {
            // Si no hay búsqueda, mostrar todo
            if ($.trim(params.term) === '') {
                return data;
            }

            // No buscar en la opción "Agregar nuevo"
            if (data.id === 'otro') {
                return data;
            }
            // Buscar en el texto y en el atributo data-area
            const searchTerm = params.term.toLowerCase();
            const text = data.text.toLowerCase();
            const area = $(data.element).data('area');
            const areaText = area ? area.toString().toLowerCase() : '';

            if (text.indexOf(searchTerm) > -1 || areaText.indexOf(searchTerm) > -1) {
                return data;
            }

            return null;
        },
        language: {
            noResults: function() {
                return "No se encontró ningún tipo de expediente";
            },
            searching: function() {
                return "Buscando...";
            },
            placeholder: function() {
                return "Buscar por nombre o área (ej: Civil, Penal, Laboral...)";
            }
        }
    });
});

// ========================================
// MODALES - ABRIR/CERRAR
// ========================================
function abrirModalEstudiante() {
    document.getElementById('modalEstudiante').classList.add('active');
    document.getElementById('est_nombre').focus();
}

function cerrarModalEstudiante() {
    document.getElementById('modalEstudiante').classList.remove('active');
    document.getElementById('formEstudiante').reset();
    document.getElementById('nuevaCarreraDiv').style.display = 'none';
}

function abrirModalInteresado() {
    document.getElementById('modalInteresado').classList.add('active');
    document.getElementById('int_nombre').focus();
}

function cerrarModalInteresado() {
    document.getElementById('modalInteresado').classList.remove('active');
    document.getElementById('formInteresado').reset();
}

function abrirModalAsesor() {
    document.getElementById('modalAsesor').classList.add('active');
    document.getElementById('ase_nombre').focus();
}

function cerrarModalAsesor() {
    document.getElementById('modalAsesor').classList.remove('active');
    document.getElementById('formAsesor').reset();
}

// Cerrar modal al hacer clic fuera
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Mostrar/ocultar campo para nuevo juzgado
document.getElementById('select-juzgado').addEventListener('change', function() {
    const campo = document.getElementById('nuevo-juzgado');
    if (this.value === 'otro') {
        campo.style.display = 'block';
        campo.required = true;
    } else {
        campo.style.display = 'none';
        campo.required = false;
        campo.value = '';
    }
});

// Mostrar/ocultar campos para nuevo tipo de expediente
document.getElementById('select-tipo').addEventListener('change', function() {
    const campoTipo = document.getElementById('nuevo-tipo');
    const campoArea = document.getElementById('nueva-area');
    const mostrar = this.value === 'otro';

    campoTipo.style.display = mostrar ? 'block' : 'none';
    campoArea.style.display = mostrar ? 'block' : 'none';
    campoTipo.required = mostrar;
    campoArea.required = mostrar;

    if (!mostrar) {
        campoTipo.value = '';
        campoArea.value = '';
    }
});


// Mostrar/ocultar campo para nuevo estante
document.getElementById('select-estante').addEventListener('change', function() {
    const campo = document.getElementById('nuevo-estante');
    if (this.value === 'otro') {
        campo.style.display = 'block';
        campo.required = true;
    } else {
        campo.style.display = 'none';
        campo.required = false;
        campo.value = '';
    }
});

// Función para validar el número de caso
function validarNumeroCaso(input) {
    // Eliminar cualquier caracter que no sea número
    input.value = input.value.replace(/[^0-9]/g, '');

    // Convertir a número y validar que sea positivo
    let valor = parseInt(input.value);

    if (valor < 1 && input.value !== '') {
        input.value = '';
    }

    // Eliminar ceros a la izquierda
    if (input.value.length > 1 && input.value[0] === '0') {
        input.value = input.value.replace(/^0+/, '');
    }
}

// Prevenir entrada de caracteres no numéricos en número de caso
document.getElementById('numero-caso').addEventListener('keypress', function(e) {
    if (e.key < '0' || e.key > '9') {
        e.preventDefault();
    }
});

// Prevenir pegado de contenido no numérico en número de caso
document.getElementById('numero-caso').addEventListener('paste', function(e) {
    e.preventDefault();
    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
    const numericValue = pastedText.replace(/[^0-9]/g, '');
    if (numericValue) {
        this.value = numericValue;
        validarNumeroCaso(this);
    }
});

// Función para validar el estante
function validarEstante(input) {
    // Eliminar cualquier caracter que no sea número
    input.value = input.value.replace(/[^0-9]/g, '');

    // Convertir a número y validar que sea positivo
    let valor = parseInt(input.value);

    if (valor < 1 && input.value !== '') {
        input.value = '';
    }

    // Eliminar ceros a la izquierda
    if (input.value.length > 1 && input.value[0] === '0') {
        input.value = input.value.replace(/^0+/, '');
    }
}

// Validar antes de enviar el formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const selectEstante = document.getElementById('select-estante');
    const nuevoEstante = document.getElementById('nuevo-estante');

    if (selectEstante.value === 'otro') {
        const valor = nuevoEstante.value.trim();

        if (valor === '') {
            e.preventDefault();
            alert('Por favor, ingrese el número del nuevo estante');
            nuevoEstante.focus();
            return false;
        }
        
        if (!/^[1-9][0-9]*$/.test(valor)) {
            e.preventDefault();
            alert('El número de estante debe ser un número positivo válido');
            nuevoEstante.focus();
            return false;
        }
        
        const numero = parseInt(valor);
        if (numero < 1) {
            e.preventDefault();
            alert('El número de estante debe ser mayor a 0');
            nuevoEstante.focus();
            return false;
        }
    }
});

// Prevenir entrada de caracteres no numéricos
document.getElementById('nuevo-estante').addEventListener('keypress', function(e) {
    // Solo permitir números
    if (e.key < '0' || e.key > '9') {
        e.preventDefault();
    }
});

// Prevenir pegado de contenido no numérico
document.getElementById('nuevo-estante').addEventListener('paste', function(e) {
    e.preventDefault();
    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
    const numericValue = pastedText.replace(/[^0-9]/g, '');
    if (numericValue) {
        this.value = numericValue;
        validarEstante(this);
    }
});

// ========================================
// TOGGLE NUEVA CARRERA
// ========================================
function toggleNuevaCarrera() {
    const select = document.getElementById('est_carrera');
    const div = document.getElementById('nuevaCarreraDiv');
    const input = document.getElementById('est_nueva_carrera');
    
    if (select.value === 'otros') {
        div.style.display = 'block';
        input.required = true;
    } else {
        div.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
    // ========== MODAL INTERESADO - ABRIR/CERRAR ==========
function abrirModalInteresado() {
    document.getElementById('modalInteresado').classList.add('active');
    document.getElementById('int_nombre').focus();
}

function cerrarModalInteresado() {
    document.getElementById('modalInteresado').classList.remove('active');
    document.getElementById('formInteresado').reset();
    document.getElementById('otroLugarDiv').style.display = 'none';
}

// ========== TOGGLE OTRO MUNICIPIO ==========
function toggleOtroLugar() {
    const select = document.getElementById('int_lugar');
    const div = document.getElementById('otroLugarDiv');
    const input = document.getElementById('int_otro_lugar');

    console.log('toggleOtroLugar llamado, valor:', select.value); // Debug

    if (select.value === 'otros') {
        // Mostrar con animación
        div.style.display = 'block';
        input.required = true;
        input.focus(); // Poner foco en el campo

        console.log('Campo mostrado'); // Debug
    } else {
        // Ocultar
        div.style.display = 'none';
        input.required = false;
        input.value = '';

        console.log('Campo oculto'); // Debug
    }
}


// ========================================
// GUARDAR ESTUDIANTE VÍA AJAX
// ========================================
function guardarEstudiante(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.append('nombre', document.getElementById('est_nombre').value);
    formData.append('apellido', document.getElementById('est_apellido').value);
    formData.append('dpi', document.getElementById('est_dpi').value);
    formData.append('carnet', document.getElementById('est_carnet').value);
    formData.append('telefono', document.getElementById('est_telefono').value);
    formData.append('id_carrera', document.getElementById('est_carrera').value);
    formData.append('nueva_carrera', document.getElementById('est_nueva_carrera').value);

    const modal = document.getElementById('modalEstudiante');
    modal.classList.add('loading');

    fetch('ajax_crear_estudiante.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        modal.classList.remove('loading');

        if (data.success) {
            // Agregar el nuevo estudiante al select
            const select = document.getElementById('select-estudiante');
            const option = document.createElement('option');
            option.value = data.estudiante.id;
            option.selected = true;

            let texto = data.estudiante.nombre + ' ' + data.estudiante.apellido;
            if (data.estudiante.dpi) texto += ' - DPI: ' + data.estudiante.dpi;
            if (data.estudiante.carnet) texto += ' - Carnet: ' + data.estudiante.carnet;

            option.textContent = texto;
            select.appendChild(option);

            // Actualizar Select2
            $(select).trigger('change');

            // Cerrar modal
            cerrarModalEstudiante();

            // Mostrar mensaje
            alert('✅ ' + data.mensaje);
        } else {
            alert('❌ ' + data.mensaje);
        }
    })
    .catch(error => {
        modal.classList.remove('loading');
        alert('❌ Error de conexión: ' + error);
    });
}

// ========================================
// GUARDAR interesado VÍA AJAX
// ========================================
function guardarInteresado(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('nombre', document.getElementById('int_nombre').value);
    formData.append('apellido', document.getElementById('int_apellido').value);
    formData.append('dpi', document.getElementById('int_dpi').value);
    formData.append('telefono', document.getElementById('int_telefono').value);
    formData.append('direccion', document.getElementById('int_direccion').value);
    formData.append('id_lugar', document.getElementById('int_lugar').value);
    formData.append('otro_lugar', document.getElementById('int_otro_lugar').value);

    const modal = document.getElementById('modalInteresado');
    modal.classList.add('loading');

    fetch('ajax_crear_interesado.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        modal.classList.remove('loading');

        if (data.success) {
            // Agregar el nuevo interesado al select
            const select = document.getElementById('select-interesado');
            const option = document.createElement('option');
            option.value = data.interesado.id;
            option.selected = true;

            let texto = data.interesado.nombre + ' ' + data.interesado.apellido;
            if (data.interesado.dpi) texto += ' - DPI: ' + data.interesado.dpi;

            option.textContent = texto;
            select.appendChild(option);

            // Actualizar Select2
            $(select).trigger('change');

            // Cerrar modal
            cerrarModalInteresado();

            // Mostrar mensaje
            alert('✅ ' + data.mensaje);
        } else {
            alert('❌ ' + data.mensaje);
        }
    })
    .catch(error => {
        modal.classList.remove('loading');
        alert('❌ Error de conexión: ' + error);
    });
}
// ========================================
// GUARDAR ASESOR VÍA AJAX
// ========================================
function guardarAsesor(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.append('nombre', document.getElementById('ase_nombre').value);
    formData.append('apellido', document.getElementById('ase_apellido').value);
    formData.append('telefono', document.getElementById('ase_telefono').value);
    const modal = document.getElementById('modalAsesor');
    modal.classList.add('loading');
    fetch('ajax_crear_asesor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        modal.classList.remove('loading');
        if (data.success) {
            // Agregar el nuevo asesor al select
            const select = document.getElementById('select-asesor');
            const option = document.createElement('option');
            option.value = data.asesor.id;
            option.selected = true;
            option.textContent = data.asesor.nombre + ' ' + data.asesor.apellido;
            select.appendChild(option);

            // Actualizar Select2
            $(select).trigger('change');

            // Cerrar modal
            cerrarModalAsesor();

            // Mostrar mensaje
            alert('✅ ' + data.mensaje);
        } else {
            alert('❌ ' + data.mensaje);
        }
    })
    .catch(error => {
        modal.classList.remove('loading');
        alert('❌ Error de conexión: ' + error);
    });
}
</script>
</body>
</html>