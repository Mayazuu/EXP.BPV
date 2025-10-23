// ========================================
// USUARIOS.JS - GESTIÓN DE USUARIOS
// Versión sin cambio de contraseña en editar
// ========================================

// Variables globales
let idUsuarioDesactivar = null;

// ========================================
// INICIALIZACIÓN
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    inicializarModales();
    mostrarMensajeInicial();
    inicializarFormularios();
});

// ========================================
// INICIALIZAR MODALES
// ========================================

function inicializarModales() {
    // Modal de mensaje
    const modalMensaje = document.getElementById('modalMensaje');
    const cerrarBtnMensaje = modalMensaje?.querySelector('.cerrar-btn');
    const aceptarBtn = document.getElementById('aceptarBtn');

    if (cerrarBtnMensaje) {
        cerrarBtnMensaje.onclick = () => cerrarModal('modalMensaje');
    }

    if (aceptarBtn) {
        aceptarBtn.onclick = () => cerrarModal('modalMensaje');
    }

    // Modal de desactivación
    const modalDesactivar = document.getElementById('modalDesactivar');
    const cerrarBtnDesactivar = modalDesactivar?.querySelector('.cerrar-btn-desactivar');
    const cancelarBtn = document.getElementById('cancelarBtn');
    const confirmarBtn = document.getElementById('confirmarBtn');

    if (cerrarBtnDesactivar) {
        cerrarBtnDesactivar.onclick = () => cerrarModal('modalDesactivar');
    }

    if (cancelarBtn) {
        cancelarBtn.onclick = () => cerrarModal('modalDesactivar');
    }

    if (confirmarBtn) {
        confirmarBtn.onclick = confirmarDesactivacion;
    }

    // Modal de crear
    const modalCrear = document.getElementById('modalCrear');
    const cerrarBtnCrear = modalCrear?.querySelector('.cerrar-btn-crear');

    if (cerrarBtnCrear) {
        cerrarBtnCrear.onclick = () => cerrarModal('modalCrear');
    }

    // Modal de editar
    const modalEditar = document.getElementById('modalEditar');
    const cerrarBtnEditar = modalEditar?.querySelector('.cerrar-btn-editar');

    if (cerrarBtnEditar) {
        cerrarBtnEditar.onclick = () => cerrarModal('modalEditar');
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            cerrarModal(event.target.id);
        }
    }

    // Validación en tiempo real del textarea de desactivación
    const razonDesactivacion = document.getElementById('razonDesactivacion');
    if (razonDesactivacion) {
        razonDesactivacion.addEventListener('input', function() {
            const errorRazon = document.getElementById('errorRazon');
            if (this.value.trim().length >= 10) {
                this.classList.remove('input-error');
                errorRazon.style.display = 'none';
            }
        });
    }
}

// ========================================
// INICIALIZAR FORMULARIOS
// ========================================

