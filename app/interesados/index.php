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
$buscar_documento = isset($_GET['buscar_documento']) ? trim($_GET['buscar_documento']) : '';

// ===== PAGINACIÓN =====
$registros_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

// WHERE para búsqueda con múltiples condiciones
$where_conditions = [];
$params = [];

if (!empty($buscar_nombre)) {
    $where_conditions[] = "i.nombre LIKE :nombre";
    $params[':nombre'] = "%{$buscar_nombre}%";
}

if (!empty($buscar_apellido)) {
    $where_conditions[] = "i.apellido LIKE :apellido";
    $params[':apellido'] = "%{$buscar_apellido}%";
}

if (!empty($buscar_documento)) {
    $where_conditions[] = "(i.id_interesado LIKE :documento OR i.dpi_interesado LIKE :documento OR i.telefono LIKE :documento)";
    $params[':documento'] = "%{$buscar_documento}%";
}

$where = '';
if (!empty($where_conditions)) {
    $where = "WHERE " . implode(" AND ", $where_conditions);
}

// ====== CONTAR TOTAL ======
$sql_count = "SELECT COUNT(*) FROM interesados i {$where}";
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_interesados = (int)$stmt_count->fetchColumn();

$total_paginas = (int)ceil($total_interesados / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ====== CONSULTA PRINCIPAL ======
$sql = "
    SELECT i.id_interesado, i.dpi_interesado, i.nombre, i.apellido, i.telefono,
           i.direccion_exacta, l.municipio, l.id_lugar
    FROM interesados i
    INNER JOIN lugares l ON i.id_lugar = l.id_lugar
    {$where}
    ORDER BY i.id_interesado DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$interesados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lugares para los modales
$lugares = $conn->query("SELECT * FROM lugares ORDER BY municipio")->fetchAll(PDO::FETCH_ASSOC);

// ====== INFORMACIÓN DE RANGO ======
$registro_inicio = $total_interesados > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_interesados);
$paginaAnterior = max(1, $pagina_actual - 1);
$paginaSiguiente = min($total_paginas, $pagina_actual + 1);

// Función para construir URL de paginación
function buildPaginationUrl($pagina) {
    global $buscar_nombre, $buscar_apellido, $buscar_documento;
    $params = ['pagina' => $pagina];
    if (!empty($buscar_nombre)) $params['buscar_nombre'] = $buscar_nombre;
    if (!empty($buscar_apellido)) $params['buscar_apellido'] = $buscar_apellido;
    if (!empty($buscar_documento)) $params['buscar_documento'] = $buscar_documento;
    return '?' . http_build_query($params);
}

