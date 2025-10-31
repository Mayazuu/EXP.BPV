<?php
include('../session_config.php');

// Verifica si hay sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Recupera el ID del rol desde la sesión
$id_rol = $_SESSION['id_rol'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bufete Popular</title>
    <link href="../css/style.css" rel="stylesheet">
    <link href="styleInicio.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="body_dash">
    <?php include('../navbar.php'); ?>
    <?php include('../boton_ayuda.php'); ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="bi bi-house-heart-fill"></i>
                Bienvenida, <?= htmlspecialchars($_SESSION['nombre']) ?>
            </h1>
            <p class="dashboard-subtitle">Sistema de Gestión - Bufete Popular La Verapaz</p>
        </div>

        <div class="modulos-grid">
            <a href="../estudiantes/index.php" class="modulo-card estudiantes">
                <div class="card-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3 class="card-title">Estudiantes</h3>
                <p class="card-description">Gestión de estudiantes practicantes</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>

            <a href="../interesados/index.php" class="modulo-card interesados">
                <div class="card-icon">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <h3 class="card-title">Interesados</h3>
                <p class="card-description">Registro de clientes e interesados</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>

            <a href="../asesores/index.php" class="modulo-card asesores">
                <div class="card-icon">
                    <i class="bi bi-person-lines-fill"></i>
                </div>
                <h3 class="card-title">Asesores</h3>
                <p class="card-description">Administración de asesores legales</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>

            <a href="../expedientes/index.php" class="modulo-card expedientes">
                <div class="card-icon">
                    <i class="bi bi-folder-fill"></i>
                </div>
                <h3 class="card-title">Expedientes</h3>
                <p class="card-description">Catálogo de casos y expedientes</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>

            <a href="../prestamos/index.php" class="modulo-card prestamos">
                <div class="card-icon">
                    <i class="bi bi-journal-check"></i>
                </div>
                <h3 class="card-title">Préstamos</h3>
                <p class="card-description">Control de préstamos de expedientes</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>

            <a href="../reportes/index.php" class="modulo-card reportes">
                <div class="card-icon">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>
                <h3 class="card-title">Reportes</h3>
                <p class="card-description">Estadísticas y reportes del sistema</p>
                <div class="card-arrow">
                    <i class="bi bi-arrow-right-circle-fill"></i>
                </div>
            </a>


            <?php if ($_SESSION['rol'] === 'Directora'): ?>
                <a href="../usuarios/index.php" class="modulo-card usuarios">
                    <div class="card-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="card-title">Usuarios</h3>
                    <p class="card-description">Administración de usuarios del sistema</p>
                    <div class="card-arrow">
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </div>
                </a>
            <?php endif; ?>

        </div>
    </div>

    <?php include('../footer.php'); ?>
</body>
</html>