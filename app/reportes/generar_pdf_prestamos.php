<?php
require('../lib/fpdf.php');
include('../conexion.php');
include('../session_config.php');

// Función helper para convertir encoding
function convertir_utf8($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// Validar sesión
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado - Bufete Popular</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
            .mensaje-container { background:white; padding:50px 40px; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.3); text-align:center; max-width:500px; }
            .icono { font-size:80px; margin-bottom:20px; }
            h1 { color:#d32f2f; font-size:28px; margin-bottom:15px; }
            p { color:#666; font-size:16px; margin-bottom:30px; }
            .btn { padding:14px 30px; border-radius:10px; text-decoration:none; font-weight:bold; font-size:15px; background:linear-gradient(135deg,#fc4a1a 0%,#f7b733 100%); color:white; display:inline-block; transition:all 0.3s; }
            .btn:hover { transform:translateY(-2px); box-shadow:0 10px 25px rgba(252,74,26,0.4); }
        </style>
    </head>
    <body>
        <div class="mensaje-container">
            <div class="icono">🔒</div>
            <h1>Acceso Denegado</h1>
            <p>No tienes permisos para acceder a esta página.</p>
            <a href="../modulo_inicio/login.php" class="btn">🏠 Volver al Login</a>
        </div>
    </body>
    </html>';
    exit();
}

// Capturar filtros
$estado_seleccionado = $_POST['id_estado'] ?? '';
$fecha_desde = $_POST['fecha_desde'] ?? '';
$fecha_hasta = $_POST['fecha_hasta'] ?? '';

if (!$estado_seleccionado) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><title>Falta Información</title></head>
    <body>
        <div class="mensaje-container">
            <div class="icono">⚠️</div>
            <h1>Falta Información</h1>
            <p>Debes seleccionar un estado para generar el reporte.</p>
            <a href="javascript:history.back()" class="btn">🔙 Volver</a>
        </div>
    </body>
    </html>';
    exit();
}

// Obtener nombre del estado y preparar filtro
$params = [];
if ($estado_seleccionado === 'todos') {
    $estado_nombre = 'Todos los estados';
    $sql_where = "WHERE 1=1";
} else {
    $stmt = $conn->prepare("SELECT estado_prest FROM estados_prest WHERE id_estado_prest = ?");
    $stmt->execute([$estado_seleccionado]);
    $estado_nombre = $stmt->fetchColumn();
    $sql_where = "WHERE p.id_estado_prest = ?";
    $params[] = $estado_seleccionado;
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

// Agregar filtro de fechas
if ($fecha_desde && $fecha_hasta) {
    $sql .= " AND p.fecha_entrega BETWEEN ? AND ?";
    $params[] = $fecha_desde;
    $params[] = $fecha_hasta;
}

$sql .= " ORDER BY p.fecha_entrega DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no hay resultados
if (empty($prestamos)) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><title>Sin Resultados</title></head>
    <body>
        <div class="mensaje-container">
            <div class="icono">📚</div>
            <h1>No se encontraron préstamos</h1>
            <p>No hay préstamos que coincidan con el estado seleccionado' . ($fecha_desde && $fecha_hasta ? ' y el rango de fechas especificado' : '') . '.</p>
            <div class="botones">
                <a href="javascript:history.back()" class="btn btn-primary">🔙 Cambiar Filtros</a>
                <a href="reporte_prestamos.php" class="btn btn-secondary">🔄 Nueva Búsqueda</a>
                <a href="index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

// Generar PDF
class PDF extends FPDF {
    function Header() {
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/bufete/app/img/logo.png';
        if(file_exists($logo_path)) $this->Image($logo_path, 10, 8, 25);

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(63,81,181);
        $this->Cell(0, 10, 'BUFETE POPULAR LA VERAPAZ', 0, 1, 'C');
        $this->SetFont('Arial','',12);
        $this->Cell(0, 8, 'Reporte de Expedientes', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Página '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Info reporte
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Fecha de generación: '.date('d/m/Y H:i:s'),0,1);
$pdf->Cell(0,6,'Estado seleccionado: '.$estado_nombre,0,1);
if($fecha_desde && $fecha_hasta){
    $pdf->Cell(0,6,'Rango de fechas: '.date('d/m/Y',strtotime($fecha_desde)).' - '.date('d/m/Y',strtotime($fecha_hasta)),0,1);
}
$pdf->Cell(0,6,'Generado por: '.$_SESSION['usuario'].' ('.$_SESSION['rol'].')',0,1);
$pdf->Ln(5);

// Encabezado tabla
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(33,150,243);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(20,8,'ID',1,0,'C',true);
$pdf->Cell(30,8,'DPI',1,0,'C',true);
$pdf->Cell(55,8,'Estudiante',1,0,'C',true);
$pdf->Cell(25,8,'Expediente',1,0,'C',true);
$pdf->Cell(35,8,'Fecha Prestamo',1,0,'C',true);
$pdf->Cell(30,8,'Fecha Est. Dev.',1,0,'C',true);
$pdf->Cell(35,8,'Fecha Devolucion',1,0,'C',true);
$pdf->Cell(25,8,'Estado',1,0,'C',true);
$pdf->Cell(25,8,'Usuario',1,1,'C',true);

// Datos
// Datos
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);

// Sin relleno alternado - todo en blanco
foreach($prestamos as $prest) {
    $pdf->Cell(20, 7, $prest['id_prestamo'], 1, 0, 'C', false);
    $pdf->Cell(30, 7, $prest['DPI_estudiante'], 1, 0, 'L', false);
    $pdf->Cell(55, 7, convertir_utf8(substr($prest['nombre_estudiante'], 0, 30)), 1, 0, 'L', false);
    $pdf->Cell(25, 7, $prest['id_expediente'], 1, 0, 'C', false);
    $pdf->Cell(35, 7, date('d/m/Y H:i', strtotime($prest['fecha_entrega'])), 1, 0, 'C', false);
    $pdf->Cell(30, 7, date('d/m/Y', strtotime($prest['fecha_estimada_dev'])), 1, 0, 'C', false);

    $fecha_dev = $prest['fecha_devolucion']
        ? date('d/m/Y H:i', strtotime($prest['fecha_devolucion']))
        : 'Pendiente';
    $pdf->Cell(35, 7, $fecha_dev, 1, 0, 'C', false);

    $pdf->Cell(25, 7, convertir_utf8(substr($prest['estado'], 0, 15)), 1, 0, 'C', false);
    $pdf->Cell(25, 7, convertir_utf8(substr($prest['usuario_registro'] ?? 'N/A', 0, 15)), 1, 1, 'C', false);
}

// Total registros
$pdf->Ln(5);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,8,'Total de prestamos: '.count($prestamos),0,1);

// Salida PDF
$pdf->Output('I','Reporte_Prestamos_'.$estado_nombre.'_'.date('Y-m-d').'.pdf');