// Verificar si hay búsqueda activa
$hay_busqueda = !empty($buscar_nombre) || !empty($buscar_apellido) || !empty($buscar_documento);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Interesados - Bufete Popular</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="styleI.css">
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
                <h1>👥 Gestión de Interesados</h1>
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
                        <label for="buscar_documento">🔍 ID / DPI / Teléfono</label>
                        <input type="text"
                            id="buscar_documento"
                            name="buscar_documento"
                            placeholder="Ej: 1234567890123"
                            value="<?= htmlspecialchars($buscar_documento) ?>">
                    </div>

                    <div class="search-buttons">
                        <button type="submit" class="btn btn-primary" title="Buscar interesados">
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
                    ➕ Nuevo Interesado
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
                    <?php if (!empty($buscar_documento)): ?>
                        <span class="filter-tag">
                            <strong>ID/DPI/Tel:</strong> <?= htmlspecialchars($buscar_documento) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ALERTA DE BÚSQUEDA -->
            <?php if ($hay_busqueda): ?>
                <div class="alert alert-info">
                    📌 Resultados encontrados: <strong><?= $total_interesados ?></strong> interesado<?= $total_interesados != 1 ? 's' : '' ?>
                </div>
            <?php endif; ?>

            <!-- TABLA -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>DPI/Cédula</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Municipio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($interesados)): ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <?php if ($hay_busqueda): ?>
                                        ❌ No se encontraron interesados con los criterios de búsqueda especificados
                                    <?php else: ?>
                                        📋 No hay interesados registrados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($interesados as $int): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($int['id_interesado']) ?></strong></td>
                                    <td>
                                        <?= $int['dpi_interesado']
                                            ? htmlspecialchars($int['dpi_interesado'])
                                            : '<span style="color:#999;">No registrado</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($int['nombre']) ?></td>
                                    <td><?= htmlspecialchars($int['apellido']) ?></td>
                                    <td>
                                        <?= $int['telefono']
                                            ? htmlspecialchars($int['telefono'])
                                            : '<span style="color:#999;">No registrado</span>' ?>
                                    </td>
                                    <td><?= htmlspecialchars($int['direccion_exacta']) ?></td>
                                    <td><?= htmlspecialchars($int['municipio']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($_SESSION['rol'] === 'Directora'): ?>
                                                <button onclick="abrirModalEditar(
                                                    <?= $int['id_interesado'] ?>,
                                                    '<?= htmlspecialchars($int['dpi_interesado'] ?? '', ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($int['nombre'], ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($int['apellido'], ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($int['telefono'] ?? '', ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($int['direccion_exacta'], ENT_QUOTES) ?>',
                                                    <?= $int['id_lugar'] ?>
                                                )" class="btn-icon btn-edit" title="Editar interesado">
                                                    ✏️
                                                </button>
                                            <?php else: ?>
                                                <span class="btn-icon btn-disabled" title="Sin permisos">🔒</span>
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
                        Mostrando <?= $registro_inicio ?> - <?= $registro_fin ?> de <?= $total_interesados ?> interesados
                    </div>

                    <div class="pagination-buttons">
                        <a href="<?= buildPaginationUrl($paginaAnterior) ?>"
                        class="page-btn <?= $pagina_actual == 1 ? 'disabled' : '' ?>">
                            ◀️
                        </a>


                        <a href="<?= buildPaginationUrl($paginaSiguiente) ?>"
                        class="page-btn <?= $pagina_actual == $total_paginas ? 'disabled' : '' ?>">
                            ▶️
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ========================================
            MODALES
======================================== -->

