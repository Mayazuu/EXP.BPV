<?php
include('../conexion.php');
include('../session_config.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// ===== BÚSQUEDA MEJORADA CON CAMPOS SEPARADOS =====
$buscar_nombre = isset($_GET['buscar_nombre']) ? trim($_GET['buscar_nombre']) : '';
$buscar_apellido = isset($_GET['buscar_apellido']) ? trim($_GET['buscar_apellido']) : '';
$buscar_otro = isset($_GET['buscar_otro']) ? trim($_GET['buscar_otro']) : '';

// ===== PAGINACIÓN =====
$registros_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

// WHERE para búsqueda con múltiples condiciones
$where_conditions = [];
$params = [];

if (!empty($buscar_nombre)) {
    $where_conditions[] = "s.nombre LIKE :nombre";
    $params[':nombre'] = "%{$buscar_nombre}%";
}

if (!empty($buscar_apellido)) {
    $where_conditions[] = "s.apellido LIKE :apellido";
    $params[':apellido'] = "%{$buscar_apellido}%";
}

if (!empty($buscar_otro)) {
    $where_conditions[] = "(s.id_estudiante LIKE :otro OR s.dpi_estudiante LIKE :otro OR s.carnetEstudiantil LIKE :otro)";
    $params[':otro'] = "%{$buscar_otro}%";
}

$where = '';
if (!empty($where_conditions)) {
    $where = "WHERE " . implode(" AND ", $where_conditions);
}

// ====== CONTAR TOTAL ======
$sql_count = "SELECT COUNT(*) FROM estudiantes s {$where}";
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_estudiantes = (int)$stmt_count->fetchColumn();

$total_paginas = (int)ceil($total_estudiantes / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ====== CONSULTA PRINCIPAL ======
$sql = "
    SELECT s.id_estudiante, s.dpi_estudiante, s.carnetEstudiantil, s.nombre, s.apellido, s.telefono,
    c.carrera, c.id_carrera, e.estado, e.id_estado
    FROM estudiantes s
    INNER JOIN carreras c ON s.id_carrera = c.id_carrera
    INNER JOIN estados e ON s.id_estado = e.id_estado
    {$where}
    ORDER BY s.id_estudiante DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener carreras y estados para los modales
$carreras = $conn->query("SELECT * FROM carreras ORDER BY carrera")->fetchAll(PDO::FETCH_ASSOC);
$estados = $conn->query("SELECT * FROM estados")->fetchAll(PDO::FETCH_ASSOC);

// ====== INFORMACIÓN DE RANGO ======
$registro_inicio = $total_estudiantes > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_estudiantes);
$paginaAnterior = max(1, $pagina_actual - 1);
$paginaSiguiente = min($total_paginas, $pagina_actual + 1);

// Función para construir URL de paginación
function buildPaginationUrl($pagina) {
    global $buscar_nombre, $buscar_apellido, $buscar_otro;
    $params = ['pagina' => $pagina];
    if (!empty($buscar_nombre)) $params['buscar_nombre'] = $buscar_nombre;
    if (!empty($buscar_apellido)) $params['buscar_apellido'] = $buscar_apellido;
    if (!empty($buscar_otro)) $params['buscar_otro'] = $buscar_otro;
    return '?' . http_build_query($params);
}

// Verificar si hay búsqueda activa
$hay_busqueda = !empty($buscar_nombre) || !empty($buscar_apellido) || !empty($buscar_otro);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Estudiantes - Bufete Popular</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="styleE.css">
</head>
<body>

<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main class="main-content">
    <div class="container">
        <div class="card">
            <!-- HEADER -->
            <div class="card-header">
                <h1>👨‍🎓 Gestión de Estudiantes</h1>
            </div>

            <!-- BARRA DE ACCIONES CON BÚSQUEDA MEJORADA -->
            <div class="action-bar">
                <form method="GET" action="" class="search-form-advanced">
                    <div class="search-group">
                        <label for="buscar_nombre">Nombre</label>
                        <input type="text"
                            id="buscar_nombre"
                            name="buscar_nombre"
                            value="<?= htmlspecialchars($buscar_nombre) ?>">
                    </div>

                    <div class="search-group">
                        <label for="buscar_apellido">Apellido</label>
                        <input type="text"
                            id="buscar_apellido"
                            name="buscar_apellido"
                            value="<?= htmlspecialchars($buscar_apellido) ?>">
                    </div>

                    <div class="search-group">
                        <label for="buscar_otro">🔍 ID / DPI / Carnet</label>
                        <input type="text"
                            id="buscar_otro"
                            name="buscar_otro"
                            placeholder="Ej: 14168987"
                            value="<?= htmlspecialchars($buscar_otro) ?>">
                    </div>

                    <div class="search-buttons">
                        <button type="submit" class="btn btn-primary" title="Buscar estudiantes">
                            🔍 Buscar
                        </button>
                        <?php if ($hay_busqueda): ?>
                            <a href="index.php" class="btn btn-clear" title="Limpiar búsqueda">
                                ✖
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <button onclick="abrirModalCrear()" class="btn btn-success">
                    ➕ Nuevo Estudiante
                </button>
            </div>

            <!-- TAGS DE FILTROS ACTIVOS -->
            <?php if ($hay_busqueda): ?>
                <div class="filter-tags">
                    <?php if (!empty($buscar_nombre)): ?>
                        <span class="filter-tag">
                            <strong>Nombre:</strong> <?= htmlspecialchars($buscar_nombre) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($buscar_apellido)): ?>
                        <span class="filter-tag">
                            <strong>Apellido:</strong> <?= htmlspecialchars($buscar_apellido) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($buscar_otro)): ?>
                        <span class="filter-tag">
                            <strong>ID/DPI/Carnet:</strong> <?= htmlspecialchars($buscar_otro) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ALERTA DE BÚSQUEDA -->
            <?php if ($hay_busqueda): ?>
                <div class="alert alert-info">
                    📌 Resultados encontrados: <strong><?= $total_estudiantes ?></strong> estudiante<?= $total_estudiantes != 1 ? 's' : '' ?>
                </div>
            <?php endif; ?>

            <!-- TABLA -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>DPI</th>
                            <th>Carnet</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Teléfono</th>
                            <th>Carrera</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($estudiantes)): ?>
                            <tr>
                                <td colspan="9" class="text-center">
                                    <?php if ($hay_busqueda): ?>
                                        ❌ No se encontraron estudiantes con los criterios de búsqueda especificados
                                    <?php else: ?>
                                        📋 No hay estudiantes registrados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($estudiantes as $est): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($est['id_estudiante']) ?></strong></td>
                                    <td>
                                        <?= $est['dpi_estudiante']
                                            ? htmlspecialchars($est['dpi_estudiante'])
                                            : '<span style="color:#999;">No registrado</span>' ?>
                                    </td>
                                    <td>
                                        <?= $est['carnetEstudiantil']
                                            ? htmlspecialchars($est['carnetEstudiantil'])
                                            : '<span style="color:#999;">No registrado</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($est['nombre']) ?></td>
                                    <td><?= htmlspecialchars($est['apellido']) ?></td>
                                    <td>
                                        <?= $est['telefono']
                                            ? htmlspecialchars($est['telefono'])
                                            : '<span style="color:#999;">No registrado</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($est['carrera']) ?></td>
                                    <td>
                                        <span class="badge <?= $est['id_estado'] == 1 ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= htmlspecialchars($est['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button onclick="abrirModalEditar(
                                                <?= $est['id_estudiante'] ?>,
                                                '<?= htmlspecialchars($est['dpi_estudiante'] ?? '', ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['carnetEstudiantil'] ?? '', ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['nombre'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['apellido'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['telefono'] ?? '', ENT_QUOTES) ?>',
                                                <?= $est['id_carrera'] ?>
                                            )" class="btn-icon btn-edit" title="Editar estudiante">
                                                ✏️
                                            </button>

                                            <!-- ========== RESTRICCIÓN DE DESACTIVAR/ACTIVAR - SOLO DIRECTORA ========== -->
                                            <?php if ($_SESSION['rol'] === 'Directora'): ?>
                                                <!-- DIRECTORA: Ver botones funcionales -->
                                                <?php if ($est['id_estado'] == 1): ?>
                                                    <button onclick="abrirModalDesactivar(
                                                        <?= $est['id_estudiante'] ?>,
                                                        '<?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido'], ENT_QUOTES) ?>'
                                                    )" class="btn-icon btn-delete" title="Desactivar estudiante">
                                                        🚫
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="abrirModalActivar(
                                                        <?= $est['id_estudiante'] ?>,
                                                        '<?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido'], ENT_QUOTES) ?>'
                                                    )" class="btn-icon btn-activate" title="Activar estudiante">
                                                        ✅
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- SECRETARIA: Ver botón deshabilitado -->
                                                <button class="btn-icon btn-disabled" title="Sin permisos" disabled>
                                                    🚫
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Mostrando <?= $registro_inicio ?> - <?= $registro_fin ?> de <?= $total_estudiantes ?> estudiantes
                    </div>
                    <div class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="<?= buildPaginationUrl(1) ?>" class="pagination-btn">⏮️ Primera</a>
                            <a href="<?= buildPaginationUrl($paginaAnterior) ?>" class="pagination-btn">⬅️ Anterior</a>
                        <?php endif; ?>

                        <?php
                        $inicio = max(1, $pagina_actual - 2);
                        $fin = min($total_paginas, $pagina_actual + 2);

                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <a href="<?= buildPaginationUrl($i) ?>"
                               class="pagination-btn <?= $i == $pagina_actual ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="<?= buildPaginationUrl($paginaSiguiente) ?>" class="pagination-btn">Siguiente ➡️</a>
                            <a href="<?= buildPaginationUrl($total_paginas) ?>" class="pagination-btn">Última ⏭️</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ========================================
            MODALES
