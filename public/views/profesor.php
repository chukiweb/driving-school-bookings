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
    $config = get_user_meta($user_id, 'dsb_clases_config', true);

    // Configuración por defecto con descansos vacíos
    $default_config = [
        'dias' => [],
        'hora_inicio' => '08:00',
        'hora_fin' => '20:00',
        'duracion' => 45,
        'descansos' => []
    ];

    // Merge con configuración existente
    $teacher['config'] = is_array($config) ? array_merge($default_config, $config) : $default_config;

    // Asegurar que descansos es un array
    if (!isset($teacher['config']['descansos']) || !is_array($teacher['config']['descansos'])) {
        $teacher['config']['descansos'] = [];
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
                'title' => "Reservado con {$student_name}",
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
$today_count = 0;
$total_count = 0;
$today = date('Y-m-d');

foreach ($bookings as $booking) {
    if ($booking['status'] === 'blocked') {
        continue; // Ignorar reservas bloqueadas
    }
    if ($booking['date'] === $today && $booking['status'] !== 'cancelled') {
        $today_count++;
    }
    if ($booking['status'] !== 'cancelled') {
        $total_count++;
    }
}

$css_base_url = plugin_dir_url(__FILE__) . '../css/';
$js_base_url = plugin_dir_url(__FILE__) . '../js/';
$lib_base_url = plugin_dir_url(__FILE__) . '../lib/';
$plugin_url = plugin_dir_url(__FILE__);

// Configuración para JavaScript
$js_config = [
    'jwtToken' => isset($_SESSION['jwt_token']) ? $_SESSION['jwt_token'] : '',
    'apiBaseUrl' => esc_url(rest_url('driving-school/v1')),
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
    <title>Autoescuela Universitaria - Panel del Profesor</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $css_base_url ?>profesor.css?ver=<?= DSB_VERSION ?>">
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
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-container me-3">
                            <div class="avatar-wrapper" style="width: 70px; height: 70px;">
                                <img id="teacher-avatar" src="<?= esc_url(DSB_User_Service::get_avatar_url($teacher['id'])); ?>" alt="Avatar de <?= esc_attr($teacher['display_name']); ?>" class="rounded-circle avatar-img">
                                <div class="avatar-overlay">
                                    <i class="bi bi-camera-fill"></i>
                                </div>
                            </div>
                            <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                        </div>
                        <div class="ms-auto text-center">
                            <div class="bg-primary text-white rounded-3 px-3 py-2 text-center">
                                <span class="fs-4 fw-bold"><?= count($teacher['students']) ?></span>
                                <div><small>alumnos</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1 mt-2">
                        <h4 id="teacher-name" class="mb-1"><?= esc_html($teacher['display_name']) ?></h4>
                        <p id="teacher-email" class="text-muted mb-0 small"><?= esc_html($teacher['email']) ?></p>
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
                                                    <i class="bi bi-car-front-fill"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Coche</small>
                                                    <p id="assigned-car" class="mb-0"><?= esc_html($teacher['vehicle']['b']['name']) ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-bicycle"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Moto</small>
                                                    <p id="assigned-car" class="mb-0"><?= esc_html($teacher['vehicle']['a']['name']) ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-geo-alt"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Ubicación de recogida</small>
                                                    <p id="pickup-location" class="mb-0"><?= esc_html(DSB_Settings::get('pickup_location') ?? 'No configurada') ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-calendar-check"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Clases hoy</small>
                                                    <p class="mb-0"><?= $today_count ?> clases</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <div class="me-3 text-primary">
                                                    <i class="bi bi-calendar-date"></i>
                                                </div>
                                                <div>
                                                    <small class="text-muted d-block">Total reservas</small>
                                                    <p class="mb-0"><?= $total_count ?> clases</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#configCollapse">
                                    <i class="bi bi-gear me-2"></i> Configuración de horarios
                                </button>
                            </h2>
                            <div id="configCollapse" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
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

                                        <!-- Nueva sección de descansos -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <label class="form-label">Descansos</label>
                                                <div id="descansos-container" class="mb-3">
                                                    <?php
                                                    $descansos_config = $teacher['config']['descansos'] ?? [];
                                                    if (!empty($descansos_config) && is_array($descansos_config)):
                                                        foreach ($descansos_config as $index => $descanso):
                                                            if (isset($descanso['inicio']) && isset($descanso['fin'])):
                                                    ?>
                                                                <div class="descanso-item mb-2 p-3 border rounded bg-light" data-descanso-id="<?= $index + 1 ?>">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-md-4">
                                                                            <label class="form-label small">Hora inicio</label>
                                                                            <input type="time" class="form-control form-control-sm"
                                                                                name="descansos[<?= $index + 1 ?>][inicio]"
                                                                                value="<?= esc_attr($descanso['inicio']) ?>" required>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <label class="form-label small">Hora fin</label>
                                                                            <input type="time" class="form-control form-control-sm"
                                                                                name="descansos[<?= $index + 1 ?>][fin]"
                                                                                value="<?= esc_attr($descanso['fin']) ?>" required>
                                                                        </div>
                                                                        <div class="col-md-4 d-flex align-items-end">
                                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-descanso-btn">
                                                                                <i class="bi bi-trash"></i> Eliminar
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                    <?php
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                    ?>
                                                </div>
                                                <button type="button" id="add-descanso-btn" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-plus-circle"></i> Añadir Descanso
                                                </button>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 d-flex align-items-center">
                            <i class="bi bi-people-fill me-2 text-primary"></i>
                            Mis Alumnos
                            <span class="badge bg-primary rounded-pill ms-2"><?= count($teacher['students']) ?></span>
                        </h5>
                        <div>
                            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#alumnosOffcanvas">
                                <i class="bi bi-search me-1"></i> Buscar y filtrar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Mostrar estadísticas resumidas -->
                    <div class="row g-0 border-bottom">
                        <div class="col-4 border-end p-3 text-center">
                            <?php
                            // Contar alumnos con clases pendientes
                            $alumnos_pendientes = 0;
                            $alumno_ids_pendientes = [];
                            foreach ($bookings as $booking) {
                                if ($booking['status'] === 'pending' && !in_array($booking['student_id'], $alumno_ids_pendientes)) {
                                    $alumno_ids_pendientes[] = $booking['student_id'];
                                    $alumnos_pendientes++;
                                }
                            }
                            ?>
                            <div class="d-flex flex-column align-items-center">
                                <div class="stat-icon bg-warning-subtle rounded-circle p-3 mb-2">
                                    <i class="bi bi-hourglass text-warning fs-4"></i>
                                </div>
                                <h2 class="fs-4 mb-0"><?= $alumnos_pendientes ?></h2>
                                <div class="text-muted small">Con citas pendientes</div>
                            </div>
                        </div>
                        <div class="col-4 border-end p-3 text-center">
                            <?php
                            // Contar alumnos con citas hoy
                            $alumnos_hoy = 0;
                            $alumno_ids_hoy = [];
                            $today = date('Y-m-d');
                            foreach ($bookings as $booking) {
                                if ($booking['date'] === $today && $booking['status'] !== 'cancelled' && $booking['status'] !== 'blocked' && !in_array($booking['student_id'], $alumno_ids_hoy)) {
                                    $alumno_ids_hoy[] = $booking['student_id'];
                                    $alumnos_hoy++;
                                }
                            }
                            ?>
                            <div class="d-flex flex-column align-items-center">
                                <div class="stat-icon bg-success-subtle rounded-circle p-3 mb-2">
                                    <i class="bi bi-calendar-check text-success fs-4"></i>
                                </div>
                                <h2 class="fs-4 mb-0"><?= $alumnos_hoy ?></h2>
                                <div class="text-muted small">Con clases hoy</div>
                            </div>
                        </div>
                        <div class="col-4 p-3 text-center">
                            <?php
                            // Contar alumnos sin clases recientes (últimos 30 días)
                            $alumnos_sin_clases_recientes = count($teacher['students']);
                            $alumno_ids_con_clases = [];
                            $today = date('Y-m-d');
                            foreach ($bookings as $booking) {
                                if (empty($booking['student_id'])) continue;
                                if ($booking['date'] === $today && !in_array($booking['student_id'], $alumno_ids_con_clases)) {
                                    $alumno_ids_con_clases[] = $booking['student_id'];
                                }
                            }
                            $alumnos_sin_clases_recientes = count($teacher['students']) - count($alumno_ids_con_clases);
                            ?>
                            <div class="d-flex flex-column align-items-center">
                                <div class="stat-icon bg-danger-subtle rounded-circle p-3 mb-2">
                                    <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                                </div>
                                <h2 class="fs-4 mb-0"><?= $alumnos_sin_clases_recientes ?></h2>
                                <div class="text-muted small">No han reservado hoy</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mostrar alumnos destacados (por ejemplo, con clases hoy) -->
                    <div class="p-3">
                        <h6 class="border-bottom pb-2 mb-3">Alumnos con clases hoy</h6>

                        <?php if (empty($alumno_ids_hoy)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>No hay alumnos con clases programadas para hoy
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php
                                $alumnos_mostrados = 0;
                                foreach ($teacher['students'] as $student):
                                    if (in_array($student['id'], $alumno_ids_hoy) && $alumnos_mostrados < 3):
                                        $alumnos_mostrados++;

                                        // Encontrar las clases de hoy para este alumno
                                        $clases_hoy = [];
                                        foreach ($bookings as $booking) {
                                            if ($booking['student_id'] == $student['id'] && $booking['date'] === $today && $booking['status'] !== 'cancelled') {
                                                $clases_hoy[] = $booking;
                                            }
                                        }
                                ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= esc_url(DSB_User_Service::get_avatar_url($student['id'])); ?>" alt="Avatar" class="rounded-circle me-3" width="42">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?= esc_html($student['display_name']) ?></h6>
                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                        <?php foreach ($clases_hoy as $index => $clase):
                                                            if ($index < 2): // Mostrar máximo 2 clases
                                                        ?>
                                                                <span class="badge bg-light text-dark">
                                                                    <i class="bi bi-clock me-1"></i><?= $clase['time'] ?>
                                                                </span>
                                                            <?php
                                                            endif;
                                                            if ($index == 2): // Indicar si hay más clases
                                                            ?>
                                                                <span class="badge bg-light text-dark">+<?= count($clases_hoy) - 2 ?> más</span>
                                                        <?php
                                                            endif;
                                                        endforeach;
                                                        ?>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary ver-alumno" data-id="<?= $student['id'] ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                <?php endif;
                                endforeach; ?>
                            </div>

                            <?php if (count($alumno_ids_hoy) > 3): ?>
                                <div class="text-center mt-2">
                                    <button class="btn btn-link btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#alumnosOffcanvas">
                                        Ver todos (<?= count($alumno_ids_hoy) ?>) <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel lateral con búsqueda y filtros avanzados -->
            <div class="offcanvas offcanvas-end" tabindex="-1" id="alumnosOffcanvas" aria-labelledby="alumnosOffcanvasLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="alumnosOffcanvasLabel">
                        <i class="bi bi-people-fill me-2"></i>Todos mis alumnos
                    </h5>
                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <!-- Buscador con filtros -->
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchStudentOffcanvas" placeholder="Buscar por nombre o email...">
                            <button class="btn btn-primary" type="button" id="searchStudentBtn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <select class="form-select" id="studentFilter">
                            <option value="all">Todos los alumnos</option>
                            <option value="today">Con clases hoy</option>
                            <option value="pending">Con clases pendientes</option>
                            <option value="inactive">Sin clases recientes</option>
                        </select>
                    </div>

                    <!-- Lista de alumnos con paginación -->
                    <div class="student-list-container" id="studentListContainer">
                        <?php if (empty($teacher['students'])): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>No tienes alumnos asignados
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush" id="studentList">
                                <?php foreach ($teacher['students'] as $index => $student):
                                    // Contando clases del alumno
                                    $student_classes = 0;
                                    $pending_classes = 0;
                                    $today_classes = 0;
                                    $recent_classes = 0; // clases en los últimos 30 días

                                    foreach ($bookings as $booking) {
                                        if ($booking['student_id'] == $student['id']) {
                                            if ($booking['status'] !== 'cancelled') {
                                                $student_classes++;

                                                if ($booking['date'] === $today) {
                                                    $today_classes++;
                                                }

                                                if (strtotime($booking['date']) >= strtotime($treinta_dias)) {
                                                    $recent_classes++;
                                                }
                                            }

                                            if ($booking['status'] === 'pending') {
                                                $pending_classes++;
                                            }
                                        }
                                    }

                                    // Agregar data-attributes para facilitar el filtrado y búsqueda con JavaScript
                                    $data_attrs = 'data-name="' . esc_attr(strtolower($student['display_name'])) . '" ';
                                    $data_attrs .= 'data-email="' . esc_attr(strtolower($student['email'])) . '" ';
                                    $data_attrs .= 'data-pending="' . ($pending_classes > 0 ? 'true' : 'false') . '" ';
                                    $data_attrs .= 'data-today="' . ($today_classes > 0 ? 'true' : 'false') . '" ';
                                    $data_attrs .= 'data-inactive="' . ($recent_classes == 0 ? 'true' : 'false') . '" ';
                                    $data_attrs .= 'data-license="' . esc_attr(strtolower($student['license_type'])) . '"';
                                ?>
                                    <div class="list-group-item list-group-item-action p-3 student-item" <?= $data_attrs ?>>
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="<?= esc_url(DSB_User_Service::get_avatar_url($student['id'])); ?>" alt="Avatar" class="rounded-circle me-3" width="48">
                                            <div>
                                                <h6 class="mb-0"><?= esc_html($student['display_name']) ?></h6>
                                                <p class="text-muted small mb-0"><?= esc_html($student['email']) ?></p>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <?php if ($student['license_type']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="bi bi-card-checklist me-1"></i><?= esc_html($student['license_type']) ?>
                                                </span>
                                            <?php endif; ?>

                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-calendar-check me-1"></i><?= $student_classes ?> clases
                                            </span>

                                            <?php if ($pending_classes > 0): ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-hourglass me-1"></i><?= $pending_classes ?> pendientes
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($today_classes > 0): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-clock me-1"></i>Hoy
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button class="btn btn-sm btn-outline-primary ver-alumno" data-id="<?= $student['id'] ?>" data-bs-dismiss="offcanvas">
                                                <i class="bi bi-eye me-1"></i> Ver detalles
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Paginación simple para manejar muchos alumnos -->
                            <?php if (count($teacher['students']) > 15): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3" id="studentPagination">
                                    <button class="btn btn-sm btn-outline-secondary" id="prevPage" disabled>
                                        <i class="bi bi-chevron-left"></i> Anterior
                                    </button>
                                    <span id="paginationInfo">Página <span id="currentPage">1</span> de <span id="totalPages"><?= ceil(count($teacher['students']) / 15) ?></span></span>
                                    <button class="btn btn-sm btn-outline-secondary" id="nextPage">
                                        Siguiente <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Calendario de clases -->
        <section id="calendario" class="mb-5">
            <div class="calendar-header d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h3 class="mb-0 me-3"><i class="bi bi-calendar-check me-2"></i>Calendario de Clases</h3>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Leyenda de colores mejorada -->
                <div class="calendar-legend p-2 px-3 border-bottom d-flex flex-wrap justify-content-center gap-3">
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
                    <div id="calendar" class="calendar-responsive"></div>
                </div>

                <!-- Controles adicionales y ayuda -->
                <div class="card-footer bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center">
                    <div class="small text-muted order-2 order-md-1 mt-2 mt-md-0">
                        <i class="bi bi-info-circle me-1"></i> Haz clic en una reserva para ver detalles
                    </div>
                    <div class="calendar-controls order-1 order-md-2 d-flex gap-2">
                        <div class="btn-group" role="group">
                            <button id="blockTimeBtn" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Bloquear horario">
                                <i class="bi bi-lock-fill"></i> Bloquear horario
                            </button>
                            <button id="createBookingBtn" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Crear reserva">
                                <i class="bi bi-calendar-plus"></i> Crear reserva
                            </button>
                        </div>
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
                        <div class="d-flex justify-content-center ms-auto text-center">
                            <div class="bg-success text-white rounded-3 px-3 py-2 w-25">
                                <span class="saldo-actual fs-6 fw-bold" id="modal-student-saldo"></span>
                                <div><small>Saldo</small></div>
                            </div>
                        </div>

                        <!-- Botones de contacto -->
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a id="modal-student-call-btn" href="#" class="btn btn-success btn-sm">
                                <i class="bi bi-telephone-fill me-1"></i>Llamar
                            </a>
                            <a id="modal-student-whatsapp-btn" href="#" target="_blank" class="btn btn-success btn-sm">
                                <i class="bi bi-whatsapp me-1"></i>WhatsApp
                            </a>
                        </div>
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
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3 text-primary">
                                    <i class="bi bi-calendar-check fs-4"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Clases aceptadas</small>
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

    <!-- Modal para bloquear horario -->
    <div class="modal fade" id="blockTimeModal" tabindex="-1" aria-labelledby="blockTimeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="blockTimeModalLabel">Bloquear horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="blockTimeForm">
                        <div class="mb-3">
                            <label for="block_start_date" class="form-label">Fecha inicio</label>
                            <input type="date" class="form-control" id="block_start_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="block_start_time" class="form-label">Hora inicio</label>
                            <input type="time" class="form-control" id="block_start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="block_end_date" class="form-label">Fecha fin</label>
                            <input type="date" class="form-control" id="block_end_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="block_end_time" class="form-label">Hora fin</label>
                            <input type="time" class="form-control" id="block_end_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="block_reason" class="form-label">Motivo (opcional)</label>
                            <input type="text" class="form-control" id="block_reason" placeholder="Indique el motivo del bloqueo">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="saveBlockBtn" class="btn btn-danger">Bloquear horario</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para crear reserva -->
    <div class="modal fade" id="createBookingModal" tabindex="-1" aria-labelledby="createBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createBookingModalLabel">Crear reserva para alumno</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="createBookingForm">
                        <div class="mb-3">
                            <label for="new_booking_date" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="new_booking_date" readonly>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="new_booking_start_time" class="form-label">Hora inicio</label>
                                <input type="time" class="form-control" id="new_booking_start_time" readonly>
                            </div>
                            <div class="col">
                                <label for="new_booking_end_time" class="form-label">Hora fin</label>
                                <input type="time" class="form-control" id="new_booking_end_time" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="student_select" class="form-label">Alumno</label>
                            <select class="form-select" id="student_select" required>
                                <option value="">Seleccione un alumno</option>
                                <?php foreach ($teacher['students'] as $student): ?>
                                    <option value="<?= esc_attr($student['id']) ?>"><?= esc_html($student['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="license_type" class="form-label">Permiso</label>
                            <input type="text" class="form-control" id="license_type" value="" disabled>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Esta reserva se creará con estado "Aceptada" automáticamente.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="saveBookingBtn" class="btn btn-primary">Crear reserva</button>
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
        // Datos del profesor y reservas
        const teacherData = <?= json_encode(dsb_get_teacher_data()); ?>;
        const bookingsData = <?= json_encode(dsb_get_teacher_bookings()); ?>;
        const DSB_CONFIG = <?= json_encode($js_config); ?>;
        const DSB_PUSHER = {
            serviceWorkerUrl: '<?= $plugin_url; ?>/service-worker.js',
            instanceId: '02609d94-0e91-4039-baf6-7d9d04b1fb6e'
        };
    </script>

    <!-- Nuestros scritps -->
    <script src="<?= $js_base_url; ?>pusher-init.js?ver=<?= DSB_VERSION ?>"></script>
    <script src="<?= $js_base_url; ?>profesor.js?ver=<?= DSB_VERSION ?>"></script>
</body>

</html>