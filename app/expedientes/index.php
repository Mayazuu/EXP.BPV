<?php
include('../session_config.php');
include('../conexion.php');


if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// ===== BÚSQUEDA =====
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_anio = isset($_GET['anio']) ? trim($_GET['anio']) : '';
$filtro_tipo_caso = isset($_GET['tipo_caso']) ? trim($_GET['tipo_caso']) : '';

// ===== PAGINACIÓN =====
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

// WHERE para búsqueda
$where = 'WHERE 1=1';
$params = [];

if (!empty($busqueda)) {
    // Dividir la búsqueda en palabras (para buscar nombre y apellido por separado)
    $palabras = array_filter(explode(' ', $busqueda));

    if (count($palabras) > 0) {
        $condiciones_nombres = [];

        // Para cada palabra, buscar en nombre y apellido
        foreach ($palabras as $index => $palabra) {
            $param_key = ":palabra{$index}";
            $params[$param_key] = "%{$palabra}%";

            // Buscar cada palabra en nombres y apellidos del estudiante e interesado
            $condiciones_nombres[] = "(es.nombre LIKE {$param_key} OR es.apellido LIKE {$param_key} 
                                    OR i.nombre LIKE {$param_key} OR i.apellido LIKE {$param_key})";
        }

        // Búsqueda en campos adicionales (DPI, ficha, número de caso)
        $busqueda_completa_param = ':busqueda_completa';
        $params[$busqueda_completa_param] = "%{$busqueda}%";

        // Combinar todas las condiciones: 
        // - Todas las palabras deben estar presentes en nombres/apellidos, O
        // - El término completo debe estar en DPI, ficha o número de caso
        $where .= " AND ((" . implode(' AND ', $condiciones_nombres) . ") 
                    OR i.dpi_interesado LIKE {$busqueda_completa_param}
                    OR e.ficha_social LIKE {$busqueda_completa_param}
                    OR e.numero_caso LIKE {$busqueda_completa_param})";
    }
}

if (!empty($filtro_anio)) {
    $where .= " AND e.anio = :anio";
    $params[':anio'] = $filtro_anio;
}

if (!empty($filtro_tipo_caso)) {
    $where .= " AND e.id_tipo_exp = :tipo_caso";
    $params[':tipo_caso'] = $filtro_tipo_caso;
}

// ====== CONTAR TOTAL ======
$sql_count = "
    SELECT COUNT(DISTINCT e.id_expediente)
    FROM expedientes e
    INNER JOIN estudiantes es ON e.id_estudiante = es.id_estudiante
    INNER JOIN interesados i ON e.id_interesado = i.id_interesado
    INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
    {$where}
";

$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_expedientes = (int)$stmt_count->fetchColumn();

