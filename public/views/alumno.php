<?php

/**
 * Template Name: Vista alumno
 */
if (!defined('ABSPATH')) {
    exit;
}
function dsb_get_student_data()
{
    $decoded = DSB()->jwt->validate_token($_SESSION['jwt_token']);

    $user_id = $decoded->user->id;

    $user = DSB()->user_manager->get_user_by_id($user_id);
    
    $user['avatar'] = DSB_User_Service::get_avatar_url($user_id);

    return $user;
}

$user = dsb_get_student_data();

function dsb_get_bookings_data()
{
    $decoded = DSB()->jwt->validate_token($_SESSION['jwt_token']);

    $user_id = $decoded->user->id;

    $bookings = DSB_Calendar_Service::get_student_full_calendar_format($user_id);

    return $bookings;
}

$bookings = $user['bookings'];

$settings = DSB_Settings::get_settings();

// Contar clases de hoy
$today = date('Y-m-d');
$classes_today = 0;

foreach ($bookings as $booking) {
    if ($booking['date'] == $today && $booking['status'] != 'cancelled') {
        $classes_today++;
    }
}

$daily_limit = DSB_Settings::get('daily_limit');

// Función para obtener las reservas del profesor asignado al alumno
function dsb_get_teacher_bookings_data()
{
    $student_data = dsb_get_student_data();
    $teacher_id = $student_data['teacher']['id'];

    if (!$teacher_id) return [];

    $args = [
        'post_type' => 'dsb_booking',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '='],
            ['key' => 'status', 'value' => 'cancelled', 'compare' => '!='],
            ['key' => 'date', 'value' => date('Y-m-d'), 'compare' => '>='] // Solo reservas futuras
        ]
    ];

    $bookings = get_posts($args);
    $formatted_bookings = [];

    foreach ($bookings as $booking) {
        $date = get_post_meta($booking->ID, 'date', true);
        $time = get_post_meta($booking->ID, 'time', true);
        $end_time = get_post_meta($booking->ID, 'end_time', true);
        $status = get_post_meta($booking->ID, 'status', true);

        $formatted_bookings[] = [
            'id' => $booking->ID,
            'title' => 'Reservado',
            'start' => "{$date}T{$time}",
            'end' => "{$date}T{$end_time}",
            'status' => $status,
            'teacher_booking' => true // Marca para identificar que es reserva del profesor
        ];
    }

    return $formatted_bookings;
}

