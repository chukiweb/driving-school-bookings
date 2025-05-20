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
        $moto_id = get_user_meta($teacher_id, 'assigned_motorcycle', true)? : null;
        
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
                        'id' => $moto_id ? : null,
                        'name' => get_the_title($moto_id) ? : null,
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

    // public static function get_professor_availability($teacher_id)
    // {
    //     $professor_id = $teacher_id;
    //     if (!$professor_id) {
    //         return new WP_REST_Response(['success' => false, 'message' => 'No autenticado'], 401);
    //     }

    //     $horarios = get_user_meta($professor_id, 'available_schedules', true);
    //     $horarios = $horarios ? json_decode($horarios, true) : [];

    //     return new WP_REST_Response(['success' => true, 'data' => $horarios], 200);
    // }

    // public static function save_professor_availability(WP_REST_Request $request, $teacher_id)
    // {
    //     $professor_id = $teacher_id;
    //     if (!$professor_id) {
    //         return new WP_REST_Response(['success' => false, 'message' => 'No autenticado'], 401);
    //     }

    //     $data = json_decode($request->get_body(), true);
    //     update_user_meta($professor_id, 'available_schedules', json_encode($data['events']));

    //     return new WP_REST_Response(['success' => true, 'message' => 'Horarios guardados correctamente'], 200);
    // }

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

        if (!user_can($user_id, 'teacher')) {
            return new WP_Error('invalid_user', 'El usuario no es un profesor válido', ['status' => 403]);
        }

        $params = json_decode($request->get_body(), true);

        $dias = array_map('sanitize_text_field', $params['dias'] ?? []);
        $hora_inicio = sanitize_text_field($params['hora_inicio'] ?? '');
        $hora_fin = sanitize_text_field($params['hora_fin'] ?? '');
        $duracion = intval($params['duracion'] ?? 0);

        if (empty($dias) || empty($hora_inicio) || empty($hora_fin) || $duracion <= 0) {
            return new WP_Error('invalid_data', 'Faltan datos obligatorios', ['status' => 400]);
        }

        $config = [
            'dias' => $dias,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'duracion' => $duracion,
        ];

        update_user_meta($user_id, 'dsb_clases_config', $config);

        return rest_ensure_response(['success' => true, 'message' => 'Configuración del profesor actualizada']);
    }
}
