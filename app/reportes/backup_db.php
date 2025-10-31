<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión - SOLO DIRECTORA puede hacer backups
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Directora') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado - Bufete Popular</title>
        <link rel="stylesheet" href="../css/style.css">
        <link rel="stylesheet" href="css/style_reportes.css">

    </head>
    <body>
        <div class="mensaje-container">
            <div class="icono">🔒</div>
            <h1>Acceso Denegado</h1>
            <p>Solo la Directora puede realizar respaldos de la base de datos.</p>
            <a href="index.php" class="btn">🏠 Volver</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Función para exportar tabla a SQL
function exportarTabla($conn, $tabla) {
    $sql = "";

    // Obtener estructura de la tabla
    $sql .= "\n-- --------------------------------------------------------\n";
    $sql .= "-- Estructura de tabla para `$tabla`\n";
    $sql .= "-- --------------------------------------------------------\n\n";

    $sql .= "DROP TABLE IF EXISTS `$tabla`;\n";

    $stmt = $conn->query("SHOW CREATE TABLE `$tabla`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $sql .= $row['Create Table'] . ";\n\n";

    // Obtener datos de la tabla
    $stmt = $conn->query("SELECT * FROM `$tabla`");
    $numRows = $stmt->rowCount();

    if ($numRows > 0) {
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Volcado de datos para la tabla `$tabla`\n";
        $sql .= "-- --------------------------------------------------------\n\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql .= "INSERT INTO `$tabla` VALUES (";

            $values = array();
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . addslashes($value) . "'";
                }
            }

            $sql .= implode(", ", $values);
            $sql .= ");\n";
        }

        $sql .= "\n";
    }

    return $sql;
}

// Obtener nombre de la base de datos desde la conexión
$dbname = $conn->query('SELECT DATABASE()')->fetchColumn();

// Crear encabezado del archivo SQL
$sqlDump = "-- ========================================================\n";
$sqlDump .= "-- RESPALDO DE BASE DE DATOS - BUFETE POPULAR LA VERAPAZ\n";
$sqlDump .= "-- ========================================================\n";
$sqlDump .= "-- Base de datos: `$dbname`\n";
$sqlDump .= "-- Fecha de respaldo: " . date('Y-m-d H:i:s') . "\n";
$sqlDump .= "-- Generado por: " . $_SESSION['usuario'] . " (" . $_SESSION['rol'] . ")\n";
$sqlDump .= "-- ========================================================\n\n";

$sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sqlDump .= "SET time_zone = \"+00:00\";\n\n";

// Obtener lista de todas las tablas
$stmt = $conn->query("SHOW TABLES");
$tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Exportar cada tabla
foreach ($tablas as $tabla) {
    $sqlDump .= exportarTabla($conn, $tabla);
}

$sqlDump .= "\n-- ========================================================\n";
$sqlDump .= "-- FIN DEL RESPALDO\n";
$sqlDump .= "-- ========================================================\n";

// Registrar transacción
try {
    $stmt = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, tabla, descripcion) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['id_usuario'],
        'BACKUP',
        'SISTEMA',
        'Respaldo completo de la base de datos'
    ]);
} catch(Exception $e) {
    // Si falla el registro, continuar con la descarga
}

// Configurar headers para descarga
$filename = "backup_bufete_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sqlDump));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Enviar archivo
echo $sqlDump;

exit();
?>