<?php

/**
 * Template Name: Vista Profesor
 */
if (!defined('ABSPATH')) {
    exit;
}

function dsb_get_teacher_data()
{
    $decoded = DSB()->jwt->validate_token($_SESSION['jwt_token']);
    $user_id = $decoded->user->id;

    // Obtener datos del profesor
    $result = DSB_Teacher_Service::get_teacher($user_id);
    $teacher = $result->get_data()['data'];

    // Obtener configuración de clases
    $teacher['config'] = get_user_meta($user_id, 'dsb_clases_config', true) ?: [
        'dias' => [],
        'hora_inicio' => '08:00',
        'hora_fin' => '20:00',
        'duracion' => 45
    ];

    // Obtener vehículo
    $vehicle_id = $teacher['vehicle_id'];
    if ($vehicle_id) {
        $vehicle = get_post($vehicle_id);
        $teacher['vehicle'] = $vehicle ? $vehicle->post_title : 'Sin vehículo asignado';
    } else {
        $teacher['vehicle'] = 'Sin vehículo asignado';
    }

    return $teacher;
}

function dsb_get_teacher_bookings()
{
    $decoded = DSB()->jwt->validate_token($_SESSION['jwt_token']);
    $teacher_id = $decoded->user->id;

    $args = [
        'post_type' => 'dsb_booking',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'teacher_id',
                'value' => $teacher_id,
                'compare' => '='
            ]
        ]
    ];

    $bookings_query = new WP_Query($args);
    $bookings = [];

    if ($bookings_query->have_posts()) {
        while ($bookings_query->have_posts()) {
            $bookings_query->the_post();
            $booking_id = get_the_ID();
            $student_id = get_post_meta($booking_id, 'student_id', true);
            $student = get_userdata($student_id);
            $student_name = $student ? $student->display_name : 'Estudiante Desconocido';

            $date = get_post_meta($booking_id, 'date', true);
            $time = get_post_meta($booking_id, 'time', true);
            $end_time = get_post_meta($booking_id, 'end_time', true);
            $status = get_post_meta($booking_id, 'status', true);
            $vehicle_id = get_post_meta($booking_id, 'vehicle_id', true);
            $vehicle_name = get_post($vehicle_id)->post_title;

            $bookings[] = [
                'id' => $booking_id,
                'title' => "Clase con {$student_name}",
                'start' => "{$date}T{$time}",
                'end' => "{$date}T{$end_time}",
                'status' => $status,
                'student_id' => $student_id,
                'student_name' => $student_name,
                'vehicle' => $vehicle_name,
                'date' => $date,
                'time' => $time,
                'end_time' => $end_time
            ];
        }
    }

    wp_reset_postdata();

    return $bookings;
}

$teacher = dsb_get_teacher_data();
if (!isset($teacher['config']) || !is_array($teacher['config'])) {
    $teacher['config'] = [
        'dias' => [],
        'hora_inicio' => '08:00',
        'hora_fin' => '20:00',
        'duracion' => 45
    ];
}

$bookings = dsb_get_teacher_bookings();

// Contadores para estadísticas
$pending_count = 0;
$today_count = 0;
$total_count = 0;
$today = date('Y-m-d');

foreach ($bookings as $booking) {
    if ($booking['status'] === 'pending') {
        $pending_count++;
    }
    if ($booking['date'] === $today && $booking['status'] !== 'cancelled') {
        $today_count++;
    }
    if ($booking['status'] !== 'cancelled') {
        $total_count++;
    }
}