function inicializarFormularios() {
    // Formulario de crear
    const formCrear = document.getElementById('formCrear');
    if (formCrear) {
        formCrear.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormCrear();
        });
    }

    // Formulario de editar (SIN CONTRASEÑA)
    const formEditar = document.getElementById('formEditar');
    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormEditar();
        });
    }

    // Validación de confirmación de contraseña en crear
    const crearConfirmar = document.getElementById('crear_confirmar');
    if (crearConfirmar) {
        crearConfirmar.addEventListener('input', function() {
            const password = document.getElementById('crear_contrasena').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    }
}

// ========================================
// MOSTRAR MENSAJE INICIAL
// ========================================

function mostrarMensajeInicial() {
    const urlParams = new URLSearchParams(window.location.search);
    const mensaje = urlParams.get('mensaje');

    if (mensaje) {
        const textoMensaje = document.getElementById('textoMensaje');
        if (textoMensaje) {
            const mensajeDecoded = decodeURIComponent(mensaje);
            if (mensajeDecoded.includes('||')) {
                textoMensaje.innerHTML = mensajeDecoded.split('||').join('<br>');
            } else {
                textoMensaje.textContent = mensajeDecoded;
            }
            document.getElementById('modalMensaje').style.display = 'flex';
        }
    }
}

// ========================================
// ABRIR MODAL DE CREAR
// ========================================

function abrirModalCrear() {
    // Limpiar formulario
    document.getElementById('formCrear').reset();
    document.getElementById('errorCrear').style.display = 'none';
    
    // Mostrar modal
    document.getElementById('modalCrear').style.display = 'flex';
    
    // Enfocar primer campo
    setTimeout(() => {
        document.getElementById('crear_nombre').focus();
    }, 300);
}

// ========================================
// ABRIR MODAL DE EDITAR (SIN CONTRASEÑA)
// ========================================

function abrirModalEditar(usuario) {
    console.log('Abriendo modal de editar con usuario:', usuario);
    
    // Verificar que los elementos existen
    const elementos = {
        id: document.getElementById('editar_id'),
        usuario_text: document.getElementById('editar_usuario_text'),
        nombre: document.getElementById('editar_nombre'),
        apellido: document.getElementById('editar_apellido'),
        rol: document.getElementById('editar_rol'),
        estado: document.getElementById('editar_estado'),
        modal: document.getElementById('modalEditar')
    };
    
    // Debug: verificar elementos
    for (let [key, elem] of Object.entries(elementos)) {
        if (!elem) {
            console.error(`Elemento no encontrado: ${key}`);
            return;
        }
    }
    
    // Llenar formulario con datos del usuario
    elementos.id.value = usuario.id_usuario;
    elementos.usuario_text.textContent = usuario.usuario;
    elementos.nombre.value = usuario.nombre;
    elementos.apellido.value = usuario.apellido;
    elementos.rol.value = usuario.id_rol;
    elementos.estado.value = usuario.id_estado;
    
    // Mostrar modal
    elementos.modal.style.display = 'flex';
    
    // Enfocar primer campo
    setTimeout(() => {
        elementos.nombre.focus();
    }, 300);
    
    console.log('Modal abierto correctamente');
}

// ========================================
// SUBMIT FORMULARIO DE CREAR
// ========================================

function submitFormCrear() {
    const formData = new FormData(document.getElementById('formCrear'));
    const errorDiv = document.getElementById('errorCrear');
    
    // Ocultar error previo
    errorDiv.style.display = 'none';
    
    // Validar que las contraseñas coincidan
    const password = formData.get('contrasena');
    const confirmar = formData.get('confirmar_contrasena');
    
    if (password !== confirmar) {
        mostrarError('errorCrear', 'Las contraseñas no coinciden');
        return;
    }
    
    if (password.length < 6) {
        mostrarError('errorCrear', 'La contraseña debe tener al menos 6 caracteres');
        return;
    }
    
    // Enviar petición
    fetch('procesar_crear.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal de crear
            cerrarModal('modalCrear');
            
            // Mostrar modal de credenciales
            document.getElementById('cred_nombre').textContent = data.nombre;
            document.getElementById('cred_usuario').textContent = data.usuario;
            document.getElementById('cred_password').textContent = data.password;
            
            // Si se generó una variante, mostrar advertencia
            const credencialesBox = document.querySelector('.credenciales-box');
            const alertWarning = document.querySelector('.alert-warning-box');
            
            if (data.es_variante) {
                // Agregar mensaje de variante después de la caja de credenciales
                if (alertWarning && !document.getElementById('mensaje-variante')) {
                    const mensajeVariante = document.createElement('div');
                    mensajeVariante.id = 'mensaje-variante';
                    mensajeVariante.className = 'alert-info-box';
                    mensajeVariante.style.cssText = 'background-color: #DBEAFE; border-left: 4px solid #3B82F6; padding: 12px; margin: 15px 0; border-radius: 4px;';
                    mensajeVariante.innerHTML = `
                        <i class="fas fa-info-circle" style="color: #3B82F6;"></i>
                        <p style="margin: 0;"><strong>Nota:</strong> El usuario ya existía, se generó "${data.usuario}".</p>
                    `;
                    alertWarning.parentNode.insertBefore(mensajeVariante, alertWarning);
                }
            } else {
                // Remover mensaje de variante si existe
                const mensajeVariante = document.getElementById('mensaje-variante');
                if (mensajeVariante) {
                    mensajeVariante.remove();
                }
            }
            
            document.getElementById('modalCredenciales').style.display = 'flex';
        } else {
            mostrarError('errorCrear', data.mensaje);
        }
    })
    .catch(error => {
        mostrarError('errorCrear', 'Error al procesar la solicitud');
        console.error('Error:', error);
    });
}

// ========================================
// SUBMIT FORMULARIO DE EDITAR (SIN CONTRASEÑA)
// ========================================

