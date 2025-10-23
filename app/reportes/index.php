<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

$es_directora = ($_SESSION['rol'] == 'Directora');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Reportes - Bufete Popular</title>
    <link rel="stylesheet" href="style_reportes.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="reportes-body">
<?php include('../navbar.php'); ?>
<?php include('../boton_ayuda.php'); ?>
<?php include('../boton_volver.php'); ?>

<main class="reportes-main">
    <div class="reportes-contenedor-full">
        <div class="reportes-header">
            <h1 class="reportes-titulo">📊 Módulo de Reportes y Transacciones</h1>
            <p class="reportes-subtitulo">
                <span class="usuario-badge">👤 <?= htmlspecialchars($_SESSION['usuario']) ?></span>
                <span class="rol-badge"><?= htmlspecialchars($_SESSION['rol']) ?></span>
            </p>
        </div>

        <!-- REPORTES PRINCIPALES -->
        <section class="reportes-seccion">
            <h2 class="seccion-titulo">📄 Reportes del Sistema</h2>
            <div class="reportes-grid-full">
                
                <!-- REPORTE DE EXPEDIENTES -->
                <div class="reporte-card">
                    <div class="card-header">
                        <div class="card-icon expedientes">📁</div>
                        <h3>Reporte de Expedientes</h3>
                    </div>
                    <p class="card-descripcion">Generar reporte detallado de expedientes con múltiples filtros de búsqueda.</p>
                    <div class="filtros-lista">
                        <span class="filtro-tag">📅 Por año</span>
                        <span class="filtro-tag">⚖️ Área legal</span>
                        <span class="filtro-tag">📋 Tipo</span>
                        <span class="filtro-tag">✅ Estado</span>
                        <span class="filtro-tag">👨‍🎓 Estudiante</span>
                        <span class="filtro-tag">📆 Rango fechas</span>
                    </div>
                    <a href="reporte_expedientes.php" class="btn-reporte expedientes-btn">
                        <span>📥 Generar Reporte PDF</span>
                    </a>
                </div>

                <!-- REPORTE DE PRÉSTAMOS -->
                <div class="reporte-card">
                    <div class="card-header">
                        <div class="card-icon prestamos">📚</div>
                        <h3>Reporte de Préstamos</h3>
                    </div>
                    <p class="card-descripcion">Consultar y descargar el estado actual de préstamos de expedientes.</p>
                    <div class="filtros-lista">
                        <span class="filtro-tag">🟢 Vigentes</span>
                        <span class="filtro-tag">🔴 Vencidos</span>
                        <span class="filtro-tag">✅ Devueltos</span>
                    </div>
                    <a href="reporte_prestamos.php" class="btn-reporte prestamos-btn">
                        <span>👁️ Ver y Descargar PDF</span>
                    </a>
                </div>

            </div>
        </section>

        <?php if ($es_directora): ?>
        <!-- SECCIÓN ADMINISTRATIVA (Solo Directora) -->
        <section class="reportes-seccion admin-seccion">
            <h2 class="seccion-titulo">🔐 Panel Administrativo</h2>
            <div class="reportes-grid-full">
                
                <!-- TRANSACCIONES -->
                <div class="reporte-card admin-card">
                    <div class="card-header">
                        <div class="card-icon transacciones">📝</div>
                        <h3>Registro de Transacciones</h3>
                    </div>
                    <p class="card-descripcion">Bitácora completa de todas las acciones realizadas en el sistema.</p>
                    <div class="filtros-lista">
                        <span class="filtro-tag">👤 Por usuario</span>
                        <span class="filtro-tag">🗂️ Tabla afectada</span>
                        <span class="filtro-tag">📅 Por fecha</span>
                        <span class="filtro-tag">🕐 Últimas acciones</span>
                    </div>
                    <a href="ver_transacciones.php" class="btn-reporte admin-btn">
                        <span>📊 Visualizar Transacciones</span>
                    </a>
                </div>

                <!-- INICIOS FALLIDOS -->
                <div class="reporte-card admin-card">
                    <div class="card-header">
                        <div class="card-icon seguridad">🔒</div>
                        <h3>Inicios de Sesión Fallidos</h3>
                    </div>
                    <p class="card-descripcion">Monitor de seguridad e intentos fallidos de acceso al sistema.</p>
                    <div class="filtros-lista">
                        <span class="filtro-tag">👤 Usuario</span>
                        <span class="filtro-tag">📅 Fecha</span>
                        <span class="filtro-tag">🌐 Dirección IP</span>
                        <span class="filtro-tag">📈 Estadísticas</span>
                    </div>
                    <a href="ver_inicios_fallidos.php" class="btn-reporte seguridad-btn">
                        <span>🔍 Visualizar Registros</span>
                    </a>
                </div>

            <!-- BACKUP DE BASE DE DATOS -->
                <div class="reporte-card admin-card">
                    <div class="card-header">
                        <div class="card-icon backup">💾</div>
                        <h3>Respaldo de Base de Datos</h3>
                    </div>
                    <p class="card-descripcion">Generar copia de seguridad completa de toda la información del sistema.</p>
                    <div class="filtros-lista">
                        <span class="filtro-tag">📊 Todas las tablas</span>
                        <span class="filtro-tag">🔐 Solo Directora</span>
                        <span class="filtro-tag">📥 Descarga SQL</span>
                        <span class="filtro-tag">📝 Registro automático</span>
                    </div>

                    <div style="background: #fff3e0; padding: 12px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ff9800;">
                        <strong style="color: #f57c00;">⚠️ Importante:</strong>
                        <p style="color: #666; margin: 5px 0 0 0; font-size: 13px;">
                            El respaldo incluye TODA la información: expedientes, estudiantes, interesados, 
                            préstamos, usuarios y transacciones. Guarda el archivo en un lugar seguro.
                        </p>
                    </div>

                    <a href="backup_db.php" class="btn-reporte backup-btn" 
                    onclick="return confirm('¿Deseas generar un respaldo completo de la base de datos?\n\nEsto descargará un archivo .sql con toda la información del sistema.')">
                        <span>💾 Generar Respaldo Ahora</span>
                    </a>
                    
                    <div style="margin-top: 10px; text-align: center;">
                        <small style="color: #999;">
                            ⏱️ El proceso puede tardar algunos segundos según la cantidad de datos
                        </small>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- INFORMACIÓN Y AYUDA -->
        <section class="info-seccion">
            <div class="info-card">
                <h3>ℹ️ Información sobre los reportes</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>📁 Expedientes:</strong> Los reportes se generan en PDF con fecha de generación y filtros aplicados.
                    </div>
                    <div class="info-item">
                        <strong>📚 Préstamos:</strong> Visualiza el reporte en pantalla antes de descargarlo en PDF.
                    </div>
                    <?php if ($es_directora): ?>
                    <div class="info-item">
                        <strong>📝 Transacciones:</strong> Visualización completa de la bitácora del sistema.
                    </div>
                    <div class="info-item">
                        <strong>🔒 Inicios Fallidos:</strong> Monitoreo de seguridad del sistema.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$es_directora): ?>
            <div class="nota-permiso">
                <strong>📌 Nota:</strong> Como Secretaria, tienes acceso a los reportes de Expedientes y Préstamos.
            </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<script src="../js/session_timeout.js"></script>
<?php include('../footer.php'); ?>
</body>
</html>