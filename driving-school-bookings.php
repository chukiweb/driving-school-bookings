<?php
/**
* Plugin Name: Driving School Bookings
* Description: Sistema de gestión de reservas para autoescuela
* Version: 1.0.0
* Author: Tu Nombre
* Text Domain: driving-school-bookings
*/

if (!defined('ABSPATH')) exit;

//require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dsb-init.php';

function DSB() {
   return DSB_Init::getInstance();
}

add_action('plugins_loaded', 'DSB');