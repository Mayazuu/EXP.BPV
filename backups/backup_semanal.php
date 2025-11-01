<?php
// ═══════════════════════════════════════════════════════════════════
// BACKUP SEMANAL AUTOMÁTICO
// Versión PHP (no requiere mysqldump)
// Se ejecuta cada semana y mantiene solo las últimas 2 semanas
// ═══════════════════════════════════════════════════════════════════

// CONFIGURACIÓN
$BACKUP_DIR = dirname(__FILE__) . '/backups_semanales';
$DIAS_RETENER = 14; // Solo mantener backups de las últimas 2 semanas

// Incluir conexión a la base de datos
include('C:/xampp/htdocs/bufete/app/conexion.php');

// Crear carpeta de backups si no existe
if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0755, true);
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
        $sql .= "-- Volcado de datos para la tabla `$tabla` ($numRows registros)\n";
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

// Obtener nombre de la base de datos
$dbname = $conn->query('SELECT DATABASE()')->fetchColumn();

echo "═══════════════════════════════════════════════════════════\n";
echo "   BACKUP SEMANAL - BUFETE POPULAR LA VERAPAZ\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "📅 Fecha: " . date('d/m/Y H:i:s') . "\n\n";

// Crear encabezado del archivo SQL
$sqlDump = "-- ========================================================\n";
$sqlDump .= "-- BACKUP SEMANAL - BUFETE POPULAR LA VERAPAZ\n";
$sqlDump .= "-- ========================================================\n";
$sqlDump .= "-- Base de datos: `$dbname`\n";
$sqlDump .= "-- Fecha de backup: " . date('Y-m-d H:i:s') . "\n";
$sqlDump .= "-- Tipo: BACKUP SEMANAL (Retención: $DIAS_RETENER días)\n";
$sqlDump .= "-- ========================================================\n\n";

$sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sqlDump .= "SET time_zone = \"+00:00\";\n\n";

// Obtener lista de todas las tablas
echo "📦 Generando backup...\n";
$stmt = $conn->query("SHOW TABLES");
$tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Exportar cada tabla
$totalTablas = count($tablas);
$contador = 0;
foreach ($tablas as $tabla) {
    $contador++;
    echo "   → Procesando tabla $contador/$totalTablas: $tabla\n";
    $sqlDump .= exportarTabla($conn, $tabla);
}

$sqlDump .= "\n-- ========================================================\n";
$sqlDump .= "-- FIN DEL BACKUP SEMANAL\n";
$sqlDump .= "-- ========================================================\n";

// Guardar archivo
$fecha = date('Y-m-d_H-i-s');
$nombreArchivo = "backup_semanal_{$fecha}.sql";
$rutaCompleta = $BACKUP_DIR . '/' . $nombreArchivo;

file_put_contents($rutaCompleta, $sqlDump);

$tamanio = filesize($rutaCompleta);
$tamanioMB = round($tamanio / 1024 / 1024, 2);

echo "\n✓ Backup generado exitosamente\n";
echo "  → Archivo: $nombreArchivo\n";
echo "  → Tamaño: $tamanioMB MB\n";

// Comprimir el archivo
echo "\n📦 Comprimiendo backup...\n";
$archivoGz = $rutaCompleta . '.gz';
$contenido = file_get_contents($rutaCompleta);
$gz = gzopen($archivoGz, 'w9');
gzwrite($gz, $contenido);
gzclose($gz);

// Eliminar archivo sin comprimir
unlink($rutaCompleta);

$tamanioGz = filesize($archivoGz);
$tamanioGzMB = round($tamanioGz / 1024 / 1024, 2);

echo "✓ Backup comprimido\n";
echo "  → Archivo: {$nombreArchivo}.gz\n";
echo "  → Tamaño: $tamanioGzMB MB\n";

// Eliminar backups antiguos (más de 2 semanas)
echo "\n🗑️  Limpiando backups antiguos (> $DIAS_RETENER días)...\n";

$archivosEliminados = 0;
$files = glob($BACKUP_DIR . '/backup_semanal_*.sql.gz');

foreach ($files as $file) {
    if (is_file($file)) {
        $diasAntiguedad = (time() - filemtime($file)) / (60 * 60 * 24);
        
        if ($diasAntiguedad > $DIAS_RETENER) {
            unlink($file);
            $archivosEliminados++;
        }
    }
}

if ($archivosEliminados > 0) {
    echo "✓ Se eliminaron $archivosEliminados backup(s) antiguo(s)\n";
} else {
    echo "ℹ  No hay backups antiguos para eliminar\n";
}

// Mostrar resumen de backups actuales
echo "\n═══════════════════════════════════════════════════════════\n";
echo "   BACKUPS ACTUALES (Últimas 2 semanas)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$backupsActuales = glob($BACKUP_DIR . '/backup_semanal_*.sql.gz');
rsort($backupsActuales); // Ordenar de más reciente a más antiguo

$numero = 1;
foreach ($backupsActuales as $backup) {
    $nombre = basename($backup);
    $tamanio = filesize($backup);
    $tamanioMB = round($tamanio / 1024 / 1024, 2);
    $fecha = date('Y-m-d H:i:s', filemtime($backup));
    
    echo "{$numero}. $nombre\n";
    echo "   → Tamaño: {$tamanioMB} MB | Fecha: $fecha\n";
    $numero++;
}

$totalBackups = count($backupsActuales);
echo "\n═══════════════════════════════════════════════════════════\n";
echo "✓ Total de backups disponibles: $totalBackups\n";
echo "═══════════════════════════════════════════════════════════\n";

// Espacio utilizado
$dirSize = 0;
foreach (glob($BACKUP_DIR . '/*') as $file) {
    $dirSize += filesize($file);
}
$dirSizeMB = round($dirSize / 1024 / 1024, 2);

echo "\n💾 Espacio utilizado:\n";
echo "  → Carpeta backups: {$dirSizeMB} MB\n";

echo "\n✓ Proceso completado exitosamente\n\n";

// Opcional: Registrar en transacciones (comentado por defecto)

try {
    $stmt = $conn->prepare("INSERT INTO transacciones (id_usuario, accion, tabla, descripcion) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        1, // ID de usuario sistema
        'BACKUP_SEMANAL',
        'SISTEMA',
        "Backup semanal automático generado: {$nombreArchivo}.gz ({$tamanioGzMB} MB)"
    ]);
} catch(Exception $e) {
}

?>