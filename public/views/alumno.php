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
    <meta charset="utf-8">
    <meta name="author" content="Roma & Nico">
    <meta name="description" content="Aplicación de autoescuela">
    <meta name="keywords" content="Autoescuela">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/x-icon" href="">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-car-front-fill me-2"></i>DrivingApp
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#perfil">
                            <i class="bi bi-person-circle me-1"></i>Mi Perfil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#reservas">
                            <i class="bi bi-calendar-check me-1"></i>Mis Reservas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#calendario">
                            <i class="bi bi-calendar-plus me-1"></i>Reservar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Perfil del alumno -->
        <section id="perfil" class="mb-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-4 mb-4 mb-lg-0 text-center">
                            <div class="avatar-container mb-3">
                                <div class="avatar-wrapper mx-auto">
                                    <img id="estudiante-avatar" src="<?php echo esc_url(DSB_User_Service::get_avatar_url($user['id'])); ?>" alt="Avatar de <?= esc_attr($user['name']); ?>" class="rounded-circle avatar-img">
                                    <div class="avatar-overlay">
                                        <i class="bi bi-camera-fill"></i>
                                        <span>Cambiar foto</span>
                                    </div>
                                </div>
                                <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <h2 id="estudiante-name" class="mb-3 text-center text-lg-start"><?= $user['name'] ?></h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-envelope-fill me-2"></i>Email</h6>
                                            <p id="estudiante-email" class="card-text"><?= $user['email'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-person-badge me-2"></i>Profesor</h6>
                                            <p id="assigned-teacher" class="card-text"><?= $user['teacher']['name'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-car-front-fill me-2"></i>Vehículo</h6>
                                            <p id="assigned-car" class="card-text"><?= $user['teacher']['vehicle_name'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-success text-white border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-white"><i class="bi bi-coin me-2"></i>Saldo Actual</h6>
                                            <p class="card-text fs-4 fw-bold saldo-actual"><?= $user['class_points'] ?></p>
                                            <small>puntos</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white p-0">
                    <div class="accordion accordion-flush" id="infoAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#infoCollapse">
                                    <i class="bi bi-info-circle me-2"></i> Información de tarifas y políticas
                                </button>
                            </h2>
                            <div id="infoCollapse" class="accordion-collapse collapse" data-bs-parent="#infoAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white p-2 rounded-circle me-3">
                                                    <i class="bi bi-tag-fill"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Precio por clase</small>
                                                    <span id="precio-clase" class="fw-bold"><?= $settings['class_cost'] ?> puntos</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning text-white p-2 rounded-circle me-3">
                                                    <i class="bi bi-clock-fill"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Cancelación gratuita</small>
                                                    <span id="horas-cancelacion" class="fw-bold"><?= $settings['cancelation_time_hours'] ?> h. antes</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success text-white p-2 rounded-circle me-3">
                                                    <i class="bi bi-calendar-check-fill"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Máximo diario</small>
                                                    <span id="max-bookings" class="fw-bold"><?= $settings['daily_limit'] ?> clases</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mis Reservas -->
        <section id="reservas" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Mis Reservas</h3>
                <a href="#calendario" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Nueva Reserva
                </a>
            </div>

            <div class="row gy-3" id="reservas-container">
                <?php if (empty($bookings)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>No tienes reservas activas. ¡Haz tu primera reserva ahora!
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">
                                        <?= date('d/m/Y', is_numeric($booking['date']) ? $booking['date'] : strtotime($booking['date'])) ?>
                                    </span>
                                    <?php
                                    $badge_class = '';
                                    $badge_text = '';
                                    switch ($booking['status']) {
                                        case 'pending':
                                            $badge_class = 'bg-warning';
                                            $badge_text = 'Pendiente';
                                            break;
                                        case 'accepted':
                                            $badge_class = 'bg-success';
                                            $badge_text = 'Aceptada';
                                            break;
                                        case 'cancelled':
                                            $badge_class = 'bg-danger';
                                            $badge_text = 'Cancelada';
                                            break;
                                        default:
                                            $badge_class = 'bg-secondary';
                                            $badge_text = $booking['status'];
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex mb-2">
                                        <div class="me-3 text-primary">
                                            <i class="bi bi-clock fs-5"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted">Hora</small>
                                            <p class="mb-0 fw-medium"><?= $booking['start'] ?></p>
                                        </div>
                                    </div>
                                    <div class="d-flex mb-2">
                                        <div class="me-3 text-primary">
                                            <i class="bi bi-person fs-5"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted">Profesor</small>
                                            <p class="mb-0 fw-medium"><?= $booking['teacher_name'] ?></p>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="me-3 text-primary">
                                            <i class="bi bi-car-front fs-5"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted">Vehículo</small>
                                            <p class="mb-0 fw-medium"><?= $booking['vehicle'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer border-top-0 bg-white">
                                    <button class="btn btn-outline-primary w-100 ver-detalles" data-bs-toggle="modal" data-bs-target="#detalleReservaModal" data-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-eye me-1"></i> Ver detalles
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Sección del calendario mejorada -->
        <section id="calendario" class="mb-5">
            <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Reservar Clase</h3>
                <div class="calendar-view-switcher btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" data-view="dayGridMonth">
                        <i class="bi bi-calendar-month me-1 d-none d-sm-inline"></i>Mes
                    </button>
                    <button type="button" class="btn btn-outline-primary active" data-view="timeGridWeek">
                        <i class="bi bi-calendar-week me-1 d-none d-sm-inline"></i>Semana
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-view="timeGridDay">
                        <i class="bi bi-calendar-day me-1 d-none d-sm-inline"></i>Día
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Leyenda de colores mejorada -->
                <div class="calendar-legend bg-light p-2 px-3 border-bottom d-flex flex-wrap justify-content-center gap-3">
                    <div class="legend-item">
                        <span class="color-box available"></span> Disponible
                    </div>
                    <div class="legend-item">
                        <span class="color-box booked"></span> Reservado
                    </div>
                    <div class="legend-item">
                        <span class="color-box past"></span> Pasado
                    </div>
                    <div class="legend-item">
                        <span class="color-box recent"></span>
                        <1h
                            </div>
                    </div>

                    <!-- Filtros de calendario (opcional) -->
                    <div class="calendar-filters p-2 px-3 border-bottom bg-light">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <label class="col-form-label col-form-label-sm">Filtrar:</label>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="showMyClassesOnly" checked>
                                    <label class="form-check-label small" for="showMyClassesOnly">Mis clases</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="showAvailableOnly">
                                    <label class="form-check-label small" for="showAvailableOnly">Solo disponibles</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contenedor del calendario con ajustes responsive -->
                    <div class="card-body p-0">
                        <div id="calendar" class="calendar-responsive"></div>
                    </div>

                    <!-- Info extra y atajos -->
                    <div class="card-footer bg-white py-2 px-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="small text-muted">
                                <i class="bi bi-info-circle me-1"></i> Haz clic en un horario disponible para reservar
                            </div>
                            <div>
                                <button id="goToToday" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-calendar-check me-1"></i>Hoy
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
        </section>
    </div>

    <!-- Modales (sin cambios para mantener compatibilidad con JS) -->
    <div class="modal fade" id="studentCalendarModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Nueva Reserva</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" id="studentCalendarForm" class="dsb-form">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3 fs-3">
                                    <i class="bi bi-coin"></i>
                                </div>
                                <div>
                                    Esta reserva costará <strong><?php echo esc_html(DSB_Settings::get('class_cost')); ?></strong> créditos.
                                    <br>Tu saldo actual es de <strong class="saldo-actual"><?php echo esc_html($user['class_points']); ?></strong> créditos.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="date" class="form-label">Fecha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                <input type="date" class="form-control" name="date" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col">
                                <label for="time" class="form-label">Hora de inicio</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="time" class="form-control" name="time" readonly>
                                </div>
                            </div>
                            <div class="col">
                                <label for="end_time" class="form-label">Hora de fin</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-fill"></i></span>
                                    <input type="time" class="form-control" name="end_time" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Confirmar Reserva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentCalendarInfoModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detalles de la Clase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" id="studentCalendarInfoForm" class="dsb-form">
                    <input type="hidden" name="booking_id" value="">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date" class="form-label">Fecha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                    <input type="date" class="form-control" name="date" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="time" class="form-label">Hora de inicio</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="time" class="form-control" name="time" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="end_time" class="form-label">Hora de fin</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock-fill"></i></span>
                                <input type="time" class="form-control" name="end_time" readonly>
                            </div>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-coin me-2"></i>Información de Créditos</h6>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <small class="text-muted d-block">Puntos de la reserva</small>
                                        <span class="booking-points fw-bold">0</span> puntos
                                    </div>
                                    <div class="col-sm-6">
                                        <small class="text-muted d-block">Saldo actual</small>
                                        <span class="saldo-actual fw-bold"><?= $user['class_points'] ?></span> puntos
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <div class="d-flex">
                                <div class="me-3 fs-4">
                                    <i class="bi bi-info-circle-fill"></i>
                                </div>
                                <div>
                                    Si cancelas la clase con al menos <?= $settings['cancelation_time_hours'] ?> horas de antelación, recuperarás los puntos utilizados en esta reserva.
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-cancel-booking">
                                <i class="bi bi-x-circle me-1"></i> Cancelar esta reserva
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detalleReservaModal" tabindex="-1" aria-labelledby="detalleReservaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detalles de la Clase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-calendar-date fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Fecha</small>
                                    <p id="modal-fecha" class="fw-bold mb-0"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-clock fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Hora</small>
                                    <p id="modal-hora" class="fw-bold mb-0"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-person fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Profesor</small>
                                    <p id="modal-profesor" class="fw-bold mb-0"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-car-front fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Vehículo</small>
                                    <p id="modal-vehiculo" class="fw-bold mb-0"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-tag fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Estado</small>
                                    <p id="modal-estado" class="fw-bold mb-0"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <h5>DrivingApp</h5>
                    <p class="small">La mejor app para tu autoescuela</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="small">© <?= date('Y') ?> DrivingApp. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>

</html>