// ====== CALCULAR PAGINACIÓN ======
$total_paginas = (int)ceil($total_expedientes / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $registros_por_pagina;
if ($offset < 0) $offset = 0;

// ====== CONSULTA PRINCIPAL ======
$sql = "
    SELECT
        e.id_expediente,
        e.ficha_social,
        e.numero_caso,
        e.anio,
        e.folios,
        es.id_estudiante,
        es.dpi_estudiante,
        es.nombre AS estudiante_nombre,
        es.apellido AS estudiante_apellido,
        i.id_interesado,
        i.dpi_interesado,
        i.nombre AS interesado_nombre,
        i.apellido AS interesado_apellido,
        ee.estado_exp AS estado,
        ee.id_estado_exp,
        tc.caso AS tipo_caso,
        est.estante AS estante_nombre,
        e.id_estante
    FROM expedientes e
    INNER JOIN estudiantes es ON e.id_estudiante = es.id_estudiante
    INNER JOIN interesados i ON e.id_interesado = i.id_interesado
    INNER JOIN estados_exp ee ON e.id_estado_exp = ee.id_estado_exp
    INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
    LEFT JOIN estantes est ON e.id_estante = est.id_estante
    {$where}
    ORDER BY e.id_expediente DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$expedientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener años y tipos de caso para filtros
$anios = $conn->query("SELECT DISTINCT anio FROM expedientes ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
$tipos_caso = $conn->query("SELECT id_tipo_exp, caso FROM tipo_caso ORDER BY caso")->fetchAll(PDO::FETCH_ASSOC);

// ====== INFORMACIÓN DE RANGO ======
$registro_inicio = $total_expedientes > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_expedientes);
$paginaAnterior = max(1, $pagina_actual - 1);
$paginaSiguiente = min($total_paginas, $pagina_actual + 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Expedientes - Bufete Popular</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="styleExp.css">
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
                <h1>📂 Gestión de Expedientes</h1>
            </div>

            <!-- BARRA DE ACCIONES -->
            <div class="action-bar">
                <form method="GET" action="" class="search-form">
                    <input type="text"
                        name="buscar"
                        class="search-input"
                        placeholder="🔍 Buscar por estudiante, cliente, DPI, ficha o caso..."
                        value="<?= htmlspecialchars($busqueda) ?>">

                    <select name="anio" class="search-input" style="max-width: 150px;">
                        <option value="">Todos los años</option>
                        <?php foreach($anios as $a): ?>
                            <option value="<?= $a ?>" <?= $filtro_anio == $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="tipo_caso" class="search-input" style="max-width: 200px;">
                        <option value="">Todos los tipos</option>
                        <?php foreach($tipos_caso as $tc): ?>
                            <option value="<?= $tc['id_tipo_exp'] ?>" <?= $filtro_tipo_caso == $tc['id_tipo_exp'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tc['caso']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if (!empty($busqueda) || !empty($filtro_anio) || !empty($filtro_tipo_caso)): ?>
                        <a href="index.php" class="btn btn-clear" title="Limpiar búsqueda">✖</a>
                    <?php endif; ?>
                </form>

                <a href="crear_exp.php" class="btn btn-success">
                    ➕ Nuevo Expediente
                </a>
            </div>

            <!-- ALERTA DE BÚSQUEDA -->
            <?php if (!empty($busqueda) || !empty($filtro_anio) || !empty($filtro_tipo_caso)): ?>
                <div class="alert alert-info">
                    📌 Mostrando resultados filtrados
                    (<?= $total_expedientes ?> encontrado<?= $total_expedientes != 1 ? 's' : '' ?>)
                </div>
            <?php endif; ?>

            <!-- TABLA -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ficha Social</th>
                            <th>N° Caso</th>
                            <th>Año</th>
                            <th>Estudiante</th>
                            <th>Cliente</th>
                            <th>DPI Cliente</th>
                            <th>Tipo de Caso</th>
                            <th>Estado</th>
                            <th>Estante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expedientes)): ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <?php if (!empty($busqueda) || !empty($filtro_anio) || !empty($filtro_tipo_caso)): ?>
                                        No se encontraron expedientes con los filtros aplicados
                                    <?php else: ?>
                                        No hay expedientes registrados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($expedientes as $exp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exp['id_expediente']) ?></td>
                                    <td><?= htmlspecialchars($exp['ficha_social']) ?></td>
                                    <td><?= htmlspecialchars($exp['numero_caso']) ?></td>
                                    <td><?= htmlspecialchars($exp['anio']) ?></td>
                                    <td><?= htmlspecialchars($exp['estudiante_nombre'] . ' ' . $exp['estudiante_apellido']) ?></td>
                                    <td><?= htmlspecialchars($exp['interesado_nombre'] . ' ' . $exp['interesado_apellido']) ?></td>
                                    <td><?= htmlspecialchars($exp['dpi_interesado']) ?></td>
                                    <td><?= htmlspecialchars($exp['tipo_caso']) ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($exp['estado']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($exp['estante_nombre'] ?: 'Sin asignar') ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button onclick="editar(<?= $exp['id_expediente'] ?>)"
                                                    class="btn-icon btn-edit"
                                                    title="Editar expediente">
                                                ✏️
                                            </button>
                                            <button onclick="verDetalle(<?= $exp['id_expediente'] ?>)"
                                                    class="btn-icon btn-view"
                                                    title="Ver detalles completos">
                                                👁️
                                            </button>
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
                        Mostrando <?= $registro_inicio ?> - <?= $registro_fin ?> de <?= $total_expedientes ?> expedientes
                    </div>

                    <div class="pagination-buttons">
                        <a href="?pagina=<?= $paginaAnterior ?><?= !empty($busqueda) ? '&buscar='.urlencode($busqueda) : '' ?><?= !empty($filtro_anio) ? '&anio='.urlencode($filtro_anio) : '' ?><?= !empty($filtro_tipo_caso) ? '&tipo_caso='.urlencode($filtro_tipo_caso) : '' ?>" 
                        class="page-btn <?= $pagina_actual == 1 ? 'disabled' : '' ?>">
                            ◀️
                        </a>

                        <a href="?pagina=<?= $paginaSiguiente ?><?= !empty($busqueda) ? '&buscar='.urlencode($busqueda) : '' ?><?= !empty($filtro_anio) ? '&anio='.urlencode($filtro_anio) : '' ?><?= !empty($filtro_tipo_caso) ? '&tipo_caso='.urlencode($filtro_tipo_caso) : '' ?>" 
                        class="page-btn <?= $pagina_actual == $total_paginas ? 'disabled' : '' ?>">
                            ▶️
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

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

<!-- MODAL VER DETALLE -->
<div id="detalleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Detalle del Expediente</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Aquí se cargará el contenido dinámicamente -->
            Cargando...
        </div>
    </div>
</div>


<script>

    // ========== EDITAR ==========
function editar(id) {
    fetch('editar_modal.php?id_expediente=' + id)
        .then(response => response.text())
        .then(html => {
            // Remover cualquier modal previo
            const modalExistente = document.getElementById('modalEditar');
            if(modalExistente) modalExistente.remove();

            // Crear contenedor temporal
            let tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            document.body.appendChild(tempDiv);

            // Mostrar modal
            const modal = document.getElementById('modalEditar');
            if(modal) modal.style.display = 'flex';

            // Botón cerrar
            modal.querySelectorAll('.modal-close, .btn-secondary').forEach(btn => {
                btn.addEventListener('click', () => modal.remove());
            });

            // Cerrar modal al hacer clic fuera
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });

            // Inicializar el listener de "nuevo estante"
            const selectEstante = modal.querySelector('#selectEstanteModal');
            const nuevoEstante = modal.querySelector('#nuevoEstanteModal');
            if(selectEstante) {
                selectEstante.addEventListener('change', function() {
                    if (this.value === 'otro') {
                        nuevoEstante.style.display = 'block';
                        nuevoEstante.required = true;
                    } else {
                        nuevoEstante.style.display = 'none';
                        nuevoEstante.required = false;
                        nuevoEstante.value = '';
                    }
                });
            }

            // Inicializar el submit AJAX del formulario
            const form = modal.querySelector('#formEditarModal');
            if(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Guardando...';

                    fetch('editar_modal.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            const msgDiv = modal.querySelector('#editarModalMensaje');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            if(data.error) {
                                msgDiv.innerHTML = '<div class="alert alert-error">❌ '+data.error+'</div>';
                            } else if(data.success) {
                                msgDiv.innerHTML = '<div class="alert alert-success">✅ '+data.success+'</div>';
                                setTimeout(() => location.reload(), 3000);
                            }
                        })
                        .catch(err => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            modal.querySelector('#editarModalMensaje').innerHTML = '<div class="alert alert-error">❌ '+err.message+'</div>';
                        });
                });
            }
        })
        .catch(err => console.error('Error cargando modal:', err));
}


