<?php
/**
 * Template Name: Vista Profesor
 */
if (!defined('ABSPATH')) {
    exit;
}

// Incluir estilos y scripts específicos para esta vista
function dsb_enqueue_acceso_assets() {
    wp_enqueue_style('dsb-acceso-css', plugin_dir_url(__FILE__) . '../css/estudiante.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-acceso-js', plugin_dir_url(__FILE__) . '../js/estudiante.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'dsb_enqueue_acceso_assets');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <title>Bienvenido </title>
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
        <div class="container-fluid">
            <!--DIV DE ICONOS DE MENU Y CIERRE DE SESION-->
            <div class="row justify-content-between iconosarriba">
                <div class="col-6 iconomenu"><i class="bi bi-list"></i></div>
                <div class="col-6 cerrarsesion"><i class="bi bi-person-fill-lock"></i></div>
            </div>
            <!--DIV DE DATOS DE CREDITOS Y CLASES-->
            <div id="creditos">

            </div>
            <!--CAJA DE PERFIL DE USUARIO FOTOGRAFIA, CNOMBRE Y VEHICULO-->
            <div class="row justify-content-between perfilusuario">
                <div class="col-3 col-lg-6 col-xl-1 fotousuario"></div>
                <div class="col-9  col-lg-6 col-xl-11 informacionusuario"><span><h1 class="nombredealumno">Estefania Garcia Soto</h1>
                                                       <br><h2  class="nombredecoche" ><i class="bi bi-car-front-fill"></i>Volkswagen Golf</h2></span></div>
            </div>
            <!--INFORMACION SOBRE LAS CLASES-->
            <div class="row informacionclases ">
                <div class="col-12 informaciondeclase"><p style="">No tienes clases disponibles <i class="bi bi-hourglass-split"></i></div>
                <div class="col-12 fecha">03 de Marzo de 2025</div>    
            </div>
            <!--DIV DE RESERVAR CITA-->
            <div class="row justify-content-start citas">
                <div class="col-2 col-xl-1 iconocitas"><i class="bi bi-calendar-date-fill"></i></div>
                <div class="col-10 col-xl-11 reservacitas"><p class="reservarcitaletras" style="margin-left: 10px;margin-top: 33px; font-weight: bold;">Consultar citas de alumnos</p></div>
            </div>
            <!--DATOS-->
            <div class="row justify-content-start datos">
                <div class="col-6 col-xl-2 alumno"><p style="text-align: center;font-weight: bold;margin-top: 30px;">Alumnos</p><br><i class="bi bi-person-lines-fill"></i></div>
                <div class="col-6 col-xl-2 verdatos"><p style="text-align: center;font-weight: bold;margin-top: 30px;">Mis datos</p><br><i class="bi bi-bar-chart-steps"></i></div>
            </div>
            <!--FAQ Y ACCESO A AGENDA-->
            <div class="row justify-content-start faq">
                <div class="col-sm-10 preguntas"><img src=" <?php plugin_dir_url(__FILE__) . '../images/AlGUNADUDA.png' ?> " alt="faq" description="preguntas frecuentes" width="220px"></div>
                <div class="col-sm-2 agenda"><img src="<?php plugin_dir_url(__FILE__) . '../images/llamar.gif' ?> " alt="numero autoescuela" description="llamar" width="160px"></div>
            <!--</div class="container-fluid" >
            <div class="row justify-content-end datosusuariopantalla"> 
            </div>-->
        </div>
        <?php wp_footer(); ?> 
    </body>
</html>