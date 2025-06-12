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
        $end_time   = $request->get_param('end_time');
        $status     = sanitize_text_field($request->get_param('status')) ?: DSB_Settings::get('default_booking_status');

        $today = date('Y-m-d');
        if ($date < $today) {
            return new WP_Error(
                'past_date',
                'No es posible reservar clases para fechas pasadas',
                ['status' => 400]
            );
        }

        // 1.5. NUEVA VALIDACIÓN: Verificar antelación mínima y máxima
        $antelacion_validation = self::validate_booking_antelacion($date, $start_time);
        if (is_wp_error($antelacion_validation)) {
            return $antelacion_validation;
        }

        // 2. Obtener configuración del profesor y duración esperada
        $teacher_config = get_user_meta($teacher_id, 'dsb_clases_config', true);
        $expected_duration = !empty($teacher_config['duracion'])
            ? intval($teacher_config['duracion'])
            : DSB_Settings::get('class_duration');

        // 3. Calcular hora de fin ANTES de las validaciones
        if (! empty($end_time)) {
            $end_time = sanitize_text_field($end_time);

            // NUEVA VALIDACIÓN: Verificar que la duración coincida con la configuración del profesor
            $start_timestamp = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            $actual_duration = ($end_timestamp - $start_timestamp) / 60; // Duración en minutos

            if ($actual_duration != $expected_duration) {
                return new WP_Error(
                    'invalid_duration',
                    sprintf(
                        'La duración de la clase debe ser de %d minutos. Duración recibida: %d minutos.',
                        $expected_duration,
                        $actual_duration
                    ),
                    ['status' => 400]
                );
            }
        } else {
            // Calcular end_time basado en la configuración del profesor
            $dt_inicio = new DateTime("{$date} {$start_time}");
            $dt_fin    = clone $dt_inicio;
            $dt_fin->modify('+' . $expected_duration . ' minutes');
            $end_time = $dt_fin->format('H:i');
        }

        // 4. NUEVA VALIDACIÓN: Verificar que la hora de inicio sea válida según la configuración del profesor
        if (!empty($teacher_config)) {
            $hora_inicio_profesor = $teacher_config['hora_inicio'] ?? '08:00';
            $hora_fin_profesor = $teacher_config['hora_fin'] ?? '20:00';

            if ($start_time < $hora_inicio_profesor || $start_time >= $hora_fin_profesor) {
                return new WP_Error(
                    'invalid_time_range',
                    sprintf(
                        'La hora seleccionada (%s) está fuera del horario de trabajo del profesor (%s - %s)',
                        $start_time,
                        $hora_inicio_profesor,
                        $hora_fin_profesor
                    ),
                    ['status' => 400]
                );
            }

            if ($end_time > $hora_fin_profesor) {
                return new WP_Error(
                    'class_exceeds_working_hours',
                    sprintf(
                        'La clase terminaría fuera del horario de trabajo del profesor. Hora de fin: %s, Límite: %s',
                        $end_time,
                        $hora_fin_profesor
                    ),
                    ['status' => 400]
                );
            }
        }

        // 4.5. NUEVA VALIDACIÓN: Verificar que la reserva coincida con un slot válido
        $slot_validation = self::validate_booking_slot($teacher_id, $date, $start_time, $end_time);
        if (is_wp_error($slot_validation)) {
            return $slot_validation;
        }

        // 5. Validar horarios de descanso del profesor
        $is_valid = DSB_Booking_Service::validate_booking_time($teacher_id, $date, $start_time, $end_time);

        if (is_wp_error($is_valid)) {
            return $is_valid;
        }

        // 4. Verificar tiempo mínimo de antelación (1 hora)
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

        // 5. Verificar límite de clases por fecha de la reserva
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

        // 6. NUEVA VALIDACIÓN: Verificar solapamientos con reservas del profesor
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

        // Verificar solapamientos
        foreach ($existing_teacher_bookings as $booking) {
            $booking_start = get_post_meta($booking->ID, 'time', true);
            $booking_end = get_post_meta($booking->ID, 'end_time', true);

            // Verificar si hay solapamiento
            if (self::time_ranges_overlap($start_time, $end_time, $booking_start, $booking_end)) {
                return new WP_Error(
                    'slot_not_available',
                    sprintf(
                        'El horario seleccionado (%s %s-%s) se solapa con una reserva existente (%s-%s). Por favor, seleccione otro horario.',
                        date('d/m/Y', strtotime($date)),
                        $start_time,
                        $end_time,
                        $booking_start,
                        $booking_end
                    ),
                    ['status' => 400]
                );
            }
        }

        // 7. NUEVA VALIDACIÓN: Verificar solapamientos con reservas del estudiante
        foreach ($existing_bookings as $booking) {
            $booking_start = get_post_meta($booking->ID, 'time', true);
            $booking_end = get_post_meta($booking->ID, 'end_time', true);

            // Verificar si hay solapamiento
            if (self::time_ranges_overlap($start_time, $end_time, $booking_start, $booking_end)) {
                return new WP_Error(
                    'student_slot_conflict',
                    sprintf(
                        'Ya tienes una clase reservada en un horario que se solapa (%s-%s) para el día %s. No puedes tener clases simultáneas.',
                        $booking_start,
                        $booking_end,
                        date('d/m/Y', strtotime($date))
                    ),
                    ['status' => 400]
                );
            }
        }

        // 8. Verificar saldo de tokens del alumno
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

        // 9. Preparar datos del post
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

        // 10. Insertar el post y manejar errores
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // 11. Restar tokens al estudiante
        $new_balance = $student_tokens - $class_cost;
        update_user_meta($student_id, 'class_points', $new_balance);

        // 12. Disparar acción de reserva creada
        do_action('dsb_booking_created', $post_id);

        // 13. Devolver respuesta con ID y datos de la reserva
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

    /**
     * Verificar si dos rangos de tiempo se solapan
     */
    private static function time_ranges_overlap($start1, $end1, $start2, $end2)
    {
        // Convertir strings de tiempo a timestamps para comparación
        $start1_ts = strtotime($start1);
        $end1_ts = strtotime($end1);
        $start2_ts = strtotime($start2);
        $end2_ts = strtotime($end2);

        // Dos rangos se solapan si:
        // - El inicio del rango 1 está antes del fin del rango 2 Y
        // - El fin del rango 1 está después del inicio del rango 2
        return ($start1_ts < $end2_ts && $end1_ts > $start2_ts);
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
        if ($booking_status === 'cancelled') {
            return new WP_Error('booking_already_cancelled', 'La reserva ya ha sido cancelada', ['status' => 400]);
        }

        // Verificar si la cancelación es con tiempo suficiente para reembolso
        $booking_date = get_post_meta($booking_id, 'date', true);
        $booking_time = get_post_meta($booking_id, 'time', true);
        $class_datetime = new DateTime($booking_date . ' ' . $booking_time);
        $now = new DateTime();

        // Si la reserva ya ha pasado, no se puede cancelar
        if ($class_datetime < $now) {
            return new WP_Error(
                'booking_past',
                'No se puede cancelar una reserva que ya ha pasado',
                ['status' => 400]
            );
        }

        $hours_diff = ($class_datetime->getTimestamp() - $now->getTimestamp()) / 3600;
        $cancel_hours_limit = DSB_Settings::get('cancelation_time_hours');

        // Reembolsar tokens si cancela con tiempo
        $refund = false;
        if ($hours_diff >= $cancel_hours_limit) {
            $cost = get_post_meta($booking_id, 'cost', true);
            if ($cost) {
                $current_tokens = intval(get_user_meta($student_id, 'class_points', true));
                update_user_meta($student_id, 'class_points', $current_tokens + $cost);
                $refund = true;
            }
        }

        // Si la cancela un profesor, reembolsar siempre
        if (intval($current_user_id) === intval($teacher_id)) {
            $cost = get_post_meta($booking_id, 'cost', true);
            if ($cost) {
                $current_tokens = intval(get_user_meta($student_id, 'class_points', true));
                update_user_meta($student_id, 'class_points', $current_tokens + $cost);
                $refund = true;
            }
        }

        // Actualizar el estado de la reserva
        update_post_meta($booking_id, 'status', 'cancelled');
        do_action('dsb_booking_status_cancelled', $booking_id, 'cancelled', $booking_status);

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
        $reason     = sanitize_text_field($request->get_param('reason')) ?: 'Bloqueo manual';

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

        $is_valid = DSB_Booking_Service::validate_booking_time($teacher_id, $date, $start_time, $end_time);

        if (is_wp_error($is_valid)) {
            return $is_valid;
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

    public static function validate_booking_time($teacher_id, $date, $start_time, $end_time)
    {
        $config = get_user_meta($teacher_id, 'dsb_clases_config', true);

        if (!$config || !is_array($config)) {
            return new WP_Error('config_error', 'Configuración de horarios no encontrada');
        }

        // NUEVA VALIDACIÓN: Verificar duración de la clase
        $expected_duration = !empty($config['duracion']) ? intval($config['duracion']) : DSB_Settings::get('class_duration');

        if (!empty($end_time)) {
            $start_timestamp = strtotime($start_time);
            $end_timestamp = strtotime($end_time);
            $actual_duration = ($end_timestamp - $start_timestamp) / 60;

            if ($actual_duration != $expected_duration) {
                return new WP_Error('invalid_class_duration', sprintf(
                    'La duración de la clase debe ser exactamente %d minutos, recibida: %d minutos',
                    $expected_duration,
                    $actual_duration
                ));
            }
        } else {
            // Si no se proporciona end_time, calcularlo
            $dt_inicio = new DateTime("{$date} {$start_time}");
            $dt_fin = clone $dt_inicio;
            $dt_fin->modify('+' . $expected_duration . ' minutes');
            $end_time = $dt_fin->format('H:i');
        }

        $descansos = $config['descansos'] ?? [];

        // Verificar si la reserva coincide con algún descanso
        foreach ($descansos as $descanso) {
            if (!isset($descanso['inicio']) || !isset($descanso['fin'])) {
                continue; // Saltar descansos malformados
            }

            if (($start_time >= $descanso['inicio'] && $start_time < $descanso['fin']) ||
                ($end_time > $descanso['inicio'] && $end_time <= $descanso['fin']) ||
                ($start_time <= $descanso['inicio'] && $end_time >= $descanso['fin'])
            ) {
                return new WP_Error('break_time', sprintf(
                    'La reserva (%s-%s) coincide con un horario de descanso (%s-%s)',
                    $start_time,
                    $end_time,
                    $descanso['inicio'],
                    $descanso['fin']
                ));
            }
        }

        return true;
    }

    /**
     * Valida que la reserva coincida exactamente con un slot válido del profesor
     */
    private static function validate_booking_slot($teacher_id, $date, $start_time, $end_time)
    {
        // Usar el nuevo servicio centralizado
        $available_slots = DSB_Teacher_Slots_Service::get_slots_for_date($teacher_id, $date);

        if (empty($available_slots)) {
            return new WP_Error(
                'no_slots_available',
                'No hay slots disponibles para este día',
                ['status' => 400]
            );
        }

        // Verificar si existe un slot que coincida exactamente
        $matching_slot = DSB_Teacher_Slots_Service::find_matching_slot($available_slots, $start_time, $end_time);

        if (!$matching_slot) {
            return new WP_Error(
                'invalid_slot',
                sprintf(
                    'El horario %s-%s no corresponde a un slot válido. Slots disponibles: %s',
                    $start_time,
                    $end_time,
                    implode(', ', array_map(function ($s) {
                        return $s['start'] . '-' . $s['end'];
                    }, $available_slots))
                ),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Valida que la reserva cumpla con la antelación mínima y máxima configurada
     */
    private static function validate_booking_antelacion($date, $start_time)
    {
        $now = new DateTime();
        $booking_datetime = new DateTime("{$date} {$start_time}");

        // Obtener configuración
        $min_antelacion_hours = intval(DSB_Settings::get('default_min_antelacion')) ?: 1;
        $max_antelacion_days = intval(DSB_Settings::get('default_max_antelacion')) ?: 30;

        // Validar antelación mínima
        $min_allowed_time = clone $now;
        $min_allowed_time->modify("+{$min_antelacion_hours} hours");

        if ($booking_datetime < $min_allowed_time) {
            return new WP_Error(
                'insufficient_antelacion',
                sprintf(
                    'Debe reservar con al menos %d %s de antelación. Hora más próxima disponible: %s',
                    $min_antelacion_hours,
                    $min_antelacion_hours == 1 ? 'hora' : 'horas',
                    $min_allowed_time->format('d/m/Y H:i')
                ),
                ['status' => 400]
            );
        }

        // Validar antelación máxima
        $max_allowed_time = clone $now;
        $max_allowed_time->modify("+{$max_antelacion_days} days");

        if ($booking_datetime > $max_allowed_time) {
            return new WP_Error(
                'excessive_antelacion',
                sprintf(
                    'No es posible reservar con más de %d %s de antelación. Fecha límite: %s',
                    $max_antelacion_days,
                    $max_antelacion_days == 1 ? 'día' : 'días',
                    $max_allowed_time->format('d/m/Y')
                ),
                ['status' => 400]
            );
        }

        return true;
    }

}
