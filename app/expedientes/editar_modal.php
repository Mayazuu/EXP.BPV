<?php
include('../session_config.php');
include('../conexion.php');

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Secretaria', 'Directora'])) {
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}
$id_expediente = $_GET['id_expediente'] ?? $_POST['id_expediente'] ?? '';

if (empty($id_expediente)) {
    echo json_encode(['error' => 'ID de expediente no especificado']);
    exit();
}

// ===== PROCESAR ACTUALIZACIÓN (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Obtener el ID desde POST
    $id_expediente = $_POST['id_expediente'] ?? '';
    
    if (empty($id_expediente)) {
        echo json_encode(['error' => 'ID de expediente no especificado']);
        exit();
    }
    try {
        $id_estado_exp = $_POST['id_estado_exp'] ?? '';
        $id_juzgado = $_POST['id_juzgado'] ?? '';
        $folios = $_POST['folios'] ?? 0;
        $id_estante = $_POST['id_estante'] ?? '';
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validar folios
        if (!is_numeric($folios) || $folios < 0) {
            throw new Exception("El número de folios debe ser un valor numérico positivo.");
        }

        // Manejo de nuevo estante
        if ($id_estante === 'otro') {
            $nuevo_numero_estante = trim($_POST['nuevo_numero_estante'] ?? '');
            if (empty($nuevo_numero_estante)) {
                throw new Exception("Debe ingresar un número de estante.");
            }
            
            $stmt = $conn->prepare("SELECT id_estante FROM estantes WHERE estante = ?");
            $stmt->execute([$nuevo_numero_estante]);
            $estanteExistente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($estanteExistente) {
                $id_estante = $estanteExistente['id_estante'];
            } else {
                $stmt = $conn->prepare("INSERT INTO estantes (estante) VALUES (?)");
                $stmt->execute([$nuevo_numero_estante]);
                $id_estante = $conn->lastInsertId();
            }
        }

        // Validar que existan los registros relacionados
        $validaciones = [
            ["estados_exp", "id_estado_exp", $id_estado_exp, "Estado no existe."],
            ["juzgados", "id_juzgado", $id_juzgado, "Juzgado no existe."],
            ["estantes", "id_estante", $id_estante, "Estante no existe."]
        ];

        foreach ($validaciones as $v) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM {$v[0]} WHERE {$v[1]} = ?");
            $stmt->execute([$v[2]]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception($v[3]);
            }
        }

        // Actualizar expediente
        $stmt = $conn->prepare("
            UPDATE expedientes 
            SET id_estado_exp = ?, 
                id_juzgado = ?, 
                folios = ?, 
                id_estante = ?, 
                observaciones = ?
            WHERE id_expediente = ?
        ");
        
        $stmt->execute([
            $id_estado_exp,
            $id_juzgado,
            $folios,
            $id_estante,
            $observaciones,
            $id_expediente
        ]);

        echo json_encode(['success' => 'Expediente actualizado exitosamente']);
        exit();

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// ===== OBTENER DATOS DEL EXPEDIENTE (GET) =====
try {
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            es.nombre AS estudiante_nombre,
            es.apellido AS estudiante_apellido,
            i.nombre AS interesado_nombre,
            i.apellido AS interesado_apellido,
            a.nombre AS asesor_nombre,
            a.apellido AS asesor_apellido,
            j.nombre AS juzgado_nombre,
            tc.caso AS tipo_caso_nombre,
            ee.estado_exp AS estado_nombre,
            est.estante AS estante_nombre
        FROM expedientes e
        INNER JOIN estudiantes es ON e.id_estudiante = es.id_estudiante
        INNER JOIN interesados i ON e.id_interesado = i.id_interesado
        INNER JOIN asesores a ON e.id_asesor = a.id_asesor
        INNER JOIN juzgados j ON e.id_juzgado = j.id_juzgado
        INNER JOIN tipo_caso tc ON e.id_tipo_exp = tc.id_tipo_exp
        INNER JOIN estados_exp ee ON e.id_estado_exp = ee.id_estado_exp
        LEFT JOIN estantes est ON e.id_estante = est.id_estante
        WHERE e.id_expediente = ?
    ");
    $stmt->execute([$id_expediente]);
    $expediente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expediente) {
        echo json_encode(['error' => 'Expediente no encontrado']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al cargar expediente']);
    exit();
}

// ===== CONSULTAS PARA SELECTS =====
$estados_exp = $conn->query("SELECT id_estado_exp, estado_exp FROM estados_exp ORDER BY estado_exp")->fetchAll(PDO::FETCH_ASSOC);
$estantes = $conn->query("SELECT id_estante, estante FROM estantes ORDER BY estante")->fetchAll(PDO::FETCH_ASSOC);
$juzgados = $conn->query("SELECT id_juzgado, nombre FROM juzgados ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- MODAL -->
<div id="modalEditar" class="modal" style="display:flex;">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>✏️ Editar Expediente #<?= htmlspecialchars($expediente['id_expediente']) ?></h3>
            <button class="modal-close" onclick="cerrarModalEditar()">&times;</button>
        </div>
        <div class="modal-body">

            <!-- CAMPOS SOLO LECTURA -->
            <div class="editar-info-section">
                <strong>Ficha Social:</strong> <?= htmlspecialchars($expediente['ficha_social']) ?><br>
                <strong>Número de Caso:</strong> <?= htmlspecialchars($expediente['numero_caso']) ?><br>
                <strong>Año:</strong> <?= htmlspecialchars($expediente['anio']) ?><br>
                <strong>Estudiante:</strong> <?= htmlspecialchars($expediente['estudiante_nombre'].' '.$expediente['estudiante_apellido']) ?><br>
                <strong>Cliente:</strong> <?= htmlspecialchars($expediente['interesado_nombre'].' '.$expediente['interesado_apellido']) ?><br>
                <strong>Tipo de Caso:</strong> <?= htmlspecialchars($expediente['tipo_caso_nombre']) ?><br>
                <strong>Asesor:</strong> <?= htmlspecialchars($expediente['asesor_nombre'].' '.$expediente['asesor_apellido']) ?><br>
            </div>

            <hr style="margin: 1rem 0; border: none; border-top: 1px solid #E5E7EB;">

            <!-- FORMULARIO -->
            <form id="formEditarModal">
                <input type="hidden" name="id_expediente" value="<?= $expediente['id_expediente'] ?>">

                <div style="margin-bottom: 1rem;">
                    <label>Estado *</label>
                    <select name="id_estado_exp" required>
                        <option value="">-- Seleccionar Estado --</option>
                        <?php foreach($estados_exp as $estado): ?>
                            <option value="<?= $estado['id_estado_exp'] ?>" <?= $expediente['id_estado_exp']==$estado['id_estado_exp']?'selected':'' ?>>
                                <?= htmlspecialchars($estado['estado_exp']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label>Juzgado *</label>
                    <select name="id_juzgado" required>
                        <option value="">-- Seleccionar Juzgado --</option>
                        <?php foreach($juzgados as $j): ?>
                            <option value="<?= $j['id_juzgado'] ?>" <?= $expediente['id_juzgado']==$j['id_juzgado']?'selected':'' ?>>
                                <?= htmlspecialchars($j['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label>Folios *</label>
                    <input type="number" name="folios" min="0" value="<?= htmlspecialchars($expediente['folios']) ?>" required>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label>Estante *</label>
                    <select name="id_estante" id="selectEstanteModal" required>
                        <option value="">-- Seleccionar Estante --</option>
                        <?php foreach($estantes as $est): ?>
                            <option value="<?= $est['id_estante'] ?>" <?= $expediente['id_estante']==$est['id_estante']?'selected':'' ?>>
                                <?= htmlspecialchars($est['estante']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="otro">➕ Agregar Nuevo Estante</option>
                    </select>
                    <input type="text" id="nuevoEstanteModal" name="nuevo_numero_estante" placeholder="Número del nuevo estante" style="display:none; margin-top:0.5rem;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="4"><?= htmlspecialchars($expediente['observaciones']) ?></textarea>
                </div>

                <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">💾 Guardar Cambios</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalEditar()" style="flex:1;">❌ Cancelar</button>
                </div>
            </form>

            <div id="editarModalMensaje" style="margin-top:1rem;"></div>

        </div>
    </div>
</div>

<script>
// Mostrar u ocultar input nuevo estante
document.getElementById('selectEstanteModal').addEventListener('change', function() {
    const nuevo = document.getElementById('nuevoEstanteModal');
    if (this.value === 'otro') {
        nuevo.style.display = 'block';
        nuevo.required = true;
    } else {
        nuevo.style.display = 'none';
        nuevo.required = false;
        nuevo.value = '';
    }
});

// Enviar formulario vía AJAX
document.getElementById('formEditarModal').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    // DEBUG: Ver qué datos se están enviando
    console.log('Enviando datos:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }

    // Deshabilitar botón mientras procesa
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Guardando...';

    fetch('editar_modal.php', {
        method: 'POST',
        body: formData
    })
    .then(r => {
        console.log('Response status:', r.status);
        console.log('Response headers:', r.headers);
        return r.text(); // Cambiado temporalmente a text para ver respuesta completa
    })
    .then(text => {
        console.log('Response text:', text);
        
        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error('Error parseando JSON:', e);
            throw new Error('Respuesta no es JSON válido: ' + text.substring(0, 200));
        }
        
        const msgDiv = document.getElementById('editarModalMensaje');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (data.error) {
            msgDiv.innerHTML = '<div class="alert alert-error">❌ '+data.error+'</div>';
        } else if (data.success) {
            msgDiv.innerHTML = '<div class="alert alert-success">✅ '+data.success+'</div>';
            setTimeout(() => {
                location.reload(); // Recargar tabla con cambios
            }, 1000);
        } else {
            msgDiv.innerHTML = '<div class="alert alert-error">❌ Respuesta inesperada del servidor</div>';
        }
    })
    .catch(err => {
        console.error('Error completo:', err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        document.getElementById('editarModalMensaje').innerHTML = '<div class="alert alert-error">❌ Error: '+err.message+'</div>';
    });
});

// Cerrar modal
function cerrarModalEditar() {
    const modal = document.getElementById('modalEditar');
    if(modal) {
        modal.remove();
    }
}
</script>

