<?php
include('../conexion.php');
include('../session_config.php');

// Validar sesión y rol
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['Directora'])) {
    session_destroy();
    header("Location: ../modulo_inicio/login.php?error=Acceso Denegado");
    exit();
}

// ===== PAGINACIÓN =====
$registros_por_pagina = 8;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) && (int)$_GET['pagina'] > 0
    ? (int)$_GET['pagina'] : 1;

// Obtener filtros
$filtro_rol = $_GET['rol'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// ====== CONSTRUIR WHERE PARA FILTROS ======
$where = "WHERE 1=1";
$params = [];

if ($filtro_rol) {
    $where .= " AND r.rol = :rol";
    $params[':rol'] = $filtro_rol;
}

if ($filtro_estado) {
    $where .= " AND e.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($busqueda) {
    $where .= " AND (u.nombre LIKE :busqueda OR u.apellido LIKE :busqueda OR u.usuario LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

// ====== CONTAR TOTAL DE REGISTROS ======
$sql_count = "
    SELECT COUNT(*)
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    INNER JOIN estados e ON u.id_estado = e.id_estado
    $where
";

$stmt_count = $conn->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_usuarios = (int)$stmt_count->fetchColumn();

// Calcular total de páginas
$total_paginas = (int)ceil($total_usuarios / $registros_por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina_actual > $total_paginas) $pagina_actual = $total_paginas;

// Calcular OFFSET
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ====== CONSULTA PRINCIPAL CON PAGINACIÓN ======
$sql = "
    SELECT u.id_usuario, u.nombre, u.apellido, u.usuario, 
           r.rol, e.estado, u.id_rol, u.id_estado
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    INNER JOIN estados e ON u.id_estado = e.id_estado
    $where
    ORDER BY u.id_usuario DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== INFORMACIÓN DE RANGO ======
$registro_inicio = $total_usuarios > 0 ? $offset + 1 : 0;
$registro_fin = min($offset + $registros_por_pagina, $total_usuarios);
$paginaAnterior = max(1, $pagina_actual - 1);
$paginaSiguiente = min($total_paginas, $pagina_actual + 1);

// Obtener roles y estados para filtros y formularios
$roles = $conn->query("SELECT DISTINCT rol FROM roles ORDER BY rol")->fetchAll(PDO::FETCH_COLUMN);
$estados = $conn->query("SELECT DISTINCT estado FROM estados ORDER BY estado")->fetchAll(PDO::FETCH_COLUMN);

$roles_completos = $conn->query("SELECT id_rol, rol FROM roles ORDER BY rol")->fetchAll(PDO::FETCH_ASSOC);
$estados_completos = $conn->query("SELECT id_estado, estado FROM estados ORDER BY estado")->fetchAll(PDO::FETCH_ASSOC);

// Capturar mensajes
$mensaje = $_GET['mensaje'] ?? '';
$tipo_mensaje = $_GET['tipo'] ?? 'success';

// ====== FUNCIÓN PARA CONSTRUIR URL CON PARÁMETROS ======
function construirUrlPaginacion($pagina) {
    global $filtro_rol, $filtro_estado, $busqueda;
    
    $params = ['pagina' => $pagina];
    
    if (!empty($filtro_rol)) {
        $params['rol'] = $filtro_rol;
    }
    if (!empty($filtro_estado)) {
        $params['estado'] = $filtro_estado;
    }
    if (!empty($busqueda)) {
        $params['busqueda'] = $busqueda;
    }
    
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Bufete</title>
    <link rel="stylesheet" href="styleU.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="tabla-body">
    <?php include('../navbar.php'); ?>
    <?php include('../boton_ayuda.php'); ?>
    <?php include('../boton_volver.php'); ?>

    <!-- Modal de Mensajes -->
    <div id="modalMensaje" class="modal" style="display: none;">
        <div class="modal-contenido <?= $tipo_mensaje === 'error' ? 'modal-error' : 'modal-success' ?>">
            <span class="cerrar-btn">&times;</span>
            <div class="modal-icon">
                <i class="fas <?= $tipo_mensaje === 'error' ? 'fa-times-circle' : 'fa-check-circle' ?>"></i>
            </div>
            <p id="textoMensaje"></p>
            <button id="aceptarBtn" class="btn-aceptar">Aceptar</button>
        </div>
    </div>

    <!-- Modal de Confirmación de Desactivación -->
    <div id="modalDesactivar" class="modal" style="display: none;">
        <div class="modal-contenido modal-confirmar">
            <span class="cerrar-btn-desactivar">&times;</span>
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Confirmar Desactivación</h3>
            <p id="textoConfirmacion"></p>
            <div class="form-group">
                <label for="razonDesactivacion">Razón de la desactivación: *</label>
                <textarea id="razonDesactivacion" class="form-control" rows="3" placeholder="Ingrese el motivo de la desactivación (mínimo 10 caracteres)..." required></textarea>
                <span id="errorRazon" class="text-error" style="display: none;">La razón debe tener al menos 10 caracteres</span>
            </div>
            <div class="modal-botones">
                <button id="cancelarBtn" class="btn-cancelar">Cancelar</button>
                <button id="confirmarBtn" class="btn-confirmar">Desactivar Usuario</button>
            </div>
        </div>
    </div>

    <!-- Modal de Crear Usuario -->
    <div id="modalCrear" class="modal" style="display: none;">
        <div class="modal-contenido modal-formulario">
            <span class="cerrar-btn-crear">&times;</span>
            <div class="modal-header">
                <i class="fas fa-user-plus"></i>
                <h3>Crear Nuevo Usuario</h3>
            </div>

            <div class="info-box-modal">
                <i class="fas fa-info-circle"></i>
                <p>El usuario se generará automáticamente como: <strong>primera letra del nombre + apellido</strong></p>
            </div>

            <form id="formCrear" class="formulario-modal">
                <div class="form-row">
                    <div class="form-group">
                        <label for="crear_nombre">
                            <i class="fas fa-user"></i> Nombre *
                        </label>
                        <input type="text" id="crear_nombre" name="nombre" class="form-control" required pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+">
                    </div>

                    <div class="form-group">
                        <label for="crear_apellido">
                            <i class="fas fa-user"></i> Apellido *
                        </label>
                        <input type="text" id="crear_apellido" name="apellido" class="form-control" required pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="crear_contrasena">
                            <i class="fas fa-lock"></i> Contraseña *
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="crear_contrasena" name="contrasena" class="form-control" required minlength="6">
                            <button type="button" class="toggle-password" onclick="togglePassword('crear_contrasena')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="crear_confirmar">
                            <i class="fas fa-lock"></i> Confirmar *
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="crear_confirmar" name="confirmar_contrasena" class="form-control" required minlength="6">
                            <button type="button" class="toggle-password" onclick="togglePassword('crear_confirmar')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="crear_rol">
                            <i class="fas fa-user-tag"></i> Rol *
                        </label>
                        <select id="crear_rol" name="rol" class="form-control" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach($roles_completos as $rol): ?>
                                <option value="<?= $rol['id_rol'] ?>"><?= htmlspecialchars($rol['rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="crear_estado">
                            <i class="fas fa-toggle-on"></i> Estado *
                        </label>
                        <select id="crear_estado" name="estado" class="form-control" required>
                            <?php foreach($estados_completos as $estado): ?>
                                <option value="<?= $estado['id_estado'] ?>" <?= $estado['estado'] === 'Activo' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($estado['estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="errorCrear" class="alert alert-error" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <p></p>
                </div>

                <div class="modal-botones">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Crear Usuario
                    </button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal('modalCrear')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Editar Usuario -->
    <div id="modalEditar" class="modal" style="display: none;">
        <div class="modal-contenido modal-formulario">
            <span class="cerrar-btn-editar">&times;</span>
            <div class="modal-header">
                <i class="fas fa-user-edit"></i>
                <h3>Editar Usuario</h3>
            </div>

            <div class="info-usuario-modal">
                <p><strong>Usuario:</strong> <span id="editar_usuario_text"></span></p>
            </div>

            <form id="formEditar" class="formulario-modal">
                <input type="hidden" id="editar_id" name="id">

                <div class="form-row">
                    <div class="form-group">
                        <label for="editar_nombre">
                            <i class="fas fa-user"></i> Nombre *
                        </label>
                        <input type="text" id="editar_nombre" name="nombre" class="form-control" required pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+">
                    </div>

                    <div class="form-group">
                        <label for="editar_apellido">
                            <i class="fas fa-user"></i> Apellido *
                        </label>
                        <input type="text" id="editar_apellido" name="apellido" class="form-control" required pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editar_rol">
                            <i class="fas fa-user-tag"></i> Rol *
                        </label>
                        <select id="editar_rol" name="rol" class="form-control" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach($roles_completos as $rol): ?>
                                <option value="<?= $rol['id_rol'] ?>"><?= htmlspecialchars($rol['rol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="editar_estado">
                            <i class="fas fa-toggle-on"></i> Estado *
                        </label>
                        <select id="editar_estado" name="estado" class="form-control" required>
                            <?php foreach($estados_completos as $estado): ?>
                                <option value="<?= $estado['id_estado'] ?>"><?= htmlspecialchars($estado['estado']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-botones">
                    <button type="submit" class="btn-guardar">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal('modalEditar')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Credenciales -->
    <div id="modalCredenciales" class="modal" style="display: none;">
        <div class="modal-contenido modal-success">
            <div class="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>¡Usuario creado exitosamente!</h3>
            <div class="credenciales-box">
                <p><strong>Nombre:</strong> <span id="cred_nombre"></span></p>
                <p><strong>Usuario:</strong> <span class="credencial-valor" id="cred_usuario"></span></p>
                <p><strong>Contraseña:</strong> <span class="credencial-valor" id="cred_password"></span></p>
            </div>
            <div class="alert-warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Comparta estas credenciales de forma segura al usuario registrado e indique que debe proceder a cambiarlas al momento de ingresar. Esta información no se mostrará nuevamente.</p>
            </div>
            <div class="modal-botones">
                <button class="btn-aceptar" onclick="cerrarModalCredenciales()">
                    <i class="fas fa-check"></i> Entendido
                </button>
            </div>
        </div>
    </div>

    <div class="contenedor-principal">
        <div class="header-seccion">
            <div class="titulo-seccion">
                <i class="fas fa-users"></i>
                <h2>Gestión de Usuarios</h2>
            </div>
            <button onclick="abrirModalCrear()" class="btn-crear">
                <i class="fas fa-user-plus"></i>
                Crear Nuevo Usuario
            </button>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="filtros-contenedor">
            <form method="GET" action="" class="form-filtros">
                <div class="filtro-grupo">
                    <label for="busqueda">
                        <i class="fas fa-search"></i> Buscar:
                    </label>
                    <input 
                        type="text" 
                        id="busqueda" 
                        name="busqueda" 
                        placeholder="Nombre, apellido o usuario..." 
                        value="<?= htmlspecialchars($busqueda) ?>"
                        class="input-busqueda"
                    >
                </div>

                <div class="filtro-grupo">
                    <label for="rol">
                        <i class="fas fa-user-tag"></i> Rol:
                    </label>
                    <select id="rol" name="rol" class="select-filtro">
                        <option value="">Todos los roles</option>
                        <?php foreach($roles as $rol): ?>
                            <option value="<?= htmlspecialchars($rol) ?>" <?= $filtro_rol === $rol ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rol) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <label for="estado">
                        <i class="fas fa-toggle-on"></i> Estado:
                    </label>
                    <select id="estado" name="estado" class="select-filtro">
                        <option value="">Todos los estados</option>
                        <?php foreach($estados as $estado): ?>
                            <option value="<?= htmlspecialchars($estado) ?>" <?= $filtro_estado === $estado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-botones">
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Buscar
                    </button>
                    <a href="index.php" class="btn-limpiar">
                        <i class="fas fa-eraser"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Contador de Resultados -->
        <div class="resultados-info">
            <p>
                <i class="fas fa-info-circle"></i>
                Se encontraron <strong><?= $total_usuarios ?></strong> usuario(s)
            </p>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="tabla-responsive">
            <?php if (count($usuarios) > 0): ?>
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Nombre</th>
                            <th><i class="fas fa-user"></i> Apellido</th>
                            <th><i class="fas fa-at"></i> Usuario</th>
                            <th><i class="fas fa-user-tag"></i> Rol</th>
                            <th><i class="fas fa-toggle-on"></i> Estado</th>
                            <th><i class="fas fa-cogs"></i> Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                            <tr>
                                <td data-label="ID"><?= htmlspecialchars($u['id_usuario']) ?></td>
                                <td data-label="Nombre"><?= htmlspecialchars($u['nombre']) ?></td>
                                <td data-label="Apellido"><?= htmlspecialchars($u['apellido']) ?></td>
                                <td data-label="Usuario">
                                    <span class="badge-usuario">
                                        <i class="fas fa-user-circle"></i>
                                        <?= htmlspecialchars($u['usuario']) ?>
                                    </span>
                                </td>
                                <td data-label="Rol">
                                    <span class="badge-rol badge-rol-<?= strtolower(str_replace(' ', '-', $u['rol'])) ?>">
                                        <?= htmlspecialchars($u['rol']) ?>
                                    </span>
                                </td>
                                <td data-label="Estado">
                                    <span class="badge-estado badge-estado-<?= strtolower($u['estado']) ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= htmlspecialchars($u['estado']) ?>
                                    </span>
                                </td>
                                <td data-label="Acciones">
                                    <div class="acciones-grupo">
                                        <button 
                                            onclick="abrirModalEditar({
                                                id_usuario: <?= $u['id_usuario'] ?>,
                                                usuario: '<?= htmlspecialchars($u['usuario'], ENT_QUOTES) ?>',
                                                nombre: '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>',
                                                apellido: '<?= htmlspecialchars($u['apellido'], ENT_QUOTES) ?>',
                                                id_rol: <?= $u['id_rol'] ?>,
                                                id_estado: <?= $u['id_estado'] ?>
                                            })"
                                            class="btn-accion btn-editar"
                                            title="Editar usuario">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <?php if ($u['estado'] === 'Activo'): ?>
                                            <button 
                                                onclick="mostrarModalDesactivar(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['nombre'].' '.$u['apellido'], ENT_QUOTES) ?>')" 
                                                class="btn-accion btn-desactivar"
                                                title="Desactivar usuario"
                                                <?= ($u['id_usuario'] == $_SESSION['id_usuario']) ? 'disabled' : '' ?>>
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="activar_usuario.php?id=<?= $u['id_usuario'] ?>"
                                            class="btn-accion btn-activar"
                                            title="Activar usuario"
                                            onclick="return confirm('¿Desea activar este usuario?')">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sin-resultados">
                    <i class="fas fa-search"></i>
                    <p>No se encontraron usuarios con los filtros aplicados</p>
                    <a href="index.php" class="btn-limpiar">Limpiar filtros</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <div style="color: #6B7280; font-size: 0.875rem;">
                    Mostrando <?= $registro_inicio ?> - <?= $registro_fin ?> de <?= $total_usuarios ?> usuario<?= $total_usuarios != 1 ? 's' : '' ?>
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <a href="<?= construirUrlPaginacion($paginaAnterior) ?>"
                    class="page-btn"
                    style="<?= $pagina_actual == 1 ? 'opacity:0.5;pointer-events:none;' : '' ?>">
                        ◀️
                    </a>

                    <a href="<?= construirUrlPaginacion($paginaSiguiente) ?>"
                    class="page-btn"
                    style="<?= $pagina_actual == $total_paginas ? 'opacity:0.5;pointer-events:none;' : '' ?>">
                        ▶️
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="usuarios.js"></script>
    <script src="../js/session_timeout.js"></script>
    <?php include('../footer.php'); ?>
</body>
</html>
