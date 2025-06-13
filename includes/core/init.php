<?php
// includes/init.php
class DSB_Init
{
    private static $instance = null;
    public $jwt;
    public $auth;
    public $api;
    public $roles;
    public $user_manager;
    public $notifications;

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
        $this->loadDependencies();
        $this->initHooks();
        $this->initClasses();
        add_action('rest_api_init', function () {
            add_filter('rest_pre_serve_request', function ($value) {
                $this->dsb_add_cors_headers();
                return $value;
            });
        });


        // Hook para cargar scripts y estilos en el frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Agregar reglas de reescritura
        add_action('init', [$this, 'dsb_add_rewrite_rules']);
        add_filter('query_vars', [$this, 'dsb_add_query_vars']);
        add_action('template_redirect', [$this, 'dsb_template_redirect']);
    }

    private function defineConstants()
    {
        define('DSB_VERSION', '0.1.0-rc.1');
        define('DSB_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        define('DSB_PLUGIN_DIR_PATH', plugin_dir_path(dirname(__DIR__, 1)));
        define('DSB_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        define('DSB_PLUGIN_FULLCALENDAR_URL', DSB_PLUGIN_URL . '../public/lib/fullcalendar.min.js');
    }

    private function loadDependencies()
    {
        require_once DSB_PLUGIN_DIR . '/post-types/vehicle.php';
        require_once DSB_PLUGIN_DIR . '/post-types/booking.php';
        require_once DSB_PLUGIN_DIR . '/post-types/notification.php';
        require_once DSB_PLUGIN_DIR . 'core/api.php';
        require_once DSB_PLUGIN_DIR . 'core/settings.php';
        require_once DSB_PLUGIN_DIR . 'core/roles.php';
        require_once DSB_PLUGIN_DIR . 'core/jwt.php';
        require_once DSB_PLUGIN_DIR . 'core/template.php';
        require_once DSB_PLUGIN_DIR . 'core/class-users.php';
        require_once DSB_PLUGIN_DIR . 'core/auth.php';
        require_once DSB_PLUGIN_DIR . 'core/service-worker.php';
        require_once DSB_PLUGIN_DIR . 'core/hooks.php';

        require_once DSB_PLUGIN_DIR_PATH . 'admin/admin.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/base-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/dashboard-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/notifications-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/teachers-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/vehicles-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/students-view.php';
        require_once DSB_PLUGIN_DIR_PATH . 'admin/views/bookings-view.php';

        require_once DSB_PLUGIN_DIR . 'notifications/push-notification-service.php';
        require_once DSB_PLUGIN_DIR . 'notifications/email-notification-service.php';
        require_once DSB_PLUGIN_DIR . 'notifications/notification-manager-service.php';
    }

    private function initClasses()
    {
        if (is_admin()) {
            new DSB_Admin();
        }
        new DSB_Vehicle();
        new DSB_Booking();
        new DSB_Notification();
        new DSB_Template();
        new DSB_Settings();
        $this->user_manager = new DSB_User_Manager();
        $this->roles = new DSB_Roles();
        $this->api = new DSB_API();
        $this->jwt = new DSB_JWT();
        $this->auth = new DSB_Auth();
    }

    private function initHooks()
    {
        register_activation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'activate']);
        register_deactivation_hook(DSB_PLUGIN_DIR . 'driving-school-bookings.php', [$this, 'deactivate']);
        add_filter('theme_page_templates', [$this, 'dsb_register_templates']);
    }

    public function activate()
    {
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function dsb_add_cors_headers()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
    }

    public function dsb_register_templates($templates)
    {
        $templates['public/views/alumno.php'] = 'Vista alumno';
        $templates['public/views/profesor.php'] = 'Vista profesor';
        $templates['public/views/acceso.php'] = 'Vista acceso';
        return $templates;
    }

    public function dsb_add_rewrite_rules()
    {
        add_rewrite_rule('^acceso/?$', 'index.php?dsb_view=acceso', 'top');
        add_rewrite_rule('^alumno/?$', 'index.php?dsb_view=alumno', 'top');
        add_rewrite_rule('^profesor/?$', 'index.php?dsb_view=profesor', 'top');
    }

    public function dsb_add_query_vars($vars)
    {
        $vars[] = 'dsb_view';
        return $vars;
    }

    public function dsb_template_redirect()
    {
        $view = get_query_var('dsb_view');
        if ($view) {
            $file_path = DSB_PLUGIN_DIR_PATH . "public/views/{$view}.php";
            if (file_exists($file_path)) {
                include $file_path;
                exit;
            } else {
                wp_die("Vista no encontrada", "Error 404", ['response' => 404]);
            }
        }
    }

    public function enqueue_scripts()
    {
        // Registrar y cargar jQuery desde WordPress
        wp_enqueue_script('jquery');
    }
}
