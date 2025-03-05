<?php
class DSB_Teachers_View extends DSB_Base_View {
    public function __construct() {
        $this->title = 'Gestión de Profesores';
        $this->nonce_action = 'create_teacher';
        $this->nonce_name = 'teacher_nonce';
    }

    protected function get_data() {
        return get_users(['role' => 'teacher']);
    }

    protected function handle_form_submission() {
        $this->verify_nonce();

        $user_data = [
            'user_login' => sanitize_text_field($_POST['username']),
            'user_pass' => $_POST['password'],
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'role' => 'teacher'
        ];
        
        $user_id = wp_insert_user($user_data);
        
        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'license_number', sanitize_text_field($_POST['license_number']));
            $this->render_notice('Profesor creado exitosamente');
        } else {
            $this->render_notice($user_id->get_error_message(), 'error');
        }
    }

    protected function render_form() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
            <table class="form-table">
                <tr>
                    <th><label for="username">Usuario</label></th>
                    <td><input type="text" name="username" required /></td>
                </tr>
                <tr>
                    <th><label for="password">Contraseña</label></th>
                    <td><input type="password" name="password" required /></td>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td><input type="email" name="email" required /></td>
                </tr>
                <tr>
                    <th><label for="first_name">Nombre</label></th>
                    <td><input type="text" name="first_name" required /></td>
                </tr>
                <tr>
                    <th><label for="last_name">Apellidos</label></th>
                    <td><input type="text" name="last_name" required /></td>
                </tr>
                <tr>
                    <th><label for="license_number">Número de Licencia</label></th>
                    <td><input type="text" name="license_number" required /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear Profesor" />
            </p>
        </form>
        <?php
    }

    protected function render_table() {
        $teachers = $this->get_data();
        ?>
        <h2>Listado de Profesores</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Licencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><?php echo esc_html($teacher->user_login); ?></td>
                    <td><?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?></td>
                    <td><?php echo esc_html($teacher->user_email); ?></td>
                    <td><?php echo esc_html(get_user_meta($teacher->ID, 'license_number', true)); ?></td>
                    <td>
                        <a href="#" class="button">Editar</a>
                        <a href="#" class="button">Eliminar</a>
                        <a href="#" class="button">Calendario</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        // Solo cargar el script en la página del profesor
        if ($screen && $screen->id === 'toplevel_page_gestion_profesores') {
            wp_enqueue_script(
                'teachers-script',
                plugin_dir_url(__FILE__) . '../public/js/admin/teacher-admin-view.js',
                ['jquery', 'fullcalendar'],
                null,
                true
            );
    
            // Obtener reservas de todos los profesores
            $teachers = $this->get_data();
            $teacher_reservations = [];
    
            foreach ($teachers as $teacher) {
                $teacher_reservations[$teacher->ID] = json_decode($this->get_reservations($teacher->ID));
            }
    
            // Pasar las reservas a JavaScript
            wp_localize_script('teachers-script', 'teacherReservations', $teacher_reservations);
        }
    }
    
}