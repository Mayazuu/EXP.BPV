<?php
// Definir destino según la página actual
$destino_volver = 'index.php';  // Por defecto

// Obtener el nombre del archivo actual
$pagina_actual = basename($_SERVER['PHP_SELF']);


if ($pagina_actual === 'index.php') {
    $destino_volver = '../modulo_inicio/dashboard.php';  // ruta real del dashboard
}
?>

<!-- Botón Volver icono -->
<a href="<?= $destino_volver ?>" class="boton-volver" title="Volver">
  <svg xmlns="http://www.w3.org/2000/svg" class="icono-volver" viewBox="0 0 24 24">
    <path d="M15 18l-6-6 6-6" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</a>

<style>
/* Estilos del botón Volver */
.boton-volver { position: fixed; top: 90px; left: 20px; z-index: 9999; width: 45px; height: 45px; background-color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.3); text-decoration: none; cursor: pointer; transition: transform 0.2s ease, background-color 0.2s ease;}
.boton-volver:hover { background-color: #1e7e34;transform: scale(1.1);}
.icono-volver {width: 22px; height: 22px;stroke: white;}
</style>
