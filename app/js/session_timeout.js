// session_timeout.js - Sistema de timeout de sesión

(function() {
    // Configuración
    const TIEMPO_INACTIVIDAD = 30 * 60 * 1000; // 30 minutos en milisegundos
    const TIEMPO_ADVERTENCIA = 28 * 60 * 1000; // 28 minutos - advertir 2 minutos antes

    let timerInactividad;
    let timerAdvertencia;
    let modalAdvertenciaAbierto = false;

    // Crear modal de advertencia
    function crearModalAdvertencia() {
        const modalHTML = `
            <div id="modalTimeoutAdvertencia" class="modal-timeout" style="display: none;">
                <div class="modal-timeout-contenido">
                    <div class="modal-timeout-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Sesión por expirar</h3>
                    <p>Su sesión está a punto de expirar por inactividad.</p>
                    <p>Tiempo restante: <strong><span id="tiempoRestante">2:00</span></strong></p>
                    <div class="modal-timeout-botones">
                        <button id="btnContinuarSesion" class="btn-continuar">
                            <i class="fas fa-check"></i> Continuar sesión
                        </button>
                        <button id="btnCerrarSesion" class="btn-cerrar-timeout">
                            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Event listeners
        document.getElementById('btnContinuarSesion').addEventListener('click', continuarSesion);
        document.getElementById('btnCerrarSesion').addEventListener('click', cerrarSesion);
    }

    // Mostrar modal de advertencia
    function mostrarAdvertencia() {
        if (modalAdvertenciaAbierto) return;

        modalAdvertenciaAbierto = true;
        const modal = document.getElementById('modalTimeoutAdvertencia');
        modal.style.display = 'flex';

        // Iniciar countdown
        iniciarCountdown();
    }

    // Iniciar countdown
    function iniciarCountdown() {
        let segundosRestantes = 120; // 2 minutos
        const spanTiempo = document.getElementById('tiempoRestante');

        const countdown = setInterval(() => {
            segundosRestantes--;

            const minutos = Math.floor(segundosRestantes / 60);
            const segundos = segundosRestantes % 60;
            spanTiempo.textContent = `${minutos}:${segundos.toString().padStart(2, '0')}`;

            if (segundosRestantes <= 0) {
                clearInterval(countdown);
                cerrarSesion();
            }
        }, 1000);

        // Guardar el interval para poder limpiarlo
        window.sessionCountdown = countdown;
    }

    // Continuar sesión
    function continuarSesion() {
        modalAdvertenciaAbierto = false;
        document.getElementById('modalTimeoutAdvertencia').style.display = 'none';

        // Limpiar countdown
        if (window.sessionCountdown) {
            clearInterval(window.sessionCountdown);
        }

        // Hacer una petición al servidor para renovar la sesión
        fetch('/renovar_sesion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        }).then(() => {
            // Reiniciar timers
            resetearTimers();
        });
    }

    // Cerrar sesión
    function cerrarSesion() {
        window.location.href = 'modulo_inicio/logout.php';
    }

    // Resetear timers de inactividad
    function resetearTimers() {
        // Limpiar timers existentes
        if (timerInactividad) clearTimeout(timerInactividad);
        if (timerAdvertencia) clearTimeout(timerAdvertencia);

        // Timer para mostrar advertencia
        timerAdvertencia = setTimeout(() => {
            mostrarAdvertencia();
        }, TIEMPO_ADVERTENCIA);

        // Timer para logout automático
        timerInactividad = setTimeout(() => {
            cerrarSesion();
        }, TIEMPO_INACTIVIDAD);
    }

    // Detectar actividad del usuario
    function detectarActividad() {
        resetearTimers();
    }

    // Eventos que indican actividad del usuario
    const eventosActividad = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click'
    ];

    // Inicializar
    function inicializar() {
        // Crear modal
        crearModalAdvertencia();

        // Agregar listeners de actividad
        eventosActividad.forEach(evento => {
            document.addEventListener(evento, detectarActividad, true);
        });

        // Iniciar timers
        resetearTimers();
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();