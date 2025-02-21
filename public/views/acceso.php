<?php
if (!defined('ABSPATH')) {
    exit;
}

function dsb_enqueue_acceso_assets() {
    wp_enqueue_style('dsb-acceso-css', plugin_dir_url(__FILE__) . '../css/acceso.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-acceso-js', plugin_dir_url(__FILE__) . '../js/acceso.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'dsb_enqueue_acceso_assets');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a Driving App</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Iniciar Sesión</h2>
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="username" placeholder="Ingresa tu usuario" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" placeholder="Ingresa tu contraseña" required>
                            </div>
                            <div class="d-grid">
                                <button type="button" class="btn btn-primary" onclick="login()">Ingresar</button>
                            </div>
                        </form>
                        <p class="text-danger mt-3 text-center" id="error-message"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php wp_footer(); ?>
</body>
</html>
