<?php
include('../conexion.php');
session_start();

// Validar sesión - SOLO DIRECTORA
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] != 'Directora') {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado - Solo Directora");
    exit();
}

// Filtros
$usuario_filtro = $_GET['usuario'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = $limite;
$inicio = ($pagina - 1) * $por_pagina;

// Construcción de consulta
$sql = "
    SELECT 
        t.id_trans,
        t.id_usuario,
        u.usuario,
        t.tabla,
        t.id_registro,
        t.descripcion,
        t.fecha_hora,
        t.ip
    FROM transacciones t
    LEFT JOIN usuarios u ON t.id_usuario = u.id_usuario
    WHERE 1=1
";

$params = [];

if ($usuario_filtro) {
    $sql .= " AND u.usuario LIKE ?";
    $params[] = "%$usuario_filtro%";
}

if ($tabla_filtro) {
    $sql .= " AND t.tabla = ?";
    $params[] = $tabla_filtro;
}

if ($fecha_desde) {
    $sql .= " AND t.fecha_hora >= ?";
    $params[] = $fecha_desde . ' 00:00:00';
}

if ($fecha_hasta) {
    $sql .= " AND t.fecha_hora <= ?";
    $params[] = $fecha_hasta . ' 23:59:59';
}

$sql .= " ORDER BY t.fecha_hora DESC LIMIT $inicio, $por_pagina";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total para paginación
$count_sql = "SELECT COUNT(*) FROM transacciones t LEFT JOIN usuarios u ON t.id_usuario = u.id_usuario WHERE 1=1";
$count_params = [];

if ($usuario_filtro) {
    $count_sql .= " AND u.usuario LIKE ?";
    $count_params[] = "%$usuario_filtro%";
}
if ($tabla_filtro) {
    $count_sql .= " AND t.tabla = ?";
    $count_params[] = $tabla_filtro;
}
if ($fecha_desde) {
    $count_sql .= " AND t.fecha_hora >= ?";
    $count_params[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta) {
    $count_sql .= " AND t.fecha_hora <= ?";
    $count_params[] = $fecha_hasta . ' 23:59:59';
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($count_params);
$total = $count_stmt->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Obtener tablas disponibles
$tablas = $conn->query("SELECT DISTINCT tabla FROM transacciones WHERE tabla IS NOT NULL ORDER BY tabla")->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas
$total_trans = $conn->query("SELECT COUNT(*) FROM transacciones")->fetchColumn();
$trans_hoy = $conn->query("SELECT COUNT(*) FROM transacciones WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
$trans_semana = $conn->query("SELECT COUNT(*) FROM transacciones WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Transacciones - Bufete Popular</title>
    <link rel="stylesheet" href="style_reportes.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="reportes-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main>
    <div class="reportes-contenedor">
        <h1 class="reportes-titulo">Registro de Transacciones del Sistema</h1>
        <p class="reportes-subtitulo">Bitácora completa de acciones - Solo Directora</p>

        <!-- Estadísticas -->
        <div class="estadisticas-grid">
            <div class="stat-card">
                <div class="stat-numero"><?= number_format($total_trans) ?></div>
                <div class="stat-label">Total Transacciones</div>
            </div>
            <div class="stat-card">
                <div class="stat-numero"><?= number_format($trans_hoy) ?></div>
                <div class="stat-label">Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-numero"><?= number_format($trans_semana) ?></div>
                <div class="stat-label">Última Semana</div>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="form-filtros">
            <div class="filtros-row">
                <div class="filtro-item">
                    <label>Usuario:</label>
                    <input type="text" name="usuario" placeholder="Nombre de usuario" value="<?= htmlspecialchars($usuario_filtro) ?>">
                </div>

                <div class="filtro-item">
                    <label>Tabla:</label>
                    <select name="tabla">
                        <option value="">Todas</option>
                        <?php foreach($tablas as $tabla): ?>
                            <option value="<?= $tabla ?>" <?= $tabla_filtro == $tabla ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tabla) ?>
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

                <div class="filtro-item">
                    <label>Mostrar:</label>
                    <select name="limite">
                        <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50 registros</option>
                        <option value="100" <?= $limite == 100 ? 'selected' : '' ?>>100 registros</option>
                        <option value="200" <?= $limite == 200 ? 'selected' : '' ?>>200 registros</option>
                    </select>
                </div>

                <div class="filtro-acciones">
                    <button type="submit" class="btn-filtrar">Filtrar</button>
                    <a href="ver_transacciones.php" class="btn-limpiar">Limpiar</a>
                </div>
            </div>
        </form>

        <!-- Tabla de Transacciones -->
        <div class="tabla-wrapper">
            <table class="tabla-transacciones">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Tabla</th>
                        <th>ID Registro</th>
                        <th>Descripción</th>
                        <th>Fecha y Hora</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transacciones)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">
                                No se encontraron transacciones con los filtros aplicados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($transacciones as $trans): ?>
                            <tr>
                                <td><?= $trans['id_trans'] ?></td>
                                <td><?= htmlspecialchars($trans['usuario'] ?? 'N/A') ?></td>
                                <td><span class="badge-tabla"><?= htmlspecialchars($trans['tabla']) ?></span></td>
                                <td><?= htmlspecialchars($trans['id_registro']) ?></td>
                                <td class="descripcion-cell"><?= htmlspecialchars($trans['descripcion']) ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($trans['fecha_hora'])) ?></td>
                                <td><?= htmlspecialchars($trans['ip'] ?? 'N/A') ?></td>
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
        // Mantener filtros en los enlaces
        $query_base = "usuario=" . urlencode($usuario_filtro) .
                    "&fecha_desde=" . urlencode($fecha_desde) .
                    "&fecha_hasta=" . urlencode($fecha_hasta);
        ?>

        <!-- Botón anterior -->
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?>&<?= $query_base ?>" class="btn-pag">&laquo; Anterior</a>
        <?php endif; ?>

        <!-- Números de página -->
        <?php
        $rango = 3; // número de páginas visibles antes y después
        $inicio_rango = max(1, $pagina - $rango);
        $fin_rango = min($total_paginas, $pagina + $rango);

        for ($i = $inicio_rango; $i <= $fin_rango; $i++):
        ?>
            <?php if ($i == $pagina): ?>
                <span class="pagina-actual"><?= $i ?></span>
            <?php else: ?>
                <a href="?pagina=<?= $i ?>&<?= $query_base ?>" class="btn-pag"><?= $i ?></a>
            <?php endif; ?>
            <?php endfor; ?>

                <!-- Botón siguiente -->
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&<?= $query_base ?>" class="btn-pag">Siguiente &raquo;</a>
            <?php endif; ?>
            </div>

            <p class="pagina-info">
                Página <?= $pagina ?> de <?= $total_paginas ?> |
                Total: <?= number_format($total) ?> registros
            </p>
<?php endif; ?>


        <div class="form-acciones" style="margin-top: 30px;">
            <a href="index.php" class="btn-volver">Volver al Menú de Reportes</a>
        </div>
    </div>
</main>
<?php include('../footer.php'); ?>
</body>
</html>

