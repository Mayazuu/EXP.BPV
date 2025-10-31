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

// ===== PAGINACIÓN =====
$registros_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

// WHERE para búsqueda
$where = '';
$params = [];
if (!empty($busqueda)) {
    $where = "WHERE (a.nombre LIKE :busqueda OR a.apellido LIKE :busqueda)";
    $params[':busqueda'] = "%{$busqueda}%";
}

// ====== CONTAR TOTAL ======
$sql_count = "
    SELECT COUNT(*)
    FROM (
        SELECT a.id_asesor
        FROM asesores a
        LEFT JOIN expedientes e ON a.id_asesor = e.id_asesor
        {$where}
        GROUP BY a.id_asesor
    ) AS subquery
";
$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_asesores = (int)$stmt_count->fetchColumn();

$total_paginas = (int)ceil($total_asesores / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ====== CONSULTA PRINCIPAL ======
$sql = "
    SELECT a.id_asesor, a.nombre, a.apellido,
        IFNULL(a.telefono, '') AS telefono,
        COUNT(e.id_expediente) AS expedientes_vinculados
    FROM asesores a
    LEFT JOIN expedientes e ON a.id_asesor = e.id_asesor
    {$where}
    GROUP BY a.id_asesor, a.nombre, a.apellido, a.telefono
    ORDER BY a.nombre, a.apellido
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$asesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== INFORMACIÓN DE RANGO ======
$registro_inicio = $total_asesores > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_asesores);
$paginaAnterior = max(1, $pagina_actual - 1);
$paginaSiguiente = min($total_paginas, $pagina_actual + 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Asesores - Bufete Popular</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="styleA.css">
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
                <h1>📋 Gestión de Asesores</h1>
            </div>

            <!-- BARRA DE ACCIONES -->
            <div class="action-bar">
                <form method="GET" action="" class="search-form">
                    <input type="text"
                        name="buscar"
                        class="search-input"
                        placeholder="🔍 Buscar por nombre o apellido..."
                        value="<?= htmlspecialchars($busqueda) ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="index.php" class="btn btn-clear" title="Limpiar búsqueda">✖</a>
                    <?php endif; ?>
                </form>

                <button onclick="abrirModalCrear()" class="btn btn-success">
                    ➕ Nuevo Asesor
                </button>
            </div>

            <!-- ALERTA DE BÚSQUEDA -->
            <?php if (!empty($busqueda)): ?>
                <div class="alert alert-info">
                    📌 Mostrando resultados para: <strong>"<?= htmlspecialchars($busqueda) ?>"</strong>
                    (<?= $total_asesores ?> encontrado<?= $total_asesores != 1 ? 's' : '' ?>)
                </div>
            <?php endif; ?>

            <!-- TABLA -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Expedientes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($asesores)): ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <?php if (!empty($busqueda)): ?>
                                        No se encontraron asesores con "<?= htmlspecialchars($busqueda) ?>"
                                    <?php else: ?>
                                        No hay asesores registrados
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($asesores as $asesor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($asesor['id_asesor']) ?></td>
                                    <td><?= htmlspecialchars($asesor['nombre']) ?></td>
                                    <td><?= htmlspecialchars($asesor['apellido']) ?></td>
                                    <td><?= htmlspecialchars($asesor['telefono'] ?: 'No registrado') ?></td>
                                    <td>
                                        <span class="badge <?= $asesor['expedientes_vinculados'] > 0 ? 'badge-info' : 'badge-gray' ?>">
                                            <?= $asesor['expedientes_vinculados'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <!-- BOTÓN EDITAR CON MODAL -->
                                            <button onclick="abrirModalEditar(
                                                <?= $asesor['id_asesor'] ?>,
                                                '<?= htmlspecialchars($asesor['nombre'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($asesor['apellido'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($asesor['telefono'] ?? '', ENT_QUOTES) ?>'
                                            )" class="btn-icon btn-edit" title="Editar teléfono">
                                                ✏️
                                            </button>

                                            <!-- BOTÓN ELIMINAR -->
                                            <?php if ($_SESSION['rol'] === 'Directora'): ?>
                                                <?php if ($asesor['expedientes_vinculados'] == 0): ?>
                                                    <button onclick="confirmarEliminar(<?= $asesor['id_asesor'] ?>, '<?= addslashes($asesor['nombre'] . ' ' . $asesor['apellido']) ?>')"
                                                            class="btn-icon btn-delete"
                                                            title="Eliminar">
                                                        🗑️ 
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-icon btn-disabled"
                                                            title="Tiene <?= $asesor['expedientes_vinculados'] ?> expediente(s)"
                                                            disabled>
                                                        🔒
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
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
                    <div style="color: #6B7280; font-size: 0.875rem;">
                        Mostrando <?= $registro_inicio ?> - <?= $registro_fin ?> de <?= $total_asesores ?> asesores
                    </div>

                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?pagina=<?= $paginaAnterior ?><?= !empty($busqueda) ? '&buscar='.urlencode($busqueda) : '' ?>"
                        class="page-btn"
                        style="<?= $pagina_actual == 1 ? 'opacity:0.5;pointer-events:none;' : '' ?>">
                            ◀️
                        </a>
                        <a href="?pagina=<?= $paginaSiguiente ?><?= !empty($busqueda) ? '&buscar='.urlencode($busqueda) : '' ?>"
                        class="page-btn"
                        style="<?= $pagina_actual == $total_paginas ? 'opacity:0.5;pointer-events:none;' : '' ?>">
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

<!-- MODAL CREAR ASESOR -->
<div id="modalCrear" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>➕ Registrar Nuevo Asesor</h2>
            <span class="modal-close" onclick="cerrarModalCrear()">&times;</span>
        </div>
        <form id="formCrear" method="POST" action="crear_ase.php">
            <div style="padding: 1.5rem;">
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

                <div class="form-group">
                    <label for="telefono_crear">
                        Teléfono <span style="color: #6B7280; font-weight: normal;">(Opcional)</span>
                    </label>
                    <input type="tel" id="telefono_crear" name="telefono"
                        maxlength="20"
                        placeholder="Ej: 7512-3456">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📱 Formatos válidos: 1234-5678, +502 1234-5678
                    </small>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-success">
                    💾 Registrar Asesor
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCrear()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR ASESOR -->
<div id="modalEditar" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>✏️ Editar Asesor</h2>
            <span class="modal-close" onclick="cerrarModalEditar()">&times;</span>
        </div>
        <form id="formEditar" method="POST" action="editar_ase.php">
            <div style="padding: 1.5rem;">
                <div style="background: var(--light-blue); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <strong>📌 Editando Asesor ID:</strong>
                    <div id="infoAsesor" style="margin-top: 0.5rem;"></div>
                </div>

                <div class="form-group">
                    <label for="nombre_editar">
                        Nombre <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="nombre_editar" name="nombre"
                        required
                        maxlength="100"
                        placeholder="Nombre del asesor">
                </div>

                <div class="form-group">
                    <label for="apellido_editar">
                        Apellido <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="apellido_editar" name="apellido"
                        required
                        maxlength="100"
                        placeholder="Apellido del asesor">
                </div>

                <div class="form-group">
                    <label for="telefono_editar">
                        Teléfono <span style="color: #6B7280; font-weight: normal;">(Opcional)</span>
                    </label>
                    <input type="tel" id="telefono_editar" name="telefono"
                        maxlength="20"
                        placeholder="Ej: 7512-3456">
                    <small style="color: #6B7280; display: block; margin-top: 0.5rem;">
                        📱 Formatos válidos: 1234-5678, +502 1234-5678
                    </small>
                </div>
                <input type="hidden" id="id_asesor_editar" name="id">
            </div>

            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">
                    💾 Actualizar Asesor
                </button>
                <button type="button" class="btn btn-secondary" onclick="cerrarModalEditar()">
                    ❌ Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
<!-- MODAL ELIMINAR -->
<div id="modalEliminar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚠️ Confirmar Eliminación</h3>
            <span class="modal-close" onclick="cerrarModalEliminar()">&times;</span>
        </div>
        <p id="textoEliminar" style="padding: 1.5rem;"></p>
        <div style="padding: 0 1.5rem;">
            <div class="form-group">
                <label for="razonEliminar">Motivo de la eliminación *</label>
                <textarea id="razonEliminar" rows="3" placeholder="Ingrese el motivo..." style="width: 100%; padding: 0.75rem; border: 2px solid #E5E7EB; border-radius: 8px; font-family: inherit;"></textarea>
            </div>
        </div>
        <div class="modal-buttons">
            <button onclick="ejecutarEliminar()" class="btn btn-danger">🗑️ Eliminar</button>
            <button onclick="cerrarModalEliminar()" class="btn btn-secondary">❌ Cancelar</button>
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
        <p id="mensajeTexto" style="padding: 1.5rem;"></p>
        <div class="modal-buttons">
            <button onclick="cerrarModalMensaje()" class="btn btn-primary">Aceptar</button>
        </div>
    </div>
</div>

<?php include('../footer.php'); ?>

<!--// ========== JAVASCRIP ==========-->

<script>
// ========== VARIABLES GLOBALES ==========
let idEliminar = null;
let nombreEliminar = '';

// ========== MODAL CREAR ==========
function abrirModalCrear() {
    document.getElementById('modalCrear').style.display = 'flex';
    document.getElementById('nombre_crear').focus();
}

function cerrarModalCrear() {
    document.getElementById('modalCrear').style.display = 'none';
    document.getElementById('formCrear').reset();
}

// ========== MODAL EDITAR ==========
function abrirModalEditar(id, nombre, apellido, telefono) {
    document.getElementById('id_asesor_editar').value = id;
    document.getElementById('nombre_editar').value = nombre;
    document.getElementById('apellido_editar').value = apellido;
    document.getElementById('telefono_editar').value = telefono || '';
    document.getElementById('infoAsesor').textContent = id;
    document.getElementById('modalEditar').style.display = 'flex';
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
    document.getElementById('formEditar').reset();
}

// ========== MODAL ELIMINAR ==========
function confirmarEliminar(id, nombre) {
    idEliminar = id;
    nombreEliminar = nombre;
    document.getElementById('textoEliminar').innerHTML =
        `¿Está seguro de eliminar al asesor?<br><br><strong style="color: #EF4444;">${nombre}</strong><br><br><em>Esta acción NO se puede deshacer.</em>`;
    document.getElementById('razonEliminar').value = '';
    document.getElementById('modalEliminar').style.display = 'flex';
}

function ejecutarEliminar() {
    const razon = document.getElementById('razonEliminar').value.trim();
    if (!razon) {
        alert('⚠️ Debe ingresar un motivo');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'eliminar_ase.php';

    const inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id_asesor';
    inputId.value = idEliminar;

    const inputRazon = document.createElement('input');
    inputRazon.type = 'hidden';
    inputRazon.name = 'razon';
    inputRazon.value = razon;

    form.appendChild(inputId);
    form.appendChild(inputRazon);
    document.body.appendChild(form);
    form.submit();
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').style.display = 'none';
}

// ========== MODAL MENSAJE ==========
function cerrarModalMensaje() {
    document.getElementById('modalMensaje').style.display = 'none';
}

// ========== VALIDACIÓN DE TELÉFONO ==========
function validarTelefono(telefono) {
    if (telefono === '') return true; // Opcional

    const soloNumeros = telefono.replace(/[\s\-+]/g, '');

    if (soloNumeros.length < 8) {
        return '❌ El teléfono debe tener al menos 8 dígitos';
    }

    const formatoValido = /^(\+502\s?)?[\d]{4}[\s\-]?[\d]{4}$/;
    if (!formatoValido.test(telefono)) {
        return '❌ Formato inválido.\n\nFormatos válidos:\n• 1234-5678\n• 1234 5678\n• +502 1234-5678';
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function() {

    // ========== VALIDACIÓN SOLO NÚMEROS EN TIEMPO REAL ==========
    const telefonoInputs = [
        document.getElementById('telefono_crear'),
        document.getElementById('telefono_editar')
    ];

    telefonoInputs.forEach(input => {
        if (input) {
            // Solo permitir números, espacios, guiones y +
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d\s\-+]/g, '');
            });
        }
    });

    // ========== VALIDAR FORMULARIO CREAR AL ENVIAR ==========
    const formCrear = document.getElementById('formCrear');
    if (formCrear) {
        formCrear.addEventListener('submit', function(e) {
            const telefono = document.getElementById('telefono_crear').value.trim();
            const resultado = validarTelefono(telefono);

            if (resultado !== true) {
                e.preventDefault();
                alert(resultado);
                document.getElementById('telefono_crear').focus();
                return false;
            }
        });
    }

    // ========== VALIDAR FORMULARIO EDITAR AL ENVIAR ==========
    const formEditar = document.getElementById('formEditar');
    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            const telefono = document.getElementById('telefono_editar').value.trim();
            const resultado = validarTelefono(telefono);

            if (resultado !== true) {
                e.preventDefault();
                alert(resultado);
                document.getElementById('telefono_editar').focus();
                return false;
            }
        });
    }

    // ========== MOSTRAR MENSAJE SI VIENE EN URL ==========
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
