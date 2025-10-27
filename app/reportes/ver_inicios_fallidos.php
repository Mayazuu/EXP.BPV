<?php
include('../session_config.php');
include('../conexion.php');

// Validar sesión - SOLO DIRECTORA
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 'Directora') {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado - Solo Directora");
    exit();
}

// Filtros
$usuario_filtro = $_GET['usuario'] ?? '';
$ip_filtro = $_GET['ip'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 15;
$inicio = ($pagina - 1) * $por_pagina;

// Construcción de consulta
$sql = "
    SELECT 
        i.id_log,
        i.id_usuario,
        i.usuario_ingresado,
        u.usuario as usuario_registrado,
        i.fecha_hora,
        i.ip
    FROM inicios_fallidos i
    LEFT JOIN usuarios u ON i.id_usuario = u.id_usuario
    WHERE 1=1
";

$params = [];

if ($usuario_filtro) {
    $sql .= " AND (i.usuario_ingresado LIKE ? OR u.usuario LIKE ?)";
    $params[] = "%$usuario_filtro%";
    $params[] = "%$usuario_filtro%";
}

if ($ip_filtro) {
    $sql .= " AND i.ip LIKE ?";
    $params[] = "%$ip_filtro%";
}

if ($fecha_desde) {
    $sql .= " AND i.fecha_hora >= ?";
    $params[] = $fecha_desde . ' 00:00:00';
}

if ($fecha_hasta) {
    $sql .= " AND i.fecha_hora <= ?";
    $params[] = $fecha_hasta . ' 23:59:59';
}

$sql .= " ORDER BY i.fecha_hora DESC LIMIT $inicio, $por_pagina";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$inicios_fallidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total para paginación
$count_sql = "SELECT COUNT(*) FROM inicios_fallidos i LEFT JOIN usuarios u ON i.id_usuario = u.id_usuario WHERE 1=1";
$count_params = [];

if ($usuario_filtro) {
    $count_sql .= " AND (i.usuario_ingresado LIKE ? OR u.usuario LIKE ?)";
    $count_params[] = "%$usuario_filtro%";
    $count_params[] = "%$usuario_filtro%";
}
if ($ip_filtro) {
    $count_sql .= " AND i.ip LIKE ?";
    $count_params[] = "%$ip_filtro%";
}
if ($fecha_desde) {
    $count_sql .= " AND i.fecha_hora >= ?";
    $count_params[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta) {
    $count_sql .= " AND i.fecha_hora <= ?";
    $count_params[] = $fecha_hasta . ' 23:59:59';
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total = $count_stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Estadísticas de seguridad
$total_fallidos = $conn->query("SELECT COUNT(*) FROM inicios_fallidos")->fetchColumn();
$fallidos_hoy = $conn->query("SELECT COUNT(*) FROM inicios_fallidos WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
$fallidos_semana = $conn->query("SELECT COUNT(*) FROM inicios_fallidos WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Usuarios NO REGISTRADOS con más intentos
$usuarios_no_registrados = $conn->query("
    SELECT usuario_ingresado, COUNT(*) as intentos
    FROM inicios_fallidos
    WHERE id_usuario IS NULL
    AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND usuario_ingresado IS NOT NULL
    GROUP BY usuario_ingresado
    ORDER BY intentos DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Usuarios REGISTRADOS con más intentos fallidos
$usuarios_registrados = $conn->query("
    SELECT u.usuario, COUNT(*) as intentos
    FROM inicios_fallidos i
    INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
    WHERE i.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY i.id_usuario
    ORDER BY intentos DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// IPs con más intentos fallidos
$ips_sospechosas = $conn->query("
    SELECT ip, COUNT(*) as intentos
    FROM inicios_fallidos
    WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND ip IS NOT NULL
    GROUP BY ip
    ORDER BY intentos DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicios de Sesión Fallidos - Bufete Popular</title>
    <link rel="stylesheet" href="style_reportes.css">
    <link rel="stylesheet" href="estilos_transacciones_inicios.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="reportes-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main>
    <div class="reportes-contenedor">
        <h1 class="reportes-titulo">🔒 Monitoreo de Seguridad</h1>
        <p class="reportes-subtitulo">Inicios de Sesión Fallidos - Solo Directora</p>

        <!-- Estadísticas de Seguridad -->
        <div class="estadisticas-grid">
            <div class="stat-card alerta">
                <div class="stat-numero"><?= number_format($total_fallidos) ?></div>
                <div class="stat-label">Total Intentos Fallidos</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-numero"><?= number_format($fallidos_hoy) ?></div>
                <div class="stat-label">Hoy</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-numero"><?= number_format($fallidos_semana) ?></div>
                <div class="stat-label">Última Semana</div>
            </div>
        </div>

        <!-- Alertas de Seguridad -->
        <div class="alertas-seguridad">
            <div class="alerta-box">
                <h3>⚠️ Usuarios NO Registrados (Último Mes)</h3>
                <table class="tabla-mini">
                    <thead>
                        <tr>
                            <th>Usuario Ingresado</th>
                            <th>Intentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios_no_registrados)): ?>
                            <tr><td colspan="2" style="text-align: center; color: #999;">No hay datos</td></tr>
                        <?php else: ?>
                            <?php foreach($usuarios_no_registrados as $usr): ?>
                                <tr>
                                    <td><span style="color: #d32f2f; font-weight: bold;"><?= htmlspecialchars($usr['usuario_ingresado']) ?> ⚠️</span></td>
                                    <td><strong><?= $usr['intentos'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="alerta-box">
                <h3>👤 Usuarios Registrados (Último Mes)</h3>
                <table class="tabla-mini">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Intentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios_registrados)): ?>
                            <tr><td colspan="2" style="text-align: center; color: #999;">No hay datos</td></tr>
                        <?php else: ?>
                            <?php foreach($usuarios_registrados as $usr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usr['usuario']) ?></td>
                                    <td><strong><?= $usr['intentos'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="alerta-box">
                <h3>🌐 IPs con Más Intentos (Último Mes)</h3>
                <table class="tabla-mini">
                    <thead>
                        <tr>
                            <th>Dirección IP</th>
                            <th>Intentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ips_sospechosas)): ?>
                            <tr><td colspan="2" style="text-align: center; color: #999;">No hay datos</td></tr>
                        <?php else: ?>
                            <?php foreach($ips_sospechosas as $ip_data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ip_data['ip']) ?></td>
                                    <td><strong><?= $ip_data['intentos'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="form-filtros">
            <div class="filtros-row">
                <div class="filtro-item">
                    <label>👤 Usuario:</label>
                    <input type="text" name="usuario" placeholder="Nombre de usuario" value="<?= htmlspecialchars($usuario_filtro) ?>">
                </div>

                <div class="filtro-item">
                    <label>🌐 Dirección IP:</label>
                    <input type="text" name="ip" placeholder="Ej: 192.168.1.1" value="<?= htmlspecialchars($ip_filtro) ?>">
                </div>

                <div class="filtro-item">
                    <label>📅 Desde:</label>
                    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>

                <div class="filtro-item">
                    <label>📅 Hasta:</label>
                    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>

                <div class="filtro-acciones">
                    <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
                    <a href="ver_inicios_fallidos.php" class="btn-limpiar">🔄 Limpiar</a>
                </div>
            </div>
        </form>

        <!-- Tabla de Inicios Fallidos -->
        <div class="tabla-wrapper" id="tabla">
            <table class="tabla-inicios-fallidos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario Ingresado</th>
                        <th>Estado</th>
                        <th>Fecha y Hora</th>
                        <th>Dirección IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inicios_fallidos)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #999;">
                                📂 No se encontraron inicios fallidos con los filtros aplicados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($inicios_fallidos as $intento): ?>
                            <tr>
                                <td><?= $intento['id_log'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($intento['usuario_ingresado'] ?? 'N/A') ?></strong>
                                </td>
                                <td>
                                    <?php if ($intento['id_usuario'] === null): ?>
                                        <span style="color: #d32f2f; font-weight: bold;">❌ NO REGISTRADO</span>
                                    <?php else: ?>
                                        <span style="color: #ff9800; font-weight: bold;">⚠️ Registrado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i:s', strtotime($intento['fecha_hora'])) ?></td>
                                <td><?= htmlspecialchars($intento['ip'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php
            // Construir cadena de filtros segura
            $query_base = "usuario=" . urlencode($usuario_filtro ?? '') .
                          "&ip=" . urlencode($ip_filtro ?? '') .
                          "&fecha_desde=" . urlencode($fecha_desde ?? '') .
                          "&fecha_hasta=" . urlencode($fecha_hasta ?? '');
            ?>

            <!-- Botón anterior -->
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?>&<?= $query_base ?>#tabla" class="btn-pag">&laquo; Anterior</a>
            <?php endif; ?>

            <!-- Números de página -->
            <?php
            $rango = 3;
            $inicio_rango = max(1, $pagina - $rango);
            $fin_rango = min($total_paginas, $pagina + $rango);

            for ($i = $inicio_rango; $i <= $fin_rango; $i++):
            ?>
                <?php if ($i == $pagina): ?>
                    <span class="pagina-actual"><?= $i ?></span>
                <?php else: ?>
                    <a href="?pagina=<?= $i ?>&<?= $query_base ?>#tabla" class="btn-pag"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Botón siguiente -->
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&<?= $query_base ?>#tabla" class="btn-pag">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>

        <p class="pagina-info">
            Página <?= $pagina ?> de <?= $total_paginas ?> |
            Total: <?= number_format($total) ?> registros
        </p>
        <?php endif; ?>

        <div class="form-acciones" style="margin-top: 30px;">
            <a href="index.php" class="btn-volver">↩️ Volver al Menú de Reportes</a>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (window.location.hash === "#tabla") {
        document.querySelector(".tabla-wrapper")?.scrollIntoView({ behavior: "smooth" });
    }
});
</script>

<?php include('../footer.php'); ?>
</body>
</html>