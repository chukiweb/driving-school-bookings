<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Calendar_Service
{
    /**
     * Obtener disponibilidad del profesor en formato ISO para FullCalendar
     */
    public static function get_teacher_calendar(int $teacher_id): array
    {
        $teacher_id = intval($teacher_id);

        $events = get_posts([
            'post_type'      => 'dsb_booking',
            'meta_query'     => [
                ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '=']
            ],
            'posts_per_page' => -1,
        ]);

        $result = [];
        foreach ($events as $event) {
            $date       = get_post_meta($event->ID, 'date',      true);
            $start_time = get_post_meta($event->ID, 'time',      true);
            $end_time   = get_post_meta($event->ID, 'end_time',  true);
            $student_id = get_post_meta($event->ID, 'student_id', true);
            $status     = get_post_meta($event->ID, 'status',    true);

            // Si está cancelada, no la incluimos en los eventos del calendario
            if ($status === 'cancelled') {
                continue;
            }

            // Si es un bloque de tiempo bloqueado, usamos formato especial
            if ($status === 'blocked') {
                $reason = get_post_meta($event->ID, 'reason', true) ?: 'Horario no disponible';
                $result[] = [
                    'id'              => $event->ID,
                    'title'           => $reason,
                    'start'           => "{$date}T{$start_time}",
                    'end'             => "{$date}T{$end_time}",
                    'backgroundColor' => '#dc3545', // Rojo para bloqueados
                    'borderColor'     => '#b02a37',
                    'textColor'       => '#fff',
                    'className'       => 'blocked-event',
                    'extendedProps'   => [
                        'status' => 'blocked',
                        'reason' => $reason
                    ]
                ];
                continue;
            }

            // Para reservas normales (pending o accepted)
            $student = get_user_by('ID', $student_id);
            $student_name = $student
                ? "{$student->first_name} {$student->last_name}"
                : 'Reservado';

            // Color según el estado
            if ($status === 'pending') {
                $color = '#ffc107'; // Amarillo para pendientes
            } else {
                $color = '#28a745'; // Verde para aceptadas
            }

            $result[] = [
                'id'              => $event->ID,
                'title'           => $student_name,
                'start'           => "{$date}T{$start_time}",
                'end'             => "{$date}T{$end_time}",
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#000',
                'extendedProps'   => [
                    'status'      => $status,
                    'studentId'   => $student_id,
                    'studentName' => $student_name
                ]
            ];
        }

        return $result;
    }

    public static function get_student_calendar($student_id): array
    {
        $student_id = intval($student_id);

        $events = get_posts([
            'post_type' => 'dsb_booking',
            'meta_query' => [
                ['key' => 'student_id', 'value' => $student_id, 'compare' => '=']
            ],
            'posts_per_page' => -1
        ]);

        $result = [];

        foreach ($events as $event) {
            $date = get_post_meta($event->ID, 'date', true);
            $start_time = get_post_meta($event->ID, 'time', true);
            $end_time = get_post_meta($event->ID, 'end_time', true);
            $teacher_id = get_post_meta($event->ID, 'teacher_id', true);

            $teacher = get_user_by('ID', $teacher_id);
            $teacher_name = $teacher ? $teacher->first_name . ' ' . $teacher->last_name : 'Reservado';
            $vehicle = get_user_meta($teacher->ID, 'assigned_vehicle', true);
            $vehicle_name = get_the_title($vehicle);

            $result[] = [
                'id' => $event->ID,
                'teacher_name' => $teacher_name,
                'date' => $date,
                'start' => $start_time,
                'end' => $end_time,
                'status' => get_post_meta($event->ID, 'status', true),
                'vehicle' => $vehicle_name,
            ];
        }

        return $result;
    }

    /**
     * Obtener calendario completo del estudiante en formato ISO para FullCalendar
     */
    public static function get_student_full_calendar_format(int $student_id): array
    {
        $student_id = intval($student_id);

        $events = get_posts([
            'post_type'      => 'dsb_booking',
            'meta_query'     => [
                ['key' => 'student_id', 'value' => $student_id, 'compare' => '=']
            ],
            'posts_per_page' => -1,
        ]);

        $result = [];
        foreach ($events as $event) {
            $date       = get_post_meta($event->ID, 'date',      true);
            $start_time = get_post_meta($event->ID, 'time',      true);
            $end_time   = get_post_meta($event->ID, 'end_time',  true);
            $teacher_id = get_post_meta($event->ID, 'teacher_id', true);
            $status     = get_post_meta($event->ID, 'status', true);

            // Si está cancelada, no la incluimos en los eventos del calendario
            if ($status === 'cancelled') {
                continue;
            }

            $teacher = get_user_by('ID', $teacher_id);
            $teacher_name = $teacher
                ? "{$teacher->first_name} {$teacher->last_name}"
                : 'Profesor no asignado';

            // Color según estado
            $color = $status === 'pending' ? '#ffc107' : '#28a745'; // amarillo para pending, verde para activas

            $result[] = [
                'id'              => $event->ID,
                'title'           => "Clase con {$teacher_name}",
                'start'           => "{$date}T{$start_time}",
                'end'             => "{$date}T{$end_time}",
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#000',
                'status'          => $status,
            ];
        }

        return $result;
    }

    /**
     * Insertar un nuevo bloque de disponibilidad para el profesor
     */
    public static function update_teacher_calendar($request)
    {
        $teacher_id = intval($request['id']);
        $time       = sanitize_text_field($request->get_param('start_time'));
        $end_time   = sanitize_text_field($request->get_param('end_time'));

        $booking_id = wp_insert_post([
            'post_type'   => 'dsb_booking',
            'post_status' => 'publish',
            'meta_input'  => [
                'teacher_id' => $teacher_id,
                'time'       => $time,
                'end_time'   => $end_time,
            ],
        ]);

        return [
            'status'     => 'success',
            'booking_id' => $booking_id,
        ];
    }

    /**
     * Eliminar una reserva por ID
     */
    public static function delete_booking(int $booking_id)
    {
        wp_delete_post($booking_id, true);
        return [
            'status'     => 'deleted',
            'booking_id' => $booking_id,
        ];
    }

    /**
     * Genera un color hex a partir de un ID
     */
    private static function generate_color_for_id(int $id): string
    {
        $hash = md5((string) $id);
        return '#' . substr($hash, 0, 6);
    }
}
