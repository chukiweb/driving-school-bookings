<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Calendar_Service
{
 // Obtener disponibilidad del profesor
 public static function get_teacher_calendar($teacher_id) {
    $teacher_id = intval($teacher_id);
    $events = get_posts([
        'post_type' => 'booking',
        'meta_query' => [['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '=']]
    ]);

    $result = [];
    foreach ($events as $event) {
        $result[] = [
            'id' => $event->ID,
            'start_time' => get_post_meta($event->ID, 'start_time', true),
            'end_time' => get_post_meta($event->ID, 'end_time', true)
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

