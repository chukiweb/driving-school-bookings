<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Teacher_Service {

    public static function get_teacher($teacher_id) {
        if (!$teacher_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID de profesor inválido',
                'errors' => ['No se proporcionó un ID válido']
            ], 400);
        }

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
                'display_name' => $user->display_name,
                'avatar' => $avatar_url,
                'students' => $students
            ]
        ], 200);
    }

    private static function get_assigned_students($teacher_id) {
        $args = [
            'role' => 'student',
            'meta_query' => [
                [
                    'key'   => 'assigned_teacher',
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
                    'display_name' => $student->display_name
                ];
            }
        }

        return $students_list;
    }
}
