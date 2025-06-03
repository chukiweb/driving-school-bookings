<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Teacher_Service
{

    public static function get_teacher($teacher_id)
    {
        if (!$teacher_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID de profesor inválido',
                'errors' => ['No se proporcionó un ID válido']
            ], 400);
        }
        // Obtener vehículos
        $coche_id = get_user_meta($teacher_id, 'assigned_vehicle', true);
        $moto_id = get_user_meta($teacher_id, 'assigned_motorcycle', true) ?: null;

        $first_name = get_user_meta($teacher_id, 'first_name', true);
        $last_name = get_user_meta($teacher_id, 'last_name', true);

        $user = get_userdata($teacher_id);
        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Profesor no encontrado',
                'errors' => ['El ID no corresponde a un profesor registrado']
            ], 404);
        }

        // Obtener la imagen de perfil
        $avatar_url = get_avatar_url($teacher_id);

        // Obtener alumnos asignados
        $students = self::get_assigned_students($teacher_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Datos del profesor obtenidos correctamente',
            'data' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $user->display_name,
                'avatar' => $avatar_url,
                'students' => $students,
                'vehicle' => [
                    'a' => [
                        'id' => $moto_id ?: null,
                        'name' => get_the_title($moto_id) ?: null,
                    ],
                    'b' => [
                        'id' => $coche_id,
                        'name' => get_the_title($coche_id),
                    ],
                ],
            ]
        ], 200);
    }

    private static function get_assigned_students($teacher_id)
    {
        $args = [
            'role' => 'student',
            'meta_query' => [
                [
                    'key' => 'assigned_teacher',
                    'value' => $teacher_id,
                    'compare' => '='
                ]
            ]
        ];

        $students_query = new WP_User_Query($args);
        $students_list = [];

        if (!empty($students_query->get_results())) {
            foreach ($students_query->get_results() as $student) {
                $students_list[] = [
                    'id' => $student->ID,
                    'username' => $student->user_login,
                    'email' => $student->user_email,
                    'display_name' => $student->display_name,
                    'license_type' => get_user_meta($student->ID, 'license_type', true),
                ];
            }
        }

        return $students_list;
    }

    public static function update_teacher($teacher_id)
    {
        $user_id = (int) $teacher_id;

        if (!user_can($user_id, 'teacher')) {
            return new WP_Error('invalid_user', 'El usuario no es un profesor válido', ['status' => 403]);
        }

        $params = $_POST['data'] ?? [];

        $first_name = sanitize_text_field($params['first_name'] ?? '');
        $last_name = sanitize_text_field($params['last_name'] ?? '');
        $email = sanitize_email($params['email'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            return new WP_Error('invalid_data', 'Faltan datos obligatorios', ['status' => 400]);
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $email,
        ]);

        return rest_ensure_response(['success' => true, 'message' => 'Datos del profesor actualizados']);
    }

    public static function update_teacher_config($request, $teacher_id)
    {
        $user_id = (int) $teacher_id;

        if (!$user_id) {
            return new WP_Error('invalid_user', 'ID de profesor inválido', ['status' => 400]);
        }

        $user = get_userdata($user_id);
        if (!$user || !in_array('teacher', $user->roles)) {
            return new WP_Error('invalid_user', 'El usuario no es un profesor válido', ['status' => 403]);
        }

        $body = $request->get_body();
        $params = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Datos JSON inválidos', ['status' => 400]);
        }

        // Validar y sanitizar datos básicos
        $dias = array_map('sanitize_text_field', $params['dias'] ?? []);
        $hora_inicio = sanitize_text_field($params['hora_inicio'] ?? '');
        $hora_fin = sanitize_text_field($params['hora_fin'] ?? '');
        $duracion = intval($params['duracion'] ?? 0);

        // Procesar descansos
        $descansos = [];
        if (isset($params['descansos']) && is_array($params['descansos'])) {
            foreach ($params['descansos'] as $descanso) {
                if (
                    isset($descanso['inicio']) && isset($descanso['fin']) &&
                    !empty($descanso['inicio']) && !empty($descanso['fin'])
                ) {

                    // Validar formato de hora
                    $inicio = sanitize_text_field($descanso['inicio']);
                    $fin = sanitize_text_field($descanso['fin']);

                    if (
                        preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $inicio) &&
                        preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $fin)
                    ) {

                        // Verificar que la hora de fin sea posterior a la de inicio
                        if (strtotime($fin) > strtotime($inicio)) {
                            $descansos[] = [
                                'inicio' => $inicio,
                                'fin' => $fin
                            ];
                        }
                    }
                }
            }
        }

        // Validar datos obligatorios
        if (empty($dias) || empty($hora_inicio) || empty($hora_fin) || $duracion <= 0) {
            return new WP_Error('invalid_data', 'Faltan datos obligatorios para guardar la configuración', ['status' => 400]);
        }

        // Validar que la hora de fin sea posterior a la de inicio
        if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
            return new WP_Error('invalid_time', 'La hora de fin debe ser posterior a la hora de inicio', ['status' => 400]);
        }

        $config = [
            'dias' => $dias,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'duracion' => $duracion,
            'descansos' => $descansos,
            'updated_at' => current_time('mysql')
        ];

        // Guardar configuración
        $result = update_user_meta($user_id, 'dsb_clases_config', $config);

        if ($result === false) {
            return new WP_Error('save_error', 'Error al guardar la configuración', ['status' => 500]);
        }

        // Log para depuración
        error_log('Configuración guardada para profesor ID ' . $user_id . ': ' . print_r($config, true));

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Configuración del profesor actualizada correctamente',
            'data' => $config
        ], 200);
    }

    public static function get_teacher_stats($teacher_id, $period = 'current')
    {
        if (!$teacher_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID de profesor inválido',
            ], 400);
        }

        $user = get_userdata($teacher_id);
        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Profesor no encontrado',
            ], 404);
        }

        // Determinar las fechas según el período
        $today = new DateTime('now');
        $start_date = new DateTime('now');
        $end_date = new DateTime('now');

        switch ($period) {
            case 'previous':
                $start_date->modify('first day of last month');
                $end_date->modify('last day of last month');
                break;
            case 'year':
                $start_date->modify('first day of January ' . $today->format('Y'));
                $end_date->modify('last day of December ' . $today->format('Y'));
                break;
            case 'current':
            default:
                $start_date->modify('first day of this month');
                $end_date->modify('last day of this month');
                break;
        }

        $args = [
            'post_type' => 'dsb_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'teacher_id',
                    'value' => $teacher_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'date',
                    'value' => [
                        $start_date->format('Y-m-d'),
                        $end_date->format('Y-m-d'),
                    ],
                    'type' => 'DATE',
                    'compare' => 'BETWEEN',
                ],
            ],
        ];

        $bookings_query = new WP_Query($args);
        $bookings = [];
        $total_count = 0;
        $completed_count = 0;
        $canceled_count = 0;

        // Preparar array para contar clases por día
        $days = [];
        $current = clone $start_date;
        while ($current <= $end_date) {
            $days[$current->format('Y-m-d')] = [
                'date' => $current->format('d/m'),
                'count' => 0,
            ];
            $current->modify('+1 day');
        }

        if ($bookings_query->have_posts()) {
            while ($bookings_query->have_posts()) {
                $bookings_query->the_post();
                $booking_id = get_the_ID();

                $status = get_post_meta($booking_id, 'status', true);
                $date = get_post_meta($booking_id, 'date', true);

                // Contar por estado
                $total_count++;
                if ($status === 'completed') {
                    $completed_count++;

                    // Añadir al conteo por día
                    if (isset($days[$date])) {
                        $days[$date]['count']++;
                    }
                } elseif ($status === 'cancelled') {
                    $canceled_count++;
                }
            }
        }

        wp_reset_postdata();

        // Convertir el array asociativo de días a un array indexado para el JSON
        $days_array = [];
        foreach ($days as $day) {
            $days_array[] = $day;
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'total' => $total_count,
                'completed' => $completed_count,
                'canceled' => $canceled_count,
                'period' => $period,
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
                'days' => $days_array,
            ]
        ], 200);
    }
}
