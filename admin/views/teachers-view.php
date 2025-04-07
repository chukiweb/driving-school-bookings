<?php
class DSB_Teachers_View extends DSB_Base_View
{
    public function __construct()
    {
        $this->title = 'Gestión de Profesores';
        $this->nonce_action = 'create_teacher';
        $this->nonce_name = 'teacher_nonce';
    }

    protected function get_data()
    {
        return get_users(['role' => 'teacher']);
    }

    protected function handle_form_submission()
    {
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
            update_user_meta($user_id, 'assigned_vehicle', sanitize_text_field($_POST['assigned_vehicle']));
            $this->render_notice('Profesor creado exitosamente');
        } else {
            $this->render_notice($user_id->get_error_message(), 'error');
        }
    }

    public function handle_class_schedule_form(): void
    {
        if (
            isset($_POST['clases_profesor_nonce'], $_POST['teacher_id']) &&
            wp_verify_nonce($_POST['clases_profesor_nonce'], 'guardar_clases_profesor')
        ) {
            $teacher_id = intval($_POST['teacher_id']);

            $dias = array_map('sanitize_text_field', $_POST['dias'] ?? []);
            $hora_inicio = sanitize_text_field($_POST['hora_inicio'] ?? '');
            $hora_fin = sanitize_text_field($_POST['hora_fin'] ?? '');
            $duracion = intval($_POST['duracion'] ?? 0);

            if (empty($dias) || empty($hora_inicio) || empty($hora_fin) || $duracion <= 0) {
                $this->render_notice('Faltan datos obligatorios para guardar las clases.', 'error');
                return;
            }

            $config = [
                'dias' => $dias,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'duracion' => $duracion,
            ];

            update_user_meta($teacher_id, 'dsb_clases_config', $config);

            $this->render_notice('Datos de clases guardados correctamente.');
        }
    }


    protected function render_form()
    {
        $vehicles = get_posts(['post_type' => 'vehiculo', 'posts_per_page' => -1]);
        ?>

        <div id="crear-profesor-form" style="display: none; margin-top: 20px;">
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
                        <th><label for="phone">Telefono</label></th>
                        <td><input type="text" name="phone" required /></td>
                    </tr>
                    <tr>
                        <th><label for="assigned_vehicle">Vehiculo asignado</label></th>
                        <td> <select id="assigned_vehicle" name="assigned_vehicle" class="form-control">
                                <option value="">-- Selecciona un vehículo --</option>
                                <?php foreach ($vehicles as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo->ID); ?>">
                                        <?php echo esc_html($vehiculo->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                    <tr>
                        <th><label for="assign_motorcycle">Moto asignada (opcional)</label></th>
                        <td> <select id="assign_motorcycle" name="assign_motorcycle" class="form-control">
                                <option value="">-- Selecciona una moto --</option>
                                <?php foreach ($vehicles as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo->ID); ?>">
                                        <?php echo esc_html($vehiculo->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Crear Profesor" />
                </p>
            </form>
        </div>
        <?php
        $this->enqueue_scripts();
    }

    protected function render_table()
    {
        $teachers = $this->get_data();
        ?>

        <div class="heding">
            <h2>Listado de Profesores</h2>
            <div class="boton-heding">
                <button id="mostrar-form-crear-profesor" class="button button-primary">Nuevo Profesor</button>
            </div>
        </div>

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
                        <td data-login="<?php echo esc_attr($teacher->user_login); ?>" data-id="<?php echo esc_attr($teacher->ID); ?>">
                            <?php echo esc_html($teacher->user_login); ?>
                        </td>
                        <td><?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?></td>
                        <td><?php echo esc_html($teacher->user_email); ?></td>
                        <td><?php echo esc_html(get_user_meta($teacher->ID, 'license_number', true)); ?></td>
                        <td>
                            <a href="#" class="button" data-id=<?php echo esc_html($teacher->ID); ?>>Editar</a>
                            <a href="#" class="button">Eliminar</a>
                            <a href="#" class="button">Calendario</a>
                            <a href="#" class="button open-class-settings" data-id=<?php echo esc_html($teacher->ID); ?>>Admin
                                clases</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="modal-clases-profesor" style="display:none;">
            <form id="form-clases-profesor" method="post"
                action="<?php echo esc_url(admin_url('admin.php?page=dsb-teachers')); ?>">

                <?php wp_nonce_field('guardar_clases_profesor', 'clases_profesor_nonce'); ?>
                <input type="hidden" name="teacher_id" id="clases_teacher_id" value="">

                <table class="form-table">
                    <tr>
                        <th><label for="dias">Días que trabaja</label></th>
                        <td>
                            <?php
                            $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                            foreach ($dias as $dia): ?>
                                <label style="margin-right:10px;">
                                    <input type="checkbox" name="dias[]" value="<?php echo $dia; ?>"> <?php echo $dia; ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hora_inicio">Hora de inicio</label></th>
                        <td><input type="time" name="hora_inicio" required></td>
                    </tr>
                    <tr>
                        <th><label for="hora_fin">Hora de fin</label></th>
                        <td><input type="time" name="hora_fin" required></td>
                    </tr>
                    <tr>
                        <th><label for="duracion">Duración de la clase (min)</label></th>
                        <td><input type="number" name="duracion" min="15" step="5" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="guardar-clases">Guardar Clases</button>
                </p>
            </form>
        </div>

        <div id="teacher-calendar-container" style="display:none; margin-top: 30px;">
            <h2>Calendario del Profesor</h2>
            <div id="teacher-calendar" style="min-height: 600px;"></div>
        </div>
        <?php
    }


    public function enqueue_scripts()
    {

        // Asegurar que jQuery esté cargado antes de FullCalendar
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'fullcalendar-js',
            DSB_PLUGIN_FULLCALENDAR_URL,
            ['jquery'],
            '5.11.3',
            true
        ); // Cargar estilo personalizado para el calendario
        wp_enqueue_style(
            'teacher-calendar-css',
            DSB_PLUGIN_URL . '../public/css/admin/teacher-view.css',
            [],
            '1.0.0'
        );
        // Cargar script de profesor.js (depende de FullCalendar)
        wp_enqueue_script('profesor-js', DSB_PLUGIN_URL . '../public/js/admin/teacher-admin-view.js', ['jquery'], '1.0.0', true);

       // Construir mapas
    $map_login_id = [];
    $teacher_config_map = [];

    foreach ($this->get_data() as $teacher) {
        $map_login_id[$teacher->user_login] = $teacher->ID;
        $teacher_config_map[$teacher->ID] = get_user_meta($teacher->ID, 'dsb_clases_config', true);
    }


        // Pasar los datos al JS
    wp_localize_script('teachers-calendar-js', 'teacherMap', $map_login_id);
    wp_localize_script('teachers-calendar-js', 'teacherMapConfig', $teacher_config_map);
    wp_localize_script('profesor-js', 'profesorAjax', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
    

    }

    // Método auxiliar para recoger reservas como array estructurado
    private function get_reservations_array($teacher_id)
    {
        $reservas = get_posts([
            'post_type' => 'reserva',
            'meta_query' => [
                [
                    'key' => 'teacher_id',
                    'value' => $teacher_id,
                    'compare' => '='
                ]
            ]
        ]);

        $eventos = [];

        foreach ($reservas as $reserva) {
            $fecha = get_post_meta($reserva->ID, 'date', true);
            $hora = get_post_meta($reserva->ID, 'time', true);
            $student_id = get_post_meta($reserva->ID, 'student_id', true);
            $student = get_user_by('id', $student_id);
            $title = $student ? $student->first_name . ' ' . $student->last_name : 'Reservado';

            $eventos[] = [
                'title' => $title,
                'start' => $fecha . 'T' . $hora,
                'backgroundColor' => '#007bff',
                'borderColor' => '#007bff'
            ];
        }

        return $eventos;
    }




}