======================================== -->

<!--  MODAL CREAR ESTUDIANTE -->
<div id="modalCrear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Registrar Nuevo Estudiante</h2>
            <span class="modal-close" onclick="cerrarModalCrear()">&times;</span>
        </div>
        <form id="formCrear" method="POST" action="crear_est.php">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="dpi_crear">DPI <span style="color:#999;"></span></label>
                        <input type="text" id="dpi_crear" name="dpi_estudiante"
                        maxlength="13"
                        pattern="\d{13}"
                        title="13 dígitos"
                        placeholder="1234567890123">
                        <small style="color:#666;">Dejar vacío si no se conoce</small>
                    </div>

                    <div class="form-group">
                        <label for="carnet_crear">Carnet Estudiantil <span style="color:#999;"></span></label>
                        <input type="text" id="carnet_crear" name="carnetEstudiantil"
                        maxlength="7"
                        pattern="\d{7}"
                        title="7 dígitos"
                        placeholder="1234567">
                        <small style="color:#666;">Dejar vacío si no se conoce</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_crear">Nombre *</label>
                        <input type="text" id="nombre_crear" name="nombre"
                        maxlength="100" required
                        placeholder="Ingrese el nombre">
                    </div>

                    <div class="form-group">
                        <label for="apellido_crear">Apellido *</label>
                        <input type="text" id="apellido_crear" name="apellido"
                        maxlength="100" required
                        placeholder="Ingrese el apellido">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono_crear">
                        Teléfono <span style="color: #6B7280; font-weight: normal;">(Opcional)</span>
                    </label>
                    <input type="tel" id="telefono_crear" name="telefono"
                    maxlength="20"
                    placeholder="1234-5678">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📱 Deje vacío si no tiene teléfono
                    </small>
                </div>

                <div class="form-group">
                    <label for="carrera_crear">Carrera *</label>
                    <select id="carrera_crear" name="id_carrera" required onchange="toggleNuevaCarreraCrear()">
                        <option value="">Seleccione una carrera</option>
                        <?php foreach($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['carrera']) ?></option>
                        <?php endforeach; ?>
                        <option value="otros">➕ Otros (Agregar nueva carrera)</option>
                    </select>
                </div>

                <div class="form-group" id="nuevaCarreraCrearContainer" style="display: none;">
                    <label for="nueva_carrera_crear">Nueva Carrera *</label>
                    <input type="text" id="nueva_carrera_crear" name="nueva_carrera"
                        maxlength="100"
                        placeholder="Ingrese el nombre de la nueva carrera">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📝 Esta carrera se agregará al sistema
                    </small>
                </div>

                <div class="form-group">
                    <label for="estado_crear">Estado *</label>
                    <select id="estado_crear" name="id_estado" required>
                        <?php foreach($estados as $e): ?>
                            <option value="<?= $e['id_estado'] ?>" <?= $e['id_estado'] == 1 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-success">
                    💾 Registrar Estudiante
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCrear()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR ESTUDIANTE  -->
<div id="modalEditar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Editar Estudiante</h2>
            <span class="modal-close" onclick="cerrarModalEditar()">&times;</span>
        </div>
        <form id="formEditar" method="POST" action="editar_est.php">
            <div class="modal-body">
                <div class="info-box">
                    <strong>📌 Estudiante:</strong>
                    <div id="infoEstudiante"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dpi_editar">DPI <span style="color:#999;">(Opcional)</span></label>
                        <input type="text" id="dpi_editar_input" name="dpi_estudiante"
                        maxlength="13"
                        pattern="\d{13}"
                        title="13 dígitos"
                        placeholder="1234567890123">
                        <small style="color:#666;">Dejar vacío si no se conoce</small>
                    </div>

                    <div class="form-group">
                        <label for="carnet_editar_input">Carnet <span style="color:#999;">(Opcional)</span></label>
                        <input type="text" id="carnet_editar_input" name="carnetEstudiantil"
                        maxlength="7"
                        pattern="\d{7}"
                        title="7 dígitos"
                        placeholder="1234567">
                        <small style="color:#666;">Dejar vacío si no se conoce</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_editar">Nombre *</label>
                        <input type="text" id="nombre_editar" name="nombre"
                        maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label for="apellido_editar">Apellido *</label>
                        <input type="text" id="apellido_editar" name="apellido"
                        maxlength="100" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono_editar">
                        Teléfono <span style="color: #6B7280; font-weight: normal;">(Opcional)</span>
                    </label>
                    <input type="tel" id="telefono_editar" name="telefono"
                        maxlength="20"
                        placeholder="1234-5678">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📱 Deje vacío si no tiene teléfono
                    </small>
                </div>

                <div class="form-group">
                    <label for="carrera_editar">Carrera *</label>
                    <select id="carrera_editar" name="id_carrera" required onchange="toggleNuevaCarreraEditar()">
                        <?php foreach($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['carrera']) ?></option>
                        <?php endforeach; ?>
                        <option value="otros">➕ Otros (Agregar nueva carrera)</option>
                    </select>
                </div>

                <div class="form-group" id="nuevaCarreraEditarContainer" style="display: none;">
                    <label for="nueva_carrera_editar">Nueva Carrera *</label>
                    <input type="text" id="nueva_carrera_editar" name="nueva_carrera"
                        maxlength="100"
                        placeholder="Ingrese el nombre de la nueva carrera">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📝 Esta carrera se agregará al sistema
                    </small>
                </div>

                <input type="hidden" id="id_estudiante_editar" name="id_estudiante">
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">
                    💾 Actualizar Estudiante
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalEditar()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DESACTIVAR -->
<div id="modalDesactivar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚠️ Confirmar Desactivación</h3>
            <span class="modal-close" onclick="cerrarModalDesactivar()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoDesactivar"></p>
            <div class="form-group">
                <label for="razonDesactivar">Motivo de la desactivación *</label>
                <textarea id="razonDesactivar" rows="3" placeholder="Ingrese el motivo..."></textarea>
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="ejecutarDesactivar()" class="btn btn-danger">🚫 Desactivar</button>
            <button onclick="cerrarModalDesactivar()" class="btn btn-secondary">❌ Cancelar</button>
        </div>
    </div>
