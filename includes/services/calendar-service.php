<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Calendar_Service
{

    // Obtener disponibilidad del profesor
    public static function get_teacher_calendar($teacher_id): array {
        $teacher_id = intval($teacher_id);
    
        $events = get_posts([
            'post_type' => 'reserva',
            'meta_query' => [
                ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '=']
            ],
            'posts_per_page' => -1
        ]);
    
        $result = [];
    
        foreach ($events as $event) {
            $date = get_post_meta($event->ID, 'date', true);
            $start_time = get_post_meta($event->ID, 'time', true);
            $end_time = get_post_meta($event->ID, 'end_time', true);
            $student_id = get_post_meta($event->ID, 'student_id', true);
    
            $student = get_user_by('ID', $student_id);
            $student_name = $student ? $student->first_name . ' ' . $student->last_name : 'Reservado';
    
            // 🎨 Color basado en ID de estudiante
            $color = self::generate_color_for_id($student_id);
    
            $result[] = [
                'id' => $event->ID,
                'title' => $student_name,
                'start' => $date . 'T' . $start_time,
                'end' => $date . 'T' . $end_time,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#000',
            ];
        }
    
        return $result;
    }
    

    // Agregar disponibilidad
    public static function update_teacher_calendar($request)
    {
        $teacher_id = intval($request['id']);
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $end_time = sanitize_text_field($request->get_param('end_time'));

        $booking_id = wp_insert_post([
            'post_type' => 'booking',
            'post_status' => 'publish',
            'meta_input' => [
                'teacher_id' => $teacher_id,
                'start_time' => $start_time,
                'end_time' => $end_time
            ]
        ]);

        return ['status' => 'success', 'booking_id' => $booking_id];
    }

    // Eliminar un horario
    public static function delete_booking($booking_id)
    {
        $booking_id = intval($booking_id);
        wp_delete_post($booking_id, true);
        return ['status' => 'deleted', 'booking_id' => $booking_id];
    }

    private static function generate_color_for_id($id) {
        $hash = md5($id);
        return '#' . substr($hash, 0, 6); // devuelve un color hexadecimal
    }
    
}

