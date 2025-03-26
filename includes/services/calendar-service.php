<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Calendar_Service
{
 // Obtener disponibilidad del profesor
 public static function teacher_calendar($teacher_id): array {
    $teacher_id = intval($teacher_id);

    $events = get_posts([
        'post_type' => 'booking',
        'meta_query' => [
            ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '=']
        ],
        'posts_per_page' => -1
    ]);

    $result = [];

    foreach ($events as $event) {
        $start = get_post_meta($event->ID, 'start_time', true);
        $end = get_post_meta($event->ID, 'end_time', true);

        $result[] = [
            'id' => $event->ID,
            'title' => 'Reservado', // puedes personalizar esto con el nombre del estudiante
            'start' => $start,
            'end' => $end,
            'backgroundColor' => '#007bff',
            'borderColor' => '#007bff'
        ];
    }

    return $result;
}


// Agregar disponibilidad
public static function update_teacher_calendar($request) {
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
public static function delete_booking($booking_id) {
    $booking_id = intval($booking_id);
    wp_delete_post($booking_id, true);
    return ['status' => 'deleted', 'booking_id' => $booking_id];
}


}

