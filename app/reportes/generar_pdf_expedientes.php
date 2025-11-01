<?php
if(!defined('FPDF_FONTPATH'))
    define('FPDF_FONTPATH', dirname(__FILE__).'/../lib/font/');
require('../lib/fpdf.php');
include('../conexion.php');
include('../session_config.php');

function convertir_utf8($texto) {
    $conversiones = array(
        'á' => chr(225), 'é' => chr(233), 'í' => chr(237), 'ó' => chr(243), 'ú' => chr(250),
        'Á' => chr(193), 'É' => chr(201), 'Í' => chr(205), 'Ó' => chr(211), 'Ú' => chr(218),
        'ñ' => chr(241), 'Ñ' => chr(209),
        'ü' => chr(252), 'Ü' => chr(220),
        '¿' => chr(191), '¡' => chr(161),
        '°' => chr(176)
    );
    return strtr($texto, $conversiones);
}

// Validar sesión
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    ?>



    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado - Bufete Popular</title>
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body>
        <div class="mensaje-container">
            <div class="icono">🔒</div>
            <h1>Acceso Denegado</h1>
            <p>No tienes permisos para acceder a esta página.</p>
            <a href="../modulo_inicio/login.php" class="btn">🏠 Volver al Login</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Obtener filtros seleccionados
$filtros_aplicados = [];
$sql_where = '';
$params = [];

// Filtro por año
if (isset($_POST['filtro_ano']) && !empty($_POST['ano'])) {
    $ano = $_POST['ano'];
    $sql_where .= " AND e.anio = ?";
    $params[] = $ano;
    $filtros_aplicados[] = "Año: $ano";
}

// Filtro por área
if (isset($_POST['filtro_area']) && !empty($_POST['id_area'])) {
    $id_area = $_POST['id_area'];
    $sql_where .= " AND tc.id_area = ?";
    $params[] = $id_area;
    $stmt = $conn->prepare("SELECT area FROM areas WHERE id_area = ?");
    $stmt->execute([$id_area]);
    $area_nombre = $stmt->fetchColumn();
    $filtros_aplicados[] = "Área: $area_nombre";
}

// Filtro por tipo de caso
if (isset($_POST['filtro_tipo_caso']) && !empty($_POST['id_tipo_exp'])) {
    $id_tipo_caso = $_POST['id_tipo_exp'];
    $sql_where .= " AND e.id_tipo_exp = ?";
    $params[] = $id_tipo_caso;
    $stmt = $conn->prepare("SELECT caso FROM tipo_caso WHERE id_tipo_exp = ?");
    $stmt->execute([$id_tipo_caso]);
    $tipo_nombre = $stmt->fetchColumn();
    $filtros_aplicados[] = "Tipo: $tipo_nombre";
}

// Filtro por estado
if (isset($_POST['filtro_estado']) && !empty($_POST['id_estado_exp'])) {
    $id_estado = $_POST['id_estado_exp'];
    $sql_where .= " AND e.id_estado_exp = ?";
    $params[] = $id_estado;
    $stmt = $conn->prepare("SELECT estado_exp FROM estados_exp WHERE id_estado_exp = ?");
    $stmt->execute([$id_estado]);
    $estado_nombre = $stmt->fetchColumn();
    $filtros_aplicados[] = "Estado: $estado_nombre";
}

// Filtro por estudiante
if (isset($_POST['filtro_estudiante']) && !empty($_POST['id_estudiante'])) {
    $id_estudiante = $_POST['id_estudiante'];
    $stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM estudiantes WHERE id_estudiante = ?");
    $stmt->execute([$id_estudiante]);
    $nombre_estudiante = $stmt->fetchColumn();
    
    if ($nombre_estudiante) {
        $sql_where .= " AND e.id_estudiante = ?";
        $params[] = $id_estudiante;
        $filtros_aplicados[] = "Estudiante: {$nombre_estudiante}";
    }
}

// Filtro por fechas
if (isset($_POST['filtro_fechas']) && !empty($_POST['fecha_desde']) && !empty($_POST['fecha_hasta'])) {
    $fecha_desde = $_POST['fecha_desde'];
    $fecha_hasta = $_POST['fecha_hasta'];
    $sql_where .= " AND e.fecha_inicio BETWEEN ? AND ?";
    $params[] = $fecha_desde;
    $params[] = $fecha_hasta;
    $filtros_aplicados[] = "Fechas: " . date('d/m/Y', strtotime($fecha_desde)) . " - " . date('d/m/Y', strtotime($fecha_hasta));
}

// Texto de filtros aplicados
$filtro_texto = empty($filtros_aplicados) ? "Todos los expedientes" : implode(" | ", $filtros_aplicados);