</div>

<!-- MODAL ACTIVAR  -->
<div id="modalActivar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✅ Confirmar Activación</h3>
            <span class="modal-close" onclick="cerrarModalActivar()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoActivar"></p>
            <div class="info-box" style="background-color: #D1FAE5; border-left: 4px solid #10B981;">
                ℹ️ Al activar el estudiante podra asignarlo a futuros prestamos.
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="ejecutarActivar()" class="btn btn-success">✅ Activar Estudiante</button>
            <button onclick="cerrarModalActivar()" class="btn btn-secondary">❌ Cancelar</button>
        </div>
    </div>
</div>

<!-- MODAL MENSAJE -->
<div id="modalMensaje" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ℹ️ Notificación</h3>
            <span class="modal-close" onclick="cerrarModalMensaje()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="mensajeTexto"></p>
        </div>
        <div class="modal-buttons">
            <button onclick="cerrarModalMensaje()" class="btn btn-primary">Aceptar</button>
        </div>
    </div>
</div>

<?php include('../footer.php'); ?>

<script>
// ========== VARIABLES GLOBALES ==========
let idEstudianteDesactivar = null;
let idEstudianteActivar = null;

// ========== TOGGLE NUEVA CARRERA ==========
function toggleNuevaCarreraCrear() {
    const select = document.getElementById('carrera_crear');
    const container = document.getElementById('nuevaCarreraCrearContainer');
    const input = document.getElementById('nueva_carrera_crear');

    if (select.value === 'otros') {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function toggleNuevaCarreraEditar() {
    const select = document.getElementById('carrera_editar');
    const container = document.getElementById('nuevaCarreraEditarContainer');
    const input = document.getElementById('nueva_carrera_editar');

    if (select.value === 'otros') {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

// ========== MODAL CREAR ==========
function abrirModalCrear() {
    document.getElementById('modalCrear').style.display = 'flex';
    document.getElementById('nombre_crear').focus();
}

function cerrarModalCrear() {
    document.getElementById('modalCrear').style.display = 'none';
    document.getElementById('formCrear').reset();
    document.getElementById('nuevaCarreraCrearContainer').style.display = 'none';
    document.getElementById('nueva_carrera_crear').required = false;
}

// MODAL EDITAR
function abrirModalEditar(idEstudiante, dpi, carnet, nombre, apellido, telefono, idCarrera) {
    document.getElementById('modalEditar').style.display = 'flex';
    document.getElementById('id_estudiante_editar').value = idEstudiante;
    document.getElementById('dpi_editar_input').value = dpi || '';
    document.getElementById('carnet_editar_input').value = carnet || '';
    document.getElementById('nombre_editar').value = nombre;
    document.getElementById('apellido_editar').value = apellido;
    document.getElementById('telefono_editar').value = telefono || '';
    document.getElementById('carrera_editar').value = idCarrera;
    
    document.getElementById('infoEstudiante').innerHTML = `
        <strong>ID:</strong> ${idEstudiante}<br>
        <strong>Nombre:</strong> ${nombre} ${apellido}
    `;
    
    document.getElementById('nuevaCarreraEditarContainer').style.display = 'none';
    document.getElementById('nueva_carrera_editar').required = false;
    document.getElementById('nombre_editar').focus();
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
    document.getElementById('formEditar').reset();
    document.getElementById('nuevaCarreraEditarContainer').style.display = 'none';
    document.getElementById('nueva_carrera_editar').required = false;
}

// MODAL DESACTIVAR
function abrirModalDesactivar(idEstudiante, nombre) {
    idEstudianteDesactivar = idEstudiante;
    document.getElementById('textoDesactivar').innerHTML =
        `¿Está seguro de desactivar al estudiante?<br><br><strong style="color: #EF4444;">${nombre}</strong><br><br><em>Esta acción cambiará su estado a Inactivo.</em>`;
    document.getElementById('razonDesactivar').value = '';
    document.getElementById('modalDesactivar').style.display = 'flex';
}

function ejecutarDesactivar() {
    const razon = document.getElementById('razonDesactivar').value.trim();
    if (!razon) {
        alert('⚠️ Debe ingresar un motivo');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'desactivar_est.php';

    const inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id_estudiante';
    inputId.value = idEstudianteDesactivar;

    const inputRazon = document.createElement('input');
    inputRazon.type = 'hidden';
    inputRazon.name = 'razon';
    inputRazon.value = razon;

    form.appendChild(inputId);
    form.appendChild(inputRazon);
    document.body.appendChild(form);
    form.submit();
}

function cerrarModalDesactivar() {
    document.getElementById('modalDesactivar').style.display = 'none';
}

// ACTIVAR ESTUDIANTE
function abrirModalActivar(idEstudiante, nombre) {
    idEstudianteActivar = idEstudiante;
    document.getElementById('textoActivar').innerHTML =
        `¿Está seguro de activar al estudiante?<br><br><strong style="color: #10B981;">${nombre}</strong><br><br><em>Esta acción cambiará su estado a Activo.</em>`;
    document.getElementById('modalActivar').style.display = 'flex';
}

function ejecutarActivar() {
    window.location.href = 'activar_est.php?id=' + idEstudianteActivar;
}

function cerrarModalActivar() {
    document.getElementById('modalActivar').style.display = 'none';
}

// ========== MODAL MENSAJE ==========
function cerrarModalMensaje() {
    document.getElementById('modalMensaje').style.display = 'none';
}

// ========== VALIDACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    // Validar solo números en DPI y Carnet
    const dpiInputs = [
        document.getElementById('dpi_crear'),
        document.getElementById('dpi_editar_input')
    ];

    const carnetInputs = [
        document.getElementById('carnet_crear'),
        document.getElementById('carnet_editar_input')
    ];

    dpiInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        }
    });

    carnetInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        }
    });

    // Mostrar mensaje si viene en URL
    const params = new URLSearchParams(window.location.search);
    const mensaje = params.get('mensaje');
    if (mensaje) {
        document.getElementById('mensajeTexto').textContent = mensaje;
        document.getElementById('modalMensaje').style.display = 'flex';
    }
});

// ========== CERRAR MODALES AL HACER CLIC FUERA ==========
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
<script src="../js/session_timeout.js"></script>
</body>
</html>