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

$css_base_url = plugin_dir_url(__FILE__) . '../css/';
$js_base_url = plugin_dir_url(__FILE__) . '../js/';
$lib_base_url = plugin_dir_url(__FILE__) . '../lib/';
$plugin_url = plugin_dir_url(__FILE__);

$js_config = [
    'jwtToken' => isset($_SESSION['jwt_token']) ? $_SESSION['jwt_token'] : '',
    'apiBaseUrl' => esc_url(rest_url('driving-school/v1')),
    'classDuration' => DSB_Settings::get('class_duration'),
    'minAntelacion' => DSB_Settings::get('default_min_antelacion'), // en horas
    'maxAntelacion' => DSB_Settings::get('default_max_antelacion'), // en días
    'antelacionUnits' => [
        'min' => 'horas',
        'max' => 'días'
    ]
];

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Autoescuela Universitaria - Alumno</title>
    <meta charset="utf-8">
    <meta name="author" content="Roma & Nico">
    <meta name="description" content="Aplicación de autoescuela">
    <meta name="keywords" content="Autoescuela">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= $css_base_url ?>alumno.css">
    <link rel="icon" type="image/x-icon" href="">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-light">
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-car-front-fill me-2"></i>Autoescuela Universitaria
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
                    <li class="nav-item">
                        <a class="nav-link logout-btn" href="#">
                            <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Perfil del alumno -->
        <section id="perfil" class="mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-container me-3">
                            <div class="avatar-wrapper" style="width: 70px; height: 70px;">
                                <img id="estudiante-avatar" src="<?= esc_url(DSB_User_Service::get_avatar_url($user['id'])); ?>" alt="Avatar de <?= esc_attr($user['name']); ?>" class="rounded-circle avatar-img">
                                <div class="avatar-overlay">
                                    <i class="bi bi-camera-fill"></i>
                                </div>
                            </div>
                            <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                        </div>
                        <div class="ms-auto text-center">
                            <div class="bg-success text-white rounded-3 px-3 py-2">
                                <span class="saldo-actual fs-4 fw-bold"><?= $user['class_points'] ?></span>
                                <div><small>Saldo</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1 mt-2">
                        <h4 id="estudiante-name" class="mb-1"><?= $user['name'] ?></h4>
                        <p id="estudiante-email" class="text-muted mb-0 small"><?= $user['email'] ?></p>
                    </div>
                </div>
                <div class="card-footer bg-white p-0">
                    <div class="accordion accordion-flush" id="profileAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#profileDetails">
                                    <i class="bi bi-info-circle me-2"></i> Detalles del perfil
                                </button>
                            </h2>
                            <div id="profileDetails" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-person-badge"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Profesor</small>
                                                    <p id="assigned-teacher" class="mb-0"><?= $user['teacher']['name'] ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-car-front-fill"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Vehículo</small>
                                                    <p id="assigned-car" class="mb-0"><?= $user['teacher']['vehicle_name'] ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#infoCollapse">
                                    <i class="bi bi-tag me-2"></i> Tarifas y políticas
                                </button>
                            </h2>
                            <div id="infoCollapse" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                                <div class="accordion-body p-4">
                                    <div class="row g-4">
                                        <!-- Costos y Límites -->
                                        <div class="col-12">
                                            <h6 class="text-dark mb-3 fw-semibold">
                                                <i class="bi bi-coin text-primary me-2"></i>Costos y Límites
                                            </h6>

                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="card border-0 shadow-sm h-100">
                                                        <div class="card-body p-3 border-start border-4 border-primary">
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="bi bi-tag-fill"></i>
                                                                </div>
                                                                <div>
                                                                    <small class="text-muted d-block">Precio por clase</small>
                                                                    <span class="h5 mb-0 text-dark" id="precio-clase"><?= $settings['class_cost'] ?></span>
                                                                    <small class="text-muted"> puntos</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card border-0 shadow-sm h-100">
                                                        <div class="card-body p-3 border-start border-4 border-success">
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="bi bi-calendar-check-fill"></i>
                                                                </div>
                                                                <div>
                                                                    <small class="text-muted d-block">Máximo diario</small>
                                                                    <span class="h5 mb-0 text-dark"><?= $settings['daily_limit'] ?></span>
                                                                    <small class="text-muted"> clases</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card border-0 shadow-sm h-100">
                                                        <div class="card-body p-3 border-start border-4 border-warning">
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="bi bi-clock-fill"></i>
                                                                </div>
                                                                <div>
                                                                    <small class="text-muted d-block">Cancelación gratuita</small>
                                                                    <span class="h5 mb-0 text-dark"><?= $settings['cancelation_time_hours'] ?>h</span>
                                                                    <small class="text-muted"> antes</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Antelación -->
                                        <div class="col-12">
                                            <h6 class="text-dark mb-3 fw-semibold">
                                                <i class="bi bi-clock text-primary me-2"></i>Tiempos de Antelación
                                            </h6>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="card border-0 shadow-sm h-100">
                                                        <div class="card-body p-3 border-start border-4 border-info">
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-info bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="bi bi-hourglass-split"></i>
                                                                </div>
                                                                <div>
                                                                    <small class="text-muted d-block">Antelación mínima</small>
                                                                    <span class="h5 mb-0 text-dark"><?= DSB_Settings::get('default_min_antelacion') ?></span>
                                                                    <small class="text-muted"> <?= DSB_Settings::get('default_min_antelacion') == 1 ? 'hora' : 'horas' ?></small>
                                                                    <div class="small text-muted mt-1">Tiempo mínimo para reservar</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card border-0 shadow-sm h-100">
                                                        <div class="card-body p-3 border-start border-4 border-secondary">
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-secondary bg-opacity-10 p-2 rounded-circle me-3">
                                                                    <i class="bi bi-calendar-range"></i>
                                                                </div>
                                                                <div>
                                                                    <small class="text-muted d-block">Antelación máxima</small>
                                                                    <span class="h5 mb-0 text-dark"><?= DSB_Settings::get('default_max_antelacion') ?></span>
                                                                    <small class="text-muted"> <?= DSB_Settings::get('default_max_antelacion') == 1 ? 'día' : 'días' ?></small>
                                                                    <div class="small text-muted mt-1">Máximo tiempo de antelación</div>
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
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mis Reservas -->
        <section id="reservas" class="mb-4">
            <!-- Resumen de reservas con notificaciones -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="calendar-icon bg-light rounded p-2 me-3">
                                    <i class="bi bi-calendar2-week text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0">Mis Clases</h5>
                                    <p class="text-muted small mb-0">
                                        <?php
                                        $proximas = array_filter($bookings, function ($b) {
                                            return $b['status'] != 'cancelled' && strtotime($b['date']) >= strtotime('today');
                                        });
                                        echo count($proximas) > 0
                                            ? 'Tienes ' . count($proximas) . ' clase(s) programada(s)'
                                            : 'No tienes clases programadas';
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#reservasOffcanvas">
                                Ver todas <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <?php if (!empty($proximas)):
                            // Ordenar por fecha y hora
                            usort($proximas, function ($a, $b) {
                                $date_compare = strtotime($a['date']) - strtotime($b['date']);
                                if ($date_compare == 0) {
                                    return strtotime($a['start']) - strtotime($b['start']);
                                }
                                return $date_compare;
                            });
                            $next = reset($proximas); // Primera clase

                            // Determinar el estado y su color
                            $badge_class = '';
                            $badge_text = '';
                            switch ($next['status']) {
                                case 'pending':
                                    $badge_class = 'bg-warning';
                                    $badge_text = 'Pendiente';
                                    break;
                                case 'accepted':
                                    $badge_class = 'bg-success';
                                    $badge_text = 'Confirmada';
                                    break;
                                default:
                                    $badge_class = 'bg-secondary';
                                    $badge_text = ucfirst($next['status']);
                            }
                        ?>
                            <div class="list-group-item p-0">
                                <div class="px-3 py-2 bg-light small fw-bold">Próxima clase</div>
                                <div class="p-3">
                                    <div class="mb-1">
                                        <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="bi bi-calendar-date text-primary me-2"></i>
                                                <strong><?= date('d/m/Y', strtotime($next['date'])) ?></strong>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-clock text-primary me-2"></i>
                                                <span><?= $next['start'] ?> - <?= $next['end'] ?></span>
                                            </div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary ver-detalles"
                                            data-bs-toggle="modal" data-bs-target="#detalleReservaModal"
                                            data-id="<?= $next['id'] ?>">
                                            <i class="bi bi-eye me-1"></i> Ver detalles
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <div>
                                            <i class="bi bi-person me-1"></i> <?= $next['teacher_name'] ?? $user['teacher']['name'] ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-car-front me-1"></i> <?= $next['vehicle'] ?? $user['teacher']['vehicle_name'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="list-group-item p-0">
                            <div class="d-grid">
                                <a href="#calendario" class="btn btn-primary m-2">
                                    <i class="bi bi-plus-circle me-2"></i>Reservar nueva clase
                                </a>
                            </div>
                        </div>

                        <?php if ($classes_today > 0): ?>
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="calendar-icon bg-success-subtle rounded p-2 me-3">
                                        <i class="bi bi-calendar-day text-success fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Hoy tienes clase</h6>
                                        <p class="small text-muted mb-0">
                                            <?= $classes_today ?> clase<?= $classes_today > 1 ? 's' : '' ?> para hoy
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel lateral que aparece al hacer clic en "Ver todas" -->
            <div class="offcanvas offcanvas-end" tabindex="-1" id="reservasOffcanvas" aria-labelledby="reservasOffcanvasLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="reservasOffcanvasLabel">
                        <i class="bi bi-calendar-check me-2"></i>Todas mis reservas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <?php if (empty($bookings)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>No tienes reservas activas. ¡Haz tu primera reserva ahora!
                        </div>
                    <?php else: ?>
                        <!-- Pestañas para filtrar reservas -->
                        <ul class="nav nav-tabs mb-3" id="reservasTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="proximas-tab" data-bs-toggle="tab" data-bs-target="#proximas" type="button" role="tab">
                                    Próximas <span class="badge rounded-pill bg-primary ms-1"><?= count($proximas) ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab">
                                    Historial
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="canceladas-tab" data-bs-toggle="tab" data-bs-target="#canceladas" type="button" role="tab">
                                    Canceladas
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Próximas reservas -->
                            <div class="tab-pane fade show active" id="proximas" role="tabpanel">
                                <div class="list-group">
                                    <?php foreach ($proximas as $booking): ?>
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
                                                $badge_text = 'Confirmada';
                                                break;
                                            default:
                                                $badge_class = 'bg-secondary';
                                                $badge_text = ucfirst($booking['status']);
                                        }
                                        ?>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                                <small class="text-muted"><?= date('d/m/Y', strtotime($booking['date'])) ?></small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="mb-1"><strong><?= $booking['start'] ?> - <?= $booking['end'] ?></strong></div>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?= $booking['teacher_name'] ?? $user['teacher']['name'] ?>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary ver-detalles"
                                                    data-bs-toggle="modal" data-bs-target="#detalleReservaModal"
                                                    data-id="<?= $booking['id'] ?>" data-bs-dismiss="offcanvas">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Historial de reservas -->
                            <div class="tab-pane fade" id="historial" role="tabpanel">
                                <?php
                                $pasadas = array_filter($bookings, function ($b) {
                                    return $b['status'] != 'cancelled' && strtotime($b['date']) < strtotime('today');
                                });

                                if (empty($pasadas)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>No tienes clases en tu historial.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($pasadas as $booking): ?>
                                            <div class="list-group-item list-group-item-action p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="badge bg-secondary">Completada</span>
                                                    <small class="text-muted"><?= date('d/m/Y', strtotime($booking['date'])) ?></small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="mb-1"><strong><?= $booking['start'] ?> - <?= $booking['end'] ?></strong></div>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-person me-1"></i>
                                                            <?= $booking['teacher_name'] ?? $user['teacher']['name'] ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-secondary ver-detalles"
                                                        data-bs-toggle="modal" data-bs-target="#detalleReservaModal"
                                                        data-id="<?= $booking['id'] ?>" data-bs-dismiss="offcanvas">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Reservas canceladas -->
                            <div class="tab-pane fade" id="canceladas" role="tabpanel">
                                <?php
                                $canceladas = array_filter($bookings, function ($b) {
                                    return $b['status'] == 'cancelled';
                                });

                                if (empty($canceladas)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>No tienes clases canceladas.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($canceladas as $booking): ?>
                                            <div class="list-group-item list-group-item-action p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="badge bg-danger">Cancelada</span>
                                                    <small class="text-muted"><?= date('d/m/Y', strtotime($booking['date'])) ?></small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="mb-1"><strong><?= $booking['start'] ?> - <?= $booking['end'] ?></strong></div>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-person me-1"></i>
                                                            <?= $booking['teacher_name'] ?? $user['teacher']['name'] ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger ver-detalles"
                                                        data-bs-toggle="modal" data-bs-target="#detalleReservaModal"
                                                        data-id="<?= $booking['id'] ?>" data-bs-dismiss="offcanvas">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Sección del calendario mejorada -->
        <section id="calendario" class="mb-5">
            <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Reservar Clase</h3>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Leyenda de colores mejorada -->
                <div class="calendar-legend p-2 px-3 border-bottom d-flex flex-wrap justify-content-center gap-3">
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
                        <span class="color-box recent"></span> &lt;1h
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
                            <button id="goToToday" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-calendar-check me-1"></i>Hoy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modales -->
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
                                    Esta reserva costará <strong><?= esc_html(DSB_Settings::get('class_cost')); ?></strong> créditos.
                                    <br>Tu saldo actual es de <strong class="saldo-actual"><?= esc_html($user['class_points']); ?></strong> créditos.
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
                    <h5>Autoescuela Universitaria</h5>
                    <p class="small">La mejor app para tu autoescuela</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="small">© <?= date('Y') ?> Autoescuela Universitaria. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= $lib_base_url ?>fullcalendar.min.js"></script>
    <script src="<?= $lib_base_url ?>fullcalendar.js"></script>

    <script>
        // Pasar datos desde PHP a JavaScript
        const studentData = <?= json_encode(dsb_get_student_data()); ?>;
        const bookingsData = <?= json_encode(dsb_get_bookings_data()); ?>;
        const teacherBookingsData = <?= json_encode(dsb_get_teacher_bookings_data()); ?>;
        const DSB_CONFIG = <?= json_encode($js_config); ?>;
        const DSB_PUSHER = {
            serviceWorkerUrl: '<?= $plugin_url; ?>/service-worker.js',
            instanceId: '02609d94-0e91-4039-baf6-7d9d04b1fb6e'
        };
    </script>

    <!-- Nuestros scripts -->
    <script src="<?= $js_base_url; ?>pusher-init.js"></script>
    <script src="<?= $js_base_url; ?>alumno.js"></script>

</body>

</html>