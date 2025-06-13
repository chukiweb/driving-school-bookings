<?php

/**
 * Plugin Name: Driving School Bookings
 * Description: Sistema de gestión de reservas para autoescuela
 * Version: 0.1.0-rc.1
 * Author: Cubetic Consultores
 * Author URI: https://cubetic.com
 * Text Domain: driving-school-bookings 
 * Requires at least: 6.8.1
 * Requires PHP: 8.2.23
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/vendor/autoload.php';

// Carga inicial
require_once plugin_dir_path(__FILE__) . 'includes/core/init.php';

// Función global singleton
function DSB()
{
    return DSB_Init::getInstance();
}
add_action('plugins_loaded', 'DSB');

// Hooks activación / desactivación
register_activation_hook(__FILE__, 'dsb_activate_plugin');
register_deactivation_hook(__FILE__, 'dsb_deactivate_plugin');

function dsb_activate_plugin()
{
    require_once plugin_dir_path(__FILE__) . 'includes/core/service-worker.php';
    dsb_create_root_service_worker();
}

function dsb_deactivate_plugin()
{
    dsb_remove_root_service_worker();
}

// Añade configuración Pusher Beams en ajustes (puedes ampliar aquí)
add_filter('dsb_settings_fields', function ($fields) {
    $fields['pusher_beams_secret'] = [
        'label'       => 'Pusher Beams Secret Key',
        'type'        => 'text',
        'description' => 'Clave secreta para enviar notificaciones push con Pusher Beams'
    ];
    return $fields;
});