<?php
// includes/class-dsb-init.php
class DSB_Init {
    private static $instance = null;
    public $jwt;
    public $api;
    public $roles;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->defineConstants();
        $this->loadDependencies();
        $this->initHooks();
        $this->initClasses();
    }

    private function defineConstants() {
        define('DSB_VERSION', '1.0.0');
        define('DSB_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        define('DSB_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
    }

    private function loadDependencies() {
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-vehicle.php';
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-booking.php';
        require_once DSB_PLUGIN_DIR . 'includes/post-types/class-dsb-notification.php';
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-api.php';
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-roles.php';
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-jwt.php';

        require_once DSB_PLUGIN_DIR . 'admin/class-dsb-admin.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-base-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-dashboard-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-notifications-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-teachers-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-vehicles-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-students-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-bookings-view.php';

        require_once DSB_PLUGIN_DIR . 'includes/pwa/class-dsb-pwa.php';
    }

    private function initClasses() {

        if (is_admin()) {
            new DSB_Admin();
        }
        new DSB_Vehicle();
        new DSB_Booking();
        new DSB_Notification();
        $this->roles = new DSB_Roles();
        $this->api = new DSB_API();
        $this->jwt = new DSB_JWT();

        new DSB_PWA();
    }

    private function initHooks() {
        register_activation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'activate']);
        register_deactivation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'deactivate']);
    }
    public function activate() {
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}