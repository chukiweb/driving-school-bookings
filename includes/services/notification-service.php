<?php

/**
 * Servicio de notificaciones
 * 
 * @package Driving_School_Bookings
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar notificaciones push
 */
class DSB_Notification_Service
{

    private static $instance = null;
    private $beams_instance_id;
    private $beams_secret_key;

    public function __construct()
    {
        $this->beams_instance_id = '02609d94-0e91-4039-baf6-7d9d04b1fb6e';
        $this->beams_secret_key = 'B2CD91D89BA3527561FA2651A5FD759BB4BDC27CF8211554E532EF20F34CBD81';
    }

    /**
     * Obtener instancia única (Singleton)
     */
    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new DSB_Notification_Service();
        }
        return self::$instance;
    }

    /**
     * Enviar notificación a un alumno específico
     */
    public function notify_student($student_id, $title, $body, $data = [])
    {
        return $this->send_push_notification("student-{$student_id}", $title, $body, $data);
    }

    /**
     * Enviar notificación a un profesor específico
     */
    public function notify_teacher($teacher_id, $title, $body, $data = [])
    {
        return $this->send_push_notification("teacher-{$teacher_id}", $title, $body, $data);
    }

    /**
     * Enviar recordatorios de clase
     */
    public function send_class_reminder($student_id, $booking_data)
    {
        $title = 'Recordatorio de clase';
        $body = "Tienes una clase programada para mañana a las {$booking_data['time']}";

        return $this->notify_student($student_id, $title, $body, [
            'type' => 'reminder',
            'booking_id' => $booking_data['id']
        ]);
    }

    /**
     * Notificar nueva reserva a un profesor
     */
    public function notify_new_booking($booking_id)
    {
        $booking = get_post($booking_id);
        if (!$booking) return false;

        $teacher_id = get_post_meta($booking_id, 'teacher_id', true);
        $student_id = get_post_meta($booking_id, 'student_id', true);
        $date = get_post_meta($booking_id, 'date', true);
        $time = get_post_meta($booking_id, 'time', true);

        $student = get_user_by('id', $student_id);
        $student_name = $student ? $student->display_name : 'Alumno desconocido';

        $formatted_date = date('d/m/Y', strtotime($date));

        $title = 'Nueva reserva de clase';
        $body = "{$student_name} ha reservado una clase para el {$formatted_date} a las {$time}";

        return $this->notify_teacher($teacher_id, $title, $body, [
            'type' => 'new_booking',
            'booking_id' => $booking_id,
            'student_id' => $student_id
        ]);
    }

    /**
     * Notificar cambios en el estado de la reserva
     */
    public function notify_booking_status_change($booking_id, $status)
    {
        $booking = get_post($booking_id);
        if (!$booking) return false;

        $student_id = get_post_meta($booking_id, 'student_id', true);
        $teacher_id = get_post_meta($booking_id, 'teacher_id', true);
        $date = get_post_meta($booking_id, 'date', true);
        $time = get_post_meta($booking_id, 'time', true);

        $formatted_date = date('d/m/Y', strtotime($date));

        $title = '';
        $body = '';

        switch ($status) {
            case 'accepted':
                $title = '¡Reserva confirmada!';
                $body = "Tu clase para el {$formatted_date} a las {$time} ha sido confirmada por el profesor.";

                return $this->notify_student($student_id, $title, $body, [
                    'type' => 'booking_accepted',
                    'booking_id' => $booking_id
                ]);

            case 'rejected':
                $title = 'Reserva rechazada';
                $body = "Lo sentimos, tu clase para el {$formatted_date} a las {$time} ha sido rechazada por el profesor.";

                return $this->notify_student($student_id, $title, $body, [
                    'type' => 'booking_rejected',
                    'booking_id' => $booking_id
                ]);

            case 'cancelled':
                // Para el alumno ya se enviará notificación en la interfaz al cancelar
                // pero notificamos al profesor
                $student = get_user_by('id', $student_id);
                $student_name = $student ? $student->display_name : 'Alumno desconocido';

                $title = 'Clase cancelada';
                $body = "{$student_name} ha cancelado la clase del {$formatted_date} a las {$time}.";

                return $this->notify_teacher($teacher_id, $title, $body, [
                    'type' => 'booking_cancelled',
                    'booking_id' => $booking_id,
                    'student_id' => $student_id
                ]);

            default:
                return false;
        }
    }

    /**
     * Método principal para enviar notificaciones push
     */
    private function send_push_notification($interest, $title, $body, $data = [])
    {
        if (empty($this->beams_secret_key)) {
            error_log('Pusher Beams: Secret key not configured');
            return false;
        }

        $url = "https://{$this->beams_instance_id}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->beams_instance_id}/publishes";

        $payload = [
            'interests' => [$interest],
            'web' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'deep_link' => home_url(),
                    'icon' => DSB_PLUGIN_DIR . 'public/images/logo.png'
                ],
                'data' => $data
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->beams_secret_key
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code < 200 || $http_code >= 300) {
            error_log("Pusher Beams error: " . $response);
            return false;
        }

        return true;
    }

    /**
     * Programar envío de recordatorios diarios
     */
    public function schedule_class_reminders()
    {
        if (!wp_next_scheduled('dsb_daily_class_reminders')) {
            wp_schedule_event(strtotime('today 20:00'), 'daily', 'dsb_daily_class_reminders');
        }
    }

    /**
     * Enviar recordatorios para las clases de mañana
     */
    public function send_daily_reminders()
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $args = [
            'post_type' => 'dsb_booking',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'date', 'value' => $tomorrow, 'compare' => '='],
                ['key' => 'status', 'value' => ['accepted', 'pending'], 'compare' => 'IN']
            ]
        ];

        $bookings = get_posts($args);

        foreach ($bookings as $booking) {
            $student_id = get_post_meta($booking->ID, 'student_id', true);
            $time = get_post_meta($booking->ID, 'time', true);

            $booking_data = [
                'id' => $booking->ID,
                'time' => $time
            ];

            $this->send_class_reminder($student_id, $booking_data);
        }
    }
}

// Función accesible globalmente para usar el servicio de notificaciones
function DSB_Notifications()
{
    return DSB_Notification_Service::get_instance();
}

// Registrar gancho para enviar recordatorios diarios
add_action('dsb_daily_class_reminders', [DSB_Notification_Service::get_instance(), 'send_daily_reminders']);

// Asegurarse de que están programados los recordatorios
add_action('wp', [DSB_Notification_Service::get_instance(), 'schedule_class_reminders']);