// Consulta de expedientes
$sql = "
    SELECT 
        e.id_expediente,
        e.ficha_social,
        e.numero_caso,
        e.anio,
        CONCAT(est.nombre, ' ', est.apellido) as estudiante,
        CONCAT(i.nombre, ' ', i.apellido) as interesado,
        a.area as area_legal,
        ee.estado_exp as estado,
        e.fecha_inicio,
        e.folios
    FROM expedientes e
    INNER JOIN estudiantes est ON e.id_estudiante = est.id_estudiante
    INNER JOIN interesados i ON e.id_interesado = i.id_interesado
    INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
    INNER JOIN areas a ON tc.id_area = a.id_area
    INNER JOIN estados_exp ee ON e.id_estado_exp = ee.id_estado_exp
    WHERE 1=1 $sql_where
    ORDER BY e.fecha_inicio DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expedientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no hay datos, mostrar mensaje sin redirigir
// Si no hay datos, guardar mensaje en sesión y redirigir
if (empty($expedientes)) {
    $_SESSION['mensaje_error'] = [
        'titulo' => 'No se encontraron expedientes',
        'texto' => 'No hay expedientes que coincidan con los filtros aplicados.',
        'filtros' => $filtro_texto
    ];
    header("Location: reporte_expedientes.php");
    exit();
}
    

// Crear PDF con Logo
class PDF extends FPDF {
function Header() {
    $rutas_logo = [
        $_SERVER['DOCUMENT_ROOT'] . '/bufete/app/img/logo.png'
    ];
    
    $logo_encontrado = false;
    foreach ($rutas_logo as $logo_path) {
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 10, 8, 25);
            $logo_encontrado = true;
            break;
        }
    }

    // Título centrado
    $this->SetFont('Arial', 'B', 16);
    $this->SetTextColor(63, 81, 181);
    $this->Cell(0, 10, 'BUFETE POPULAR LA VERAPAZ', 0, 1, 'C');
    $this->SetFont('Arial', '', 12);
    $this->Cell(0, 8, 'Reporte de Expedientes', 0, 1, 'C');
    $this->Ln(5);
}
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Instanciar PDF
$pdf = new PDF('L', 'mm', 'A4'); // Orientación horizontal
$pdf->AliasNbPages();
$pdf->AddPage();

// Información del reporte
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Fecha de generacion: ' . date('d/m/Y H:i:s'), 0, 1);
$pdf->MultiCell(0, 6, 'Filtros aplicados: ' . $filtro_texto, 0, 'L');
$pdf->Cell(0, 6, 'Generado por: ' . $_SESSION['usuario'] . ' (' . $_SESSION['rol'] . ')', 0, 1);
$pdf->Ln(5);

// Encabezados de tabla
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(63, 81, 181);
$pdf->SetTextColor(255, 255, 255);

$pdf->Cell(20, 8, 'ID', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Ficha Social', 1, 0, 'C', true);
$pdf->Cell(15, 8, 'Caso', 1, 0, 'C', true);
$pdf->Cell(15, 8, convertir_utf8('Año'), 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Estudiante', 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Cliente', 1, 0, 'C', true);
$pdf->Cell(35, 8, convertir_utf8('Área Legal'), 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Estado', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Fecha Inicio', 1, 0, 'C', true);
$pdf->Cell(15, 8, 'Folios', 1, 1, 'C', true);

// Datos
// Datos
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);

// Datos
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);

// CAMBIAR COLOR DE LAS LÍNEAS (BORDES)
$pdf->SetDrawColor(63, 81, 181); // Azul

// Sin relleno alternado - todo en blanco
foreach($expedientes as $exp) {
    $pdf->Cell(20, 7, $exp['id_expediente'], 1, 0, 'C', false);
    $pdf->Cell(30, 7, convertir_utf8(substr($exp['ficha_social'], 0, 18)), 1, 0, 'L', false);
    $pdf->Cell(15, 7, $exp['numero_caso'], 1, 0, 'C', false);
    $pdf->Cell(15, 7, $exp['anio'], 1, 0, 'C', false);
    $pdf->Cell(50, 7, convertir_utf8(substr($exp['estudiante'], 0, 30)), 1, 0, 'L', false);
    $pdf->Cell(50, 7, convertir_utf8(substr($exp['interesado'], 0, 30)), 1, 0, 'L', false);
    $pdf->Cell(35, 7, convertir_utf8(substr($exp['area_legal'], 0, 20)), 1, 0, 'L', false);
    $pdf->Cell(25, 7, convertir_utf8(substr($exp['estado'], 0, 15)), 1, 0, 'C', false);
    $pdf->Cell(25, 7, date('d/m/Y', strtotime($exp['fecha_inicio'])), 1, 0, 'C', false);
    $pdf->Cell(15, 7, $exp['folios'], 1, 1, 'C', false);
}

// Restaurar color de líneas por defecto
$pdf->SetDrawColor(0, 0, 0);

// Total de registros
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Total de expedientes: ' . count($expedientes), 0, 1);

// Salida del PDF
$pdf->Output('I', 'Reporte_Expedientes_' . date('Y-m-d') . '.pdf');

// Total de registros
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Total de expedientes: ' . count($expedientes), 0, 1);

// Salida del PDF
$pdf->Output('I', 'Reporte_Expedientes_' . date('Y-m-d') . '.pdf');
?>

