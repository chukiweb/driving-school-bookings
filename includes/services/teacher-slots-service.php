<?php

if (!defined('ABSPATH')) {
    exit;
}

class DSB_Teacher_Slots_Service
{

    /**
     * Genera los slots disponibles para un profesor en una fecha específica
     */
    public static function get_slots_for_date($teacher_id, $date)
    {
        $config = get_user_meta($teacher_id, 'dsb_clases_config', true);

        if (!$config || !is_array($config)) {
            return [];
        }


        // Verificar que el día esté disponible
        $day_available = self::is_day_available($config, $date);

        if (!$day_available) {
            return [];
        }

        $slots = self::generate_slots_from_config($config);

        return $slots;
    }

    /**
     * Verifica si un día específico está disponible según la configuración
     */
    private static function is_day_available($config, $date)
    {
        $day_of_week = date('w', strtotime($date)); // 0=Domingo, 1=Lunes, etc.
        $day_names = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $day_name = $day_names[$day_of_week];

        $dias_disponibles = $config['dias'] ?? [];
        return in_array($day_name, $dias_disponibles);
    }

    /**
     * Genera slots basándose en la configuración del profesor
     */
    private static function generate_slots_from_config($config)
    {
        $hora_inicio = $config['hora_inicio'] ?? '08:00';
        $hora_fin = $config['hora_fin'] ?? '20:00';
        $duracion_clase = intval($config['duracion'] ?? 45);
        $descansos = $config['descansos'] ?? [];

        // Convertir tiempo a minutos para facilitar cálculos
        $inicio_minutos = self::time_to_minutes($hora_inicio);
        $fin_minutos = self::time_to_minutes($hora_fin);

        // Generar períodos laborales excluyendo descansos
        $periodos_laborales = self::calculate_work_periods($inicio_minutos, $fin_minutos, $descansos);

        // Generar slots para cada período laboral
        $slots = [];
        foreach ($periodos_laborales as $periodo) {
            $slots = array_merge($slots, self::generate_slots_for_period($periodo, $duracion_clase));
        }

        return $slots;
    }

    /**
     * Calcula los períodos laborales excluyendo descansos
     */
    private static function calculate_work_periods($inicio_minutos, $fin_minutos, $descansos)
    {
        $periodos = [];
        $descansos_ordenados = [];

        // Procesar y ordenar descansos
        foreach ($descansos as $descanso) {
            if (isset($descanso['inicio']) && isset($descanso['fin'])) {
                $descansos_ordenados[] = [
                    'inicio' => self::time_to_minutes($descanso['inicio']),
                    'fin' => self::time_to_minutes($descanso['fin'])
                ];
            }
        }

        usort($descansos_ordenados, function ($a, $b) {
            return $a['inicio'] - $b['inicio'];
        });

        // Generar períodos laborales
        $hora_actual = $inicio_minutos;
        foreach ($descansos_ordenados as $descanso) {
            if ($hora_actual < $descanso['inicio']) {
                $periodos[] = [
                    'inicio' => $hora_actual,
                    'fin' => $descanso['inicio']
                ];
            }
            $hora_actual = max($hora_actual, $descanso['fin']);
        }

        if ($hora_actual < $fin_minutos) {
            $periodos[] = [
                'inicio' => $hora_actual,
                'fin' => $fin_minutos
            ];
        }

        return $periodos;
    }

    /**
     * Genera slots para un período específico
     */
    private static function generate_slots_for_period($periodo, $duracion_clase)
    {
        $slots = [];
        $duracion_periodo = $periodo['fin'] - $periodo['inicio'];
        $num_clases = floor($duracion_periodo / $duracion_clase);

        for ($i = 0; $i < $num_clases; $i++) {
            $inicio_slot = $periodo['inicio'] + ($i * $duracion_clase);
            $fin_slot = $inicio_slot + $duracion_clase;

            $slots[] = [
                'start' => self::minutes_to_time($inicio_slot),
                'end' => self::minutes_to_time($fin_slot),
                'start_minutes' => $inicio_slot,
                'end_minutes' => $fin_slot
            ];
        }

        return $slots;
    }

    /**
     * Busca un slot que coincida exactamente con los tiempos dados
     */
    public static function find_matching_slot($slots, $start_time, $end_time)
    {
        foreach ($slots as $slot) {
            if ($slot['start'] === $start_time && $slot['end'] === $end_time) {
                return $slot;
            }
        }
        return null;
    }

    /**
     * Genera businessHours para FullCalendar
     */
    public static function generate_business_hours($config)
    {
        $dias_disponibles = $config['dias'] ?? [];
        $hora_inicio = $config['hora_inicio'] ?? '08:00';
        $hora_fin = $config['hora_fin'] ?? '20:00';

        if (empty($dias_disponibles)) {
            return [];
        }

        $day_mapping = [
            'Lunes' => 1,
            'Martes' => 2,
            'Miércoles' => 3,
            'Jueves' => 4,
            'Viernes' => 5,
            'Sábado' => 6,
            'Domingo' => 0
        ];

        $business_hours = [];
        foreach ($dias_disponibles as $dia) {
            if (isset($day_mapping[$dia])) {
                $business_hours[] = [
                    'daysOfWeek' => [$day_mapping[$dia]],
                    'startTime' => $hora_inicio,
                    'endTime' => $hora_fin
                ];
            }
        }

        return $business_hours;
    }

    /**
     * Convierte tiempo HH:MM a minutos
     */
    private static function time_to_minutes($time)
    {
        list($hours, $minutes) = explode(':', $time);
        return intval($hours) * 60 + intval($minutes);
    }

    /**
     * Convierte minutos a formato HH:MM
     */
    private static function minutes_to_time($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
}
