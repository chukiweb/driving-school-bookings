<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Ajax_Handler
{
    public static function init() {
        add_action('wp_ajax_dsb_get_teacher_calendar', [self::class, 'get_teacher_calendar']);
        add_action('wp_ajax_dsb_get_teacher_data', [self::class, 'get_teacher_data']);
        add_action('wp_ajax_dsb_save_teacher_data', [self::class, 'save_teacher_data']);
        add_action('wp_ajax_dsb_get_vehicles', [self::class, 'get_vehicles']);
    }
    

    public static function get_teacher_calendar() {
        if (!current_user_can('manage_options') && !current_user_can('teacher')) {
            wp_send_json_error('No autorizado', 403);
        }

        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if (!$teacher_id) {
            wp_send_json_error('ID de profesor inválido', 400);
        }

        // ✅ Usa el servicio aquí
        $events = DSB_Calendar_Service::get_teacher_calendar($teacher_id);

        wp_send_json($events);
    }

    public  static function get_teacher_data() {
        if (!current_user_can('manage_options') && !current_user_can('teacher')) {
            wp_send_json_error('No autorizado', 403);
        }

        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if (!$teacher_id) {
            wp_send_json_error('ID de profesor inválido', 400);
        }

        // ✅ Usa el servicio aquí
        $teacher = DSB_Teacher_Service::get_teacher($teacher_id);

        wp_send_json($teacher);
    }
    public static function save_teacher_data() {
        if (!current_user_can('manage_options') && !current_user_can('teacher')) {
            wp_send_json_error('No autorizado', 403);
        }

        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if (!$teacher_id) {
            wp_send_json_error('ID de profesor inválido', 400);
        }

        $data = $_POST['data'] ?? [];
        if (empty($data)) {
            wp_send_json_error('Datos inválidos', 400);
        }

        // ✅ Usa el servicio aquí
        $result = DSB_Teacher_Service::save_teacher($teacher_id, $data);

        wp_send_json($result);
    }
    public static function get_vehicles() {
        if (!current_user_can('manage_options') && !current_user_can('teacher')) {
            wp_send_json_error('No autorizado', 403);
        }

        $vehicles = DSB_Vehicle_Service::get_all_vehicles();

        wp_send_json($vehicles);
    }
    public static function get_vehicle_data() {
        if (!current_user_can('manage_options') && !current_user_can('teacher')) {
            wp_send_json_error('No autorizado', 403);
        }

        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        if (!$vehicle_id) {
            wp_send_json_error('ID de vehículo inválido', 400);
        }

        // ✅ Usa el servicio aquí
        $vehicle = DSB_Vehicle_Service::get_vehicle($vehicle_id);

        wp_send_json($vehicle);
    }
}