// Incluir estilos y scripts específicos para esta vista
function dsb_enqueue_alumno_assets()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('dsb-alumno-css', plugin_dir_url(__FILE__) . '../css/alumno.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-alumno-js', plugin_dir_url(__FILE__) . '../js/alumno.js', [], '1.0.0', true);
    wp_localize_script('dsb-alumno-js', 'studentData', dsb_get_student_data());
    wp_localize_script('dsb-alumno-js', 'bookingsData', dsb_get_bookings_data());
    wp_localize_script('dsb-alumno-js', 'teacherBookingsData', dsb_get_teacher_bookings_data());
    wp_localize_script(
        'dsb-alumno-js',
        'DSB_CONFIG',
        [
            'jwtToken' => isset($_SESSION['jwt_token']) ? $_SESSION['jwt_token'] : '',
            'apiBaseUrl' => esc_url(rest_url('driving-school/v1')),
            'classDuration' => DSB_Settings::get('class_duration'),
        ]
    );
    wp_enqueue_script('fullcalendar-js', plugin_dir_url(__FILE__) . '../lib/fullcalendar.js', array('jquery'), '', true);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!--ICONOS-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">

    <div class="container">
        <!-- Encabezado con imagen y datos del profesor -->
        <div class="text-center mt-4">
            <div class="avatar-container text-center mb-4">
                <div class="avatar-wrapper">
                    <img id="estudiante-avatar" src="<?php echo esc_url(DSB_User_Service::get_avatar_url($user['id'])); ?>"
                        alt="Avatar de <?= esc_attr($user['name']); ?>" class="rounded-circle avatar-img">
                    <div class="avatar-overlay">
                        <i class="bi bi-camera-fill"></i>
                        <span>Cambiar foto</span>
                    </div>
                </div>
                <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
            </div>
            <h2 id="estudiante-name" class="mt-2"><?= $user['name'] ?></h2>
            <p id="estudiante-email" class="mt-2"><i class="bi bi-envelope-fill"></i> <?= $user['email'] ?></p>
            <div class="d-flex justify-content-center align-items-center ">
                <p class="m-2" id="assigned-teacher"><i class="bi bi-person-badge"></i> <?= $user['teacher']['name'] ?></p>
                <p class="m-2" id="assigned-car"><i class="bi bi-car-front-fill"></i> <?= $user['teacher']['vehicle_name'] ?></p>
            </div>

            <div class="alert alert-info mt-3" id="saldo-container">
                <i class="bi bi-coin"></i> Saldo actual: <span id="saldo-amount" class="saldo-actual"><?= $user['class_points'] ?></span> puntos
            </div>

            <div class="alert alert-info mt-3" id="info-container">
                <i class="bi bi-info-circle"></i> Precio por clase: <span id="precio-clase"><?= $settings['class_cost'] ?></span> puntos
                <br>
                <i class="bi bi-info-circle"></i> Horas para cancelación gratuita: <span id="horas-cancelacion"><?= $settings['cancelation_time_hours'] ?></span> horas
                <br>
                <i class="bi bi-info-circle"></i> Clases máximas al día: <span id="clases-maximas"><?= $settings['daily_limit'] ?></span> clases
            </div>

            <div class="alert alert-info mt-3" id="booking-limit-info">
                <i class="bi bi-calendar-check"></i>
                <strong>Información:</strong> Puedes reservar hasta <span id="max-bookings"><?php echo esc_html($daily_limit); ?></span>
                clases por día
            </div>

            <div class="container mt-4">
                <h2 id="reservas-title" class="text-center"></h2>
                <div id="reservas-container" class="row gy-3">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col-12">
                            <div class="d-flex align-items-center p-3 shadow-sm bg-light rounded">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?= date('d/m/Y', is_numeric($booking['date']) ? $booking['date'] : strtotime($booking['date'])) ?></h6>
                                    <p class="mb-0"><strong>Hora:</strong> <?= $booking['start'] ?></p>
                                    <p class="mb-0"><strong>Profesor: </strong> <?= $booking['teacher_name'] ?></p>
                                    <p class="mb-0"><strong>Estado: </strong> <?= $booking['status'] ?></p>
                                </div>
                                <button class="btn btn-warning ms-3 ver-detalles" data-bs-toggle="modal"
                                    data-bs-target="#detalleReservaModal" data-id="<?= $booking['id'] ?>">
                                    Ver detalles
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="container">
            <div id="calendar"></div>
        </div>

        <div class="modal fade" id="studentCalendarModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalles de la Clase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" id="studentCalendarForm" class="dsb-form">
                        <div class="modal-body">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-coin"></i> Esta reserva costará
                                <strong><?php echo esc_html(DSB_Settings::get('class_cost')); ?></strong> créditos.
                                Tu saldo actual es de <strong class="saldo-actual"><?php echo esc_html($user['class_points']); ?></strong> créditos.
                            </div>
                            <div class="mb-3">
                                <label for="date" class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="date" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="time" class="form-label">Hora de inicio</label>
                                <input type="time" class="form-control" name="time" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="end_time" class="form-label">Hora de fin</label>
                                <input type="time" class="form-control" name="end_time" readonly>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="submit" class="btn btn-primary" value="Confirmar" />
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="studentCalendarInfoModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalles de la Clase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" id="studentCalendarInfoForm" class="dsb-form">
                        <input type="hidden" name="booking_id" value="">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="date" class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="date" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="time" class="form-label">Hora de inicio</label>
                                <input type="time" class="form-control" name="time" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="end_time" class="form-label">Hora de fin</label>
                                <input type="time" class="form-control" name="end_time" readonly>
                            </div>
                            <div class="mb-3">
                                <p>¿Quieres cancelar esta reserva?</p>
                                <div class="d-flex gap-2 mb-3">
                                    <button type="submit" class="btn btn-danger btn-cancel-booking">Sí, cancelar</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Puntos de la reserva</label>
                                <div class="alert alert-info">
                                    <span class="booking-points">0</span> puntos
                                </div>
                                <small class="text-muted">Este es el coste en puntos para esta reserva.</small>
                            </div>
                            <div class="mb-3">
                                <div class="alert alert-info saldo-actual-container">
                                    <i class="bi bi-coin"></i> Tu saldo actual es: <strong class="saldo-actual"><?= $user['class_points'] ?></strong> puntos
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="alert alert-warning">
                                    <i class="bi bi-info-circle-fill me-2"></i> Si cancelas la clase con al menos 24 horas de antelación, recuperarás los puntos utilizados en esta reserva.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </form>
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
                        <h5 class="modal-title">Detalles de la Clase</h5>
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