// Enqueue assets
function dsb_enqueue_profesor_assets()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css', [], '5.11.3');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', ['jquery'], '5.11.3', true);
    wp_enqueue_script('fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js', ['fullcalendar-js'], '5.11.3', true);

    wp_enqueue_style('dsb-profesor-css', plugin_dir_url(__FILE__) . '../css/profesor.css', [], '1.0.0', 'all');
    wp_enqueue_script('dsb-profesor-js', plugin_dir_url(__FILE__) . '../js/profesor.js', ['jquery', 'fullcalendar-js'], '1.0.0', true);

    // Pasar datos a JavaScript
    wp_localize_script('dsb-profesor-js', 'teacherData', dsb_get_teacher_data());
    wp_localize_script('dsb-profesor-js', 'bookingsData', dsb_get_teacher_bookings());
    wp_localize_script(
        'dsb-profesor-js',
        'DSB_CONFIG',
        [
            'jwtToken' => isset($_SESSION['jwt_token']) ? $_SESSION['jwt_token'] : '',
            'apiBaseUrl' => esc_url(rest_url('driving-school/v1')),
        ]
    );
}
add_action('wp_enqueue_scripts', 'dsb_enqueue_profesor_assets');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>DrivingApp - Panel del Profesor</title>
    <?php wp_head(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
                        <a class="nav-link" href="#alumnos">
                            <i class="bi bi-people-fill me-1"></i>Mis Alumnos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#calendario">
                            <i class="bi bi-calendar-check me-1"></i>Calendario
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
        <!-- Perfil del profesor -->
        <section id="perfil" class="mb-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-4 mb-4 mb-lg-0 text-center">
                            <div class="avatar-container mb-3">
                                <div class="avatar-wrapper mx-auto">
                                    <img id="teacher-avatar" src="<?php echo esc_url($teacher['avatar']); ?>" alt="Avatar de <?= esc_attr($teacher['display_name']); ?>" class="rounded-circle avatar-img">
                                    <div class="avatar-overlay">
                                        <i class="bi bi-camera-fill"></i>
                                        <span>Cambiar foto</span>
                                    </div>
                                </div>
                                <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <h2 id="teacher-name" class="mb-3 text-center text-lg-start"><?= esc_html($teacher['display_name']) ?></h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-envelope-fill me-2"></i>Email</h6>
                                            <p id="teacher-email" class="card-text"><?= esc_html($teacher['email']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-people me-2"></i>Alumnos asignados</h6>
                                            <p id="assigned-students" class="card-text"><?= count($teacher['students']) ?> alumnos</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-car-front-fill me-2"></i>Vehículo</h6>
                                            <p id="assigned-car" class="card-text"><?= esc_html($teacher['vehicle']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 bg-primary text-white border-0">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-white"><i class="bi bi-calendar-event me-2"></i>Estadísticas</h6>
                                            <div class="d-flex justify-content-around text-center">
                                                <div>
                                                    <p class="mb-0 fs-4"><?= $today_count ?></p>
                                                    <small>Clases hoy</small>
                                                </div>
                                                <div>
                                                    <p class="mb-0 fs-4"><?= $pending_count ?></p>
                                                    <small>Pendientes</small>
                                                </div>
                                                <div>
                                                    <p class="mb-0 fs-4"><?= $total_count ?></p>
                                                    <small>Total</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white p-0">
                    <div class="accordion accordion-flush" id="configAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#configCollapse">
                                    <i class="bi bi-gear me-2"></i>Configuración de horarios
                                </button>
                            </h2>
                            <div id="configCollapse" class="accordion-collapse collapse" data-bs-parent="#configAccordion">
                                <div class="accordion-body">
                                    <form id="horario-config-form" class="dsb-form">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Días disponibles</label>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php
                                                    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                                                    $dias_config = $teacher['config']['dias'] ?? [];

                                                    foreach ($dias as $dia):
                                                        $checked = in_array($dia, $dias_config) ? 'checked' : '';
                                                    ?>
                                                        <div class="form-check form-check-inline">
                                                            <input type="checkbox" class="form-check-input" name="dias[]" id="dia-<?= strtolower($dia) ?>" value="<?= $dia ?>" <?= $checked ?>>
                                                            <label class="form-check-label" for="dia-<?= strtolower($dia) ?>"><?= $dia ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label for="hora-inicio" class="form-label">Hora de inicio</label>
                                                        <input type="time" class="form-control" id="hora-inicio" name="hora_inicio" value="<?= $teacher['config']['hora_inicio'] ?? '08:00' ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="hora-fin" class="form-label">Hora de fin</label>
                                                        <input type="time" class="form-control" id="hora-fin" name="hora_fin" value="<?= $teacher['config']['hora_fin'] ?? '20:00' ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="duracion-clase" class="form-label">Duración de clase (minutos)</label>
                                                <select class="form-select" id="duracion-clase" name="duracion">
                                                    <?php
                                                    $duraciones = [30, 45, 60, 90];
                                                    $duracion_actual = $teacher['config']['duracion'] ?? 45;

                                                    foreach ($duraciones as $duracion):
                                                        $selected = ($duracion == $duracion_actual) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $duracion ?>" <?= $selected ?>><?= $duracion ?> minutos</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-1"></i> Guardar configuración
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Alumnos asignados -->
        <section id="alumnos" class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0"><i class="bi bi-people-fill me-2"></i>Mis Alumnos</h3>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Alumno</th>
                                <th>Correo</th>
                                <th>Licencia</th>
                                <th>Clases</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teacher['students'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No tienes alumnos asignados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teacher['students'] as $student):
                                    // Contando clases del alumno
                                    $student_classes = 0;
                                    foreach ($bookings as $booking) {
                                        if ($booking['student_id'] == $student['id'] && $booking['status'] !== 'cancelled') {
                                            $student_classes++;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0">
                                                    <img src="<?= get_avatar_url($student['id'], ['size' => 40]) ?>" alt="Avatar" class="rounded-circle" width="40">
                                                </div>
                                                <div class="ms-2">
                                                    <h6 class="mb-0"><?= esc_html($student['display_name']) ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= esc_html($student['email']) ?></td>
                                        <td>
                                            <?php
                                            $license_type = get_user_meta($student['id'], 'license_type', true);
                                            echo $license_type ? esc_html($license_type) : 'No especificado';
                                            ?>
                                        </td>
                                        <td><?= $student_classes ?> clases</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary ver-alumno" data-id="<?= $student['id'] ?>">
                                                <i class="bi bi-eye"></i> Ver detalles
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Calendario de clases -->
        <section id="calendario" class="mb-5">
            <div class="calendar-header d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h3 class="mb-0 me-3"><i class="bi bi-calendar-check me-2"></i>Calendario de Clases</h3>
                <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                    <div class="calendar-filter-wrapper me-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="checkbox" class="btn-check" id="showPendingOnly" autocomplete="off">
                            <label class="btn btn-outline-warning" for="showPendingOnly">
                                <i class="bi bi-hourglass me-1"></i>Pendientes
                            </label>

                            <input type="checkbox" class="btn-check" id="showAcceptedOnly" autocomplete="off">
                            <label class="btn btn-outline-success" for="showAcceptedOnly">
                                <i class="bi bi-check-circle me-1"></i>Aceptadas
                            </label>
                        </div>
                    </div>
                    <div class="calendar-view-switcher">
                        <div class="btn-group btn-group-sm" role="group">
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
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Leyenda de colores mejorada -->
                <div class="calendar-legend p-2 px-3 border-bottom d-flex flex-wrap align-items-center justify-content-center gap-3">
                    <div class="legend-item">
                        <span class="color-box available"></span> Disponible
                    </div>
                    <div class="legend-item">
                        <span class="color-box pending"></span> Pendiente
                    </div>
                    <div class="legend-item">
                        <span class="color-box accepted"></span> Aceptada
                    </div>
                    <div class="legend-item">
                        <span class="color-box past"></span> Pasado
                    </div>
                </div>

                <!-- Calendario con contenedor mejorado para responsividad -->
                <div class="card-body p-0">
                    <div id="teacher-calendar" class="calendar-responsive"></div>
                </div>

                <!-- Controles adicionales y ayuda -->
                <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center">
                    <div class="small text-muted order-2 order-md-1 mt-2 mt-md-0">
                        <i class="bi bi-info-circle me-1"></i> Haz clic en una reserva para ver detalles
                    </div>
                    <div class="calendar-controls order-1 order-md-2 d-flex gap-2">
                        <button id="refreshCalendar" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                        </button>
                        <button id="goToToday" class="btn btn-sm btn-primary">
                            <i class="bi bi-calendar-check me-1"></i>Hoy
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal para detalles y aceptación de reserva -->
    <div class="modal fade" id="bookingDetailModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-check me-2"></i>Detalles de la Reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bookingActionForm" class="dsb-form">
                        <input type="hidden" name="booking_id" id="booking_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Alumno</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="student_name" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                    <input type="text" class="form-control" id="booking_status" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                    <input type="date" class="form-control" id="booking_date" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                    <input type="text" class="form-control" id="booking_time" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Vehículo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-car-front"></i></span>
                                    <input type="text" class="form-control" id="booking_vehicle" readonly>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <button type="button" id="rejectBookingBtn" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle me-1"></i> Rechazar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="acceptBookingBtn" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Aceptar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles del alumno -->
    <div class="modal fade" id="studentDetailModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person me-2"></i>Detalles del Alumno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <img id="modal-student-avatar" src="" alt="Avatar del alumno" class="rounded-circle mb-3" width="100">
                        <h5 id="modal-student-name" class="mb-0"></h5>
                        <p id="modal-student-email" class="text-muted"></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3 text-primary">
                                    <i class="bi bi-card-checklist fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Tipo de Licencia</small>
                                    <p id="modal-student-license" class="mb-0 fw-medium"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3 text-primary">
                                    <i class="bi bi-telephone fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Teléfono</small>
                                    <p id="modal-student-phone" class="mb-0 fw-medium"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3 text-primary">
                                    <i class="bi bi-calendar-check fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Clases completadas</small>
                                    <p id="modal-student-classes" class="mb-0 fw-medium"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Últimas clases</h6>
                    <div class="student-classes-list">
                        <div class="text-center py-3" id="no-classes-message">
                            <p class="text-muted mb-0">Este alumno no tiene clases registradas</p>
                        </div>
                        <ul class="list-group list-group-flush" id="student-classes"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para rechazar reserva -->
    <div class="modal fade" id="rejectConfirmModal">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar rechazo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas rechazar esta reserva?</p>
                    <input type="hidden" id="reject-booking-id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="confirmRejectBtn" class="btn btn-danger">Rechazar</button>
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