function submitFormEditar() {
    const formData = new FormData(document.getElementById('formEditar'));
    
    // Enviar petición
    fetch('procesar_editar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            cerrarModal('modalEditar');
            
            // Recargar página con mensaje de éxito
            window.location.href = 'index.php?mensaje=' + encodeURIComponent(data.mensaje) + '&tipo=success';
        } else {
            // Mostrar error en un alert simple
            alert('Error: ' + data.mensaje);
        }
    })
    .catch(error => {
        alert('Error al procesar la solicitud');
        console.error('Error:', error);
    });
}

// ========================================
// MOSTRAR ERROR EN FORMULARIO
// ========================================

function mostrarError(errorId, mensaje) {
    const errorDiv = document.getElementById(errorId);
    const errorP = errorDiv.querySelector('p');
    
    errorP.textContent = mensaje;
    errorDiv.style.display = 'flex';
    
    // Scroll al error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ========================================
// CERRAR MODAL DE CREDENCIALES
// ========================================

function cerrarModalCredenciales() {
    document.getElementById('modalCredenciales').style.display = 'none';
    // Recargar página para actualizar la tabla
    window.location.href = 'index.php';
}

// ========================================
// MOSTRAR MODAL DE DESACTIVACIÓN
// ========================================

function mostrarModalDesactivar(id, nombre) {
    idUsuarioDesactivar = id;
    const textoConfirmacion = document.getElementById('textoConfirmacion');
    const modalDesactivar = document.getElementById('modalDesactivar');
    const razonDesactivacion = document.getElementById('razonDesactivacion');
    const errorRazon = document.getElementById('errorRazon');

    if (textoConfirmacion) {
        textoConfirmacion.innerHTML = `¿Está seguro que desea <strong>desactivar</strong> al usuario <strong>${nombre}</strong>?<br><small>El usuario no podrá iniciar sesión pero se mantendrán sus registros.</small>`;
    }

    if (razonDesactivacion) {
        razonDesactivacion.value = '';
        razonDesactivacion.classList.remove('input-error');
    }

    if (errorRazon) {
        errorRazon.style.display = 'none';
    }

    if (modalDesactivar) {
        modalDesactivar.style.display = 'flex';
        setTimeout(() => razonDesactivacion.focus(), 300);
    }
}

// ========================================
// CONFIRMAR DESACTIVACIÓN
// ========================================

function confirmarDesactivacion() {
    const razonDesactivacion = document.getElementById('razonDesactivacion');
    const errorRazon = document.getElementById('errorRazon');
    const razon = razonDesactivacion.value.trim();

    if (razon === '') {
        razonDesactivacion.classList.add('input-error');
        errorRazon.textContent = 'Debe ingresar una razón para la desactivación';
        errorRazon.style.display = 'block';
        razonDesactivacion.focus();
        return;
    }

    if (razon.length < 10) {
        razonDesactivacion.classList.add('input-error');
        errorRazon.textContent = 'La razón debe tener al menos 10 caracteres';
        errorRazon.style.display = 'block';
        razonDesactivacion.focus();
        return;
    }

    // Crear y enviar formulario
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'desactivar_usuario.php';

    const inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id';
    inputId.value = idUsuarioDesactivar;

    const inputRazon = document.createElement('input');
    inputRazon.type = 'hidden';
    inputRazon.name = 'razon';
    inputRazon.value = razon;

    form.appendChild(inputId);
    form.appendChild(inputRazon);
    document.body.appendChild(form);
    form.submit();
}

// ========================================
// CERRAR MODAL
// ========================================

function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        
        // Limpiar parámetros de URL si es modal de mensaje
        if (modalId === 'modalMensaje') {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        
        // Limpiar modal de desactivación
        if (modalId === 'modalDesactivar') {
            idUsuarioDesactivar = null;
            const razonDesactivacion = document.getElementById('razonDesactivacion');
            const errorRazon = document.getElementById('errorRazon');
            if (razonDesactivacion) {
                razonDesactivacion.value = '';
                razonDesactivacion.classList.remove('input-error');
            }
            if (errorRazon) {
                errorRazon.style.display = 'none';
            }
        }
        
        // Limpiar modal de crear
        if (modalId === 'modalCrear') {
            document.getElementById('formCrear').reset();
            document.getElementById('errorCrear').style.display = 'none';
        }
        
        // Limpiar modal de editar
        if (modalId === 'modalEditar') {
            document.getElementById('formEditar').reset();
        }
    }
}

// ========================================
// TOGGLE PASSWORD (Solo para crear)
// ========================================

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.toggle-password');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
