<?php
include('../session_config.php');
include('../conexion.php');

if (isset($_GET['id_expediente'])) {
    $id = (int)$_GET['id_expediente'];

    $stmt = $conn->prepare("
        SELECT
            e.id_expediente,
            e.ficha_social,
            e.numero_caso,
            e.anio,
            e.num_proceso,
            e.folios,
            e.fecha_inicio,
            e.fecha_audiencia1,
            e.fecha_audiencia2,
            e.fecha_finalizacion,
            e.observaciones,
            es.id_estudiante,
            es.dpi_estudiante,
            es.carnetEstudiantil,
            es.nombre AS estudiante_nombre,
            es.apellido AS estudiante_apellido,
            i.id_interesado,
            i.dpi_interesado,
            i.nombre AS interesado_nombre,
            i.apellido AS interesado_apellido,
            i.telefono AS interesado_telefono,
            i.direccion_exacta AS interesado_direccion,
            a.nombre AS asesor_nombre,
            a.apellido AS asesor_apellido,
            j.nombre AS juzgado_nombre,
            ee.estado_exp AS estado,
            tc.caso AS tipo_caso,
            ar.area AS area_legal,
            est.estante AS estante_nombre
        FROM expedientes e
        INNER JOIN estudiantes es ON e.id_estudiante = es.id_estudiante
        INNER JOIN interesados i ON e.id_interesado = i.id_interesado
        INNER JOIN estados_exp ee ON e.id_estado_exp = ee.id_estado_exp
        INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
        LEFT JOIN areas ar ON tc.id_area = ar.id_area
        LEFT JOIN asesores a ON e.id_asesor = a.id_asesor
        LEFT JOIN juzgados j ON e.id_juzgado = j.id_juzgado
        LEFT JOIN estantes est ON e.id_estante = est.id_estante
        WHERE e.id_expediente = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $exp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exp):
?>
<div style="font-family: 'Segoe UI', sans-serif; padding: 20px;">
    <h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 20px;">
        📂 Expediente #<?= htmlspecialchars($exp['id_expediente']) ?>
    </h3>

    <!-- INFORMACIÓN GENERAL -->
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="color: #495057; margin-top: 0;">📋 Información General</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 40%;">Ficha Social:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['ficha_social']) ?></td>
            </tr>
            <tr style="background: #fff;">
                <td style="padding: 8px; font-weight: bold;">Número de Caso:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['numero_caso']) ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Año:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['anio']) ?></td>
            </tr>
            <tr style="background: #fff;">
                <td style="padding: 8px; font-weight: bold;">Número de Proceso:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['num_proceso']) ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Estado:</td>
                <td style="padding: 8px;">
                    <span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.9em;">
                        <?= htmlspecialchars($exp['estado']) ?>
                    </span>
                </td>
            </tr>
            <tr style="background: #fff;">
                <td style="padding: 8px; font-weight: bold;">Folios:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['folios']) ?></td>
            </tr>
        </table>
    </div>

    <!-- ESTUDIANTE -->
    <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="color: #0066cc; margin-top: 0;">🎓 Estudiante Asignado</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 40%;">Nombre:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['estudiante_nombre'] . ' ' . $exp['estudiante_apellido']) ?></td>
            </tr>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">DPI:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['dpi_estudiante'] ?: 'No registrado') ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Carnet Estudiantil:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['carnetEstudiantil'] ?: 'No registrado') ?></td>
            </tr>
        </table>
    </div>

    <!-- CLIENTE / INTERESADO -->
    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="color: #856404; margin-top: 0;">👤 Cliente (Interesado)</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 40%;">Nombre:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['interesado_nombre'] . ' ' . $exp['interesado_apellido']) ?></td>
            </tr>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">DPI/Cédula:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['dpi_interesado'] ?: 'No registrado') ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Teléfono:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['interesado_telefono'] ?: 'No registrado') ?></td>
            </tr>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">Dirección:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['interesado_direccion']) ?></td>
            </tr>
        </table>
    </div>

    <!-- CASO LEGAL -->
    <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="color: #155724; margin-top: 0;">⚖️ Información Legal</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 40%;">Tipo de Caso:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['tipo_caso']) ?></td>
            </tr>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">Área Legal:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['area_legal'] ?: 'No especificada') ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Asesor:</td>
                <td style="padding: 8px;">
                    <?= $exp['asesor_nombre'] 
                        ? htmlspecialchars($exp['asesor_nombre'] . ' ' . $exp['asesor_apellido']) 
                        : 'No asignado' ?>
                </td>
            </tr>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">Juzgado:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['juzgado_nombre'] ?: 'No asignado') ?></td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Estante:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['estante_nombre'] ?: 'Sin asignar') ?></td>
            </tr>
        </table>
    </div>

    <!-- FECHAS -->
    <div style="background: #f3e5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="color: #6a1b9a; margin-top: 0;">📅 Fechas Importantes</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px; font-weight: bold; width: 40%;">Fecha de Inicio:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['fecha_inicio']) ?></td>
            </tr>
            <?php if($exp['fecha_audiencia1']): ?>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">Audiencia 1:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['fecha_audiencia1']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if($exp['fecha_audiencia2']): ?>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Audiencia 2:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['fecha_audiencia2']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if($exp['fecha_finalizacion']): ?>
            <tr style="background: rgba(255,255,255,0.5);">
                <td style="padding: 8px; font-weight: bold;">Fecha de Finalización:</td>
                <td style="padding: 8px;"><?= htmlspecialchars($exp['fecha_finalizacion']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- OBSERVACIONES -->
    <?php if($exp['observaciones']): ?>
    <div style="background: #fff; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
        <h4 style="color: #495057; margin-top: 0;">📝 Observaciones</h4>
        <p style="line-height: 1.6; color: #6c757d; margin: 0;">
            <?= nl2br(htmlspecialchars($exp['observaciones'])) ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php
    else:
        echo '<div style="text-align:center; padding:40px; color:#dc3545;">
                <h3>❌ Expediente no encontrado</h3>
                <p>El expediente solicitado no existe o fue eliminado.</p>
            </div>';
    endif;
} else {
    echo '<div style="text-align:center; padding:40px; color:#dc3545;">
            <h3>⚠️ Error</h3>
            <p>No se especificó un ID de expediente.</p>
        /div>';
}
?>



