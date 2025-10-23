<?php
header('Content-Type: text/html; charset=utf-8');
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$es_directora = ($_SESSION['rol'] == 'Directora');

// Verificar si hay mensaje de error
$mostrar_modal = false;
$mensaje_data = [];
if (isset($_SESSION['mensaje_error'])) {
    $mostrar_modal = true;
    $mensaje_data = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']); // Limpiar mensaje después de capturarlo
}

// Obtener datos para los selectores
$anos = $conn->query("SELECT DISTINCT anio FROM expedientes ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
$areas = $conn->query("SELECT a.id_area, a.area FROM areas a ORDER BY a.area")->fetchAll(PDO::FETCH_ASSOC);
$tipos_caso = $conn->query("SELECT tc.id_tipo_exp, tc.caso, a.area FROM tipo_caso tc INNER JOIN areas a ON tc.id_area = a.id_area ORDER BY a.area, tc.caso")->fetchAll(PDO::FETCH_ASSOC);
$estados = $conn->query("SELECT id_estado_exp, estado_exp FROM estados_exp ORDER BY estado_exp")->fetchAll(PDO::FETCH_ASSOC);
$estudiantes = $conn->query("SELECT dpi_estudiante, CONCAT(nombre, ' ', apellido) as nombre_completo FROM estudiantes ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Expedientes - Bufete Popular</title>
    <link rel="stylesheet" href="style_reportes.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="reportes-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main class="reportes-main">
    <div class="reportes-contenedor-full">
        <h1 class="reportes-titulo">📁 Reporte de Expedientes</h1>
        <p class="reportes-subtitulo">Seleccione uno o varios filtros para generar el reporte en PDF</p>

        <form action="generar_pdf_expedientes.php" method="POST" target="_blank" class="form-reporte">
            
            <div class="filtros-opciones">
                
                <!-- FILTRO POR AÑO -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_ano" class="filtro-label">
                        <input type="checkbox" name="filtro_ano" id="filtro_ano" value="1">
                        <strong>📅 Por Año</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_ano">
                        <select name="ano" class="form-control">
                            <option value="">Seleccione un año</option>
                            <?php foreach($anos as $ano): ?>
                                <option value="<?= $ano ?>"><?= $ano ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- FILTRO POR ÁREA LEGAL -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_area" class="filtro-label">
                        <input type="checkbox" name="filtro_area" id="filtro_area" value="1">
                        <strong>⚖️ Por Área Legal</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_area">
                        <select name="id_area" class="form-control">
                            <option value="">Seleccione un área</option>
                            <?php foreach($areas as $area): ?>
                                <option value="<?= $area['id_area'] ?>"><?= htmlspecialchars($area['area']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- FILTRO POR TIPO DE EXPEDIENTE -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_tipo_caso" class="filtro-label">
                        <input type="checkbox" name="filtro_tipo_caso" id="filtro_tipo_caso" value="1">
                        <strong>📋 Por Tipo de Expediente</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_tipo_caso">
                        <select name="id_tipo_exp" class="form-control">
                            <option value="">Seleccione un tipo</option>
                            <?php foreach($tipos_caso as $tipo): ?>
                                <option value="<?= $tipo['id_tipo_exp'] ?>">
                                    <?= htmlspecialchars($tipo['area']) ?> - <?= htmlspecialchars($tipo['caso']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- FILTRO POR ESTADO -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_estado" class="filtro-label">
                        <input type="checkbox" name="filtro_estado" id="filtro_estado" value="1">
                        <strong>📊 Por Estado</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_estado">
                        <select name="id_estado_exp" class="form-control">
                            <option value="">Seleccione un estado</option>
                            <?php foreach($estados as $estado): ?>
                                <option value="<?= $estado['id_estado_exp'] ?>"><?= htmlspecialchars($estado['estado_exp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- FILTRO POR ESTUDIANTE -->
                <div class="filtro-opcion-multi">
                    <label for="filtro_estudiante" class="filtro-label">
                        <input type="checkbox" name="filtro_estudiante" id="filtro_estudiante" value="1">
                        <strong>👤 Por Estudiante</strong>
                    </label>
                    <div class="filtro-detalle oculto" id="detalle_estudiante">
                        <!-- Campo de búsqueda -->
                        <div class="busqueda-estudiante">
                            <span class="busqueda-icon">🔍</span>
                            <input
                                type="text"
                                id="buscar_estudiante"
                                class="busqueda-input"
                                placeholder="Buscar estudiante por nombre o DPI..."
                                autocomplete="off"
                            >
                        </div>

                        <!-- Input hidden para enviar el DPI seleccionado -->
                        <input type="hidden" name="dpi_estudiante" id="dpi_estudiante_selected">

                        <!-- Estudiante seleccionado -->
                        <div id="estudiante_seleccionado" class="estudiante-seleccionado oculto">
                            <strong>Seleccionado:</strong> <span id="nombre_seleccionado"></span>
                            <button type="button" onclick="limpiarEstudiante()" class="btn-quitar">✖ Quitar</button>
                        </div>

                        <!-- Lista de resultados -->
                        <div class="estudiantes-lista" id="lista_estudiantes">
                            <?php foreach($estudiantes as $est): ?>
                                <div class="estudiante-item"
                                    data-dpi="<?= $est['dpi_estudiante'] ?>"
                                    data-nombre="<?= strtolower(htmlspecialchars($est['nombre_completo'])) ?>"
                                    onclick="seleccionarEstudiante('<?= $est['dpi_estudiante'] ?>', '<?= htmlspecialchars($est['nombre_completo']) ?>')">
                                    <strong><?= htmlspecialchars($est['nombre_completo']) ?></strong><br>
                                    <small class="texto-gris">DPI: <?= $est['dpi_estudiante'] ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="no-resultados oculto" id="no_resultados">
                            No se encontraron estudiantes con ese nombre
                        </div>
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
                                <input type="date" name="fecha_desde" class="form-control">
                            </div>
                            <div>
                                <label>Hasta:</label>
                                <input type="date" name="fecha_hasta" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="form-acciones">
                <button type="submit" class="btn-generar">📄 Generar PDF</button>
                <a href="index.php" class="btn-volver">↩️ Volver</a>
            </div>
        </form>

        <div class="info-nota">
            <strong>💡 Nota:</strong> Puede seleccionar uno o varios filtros. Los filtros se combinarán para mostrar solo los expedientes que cumplan TODAS las condiciones seleccionadas.
        </div>
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

// BÚSQUEDA DE ESTUDIANTES
const inputBusqueda = document.getElementById('buscar_estudiante');
const listaEstudiantes = document.getElementById('lista_estudiantes');
const noResultados = document.getElementById('no_resultados');

if (inputBusqueda) {
    inputBusqueda.addEventListener('input', function() {
        const busqueda = this.value.toLowerCase().trim();
        const items = document.querySelectorAll('.estudiante-item');
        let encontrados = 0;
        
        if (busqueda === '') {
            items.forEach(item => item.style.display = 'block');
            listaEstudiantes.classList.remove('oculto');
            noResultados.classList.add('oculto');
            return;
        }
        
        items.forEach(item => {
            const nombre = item.getAttribute('data-nombre');
            const dpi = item.getAttribute('data-dpi');
            
            if (nombre.includes(busqueda) || dpi.includes(busqueda)) {
                item.style.display = 'block';
                encontrados++;
            } else {
                item.style.display = 'none';
            }
        });
        
        if (encontrados === 0) {
            listaEstudiantes.classList.add('oculto');
            noResultados.classList.remove('oculto');
        } else {
            listaEstudiantes.classList.remove('oculto');
            noResultados.classList.add('oculto');
        }
    });
}

// Seleccionar estudiante
function seleccionarEstudiante(dpi, nombre) {
    document.getElementById('dpi_estudiante_selected').value = dpi;
    document.getElementById('nombre_seleccionado').textContent = nombre + ' - DPI: ' + dpi;
    document.getElementById('estudiante_seleccionado').classList.remove('oculto');
    document.getElementById('buscar_estudiante').value = '';
    document.getElementById('lista_estudiantes').classList.add('oculto');
}

// Limpiar selección
function limpiarEstudiante() {
    document.getElementById('dpi_estudiante_selected').value = '';
    document.getElementById('estudiante_seleccionado').classList.add('oculto');
    document.getElementById('lista_estudiantes').classList.remove('oculto');
    document.getElementById('buscar_estudiante').value = '';
    document.querySelectorAll('.estudiante-item').forEach(item => item.style.display = 'block');
}

</script>
<?php if ($mostrar_modal): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'info',
    title: <?= json_encode($mensaje_data['titulo'], JSON_UNESCAPED_UNICODE) ?>,
    html: `
        <p style="font-size: 16px; color: #555; margin-bottom: 20px;">
            <?= $mensaje_data['texto'] ?>
        </p>
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
            <strong style="color: #1976d2;">🔍 Filtros aplicados:</strong><br>
            <span style="color: #555;"><?= htmlspecialchars_decode($mensaje_data['filtros']) ?></span>
        </div>
        <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: left; border-left: 4px solid #ff9800;">
            <strong style="color: #f57c00;">💡 Sugerencias:</strong>
            <ul style="margin: 10px 0 0 20px; color: #666;">
                <li>Verifica que los filtros tengan datos disponibles</li>
                <li>Intenta con menos filtros o criterios más amplios</li>
                <li>Usa el rango de fechas para períodos específicos</li>
            </ul>
        </div>
    `,
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#3f51b5',
    width: 650,
    padding: '2em',
    backdrop: true,
    allowOutsideClick: true
});
</script>
<?php endif; ?>

<script src="../js/session_timeout.js"></script>
</body>
</html>