// ========== VER DETALLE EN MODAL ==========
function verDetalle(id) {
    const modal = document.getElementById('detalleModal');
    const modalBody = modal.querySelector('.modal-body');
    const closeBtn = modal.querySelector('.modal-close');

    modalBody.innerHTML = 'Cargando...';
    modal.style.display = 'flex';

    fetch('ver_detalle.php?id_expediente=' + id)
        .then(response => response.text())
        .then(html => modalBody.innerHTML = html)
        .catch(err => modalBody.innerHTML = 'Error al cargar el detalle');

    // Cerrar modal al dar clic en la X
    closeBtn.onclick = () => modal.style.display = 'none';

    // Cerrar modal al dar clic fuera del contenido
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
}

// ========== MODAL MENSAJE ==========
function cerrarModalMensaje() {
    document.getElementById('modalMensaje').style.display = 'none';
}

// ========== MOSTRAR MENSAJE SI VIENE EN URL ==========
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const mensaje = params.get('mensaje');
    const error = params.get('error');

    if (mensaje) {
        document.getElementById('mensajeTexto').textContent = mensaje;
        document.getElementById('modalMensaje').style.display = 'flex';
    }

    if (error) {
        document.getElementById('mensajeTexto').textContent = error;
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
<?php include('../footer.php'); ?>
</body>
</html>
