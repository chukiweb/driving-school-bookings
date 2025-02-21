<?php
/**
 * Template Name: Vista Estudiante
 */
if (!defined('ABSPATH')) {
    exit;
}

// Incluir estilos y scripts específicos para esta vista
function dsb_enqueue_estudiante_assets() {
    wp_enqueue_style('dsb-estudiante-css', plugin_dir_url(__FILE__) . '../css/estudiante.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-estudiante-js', plugin_dir_url(__FILE__) . '../js/estudiante.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'dsb_enqueue_estudiante_assets');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <title>Profesor</title>
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <!--ICONOS-->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    </head>

    <body>
    <body class="bg-light">
            <div class="container">
                <!-- Encabezado con imagen y datos del profesor -->
                <div class="text-center mt-4">
                    <img id="estudiante-avatar" src="../img/default-avatar.png" alt="Foto de perfil" class="rounded-circle border" width="100">
                    <h2 id="estudiante-name" class="mt-2">Cargando...</h2>
                    <h3 id="estudiante-email"  class="mt-2"></h3>
                    <h3 id="estudiante-licencia"  class="mt-2"></h3>
                    <p id="estudiante-car"><i class="fas fa-car"></i> <span>Vehículo: </span> <span id="estudiante-car">Cargando...</span></p>
                </div>

                <!-- Estado de citas -->
                <div class="text-center mt-3">
                    <p id="estudiante-clases" class="font-weight-bold">Hoy no tienes citas</p>
                    <div class="spinner-border text-success d-none" role="status" id="loading-spinner">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>

                <!-- Botones de acceso rápido -->
                <div class="row text-center mt-4">
                    <div class="col-12">
                        <button class="btn btn-warning w-100 py-3" id="appointments-btn">
                            <i class="fas fa-calendar-check"></i> Citas con alumnos
                        </button>
                    </div>
                    <div class="col-6 mt-3">
                        <button class="btn btn-warning w-100 py-3" id="students-btn">
                            <i class="fas fa-user-graduate"></i> Alumnos
                        </button>
                    </div>
                    <div class="col-6 mt-3">
                        <button class="btn btn-warning w-100 py-3" id="profile-btn">
                            <i class="fas fa-user"></i> Mis datos
                        </button>
                    </div>
                </div>
            </div>
        <?php wp_footer(); ?> 
    </body>
</html>