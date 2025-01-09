<?php
// driving-school-bookings.php

if (!defined('ABSPATH'))
    exit;

class DrivingSchoolBookings
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->defineConstants();
        $this->initHooks();
        $this->loadDependencies();
        add_action('admin_menu', [$this, 'add_menu_page']);
    }

    private function defineConstants()
    {
        define('DSB_VERSION', '1.0.0');
        define('DSB_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('DSB_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    private function initHooks()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function add_menu_page()
    {
        add_menu_page(
            'DrivingBookingsClass',
            'DrivingBookingsClass',
            'manage_options',
            'driving-bookings',
            [$this, 'render_main_page'],
            'dashicons-car',
            20
        );
    }

    public function render_main_page()
    {
        echo '<div class="wrap">';
        echo '<h1>DrivingBookingsClass</h1>';
        echo '<p>Bienvenido al sistema de gestión de reservas.</p>';
        echo '</div>';
    }

    private function loadDependencies()
    {
        // Post Types
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-vehicle.php';
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-booking.php';
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-notification.php';

        // Core Classes
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-api.php';
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-roles.php';
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-jwt.php';  // Añadido
       // require_once DSB_PLUGIN_DIR . 'vendor/autoload.php';  // Añadido para composer

        // Initialize Classes
        new DSB_Vehicle();
        new DSB_Booking();
        new DSB_Notification();

        $this->roles = new DSB_Roles();
        $this->api = new DSB_API();
        $this->jwt = new DSB_JWT();
    }

    public function activate()
    {
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }
}

function DSB()
{
    return DrivingSchoolBookings::getInstance();
}

add_action('plugins_loaded', 'DSB');