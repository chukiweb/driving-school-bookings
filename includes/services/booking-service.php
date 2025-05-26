<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Booking_Service
{
    public static function create_booking($request)
    {
        // 1. Recoger y sanitizar parámetros del cuerpo
        $student_id = intval($request->get_param('alumno'));
        $teacher_id = intval($request->get_param('profesor'));
        $vehicle_id = intval($request->get_param('vehiculo'));
        $date       = sanitize_text_field($request->get_param('fecha'));
        $start_time = sanitize_text_field($request->get_param('hora'));
        $end_param  = $request->get_param('end_time');
        $status     = sanitize_text_field($request->get_param('status')) ? : DSB_Settings::get('default_booking_status');

        $today = date('Y-m-d');
        if ($date < $today) {
            return new WP_Error(
                'past_date',
                'No es posible reservar clases para fechas pasadas',
                ['status' => 400]
            );
        }

        // 2. Verificar tiempo mínimo de antelación (1 hora)
        $current_datetime = new DateTime();
        $class_datetime = new DateTime("{$date} {$start_time}");
        $time_diff_seconds = $class_datetime->getTimestamp() - $current_datetime->getTimestamp();
        $time_diff_hours = $time_diff_seconds / 3600;

        $min_booking_hours = 1; // Mínimo 1 hora de antelación

        if ($time_diff_hours < $min_booking_hours) {
            return new WP_Error(
                'insufficient_notice',
                sprintf('Debes reservar las clases con al menos %d hora(s) de antelación', $min_booking_hours),
                ['status' => 400]
            );
        }

        // Verificar límite de clases por fecha de la reserva
        $daily_limit = DSB_Settings::get('daily_limit');

        // Contar cuántas clases activas tiene el estudiante en la fecha de la clase
        $existing_bookings = get_posts([
            'post_type' => 'dsb_booking',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'student_id', 'value' => $student_id, 'compare' => '='],
                ['key' => 'date', 'value' => $date, 'compare' => '='],
                ['key' => 'status', 'value' => 'cancelled', 'compare' => '!=']
            ],
            'posts_per_page' => -1
        ]);

        if (count($existing_bookings) >= $daily_limit) {
            return new WP_Error(
                'daily_limit_exceeded',
                sprintf('No puedes reservar más de %d clase(s) para el día %s', $daily_limit, date('d/m/Y', strtotime($date))),
                ['status' => 400]
            );
        }

        // Verificar que no existan reservas para el mismo profesor en la misma fecha y hora
        $existing_teacher_bookings = get_posts([
            'post_type' => 'dsb_booking',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '='],
                ['key' => 'date', 'value' => $date, 'compare' => '='],
                ['key' => 'time', 'value' => $start_time, 'compare' => '='],
                ['key' => 'status', 'value' => 'cancelled', 'compare' => '!=']
            ],
            'posts_per_page' => -1
        ]);

        if (!empty($existing_teacher_bookings)) {
            return new WP_Error(
                'slot_not_available',
                sprintf(
                    'El horario seleccionado (%s %s) ya no está disponible. Por favor, seleccione otro horario.',
                    date('d/m/Y', strtotime($date)),
                    $start_time
                ),
                ['status' => 400]
            );
        }

        // 2. Verificar saldo de tokens del alumno
        $class_cost = DSB_Settings::get('class_cost');
        $student_tokens = intval(get_user_meta($student_id, 'class_points', true));

        // Si el estudiante no tiene suficientes tokens
        if ($student_tokens < $class_cost) {
            return new WP_Error(
                'insufficient_tokens',
                sprintf(
                    'No tienes suficientes créditos para esta reserva. Necesitas: %s, tienes: %s',
                    $class_cost,
                    $student_tokens
                ),
                ['status' => 400]
            );
        }

        // 3. Calcular hora de fin si no viene en el request
        if (! empty($end_param)) {
            $end_time = sanitize_text_field($end_param);
        } else {
            // Obtener la duración de clase desde la configuración del profesor
            $teacher_config = get_user_meta($teacher_id, 'dsb_clases_config', true);

            // Si el profesor tiene configuración de duración, usarla; si no, usar el valor global
            $class_duration = !empty($teacher_config['duracion'])
                ? intval($teacher_config['duracion'])
                : DSB_Settings::get('class_duration');

            $dt_inicio = new DateTime("{$date} {$start_time}");
            $dt_fin    = clone $dt_inicio;
            $dt_fin->modify('+' . $class_duration . ' minutes');
            $end_time = $dt_fin->format('H:i');
        }

        // 4. Preparar datos del post
        $post_data = [
            'post_type' => 'dsb_booking',
            'post_title' => sprintf('Reserva %s - %s | %s', $date, $start_time, $student_id),
            'post_status' => 'publish',
            'meta_input' => [
                'student_id' => $student_id,
                'teacher_id' => $teacher_id,
                'vehicle_id' => $vehicle_id,
                'date' => $date,
                'time' => $start_time,
                'end_time' => $end_time,
                'status' => $status,
                'cost' => $class_cost, // Guardamos el costo para referencia y posibles reembolsos
            ],
        ];

        // 5. Insertar el post y manejar errores
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // 6. Restar tokens al estudiante
        $new_balance = $student_tokens - $class_cost;
        update_user_meta($student_id, 'class_points', $new_balance);

        // 7. Devolver respuesta con ID y datos de la reserva
        return rest_ensure_response([
            'success'   => true,
            'id'        => $post_id,
            'date'      => $date,
            'startTime' => $start_time,
            'endTime'   => $end_time,
            'cost'      => $class_cost,
            'newBalance' => $new_balance,
            'status'    => $status
        ]);
    }

    public static function accept_booking($request)
    {
        $booking_id = intval($request->get_param('id'));

        if (!$booking_id || get_post_type($booking_id) !== 'dsb_booking') {
            return new WP_Error('invalid_booking', 'Reserva inválida', ['status' => 400]);
        }

        // Obtenemos el token de la cabecera de autorización
        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));

        // Validamos y decodificamos el token para obtener el usuario
        $decoded = DSB()->jwt->validate_token($token);

        if (!$decoded || !isset($decoded->user->id)) {
            return new WP_Error('unauthorized', 'Usuario no autenticado', ['status' => 401]);
        }

        // Extraemos el ID del usuario del token JWT decodificado
        $current_user_id = $decoded->user->id;

        // Verificamos que el usuario sea el profesor de la reserva
        $teacher_id = get_post_meta($booking_id, 'teacher_id', true);

        if (intval($current_user_id) !== intval($teacher_id)) {
            return new WP_Error('unauthorized', 'No tienes permiso para aceptar esta reserva', ['status' => 403]);
        }

        // Actualizar el estado de la reserva a "accepted"
        update_post_meta($booking_id, 'status', 'accepted');

        return rest_ensure_response([
            'message' => 'Reserva aceptada correctamente',
            'newStatus' => 'accepted'
        ]);
    }

    public static function cancel_booking($request)
    {
        $booking_id = intval($request->get_param('id'));

        if (!$booking_id || get_post_type($booking_id) !== 'dsb_booking') {
            return new WP_Error('invalid_booking', 'Reserva inválida', ['status' => 400]);
        }

        // Obtenemos el token de la cabecera de autorización
        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));

        // Validamos y decodificamos el token para obtener el usuario
        $decoded = DSB()->jwt->validate_token($token);

        if (!$decoded || !isset($decoded->user->id)) {
            return new WP_Error('unauthorized', 'Usuario no autenticado', ['status' => 401]);
        }

        // Extraemos el ID del usuario del token JWT decodificado
        $current_user_id = $decoded->user->id;

        // Verificamos que el usuario sea el dueño de la reserva o el profesor
        $student_id = get_post_meta($booking_id, 'student_id', true);
        $teacher_id = get_post_meta($booking_id, 'teacher_id', true);

        if (intval($current_user_id) !== intval($student_id) && intval($current_user_id) !== intval($teacher_id)) {
            return new WP_Error('unauthorized', 'No tienes permiso para cancelar esta reserva', ['status' => 403]);
        }

        // Obtener el estado actual de la reserva
        $booking_status = get_post_meta($booking_id, 'status', true);
        // Verificar si la cancelación es con tiempo suficiente para reembolso
        $booking_date = get_post_meta($booking_id, 'date', true);
        $booking_time = get_post_meta($booking_id, 'time', true);
        $class_datetime = new DateTime($booking_date . ' ' . $booking_time);
        $now = new DateTime();

        $hours_diff = ($class_datetime->getTimestamp() - $now->getTimestamp()) / 3600;
        $cancel_hours_limit = DSB_Settings::get('cancelation_time_hours');

        // Reembolsar tokens si cancela con tiempo
        $refund = false;
        if ($hours_diff >= $cancel_hours_limit && $booking_status !== 'accepted') {
            $cost = get_post_meta($booking_id, 'cost', true);
            if ($cost) {
                $current_tokens = intval(get_user_meta($student_id, 'class_points', true));
                update_user_meta($student_id, 'class_points', $current_tokens + $cost);
                $refund = true;
            }
        }

        // Actualizar el estado de la reserva
        update_post_meta($booking_id, 'status', 'cancelled');

        return rest_ensure_response([
            'message' => 'Reserva cancelada correctamente',
            'refund' => $refund,
            'refund_amount' => $refund ? floatval(get_post_meta($booking_id, 'cost', true)) : 0,
            'newBalance' => floatval(get_user_meta($student_id, 'class_points', true)) // Añadir esto
        ]);
    }

    /**
     * Crear un nuevo evento para bloquear el horario de un profesor y que no se pueda reservar
     */
    public static function teachers_block_time($request)
    {
        $teacher_id = intval($request->get_param('teacher_id'));
        $date       = sanitize_text_field($request->get_param('date'));
        $start_time = sanitize_text_field($request->get_param('start_time'));
        $end_time   = sanitize_text_field($request->get_param('end_time'));
        $reason     = sanitize_text_field($request->get_param('reason')) ? : 'Bloqueo manual';

        // Comprobar si el profesor existe
        if (!get_user_by('ID', $teacher_id)) {
            return new WP_Error('invalid_teacher', 'Profesor no encontrado', ['status' => 404]);
        }

        $today = date('Y-m-d');
        if ($date < $today) {
            return new WP_Error(
                'past_date',
                'No es posible bloquear para fechas pasadas',
                ['status' => 400]
            );
        }

        // Verificar que no existan reservas para el profesor en la misma fecha y durante la franja horaria
        $existing_teacher_bookings = get_posts([
            'post_type' => 'dsb_booking',
            'meta_query' => [
            'relation' => 'AND',
            ['key' => 'teacher_id', 'value' => $teacher_id, 'compare' => '='],
            ['key' => 'date', 'value' => $date, 'compare' => '='],
            ['key' => 'status', 'value' => 'cancelled', 'compare' => '!=']
            ],
            'posts_per_page' => -1
        ]);

        // Comprobar si alguna reserva existente se solapa con el nuevo bloqueo
        foreach ($existing_teacher_bookings as $booking) {
            $booking_start = get_post_meta($booking->ID, 'time', true);
            $booking_end = get_post_meta($booking->ID, 'end_time', true);
            
            // Si el inicio del nuevo bloqueo está dentro de una reserva existente
            // O si el fin del nuevo bloqueo está dentro de una reserva existente
            // O si el nuevo bloqueo contiene completamente una reserva existente
            if (
            ($start_time >= $booking_start && $start_time < $booking_end) ||
            ($end_time > $booking_start && $end_time <= $booking_end) ||
            ($start_time <= $booking_start && $end_time >= $booking_end)
            ) {
            return new WP_Error(
                'slot_not_available',
                sprintf(
                'El horario seleccionado (%s %s-%s) no puede ser bloqueado porque se solapa con reservas existentes.',
                date('d/m/Y', strtotime($date)),
                $start_time,
                $end_time
                ),
                ['status' => 400]
            );
            }
        }

        // Crear el evento
        $event_id = wp_insert_post([
            'post_type'   => 'dsb_booking',
            'post_status' => 'publish',
            'meta_input'  => [
                'teacher_id' => $teacher_id,
                'date'       => $date,
                'time'       => $start_time,
                'end_time'   => $end_time,
                'reason'     => $reason,
                'status'     => 'blocked',
            ],
        ]);

        if (is_wp_error($event_id)) {
            return new WP_Error('insert_failed', 'Error al crear el evento', ['status' => 500]);
        }

        return rest_ensure_response([
            'message' => 'Horario bloqueado correctamente',
            'id' => $event_id,
        ]);
    }
}
