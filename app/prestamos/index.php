<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// Actualizar estados vencidos automáticamente
try {
    $conn->exec("
        UPDATE prestamos
        SET id_estado_prest = 2
        WHERE fecha_devolucion IS NULL
        AND fecha_estimada_dev < CURDATE()
        AND id_estado_prest = 1
    ");
} catch (PDOException $e) {
    error_log("Error al actualizar préstamos vencidos: " . $e->getMessage());
}

// --- FILTROS ---
$busqueda = $_GET['buscar'] ?? '';
$estado = isset($_GET['estado']) && is_numeric($_GET['estado']) ? $_GET['estado'] : '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 8;
$inicio = ($pagina - 1) * $por_pagina;

// --- Consulta principal con filtros ---
$sql = "
SELECT 
    p.id_prestamo,
    p.id_expediente,
    p.id_estudiante,
    e.id_estudiante,
    e.dpi_estudiante,
    e.carnetEstudiantil,
    e.nombre AS nombre_estudiante,
    e.apellido AS apellido_estudiante,
    p.fecha_entrega,
    p.fecha_estimada_dev,
    p.fecha_devolucion,
    ep.estado_prest AS estado_prestamo,
    p.id_estado_prest,
    exp.ficha_social,
    exp.numero_caso,
    (
        SELECT u.usuario
        FROM transacciones t
        INNER JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE t.descripcion LIKE CONCAT('%prestamo%', p.id_prestamo, '%')
        ORDER BY t.fecha_hora DESC
        LIMIT 1
    ) AS usuario_registro,
    (
        SELECT t.fecha_hora
        FROM transacciones t
        WHERE t.descripcion LIKE CONCAT('%prestamo%', p.id_prestamo, '%')
        ORDER BY t.fecha_hora DESC
        LIMIT 1
    ) AS fecha_registro
FROM prestamos p
INNER JOIN estudiantes e ON p.id_estudiante = e.id_estudiante
LEFT JOIN expedientes exp ON p.id_expediente = exp.id_expediente
LEFT JOIN estados_prest ep ON p.id_estado_prest = ep.id_estado_prest
WHERE 1=1
";

$params = [];

if ($busqueda) {
    $sql .= " AND (e.nombre LIKE ? OR e.apellido LIKE ? OR e.dpi_estudiante LIKE ? OR e.carnetEstudiantil LIKE ? OR exp.ficha_social LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($estado) {
    $sql .= " AND p.id_estado_prest = ?";
    $params[] = $estado;
}

if ($fecha_desde) {
    $sql .= " AND p.fecha_entrega >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND p.fecha_entrega <= ?";
    $params[] = $fecha_hasta;
}

$sql .= " ORDER BY p.fecha_entrega DESC LIMIT $inicio, $por_pagina";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Total para paginación ---
$count_sql = "
    SELECT COUNT(*)
    FROM prestamos p
    INNER JOIN estudiantes e ON p.id_estudiante = e.id_estudiante
    LEFT JOIN expedientes exp ON p.id_expediente = exp.id_expediente
    WHERE 1=1
";
$count_params = [];

if ($busqueda) {
    $count_sql .= " AND (e.nombre LIKE ? OR e.apellido LIKE ? OR e.dpi_estudiante LIKE ? OR e.carnetEstudiantil LIKE ? OR exp.ficha_social LIKE ?)";
    $count_params[] = "%$busqueda%";
    $count_params[] = "%$busqueda%";
    $count_params[] = "%$busqueda%";
    $count_params[] = "%$busqueda%";
    $count_params[] = "%$busqueda%";
}
if ($estado) {
    $count_sql .= " AND p.id_estado_prest = ?";
    $count_params[] = $estado;
}
if ($fecha_desde) {
    $count_sql .= " AND p.fecha_entrega >= ?";
    $count_params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $count_sql .= " AND p.fecha_entrega <= ?";
    $count_params[] = $fecha_hasta;
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total = $count_stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Obtener estados para el filtro
$estados = $conn->query("SELECT * FROM estados_prest ORDER BY id_estado_prest")->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$vigentes = $conn->query("SELECT COUNT(*) FROM prestamos WHERE id_estado_prest = 1")->fetchColumn();
$vencidos = $conn->query("SELECT COUNT(*) FROM prestamos WHERE id_estado_prest = 2")->fetchColumn();
$devueltos = $conn->query("SELECT COUNT(*) FROM prestamos WHERE id_estado_prest = 3")->fetchColumn();

// Datos para los modales
$estudiantes_activos = $conn->query("
    SELECT e.id_estudiante, e.dpi_estudiante, e.carnetEstudiantil, e.nombre, e.apellido
    FROM estudiantes e
    WHERE e.id_estado = 1
    AND e.dpi_estudiante IS NOT NULL
    AND e.dpi_estudiante != ''
    ORDER BY e.nombre, e.apellido
")->fetchAll(PDO::FETCH_ASSOC);

$expedientes_disponibles = $conn->query("
    SELECT e.id_expediente, e.ficha_social, tc.caso as tipo_caso, a.area, i.nombre as cliente_nombre, i.apellido as cliente_apellido
    FROM expedientes e
    INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
    INNER JOIN areas a ON tc.id_area = a.id_area
    INNER JOIN interesados i ON e.id_interesado = i.id_interesado
    WHERE a.area LIKE '%familia%'
    AND tc.caso LIKE '%ejecutivo%'
    AND e.id_expediente NOT IN (
        SELECT id_expediente FROM prestamos WHERE id_estado_prest IN (1,2)
    )
    ORDER BY e.id_expediente DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos - Bufete Popular</title>
    <link rel="stylesheet" href="styleP.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body class="index-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main>
    <div class="index-contenedor">
        <h2 class="index-titulo">📚 Gestión de Préstamos de Expedientes</h2>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($_GET['mensaje']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <button onclick="abrirModalCrear()" class="index-boton-crear">➕ Registrar Nuevo Préstamo</button>

        <!-- RESUMEN -->
        <div class="resumen-prestamos">
            <div class="resumen-item vigente">
                <strong>Vigentes</strong>
                <span class="numero"><?= $vigentes ?></span>
            </div>
            <div class="resumen-item vencido">
                <strong>Vencidos</strong>
                <span class="numero"><?= $vencidos ?></span>
            </div>
            <div class="resumen-item devuelto">
                <strong>Devueltos</strong>
                <span class="numero"><?= $devueltos ?></span>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="index-filtros">
            <form method="GET" class="filtros-form">
                <div class="filtro-item">
                    <label>Buscar:</label>
                    <input type="text"
                        name="buscar"
                        placeholder="Nombre, DPI, carnet, ficha..."
                        value="<?= htmlspecialchars($busqueda) ?>">
                </div>

                <div class="filtro-item">
                    <label>Estado:</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <?php foreach($estados as $est): ?>
                            <option value="<?= $est['id_estado_prest'] ?>" <?= ($estado == $est['id_estado_prest']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($est['estado_prest']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-item">
                    <label>Desde:</label>
                    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>

                <div class="filtro-item">
                    <label>Hasta:</label>
                    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>

                <div class="filtro-acciones">
                    <button type="submit" class="btn-filtrar">🔍 Buscar</button>
                    <a href="index.php" class="btn-limpiar">Limpiar</a>
                </div>
            </form>
        </div>

        <!-- TABLA -->
        <div class="index-wrapper">
            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>Expediente</th>
                        <th>Fecha Préstamo</th>
                        <th>Fecha Estimada</th>
                        <th>Fecha Devolución</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamos)): ?>
                        <tr>
                            <td colspan="8" class="texto-center padding-30">
                                No se encontraron préstamos
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($prestamos as $prest): ?>
                            <tr class="fila-<?= $prest['id_estado_prest'] == 1 ? 'vigente' : ($prest['id_estado_prest'] == 2 ? 'vencido' : 'devuelto') ?>">
                                <td><strong><?= $prest['id_prestamo'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($prest['nombre_estudiante'] . ' ' . $prest['apellido_estudiante']) ?>
                                    <br><small class="texto-gris">DPI: <?= htmlspecialchars($prest['dpi_estudiante'] ?: 'N/A') ?></small>
                                </td>
                                <td>
                                    <strong>Exp. <?= $prest['id_expediente'] ?></strong><br>
                                    <small><?= htmlspecialchars($prest['ficha_social']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($prest['fecha_entrega'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($prest['fecha_estimada_dev'])) ?></td>
                                <td>
                                    <?php if($prest['fecha_devolucion']): ?>
                                        <?= date('d/m/Y', strtotime($prest['fecha_devolucion'])) ?>
                                    <?php else: ?>
                                        <span class="texto-gris-claro">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?=
                                        $prest['id_estado_prest'] == 1 ? 'vigente' :
                                        ($prest['id_estado_prest'] == 2 ? 'vencido' : 'devuelto')
                                    ?>">
                                        <?= htmlspecialchars($prest['estado_prestamo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones-prestamo">
                                        <!-- Botón Ver Detalles -->
                                        <button onclick='verDetalles(<?= json_encode($prest, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                class="btn-detalles" 
                                                title="Ver detalles">
                                            👁️ Detalles
                                        </button>

                                        <!-- Botón Devolver -->
                                        <?php if ($prest['id_estado_prest'] != 3): ?>
                                            <button onclick="abrirModalDevolver(
                                                <?= $prest['id_prestamo'] ?>, 
                                                '<?= htmlspecialchars($prest['nombre_estudiante'] . ' ' . $prest['apellido_estudiante'], ENT_QUOTES) ?>',
                                                <?= $prest['id_expediente'] ?>
                                            )" 
                                                    class="btn-editar" 
                                                    title="Registrar devolución">
                                                ✅ Devolver
                                            </button>
                                        <?php endif; ?>

                                        <!-- Botón Eliminar (solo Directora y solo devueltos) -->
                                        <?php if ($_SESSION['rol'] === 'Directora' && $prest['id_estado_prest'] == 3): ?>
                                            <button onclick="abrirModalEliminar(
                                                <?= $prest['id_prestamo'] ?>,
                                                '<?= htmlspecialchars($prest['nombre_estudiante'] . ' ' . $prest['apellido_estudiante'], ENT_QUOTES) ?>',
                                                <?= $prest['id_expediente'] ?>
                                            )"
                                                    class="btn-eliminar"
                                                    title="Eliminar registro">
                                                🗑️ Eliminar
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

        <!-- INFO RESULTADOS -->
        <div class="info-resultados">
            <p>
                <?php if ($total > 0): ?>
                    Mostrando <?= $inicio + 1 ?> - <?= min($inicio + $por_pagina, $total) ?> de <?= $total ?> préstamos
                <?php else: ?>
                    No se encontraron préstamos
                <?php endif; ?>
            </p>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php
                // Primera página
                if ($pagina > 1) {
                    echo '<a href="?pagina=1&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'" class="btn-primera" title="Primera página">« Primera</a>';
                }

                // Anterior
                if ($pagina > 1) {
                    echo '<a href="?pagina='.($pagina-1).'&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'">‹ Anterior</a>';
                }

                // Números de página
                $rango = 2; // Mostrar 2 páginas antes y después de la actual
                $inicio_rango = max(1, $pagina - $rango);
                $fin_rango = min($total_paginas, $pagina + $rango);

                // Puntos suspensivos al inicio
                if ($inicio_rango > 1) {
                    echo '<a href="?pagina=1&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'">1</a>';
                    if ($inicio_rango > 2) {
                        echo '<span class="pagina-puntos">...</span>';
                    }
                }

                // Páginas en el rango
                for ($i = $inicio_rango; $i <= $fin_rango; $i++) {
                    if ($i == $pagina) {
                        echo '<span class="pagina-numero pagina-actual">'.$i.'</span>';
                    } else {
                        echo '<a href="?pagina='.$i.'&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'" class="pagina-numero">'.$i.'</a>';
                    }
                }

                // Puntos suspensivos al final
                if ($fin_rango < $total_paginas) {
                    if ($fin_rango < $total_paginas - 1) {
                        echo '<span class="pagina-puntos">...</span>';
                    }
                    echo '<a href="?pagina='.$total_paginas.'&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'">'.$total_paginas.'</a>';
                }

                // Siguiente
                if ($pagina < $total_paginas) {
                    echo '<a href="?pagina='.($pagina+1).'&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'">Siguiente ›</a>';
                }

                // Última página
                if ($pagina < $total_paginas) {
                    echo '<a href="?pagina='.$total_paginas.'&buscar='.urlencode($busqueda).'&estado='.$estado.'&fecha_desde='.$fecha_desde.'&fecha_hasta='.$fecha_hasta.'" class="btn-ultima" title="Última página">Última »</a>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- ========== MODAL CREAR PRÉSTAMO ========== -->
<div id="modalCrear" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>➕ Registrar Nuevo Préstamo</h2>
            <span class="modal-close" onclick="cerrarModalCrear()">&times;</span>
        </div>
        <form id="formCrear" method="POST" action="crear_pres.php">
            <div class="modal-body">
                <div class="info-box">
                    <strong>📋 Importante:</strong> Solo se pueden prestar expedientes EJECUTIVOS del área de Familia a estudiantes activos con DPI.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="id_estudiante_crear">Estudiante *</label>
                        <select name="id_estudiante" id="id_estudiante_crear" class="form-input select2-modal ancho-completo" required>
                            <option value="">-- Buscar estudiante --</option>
                            <?php foreach($estudiantes_activos as $est): ?>
                                <option value="<?= $est['id_estudiante'] ?>">
                                    <?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?>
                                    - DPI: <?= htmlspecialchars($est['dpi_estudiante']) ?>
                                    <?= $est['carnetEstudiantil'] ? ' - Carnet: '.htmlspecialchars($est['carnetEstudiantil']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>ℹ️ Solo estudiantes activos con DPI</small>
                        <div class="margen-top-10">
                            <a href="../estudiantes/crear_est.php" target="_blank" class="link_registrar">
                                ➕ Registrar nuevo estudiante
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="id_expediente_crear">Expediente *</label>
                        <select name="id_expediente" id="id_expediente_crear" class="form-input select2-modal ancho-completo" required>
                            <option value="">-- Buscar expediente --</option>
                            <?php foreach($expedientes_disponibles as $exp): ?>
                                <option value="<?= $exp['id_expediente'] ?>">
                                    Exp. <?= $exp['id_expediente'] ?> - <?= htmlspecialchars($exp['ficha_social']) ?>
                                    - Cliente: <?= htmlspecialchars($exp['cliente_nombre'] . ' ' . $exp['cliente_apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>ℹ️ Solo expedientes ejecutivos de Familia disponibles</small>
                        <div class="margen-top-10">
                            <a href="../expedientes/crear_exp.php" target="_blank" class="link_registrar">
                                ➕ Registrar nuevo expediente
                            </a>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_entrega_crear">Fecha de Entrega *</label>
                        <input type="date"
                            name="fecha_entrega"
                            id="fecha_entrega_crear"
                            class="form-input"
                            required
                            value="<?= date('Y-m-d') ?>">
                        <small>📅 Fecha en que se entrega el expediente</small>
                    </div>

                    <div class="form-group">
                        <label for="fecha_estimada_crear">Fecha Estimada Devolución *</label>
                        <input type="date"
                            name="fecha_estimada_dev"
                            id="fecha_estimada_crear"
                            class="form-input"
                            required
                            value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        <small>📅 Por defecto 7 días después</small>
                    </div>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-success">💾 Registrar Préstamo</button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCrear()">❌ Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== MODAL DETALLES ========== -->
<div id="modalDetalles" class="modal">
    <div class="modal-content modal-grande">
        <div class="modal-header">
            <h3>📋 Detalles del Préstamo</h3>
            <span class="modal-close" onclick="cerrarModalDetalles()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detalles-grid">
                <div class="detalle-item">
                    <label>ID Préstamo:</label>
                    <span id="det_id_prestamo"></span>
                </div>
                <div class="detalle-item">
                    <label>Estado:</label>
                    <span id="det_estado"></span>
                </div>
                <div class="detalle-item full-width">
                    <label>👤 Estudiante:</label>
                    <span id="det_estudiante"></span>
                </div>
                <div class="detalle-item">
                    <label>📇 DPI:</label>
                    <span id="det_dpi"></span>
                </div>
                <div class="detalle-item">
                    <label>🎓 Carnet:</label>
                    <span id="det_carnet"></span>
                </div>
                <div class="detalle-item full-width">
                    <label>📁 Expediente:</label>
                    <span id="det_expediente"></span>
                </div>
                <div class="detalle-item">
                    <label>📅 Fecha de Entrega:</label>
                    <span id="det_fecha_entrega"></span>
                </div>
                <div class="detalle-item">
                    <label>⏰ Fecha Estimada Devolución:</label>
                    <span id="det_fecha_estimada"></span>
                </div>
                <div class="detalle-item">
                    <label>✅ Fecha de Devolución:</label>
                    <span id="det_fecha_devolucion"></span>
                </div>
                <div class="detalle-item">
                    <label>👨‍💼 Usuario que Registró:</label>
                    <span id="det_usuario"></span>
                </div>
                <div class="detalle-item full-width">
                    <label>🕐 Fecha de Registro:</label>
                    <span id="det_fecha_registro"></span>
                </div>
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="cerrarModalDetalles()" class="btn btn-primary">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL DEVOLVER ========== -->
<div id="modalDevolver" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✅ Confirmar Devolución</h3>
            <span class="modal-close" onclick="cerrarModalDevolver()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoDevolverPrestamo"></p>
            <div class="info-box">
                ℹ️ Se registrará la fecha y hora actual como momento de devolución.
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="ejecutarDevolucion()" class="btn btn-success">✅ Confirmar Devolución</button>
            <button onclick="cerrarModalDevolver()" class="btn btn-secondary">❌ Cancelar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL ELIMINAR ========== -->
<div id="modalEliminar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚠️ Confirmar Eliminación</h3>
            <span class="modal-close" onclick="cerrarModalEliminar()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="textoEliminarPrestamo"></p>
            <div class="info-box info-box-advertencia">
                ⚠️ <strong>ADVERTENCIA:</strong> Esta acción eliminará permanentemente el registro del préstamo. No se puede deshacer.
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="ejecutarEliminacion()" class="btn btn-danger">🗑️ Confirmar Eliminación</button>
            <button onclick="cerrarModalEliminar()" class="btn btn-secondary">❌ Cancelar</button>
        </div>
    </div>
</div>

<?php include('../footer.php'); ?>

<!-- Select2 CSS y JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let idPrestamoDevolver = null;
let idPrestamoEliminar = null;

// ========== MODAL CREAR ==========
function abrirModalCrear() {
    document.getElementById('modalCrear').style.display = 'flex';
    
    setTimeout(function() {
        $('.select2-modal').select2({
            placeholder: 'Buscar...',
            allowClear: true,
            dropdownParent: $('#modalCrear'),
            language: {
                noResults: function() {
                    return "No se encontraron resultados";
                },
                searching: function() {
                    return "Buscando...";
                }
            }
        });
    }, 100);
}

function cerrarModalCrear() {
    $('.select2-modal').select2('destroy');
    document.getElementById('modalCrear').style.display = 'none';
    document.getElementById('formCrear').reset();
}

document.getElementById('fecha_entrega_crear').addEventListener('change', function() {
    let fechaEntrega = new Date(this.value);
    if (!isNaN(fechaEntrega)) {
        fechaEntrega.setDate(fechaEntrega.getDate() + 7);
        let y = fechaEntrega.getFullYear();
        let m = String(fechaEntrega.getMonth() + 1).padStart(2, '0');
        let d = String(fechaEntrega.getDate()).padStart(2, '0');
        document.getElementById('fecha_estimada_crear').value = `${y}-${m}-${d}`;
    }
});

// ========== MODAL DETALLES ==========
function verDetalles(prestamo) {
    document.getElementById('det_id_prestamo').textContent = '#' + prestamo.id_prestamo;
    
    // Estado con badge
    let estadoClass = prestamo.id_estado_prest == 1 ? 'vigente' :
                    (prestamo.id_estado_prest == 2 ? 'vencido' : 'devuelto');
    document.getElementById('det_estado').innerHTML =
        `<span class="estado-badge-detalle ${estadoClass}">${prestamo.estado_prestamo}</span>`;
    
    document.getElementById('det_estudiante').textContent =
        prestamo.nombre_estudiante + ' ' + prestamo.apellido_estudiante;
    
    document.getElementById('det_dpi').textContent = prestamo.dpi_estudiante || 'No registrado';
    document.getElementById('det_carnet').textContent = prestamo.carnetEstudiantil || 'No registrado';
    
    document.getElementById('det_expediente').innerHTML =
        `<strong>Expediente ${prestamo.id_expediente}</strong><br>${prestamo.ficha_social}`;
    
    document.getElementById('det_fecha_entrega').textContent =
        formatearFecha(prestamo.fecha_entrega);
    
    document.getElementById('det_fecha_estimada').textContent =
        formatearFecha(prestamo.fecha_estimada_dev);
    
    document.getElementById('det_fecha_devolucion').textContent =
        prestamo.fecha_devolucion ? formatearFecha(prestamo.fecha_devolucion) : 'Pendiente';
    
    document.getElementById('det_usuario').textContent = prestamo.usuario_registro || 'No disponible';
    
    document.getElementById('det_fecha_registro').textContent =
        prestamo.fecha_registro ? formatearFechaHora(prestamo.fecha_registro) : 'No disponible';
    
    document.getElementById('modalDetalles').style.display = 'flex';
}

function cerrarModalDetalles() {
    document.getElementById('modalDetalles').style.display = 'none';
}

function formatearFecha(fecha) {
    const date = new Date(fecha);
    const dia = String(date.getDate() + 1).padStart(2, '0');
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const anio = date.getFullYear();
    return `${dia}/${mes}/${anio}`;
}

function formatearFechaHora(fechaHora) {
    const date = new Date(fechaHora);
    const dia = String(date.getDate()).padStart(2, '0');
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const anio = date.getFullYear();
    const horas = String(date.getHours()).padStart(2, '0');
    const minutos = String(date.getMinutes()).padStart(2, '0');
    return `${dia}/${mes}/${anio} ${horas}:${minutos}`;
}

// ========== MODAL DEVOLVER ==========
function abrirModalDevolver(idPrestamo, nombreEstudiante, idExpediente) {
    idPrestamoDevolver = idPrestamo;
    document.getElementById('textoDevolverPrestamo').innerHTML =
        `¿Confirma la devolución del préstamo <strong>#${idPrestamo}</strong>?<br><br>` +
        `<strong>Estudiante:</strong> ${nombreEstudiante}<br>` +
        `<strong>Expediente:</strong> ${idExpediente}<br><br>` +
        `<em>Esta acción registrará la fecha y hora actual como momento de devolución.</em>`;
    document.getElementById('modalDevolver').style.display = 'flex';
}

function ejecutarDevolucion() {
    window.location.href = 'devolver_pres.php?id=' + idPrestamoDevolver;
}

function cerrarModalDevolver() {
    document.getElementById('modalDevolver').style.display = 'none';
}

// ========== MODAL ELIMINAR ==========
function abrirModalEliminar(idPrestamo, nombreEstudiante, idExpediente) {
    idPrestamoEliminar = idPrestamo;
    document.getElementById('textoEliminarPrestamo').innerHTML =
        `¿Está seguro de que desea eliminar el préstamo <strong>#${idPrestamo}</strong>?<br><br>` +
        `<strong>Estudiante:</strong> ${nombreEstudiante}<br>` +
        `<strong>Expediente:</strong> ${idExpediente}<br><br>` +
        `<strong class="texto-advertencia">Este préstamo ya fue devuelto. La eliminación es permanente y no se puede deshacer.</strong>`;
    document.getElementById('modalEliminar').style.display = 'flex';
}

function ejecutarEliminacion() {
    window.location.href = 'eliminar_pres.php?id=' + idPrestamoEliminar;
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').style.display = 'none';
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
<script src="../js/session_timeout.js"></script>
</body>
</html>


