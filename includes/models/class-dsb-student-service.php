<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Student_Service {

    public static function get_student($student_id) {
        if (!$student_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID de estudiante inválido',
            ], 400);
        }

        $user = get_userdata($student_id);
        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Estudiante no encontrado',
            ], 404);
        }

        $avatar_url = get_avatar_url($student_id);
        $bookings = self::get_student_bookings($student_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Datos del estudiante obtenidos correctamente',
            'data' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => $avatar_url,
                'bookings' => $bookings
            ]
        ], 200);
    }

    private static function get_student_bookings($student_id) {
        $args = [
            'post_type'  => 'reserva',
            'meta_query' => [
                [
                    'key'   => 'student_id',
                    'value' => $student_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];
    
        $query = new WP_Query($args);
        $bookings = [];
    
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bookings[] = [
                    'id'        => get_the_ID(),
                    'teacher'   => get_user_by('id', get_post_meta(get_the_ID(), 'teacher_id', true))->display_name,
                    'vehicle'   => get_post(get_post_meta(get_the_ID(), 'vehicle_id', true))->post_title,
                    'date'      => get_post_meta(get_the_ID(), 'date', true),
                    'time'      => get_post_meta(get_the_ID(), 'time', true),
                    'status'    => get_post_meta(get_the_ID(), 'status', true),
                ];
            }
        }
    
        wp_reset_postdata();
    
        return rest_ensure_response([
            'success' => true,
            'data'    => $bookings
        ]);
    }
}

