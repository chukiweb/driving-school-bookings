<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Vehicle_Service
{
    /**
     * Obtener listado de vehículos publicados
     */
    public static function get_all(): array
    {
        $vehicles = get_posts([
            'post_type' => 'vehiculo',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ]);

        $result = [];

        foreach ($vehicles as $vehiculo) {
            $result[] = [
                'id' => $vehiculo->ID,
                'name' => $vehiculo->post_title,
            ];
        }

        return $result;
    }

    /**
     * Obtener un solo vehículo por ID
     */
    public static function get($vehicle_id): ?array
    {
        $vehicle = get_post(intval($vehicle_id));

        if (!$vehicle || $vehicle->post_type !== 'vehiculo') {
            return null;
        }

        return [
            'id' => $vehicle->ID,
            'name' => $vehicle->post_title,
        ];
    }

    /**
     * Crear un nuevo vehículo
     */
    public static function create($data): int|WP_Error
    {
        $name = sanitize_text_field($data['name']);

        return wp_insert_post([
            'post_type' => 'vehiculo',
            'post_status' => 'publish',
            'post_title' => $name
        ]);
    }

    /**
     * Eliminar un vehículo
     */
    public static function delete($vehicle_id): bool
    {
        return wp_delete_post(intval($vehicle_id), true) !== false;
    }
}
