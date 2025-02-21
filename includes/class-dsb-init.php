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
        add_action('rest_api_init', function() {
            add_filter('rest_pre_serve_request', function($value) {
                $this->dsb_add_cors_headers();
                return $value;
            });
        });
        
        // Agregar reglas de reescritura
        add_action('init', [$this, 'dsb_add_rewrite_rules']);
        add_filter('query_vars', [$this, 'dsb_add_query_vars']);
        add_action('template_redirect', [$this, 'dsb_template_redirect']);
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
        require_once DSB_PLUGIN_DIR . 'includes/class-dsb-template.php';

        require_once DSB_PLUGIN_DIR . 'admin/class-dsb-admin.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-base-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-dashboard-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-notifications-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-teachers-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-vehicles-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-students-view.php';
        require_once DSB_PLUGIN_DIR . 'admin/views/class-dsb-bookings-view.php';
    }

    private function initClasses() {
        if (is_admin()) {
            new DSB_Admin();
        }
        new DSB_Vehicle();
        new DSB_Booking();
        new DSB_Notification();
        new DSB_Template();
        $this->roles = new DSB_Roles();
        $this->api = new DSB_API();
        $this->jwt = new DSB_JWT();
    }

    private function initHooks() {
        register_activation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'activate']);
        register_deactivation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'deactivate']);
        add_filter('theme_page_templates', [$this, 'dsb_register_templates']);
    }

    public function activate() {
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function dsb_add_cors_headers() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
    }

    public function dsb_register_templates($templates) {
        $templates['public/views/estudiante.php'] = 'Vista Estudiante';
        $templates['public/views/profesor.php'] = 'Vista Profesor';
        $templates['public/views/acceso.php'] = 'Vista Acceso';
        return $templates;
    }

    public function dsb_add_rewrite_rules() {
        add_rewrite_rule('^acceso/?$', 'index.php?dsb_view=acceso', 'top');
        add_rewrite_rule('^estudiante/?$', 'index.php?dsb_view=estudiante', 'top');
        add_rewrite_rule('^profesor/?$', 'index.php?dsb_view=profesor', 'top');
    }

    public function dsb_add_query_vars($vars) {
        $vars[] = 'dsb_view';
        return $vars;
    }

    public function dsb_template_redirect() {
        $view = get_query_var('dsb_view');
        if ($view) {
            $file_path = DSB_PLUGIN_DIR . "public/views/{$view}.php";
            if (file_exists($file_path)) {
                include $file_path;
                exit;
            } else {
                wp_die("Vista no encontrada", "Error 404", ['response' => 404]);
            }
        }
    }
}
