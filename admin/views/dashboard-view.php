<?php
class DSB_Dashboard_View extends DSB_Base_View {
    
    private $teachers;
    private $students;
    private $vehicles;
    private $bookings;

    public function __construct() {
        $this->title = 'Dashboard Autoescuela';

    }

    protected function enqueue_scripts()
    {
        
    }

    protected function render_forms()
    {
        
    }

    protected function get_data() {
        return [
            'teachers' => get_users(['role' => 'teacher']),
            'students' => get_users(['role' => 'student']),
            'vehicles' => get_posts(['post_type' => 'vehiculo', 'posts_per_page' => -1]),
            'bookings' => get_posts(['post_type' => 'reserva', 'posts_per_page' => -1])
        ];
    }

    public function render() {
        $this->handle_form_submission(); 
        $data = $this->get_data();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>
            
            <?php $this->render_stats($data); ?>
            <?php $this->render_settings_block(); ?>
            
            <div class="dsb-dashboard-grid">
                <?php
                $this->render_recent_items('Profesores', $data['teachers'], ['Nombre', 'Email']);
                $this->render_recent_items('Alumnos', $data['students'], ['Nombre', 'Email', 'DNI']);
                $this->render_recent_items('Vehículos', $data['vehicles'], ['Modelo', 'Matrícula', 'Año']);
                $this->render_recent_items('Reservas', $data['bookings'], ['Fecha', 'Estudiante', 'Profesor']);
                ?>
            </div>
        </div>
        <?php
        $this->add_dashboard_styles();
    }

    private function render_stats($data) {
        $stats = [
            ['icon' => 'groups', 'title' => 'Profesores', 'count' => count($data['teachers'])],
            ['icon' => 'welcome-learn-more', 'title' => 'Alumnos', 'count' => count($data['students'])],
            ['icon' => 'car', 'title' => 'Vehículos', 'count' => count($data['vehicles'])],
            ['icon' => 'calendar-alt', 'title' => 'Reservas', 'count' => count($data['bookings'])]
        ];

        echo '<div class="dsb-stats-grid">';
        foreach ($stats as $stat) {
            $this->render_stat_box($stat);
        }
        echo '</div>';
    }

    private function render_stat_box($stat) {
        ?>
        <div class="dsb-stat-box">
            <span class="dashicons dashicons-<?php echo esc_attr($stat['icon']); ?>"></span>
            <h3><?php echo esc_html($stat['title']); ?></h3>
            <p class="dsb-stat-number"><?php echo esc_html($stat['count']); ?></p>
        </div>
        <?php
    }

    private function render_recent_items($title, $items, $columns) {
        ?>
        <div class="dsb-section">
            <h2><?php echo esc_html($title); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th><?php echo esc_html($column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $items = array_slice($items, 0, 5);
                    foreach ($items as $item) {
                        $this->render_item_row($item);
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_item_row($item) {
        if ($item instanceof WP_User) {
            $first_name = $item->first_name ?? '';
            $last_name = $item->last_name ?? '';
            $roles = $item->roles ?? [];
            $role = !empty($roles) ? $roles[0] : '';
            
            echo '<tr>';
            echo '<td>' . esc_html($first_name . ' ' . $last_name) . '</td>';
            echo '<td>' . esc_html($item->user_email ?? '') . '</td>';
            echo '<td>' . esc_html(get_user_meta($item->ID, 'dni', true)) . '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td>' . esc_html($item->post_title ?? '') . '</td>';
            echo '<td>' . esc_html(get_post_meta($item->ID ?? 0, 'license_plate', true)) . '</td>';
            echo '<td>' . esc_html(get_post_meta($item->ID ?? 0, 'model_year', true)) . '</td>';
            echo '</tr>';
        }
    }

    private function render_settings_block() {
        $cancel_time = DSB_Settings::get('cancelation_time_hours');
        $daily_limit = DSB_Settings::get('daily_limit');
        $class_cost = DSB_Settings::get('class_cost');
    
        ?>
        <div class="dsb-section">
            <h2>Ajustes Generales del Sistema</h2>
    
            <?php if (!empty($_POST['dsb_save_settings'])): ?>
                <div class="notice notice-success is-dismissible"><p>Ajustes guardados correctamente.</p></div>
            <?php endif; ?>
    
            <form method="post">
                <?php wp_nonce_field('dsb_save_settings_nonce'); ?>
                <input type="hidden" name="dsb_save_settings" value="1" />
    
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cancelation_time_hours">Tiempo de cancelación (horas)</label></th>
                        <td><input type="number" id="cancelation_time_hours" name="cancelation_time_hours" value="<?php echo esc_attr($cancel_time); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="daily_limit">Clases diarias por estudiante</label></th>
                        <td><input type="number" id="daily_limit" name="daily_limit" value="<?php echo esc_attr($daily_limit); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="class_cost">Coste por clase (créditos)</label></th>
                        <td><input type="number" step="0.1" id="class_cost" name="class_cost" value="<?php echo esc_attr($class_cost); ?>" class="regular-text"></td>
                    </tr>
                </table>
    
                <p><button type="submit" class="button button-primary">Guardar ajustes</button></p>
            </form>
        </div>
        <?php
    }
    

    private function add_dashboard_styles() {
        ?>
        <style>
            .dsb-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .dsb-stat-box {
                background: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                text-align: center;
            }
            .dsb-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .dsb-section {
                background: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .dsb-stat-number {
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
            }
        </style>
        <?php
    }

    // Estos métodos son requeridos por la clase base pero no los usamos en el dashboard
    protected function handle_form_submission() {
        if (!empty($_POST['dsb_save_settings'])) {
            check_admin_referer('dsb_save_settings_nonce');
    
            if (current_user_can('administrator')) {
                DSB_Settings::update('cancelation_time_hours', intval($_POST['cancelation_time_hours']));
                DSB_Settings::update('daily_limit', intval($_POST['daily_limit']));
                DSB_Settings::update('class_cost', floatval($_POST['class_cost']));
            }
        }
    }
    protected function render_form() {}
    protected function render_table() {}
}