<!-- MODAL CREAR INTERESADO -->
<div id="modalCrear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Registrar Nuevo Interesado</h2>
            <span class="modal-close" onclick="cerrarModalCrear()">&times;</span>
        </div>
        <form id="formCrear" method="POST" action="crear_inte.php">
            <div class="modal-body">
                <div class="info-box" style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; margin-bottom: 20px;">
                    📋 <strong>Nota:</strong> Puede ingresar cédulas de vecindad antiguas (menos de 13 dígitos).
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dpi_crear">DPI/Cédula <span style="color:#999;"></span></label>
                        <input type="text" id="dpi_crear" name="dpi_interesado"
                        maxlength="20"
                        placeholder="Ej: 1234567890123 o A-1234567">
                        <small style="color:#666;">DPI (13 dígitos) o cédula antigua</small>
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
                        <label for="apellido_crear">Apellidos *</label>
                        <input type="text" id="apellido_crear" name="apellido"
                        maxlength="100" required
                        placeholder="Ingrese los apellidos">
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
                    <label for="direccion_crear">Dirección Exacta *</label>
                    <input type="text" id="direccion_crear" name="direccion"
                    maxlength="255" required
                    placeholder="Ingrese la dirección completa">
                </div>

                <div class="form-group">
                    <label for="lugar_crear">Municipio *</label>
                    <select id="lugar_crear" name="id_lugar" required onchange="toggleOtroLugarCrear()">
                        <option value="">Seleccione un municipio</option>
                        <?php foreach($lugares as $l): ?>
                            <option value="<?= $l['id_lugar'] ?>"><?= htmlspecialchars($l['municipio']) ?></option>
                        <?php endforeach; ?>
                        <option value="otros">➕ Otros (Agregar nuevo municipio)</option>
                    </select>
                </div>

                <!-- CAMPO OCULTO PARA NUEVO MUNICIPIO -->
                <div class="form-group" id="otroLugarCrearContainer" style="display: none;">
                    <label for="otro_lugar_crear">Nuevo Municipio *</label>
                    <input type="text" id="otro_lugar_crear" name="otro_lugar"
                        maxlength="100"
                        placeholder="Ingrese el nombre del municipio">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📝 Este municipio se agregará al sistema
                    </small>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-success">
                    💾 Registrar Interesado
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCrear()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR INTERESADO -->
<div id="modalEditar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Editar Interesado</h2>
            <span class="modal-close" onclick="cerrarModalEditar()">&times;</span>
        </div>
        <form id="formEditar" method="POST" action="editar_inte.php">
            <div class="modal-body">
                <div class="info-box">
                    <strong>📌 Interesado:</strong>
                    <div id="infoInteresado"></div>
                </div>

                <div class="form-group">
                    <label for="dpi_editar">DPI/Cédula <span style="color:#999;"></span></label>
                    <input type="text" id="dpi_editar_input" name="dpi_interesado"
                    maxlength="20"
                    placeholder="Ej: 1234567890123 o A-1234567">
                    <small style="color:#666;">DPI (13 dígitos) o cédula antigua</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_editar">Nombre *</label>
                        <input type="text" id="nombre_editar" name="nombre"
                        maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label for="apellido_editar">Apellidos *</label>
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
                    <label for="direccion_editar">Dirección Exacta *</label>
                    <input type="text" id="direccion_editar" name="direccion_exacta"
                    maxlength="255" required>
                </div>

                <div class="form-group">
                    <label for="lugar_editar">Municipio *</label>
                    <select id="lugar_editar" name="id_lugar" required onchange="toggleOtroLugarEditar()">
                        <?php foreach($lugares as $l): ?>
                            <option value="<?= $l['id_lugar'] ?>"><?= htmlspecialchars($l['municipio']) ?></option>
                        <?php endforeach; ?>
                        <option value="otros">➕ Otros (Agregar nuevo municipio)</option>
                    </select>
                </div>

                <!-- CAMPO OCULTO PARA NUEVO MUNICIPIO -->
                <div class="form-group" id="otroLugarEditarContainer" style="display: none;">
                    <label for="otro_lugar_editar">Nuevo Municipio *</label>
                    <input type="text" id="otro_lugar_editar" name="otro_lugar"
                        maxlength="100"
                        placeholder="Ingrese el nombre del municipio">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📝 Este municipio se agregará al sistema
                    </small>
                </div>

                <input type="hidden" id="id_interesado_editar" name="id_interesado">
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">
                    💾 Actualizar Interesado
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalEditar()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
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
// ========== TOGGLE OTRO LUGAR ==========
function toggleOtroLugarCrear() {
    const select = document.getElementById('lugar_crear');
    const container = document.getElementById('otroLugarCrearContainer');
    const input = document.getElementById('otro_lugar_crear');

    if (select.value === 'otros') {
        container.style.display = 'block';
        input.required = true;
    } else {
        container.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function toggleOtroLugarEditar() {
    const select = document.getElementById('lugar_editar');
    const container = document.getElementById('otroLugarEditarContainer');
    const input = document.getElementById('otro_lugar_editar');

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
    document.getElementById('otroLugarCrearContainer').style.display = 'none';
    document.getElementById('otro_lugar_crear').required = false;
}

// ========== MODAL EDITAR ==========
function abrirModalEditar(idInteresado, dpi, nombre, apellido, telefono, direccion, idLugar) {
    document.getElementById('modalEditar').style.display = 'flex';
    document.getElementById('id_interesado_editar').value = idInteresado;
    document.getElementById('dpi_editar_input').value = dpi || '';
    document.getElementById('nombre_editar').value = nombre;
    document.getElementById('apellido_editar').value = apellido;
    document.getElementById('telefono_editar').value = telefono || '';
    document.getElementById('direccion_editar').value = direccion;
    document.getElementById('lugar_editar').value = idLugar;

    document.getElementById('infoInteresado').innerHTML = `
        <strong>ID:</strong> ${idInteresado}<br>
        <strong>Nombre:</strong> ${nombre} ${apellido}
    `;
    document.getElementById('otroLugarEditarContainer').style.display = 'none';
    document.getElementById('otro_lugar_editar').required = false;
    document.getElementById('nombre_editar').focus();
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
    document.getElementById('formEditar').reset();
    document.getElementById('otroLugarEditarContainer').style.display = 'none';
    document.getElementById('otro_lugar_editar').required = false;
}

// ========== MODAL MENSAJE ==========
function cerrarModalMensaje() {
    document.getElementById('modalMensaje').style.display = 'none';
}

// ========== MOSTRAR MENSAJE SI VIENE EN URL ==========
document.addEventListener('DOMContentLoaded', function() {
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