<?php
class DSB_Admin {
    private $views = [];

    public function __construct() {
        $this->init_views();
        add_action('admin_menu', [$this, 'register_menus']);
    }

    private function init_views() {
        $this->views = [
            'dashboard' => new DSB_Dashboard_View(),
            'teachers' => new DSB_Teachers_View(),
            'students' => new DSB_Students_View(),
            'vehicles' => new DSB_Vehicles_View(),
            'bookings' => new DSB_Bookings_View(),
            'notifications' => new DSB_Notifications_View()
        ];
    }

    public function register_menus() {
        add_menu_page(
            'Autoescuela',
            'Autoescuela',
            'manage_options',
            'dsb-dashboard',
            [$this->views['dashboard'], 'render'],
            'dashicons-car',
            30
        );

        $submenus = [
            'dsb-teachers' => ['Profesores', [$this->views['teachers'], 'render']],
            'dsb-students' => ['Estudiantes', [$this->views['students'], 'render']],
            'dsb-vehicles' => ['Vehículos', [$this->views['vehicles'], 'render']],
            'dsb-bookings' => ['Reservas', [$this->views['bookings'], 'render']],
            'dsb-notifications' => ['Notificaciones', [$this->views['notifications'], 'render']]
        ];

        foreach ($submenus as $slug => $menu) {
            add_submenu_page(
                'dsb-dashboard',
                $menu[0],
                $menu[0],
                'manage_options',
                $slug,
                $menu[1]
            );
        }
    }
}