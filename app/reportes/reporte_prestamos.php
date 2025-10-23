<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// Obtener estados
$estados = $conn->query("SELECT * FROM estados_prest ORDER BY id_estado_prest")->fetchAll(PDO::FETCH_ASSOC);

// Variables para el reporte
$prestamos = [];
$estado_seleccionado = '';
$estado_nombre = '';
$fecha_desde = '';
$fecha_hasta = '';



// Si se seleccionó un estado, obtener datos
if (isset($_POST['id_estado'])) {
    $estado_seleccionado = $_POST['id_estado'];
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    
    // Verificar si es "todos" o un estado específico
    if ($estado_seleccionado === 'todos') {
        $estado_nombre = 'Todos los estados';
        $sql_where = "WHERE 1=1"; // Sin filtro de estado
        $params = [];
    } else {
        // Obtener nombre del estado específico
        $stmt = $conn->prepare("SELECT estado_prest FROM estados_prest WHERE id_estado_prest = ?");
        $stmt->execute([$estado_seleccionado]);
        $estado_nombre = $stmt->fetchColumn();
        $sql_where = "WHERE p.id_estado_prest = ?";
        $params = [$estado_seleccionado];
    }
    
    // Consulta de préstamos 
    $sql = "
        SELECT 
            p.id_prestamo,
            e.dpi_estudiante AS DPI_estudiante,
            CONCAT(e.nombre, ' ', e.apellido) AS nombre_estudiante,
            p.id_expediente,
            p.fecha_entrega,
            p.fecha_estimada_dev,
            p.fecha_devolucion,
            ep.estado_prest AS estado,
            (SELECT u.usuario FROM transacciones t 
             INNER JOIN usuarios u ON t.id_usuario = u.id_usuario 
             WHERE t.tabla = 'prestamos'
             AND t.id_registro = p.id_prestamo
             ORDER BY t.fecha_hora DESC
             LIMIT 1) AS usuario_registro
        FROM prestamos p
        INNER JOIN estudiantes e ON p.id_estudiante = e.id_estudiante
        INNER JOIN estados_prest ep ON p.id_estado_prest = ep.id_estado_prest
        $sql_where
    ";
    
    // Agregar filtro de fechas si están presentes
    if ($fecha_desde && $fecha_hasta) {
        $sql .= " AND p.fecha_entrega BETWEEN ? AND ?";
        $params[] = $fecha_desde;
        $params[] = $fecha_hasta;
    }
    
    $sql .= " ORDER BY p.fecha_entrega DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - Bufete Popular</title>
    <link rel="stylesheet" href="style_reportes.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="reportes-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main class="reportes-main">
    <div class="reportes-contenedor-full">
        <h1 class="reportes-titulo">📚 Reporte de Préstamos</h1>
        <p class="reportes-subtitulo">Seleccione uno o varios filtros para visualizar el reporte</p>

        <!-- FORMULARIO DE FILTROS -->
        <form method="POST" class="form-reporte">
            
            <div class="filtros-opciones">
                
                <!-- FILTRO POR ESTADO -->
            <div class="filtro-opcion-multi">
                    <label for="filtro_estado" class="filtro-label">
                        <input type="checkbox" name="filtro_estado" id="filtro_estado" value="1" required>
                        <strong>📊 Por Estado del Préstamo</strong>
                    </label>
                <div class="filtro-detalle oculto" id="detalle_estado">
                    <select name="id_estado" id="id_estado" class="form-control">
                        <option value="">-- Seleccione una opción --</option>
                        <option value="todos" <?= ($estado_seleccionado === 'todos') ? 'selected' : '' ?>>
                            ✅ Todos los estados
                        </option>
                        <optgroup label="Estados específicos:">
                            <?php foreach($estados as $est): ?>
                                <option value="<?= $est['id_estado_prest'] ?>" 
                                        <?= ($estado_seleccionado == $est['id_estado_prest']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($est['estado_prest']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>

                <!-- FILTRO POR RANGO DE FECHAS -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_fechas" class="filtro-label">
                        <input type="checkbox" name="filtro_fechas" id="filtro_fechas" value="1">
                        <strong>📆 Por Rango de Fechas</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_fechas">
                        <div class="fechas-rango">
                            <div>
                                <label>Desde:</label>
                                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                       value="<?= htmlspecialchars($fecha_desde) ?>">
                            </div>
                            <div>
                                <label>Hasta:</label>
                                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                       value="<?= htmlspecialchars($fecha_hasta) ?>">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="form-acciones">
                <button type="submit" class="btn-generar">📊 Ver Reporte</button>
                <?php if (!empty($prestamos)): ?>
                    <button type="button" onclick="generarPDF()" class="btn-pdf">
                        📄 Descargar PDF
                    </button>
                <?php endif; ?>
                <a href="index.php" class="btn-volver">↩️ Volver</a>
            </div>
        </form>

        <div class="info-nota">
            <strong>💡 Nota:</strong> El estado del préstamo es obligatorio. Puede agregar un rango de fechas opcional para filtrar por fecha de entrega del préstamo.
        </div>

        <!-- RESULTADOS DEL REPORTE -->
        <?php if (!empty($prestamos)): ?>
            <div class="reporte-resultado">
                <div class="resultado-header">
                    <h3>📋 Resultados: Préstamos con estado "<?= htmlspecialchars($estado_nombre) ?>"</h3>
                    <div class="resultado-info">
                        <span class="info-badge">📅 <?= date('d/m/Y H:i:s') ?></span>
                        <span class="info-badge">📊 Total: <?= count($prestamos) ?> registros</span>
                    </div>
                </div>

                <div class="tabla-wrapper">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>DPI Estudiante</th>
                                <th>Nombre Estudiante</th>
                                <th>Expediente</th>
                                <th>Fecha Préstamo</th>
                                <th>Fecha Estimada Dev.</th>
                                <th>Fecha Devolución</th>
                                <th>Estado</th>
                                <th>Usuario Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($prestamos as $prest): ?>
                                <tr>
                                    <td><?= $prest['id_prestamo'] ?></td>
                                    <td><?= htmlspecialchars($prest['DPI_estudiante']) ?></td>
                                    <td><?= htmlspecialchars($prest['nombre_estudiante']) ?></td>
                                    <td><?= htmlspecialchars($prest['id_expediente']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($prest['fecha_entrega'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($prest['fecha_estimada_dev'])) ?></td>
                                    <td>
                                        <?= $prest['fecha_devolucion'] 
                                            ? date('d/m/Y H:i', strtotime($prest['fecha_devolucion'])) 
                                            : '<span class="texto-gris-claro">Pendiente</span>' 
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($prest['estado']) ?></td>
                                    <td><?= htmlspecialchars($prest['usuario_registro'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (isset($_POST['id_estado'])): ?>
            <div class="mensaje-vacio">
                <div class="icono-vacio">📭</div>
                <h3>No se encontraron préstamos</h3>
                <p>No hay préstamos registrados con el estado seleccionado<?= ($fecha_desde && $fecha_hasta) ? ' en el rango de fechas especificado' : '' ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include('../footer.php'); ?>

<script>
// Mostrar/Ocultar detalles del filtro seleccionado
document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const detalleId = 'detalle_' + this.id.replace('filtro_', '');
        const elemento = document.getElementById(detalleId);
        
        if (elemento) {
            if (this.checked) {
                elemento.classList.remove('oculto');
            } else {
                elemento.classList.add('oculto');
            }
        }
    });
});

// Marcar checkbox de estado automáticamente si ya hay uno seleccionado
document.addEventListener('DOMContentLoaded', function() {
    const estadoSelect = document.getElementById('id_estado');
    if (estadoSelect && estadoSelect.value) {
        const checkbox = document.getElementById('filtro_estado');
        checkbox.checked = true;
        document.getElementById('detalle_estado').classList.remove('oculto');
    }
    
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');
    if (fechaDesde.value && fechaHasta.value) {
        const checkbox = document.getElementById('filtro_fechas');
        checkbox.checked = true;
        document.getElementById('detalle_fechas').classList.remove('oculto');
    }
});

function generarPDF() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generar_pdf_prestamos.php';
    form.target = '_blank';
    
    const inputEstado = document.createElement('input');
    inputEstado.type = 'hidden';
    inputEstado.name = 'id_estado';
    inputEstado.value = document.getElementById('id_estado').value;
    form.appendChild(inputEstado);
    
    const inputFechaDesde = document.createElement('input');
    inputFechaDesde.type = 'hidden';
    inputFechaDesde.name = 'fecha_desde';
    inputFechaDesde.value = document.getElementById('fecha_desde').value;
    form.appendChild(inputFechaDesde);
    
    const inputFechaHasta = document.createElement('input');
    inputFechaHasta.type = 'hidden';
    inputFechaHasta.name = 'fecha_hasta';
    inputFechaHasta.value = document.getElementById('fecha_hasta').value;
    form.appendChild(inputFechaHasta);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
<script src="../js/session_timeout.js"></script>
</body>
</html>