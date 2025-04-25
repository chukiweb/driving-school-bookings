<?php
/**
 * Template Name: Vista alumno
 */
if (!defined('ABSPATH')) {
    exit;
}

// Incluir estilos y scripts específicos para esta vista
function dsb_enqueue_alumno_assets()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('dsb-alumno-css', plugin_dir_url(__FILE__) . '../css/alumno.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-alumno-js', plugin_dir_url(__FILE__) . '../js/alumno.js', [], '1.0.0', true);
    // wp_localize_script('dsb-alumno-js', 'studentDataData', DSB_Student_Service::get_student($_SESSION['user_id']));
}
add_action('wp_enqueue_scripts', 'dsb_enqueue_alumno_assets');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>App Driving - Bienvenido</title>
    <?php wp_head(); ?>
    <!--CODIFICACION DE CARACTERES-->
    <meta charset="utf-8">
    <meta name="author" content="Roma & Nico">
    <meta name="description" content="Aplicación de autoescuela">
    <meta name="keywords" content="Autoescuela">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!--FAVICON-->
    <link rel="icon" type="image/x-icon" href="">
    <!--INTEGRACION DE BOOTSTRAP-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <!--ICONOS-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">
    <div class="container">
        <!-- Encabezado con imagen y datos del profesor -->
        <div class="text-center mt-4">
            <img id="estudiante-avatar" src="" alt="Foto de perfil"
                class="rounded-circle border" width="100">
                <!-- Input oculto para seleccionar la imagen -->
            <input type="file" id="file-input" accept="image/*" style="display:none;">
            <h2 id="estudiante-name" class="mt-2">Cargando...</h2>
            <p id="estudiante-email" class="mt-2"></p>
            <div class="d-flex justify-content-center align-items-center ">
                <p class="m-2" id="assigned-teacher"></p>
                <p class="m-2" id="assigned-car"></p>
            </div>
        </div>

        <!-- Estado de clases -->
        <div class="container mt-4">
            <h2 id="reservas-title" class="text-center"></h2>
            <div id="reservas-container" class="row gy-3">
                <!-- Aquí se cargarán las tarjetas de reservas dinámicamente -->
            </div>
        </div>
    </div>
    <?php wp_footer(); ?>

    <!-- Modal de Detalles de la Reserva -->
<div class="modal fade" id="detalleReservaModal" tabindex="-1" aria-labelledby="detalleReservaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleReservaModalLabel">Detalles de la Clase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Fecha:</strong> <span id="modal-fecha"></span></p>
                <p><strong>Hora:</strong> <span id="modal-hora"></span></p>
                <p><strong>Profesor:</strong> <span id="modal-profesor"></span></p>
                <p><strong>Vehículo:</strong> <span id="modal-vehiculo"></span></p>
                <p><strong>Estado:</strong> <span id="modal-estado"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>