<?php
class DSB_Teachers_View extends DSB_Base_View
{
    public function __construct()
    {
        $this->title = 'Gestión de Profesores';
        $this->nonce_action = 'process_teacher_form';
        $this->nonce_name = 'teacher_nonce';
    }

    protected function get_data()
    {
        return get_users(['role' => 'teacher'],);
    }

    public static function get_teacher_vehicles($teacher_id)
    {
        $coche_id = get_user_meta($teacher_id, 'assigned_vehicle', true) ?: null;
        $moto_id = get_user_meta($teacher_id, 'assigned_motorcycle', true) ?: null;

        $coche = $coche_id ? get_post($coche_id) : null;
        $moto = $moto_id ? get_post($moto_id) : null;

        return [
            'car' => $coche ? [
                'id' => $coche->ID,
                'title' => $coche->post_title,
            ] : null,
            'motorcycle' => $moto ? [
                'id' => $moto->ID,
                'title' => $moto->post_title,
            ] : null,
        ];
    }

    protected function get_events($teacher_id): array 
    {
        $events = DSB_Calendar_Service::get_teacher_calendar($teacher_id);

        return $events;
    }

    public function handle_form_submission()
    {
        $this->verify_nonce();

        switch ($_POST['form_action']) {
            case 'create_teacher':
                $this->handle_create_teacher_form();
                break;
            case 'edit_teacher':
                $this->handle_edit_teacher_form();
                break;
            case 'config_teacher':
                $this->handle_config_lesson_form();
                break;
            case 'delete_teacher':
                $this->handle_delete_teacher_form();
                break;
        }
    }

    protected function handle_create_teacher_form()
    {
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

            update_user_meta($user_id, 'assigned_vehicle', sanitize_text_field($_POST['assigned_vehicle']));

            ($_POST['assign_motorcycle']) ? update_user_meta($user_id, 'assigned_motorcycle', sanitize_text_field($_POST['assign_motorcycle'])) : '';

            ($_POST['phone']) ? update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone'])) : '';

            // Enviar correo predeterminado de WordPress para asignar la contraseña
            $user = get_user_by('id', $user_id);

            wp_mail(
                $user->user_email,
                $user->first_name . ', establece tu contraseña',
                sprintf(
                    'Bienvenido a nuestro equipo. Para establecer tu contraseña, haz clic en el siguiente enlace: %s',
                    network_site_url("wp-login.php?action=rp&key=" . get_password_reset_key($user) . "&login=" . rawurlencode($user->user_login), 'login')
                )
            );

            $this->render_notice('Profesor creado exitosamente');
        } else {
            $this->render_notice($user_id->get_error_message(), 'error');
        }
    }

    public function handle_edit_teacher_form(): void
    {
        $user_data = [
            'ID' => intval($_POST['user_id']),
            'user_login' => sanitize_text_field($_POST['username']),
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'role' => 'teacher'
        ];

        $user_id = wp_update_user($user_data);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'assigned_vehicle', sanitize_text_field($_POST['assigned_vehicle']));
            update_user_meta($user_id, 'assigned_motorcycle', sanitize_text_field($_POST['assign_motorcycle']));
            update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));

            $this->render_notice('Profesor actualizado exitosamente');
        } else {
            $this->render_notice($user_id->get_error_message(), 'error');
        }
    }

    public function handle_config_lesson_form(): void
    {

        $teacher_id = intval($_POST['user_id']);

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

    public function handle_delete_teacher_form(): void
    {
        if (isset($_POST['user_id'])) {
            $user_id = intval($_POST['user_id']);
            $result = wp_delete_user($user_id);

            if ($result) {
                $this->render_notice('Profesor eliminado exitosamente');
            } else {
                $this->render_notice('Error al eliminar el profesor', 'error');
            }
        }
    }


    protected function render_form()
    {
        $vehicles = get_posts(['post_type' => 'vehiculo', 'posts_per_page' => -1]);
?>

        <div id="createFormContainer" data-action-id="create" style="display: none; margin-top: 20px;">
            <form method="post" id="crear-profesor-form" action="">
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
                        <td> <select name="assigned_vehicle" class="form-control">
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
                        <td> <select name="assign_motorcycle" class="form-control">
                                <option value="">-- Selecciona una moto --</option>
                                <?php foreach ($vehicles as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo->ID); ?>">
                                        <?php echo esc_html($vehiculo->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                </table>

                <input type="hidden" name="form_action" value="create_teacher" />

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Crear profesor" />
                </p>
            </form>
        </div>

        <div id="editFormContainer" data-action-id="edit" style="display: none; margin-top: 20px;">
            <form method="post" id="editar-profesor-form" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="user_id" value="" />

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
                        <td> <select name="assigned_vehicle" class="form-control">
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
                        <td> <select name="assign_motorcycle" class="form-control">
                                <option value="">-- Selecciona una moto --</option>
                                <?php foreach ($vehicles as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo->ID); ?>">
                                        <?php echo esc_html($vehiculo->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                </table>

                <input type="hidden" name="form_action" value="edit_teacher" />

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Actualizar profesor" />
                </p>
            </form>
        </div>

        <div id="configFormContainer" data-action-id="open-config" style="display: none; margin-top: 20px;">
            <form method="post" id="form-clases-profesor" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="user_id" value="" />

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

                <input type="hidden" name="form_action" value="config_teacher" />
                <input type="submit" name="submit" class="button-primary" value="Guardar configuracion" />

            </form>
        </div>

        <div id="teacherCalendarContainer" style="display:none; margin-top: 30px;">
            <h2>Calendario del Profesor</h2>
            <div id="teacherCalendar" style="min-height: 600px;"></div>
        </div>

        <dialog id="deleteTeacherModal">
            <form method="post" id="deleteTeacherForm" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="user_id" value="" />
                <input type="hidden" name="form_action" value="delete_teacher" />

                <p>¿Estás seguro de que deseas eliminar este profesor?</p>

                <input type="submit" name="submit" class="button-primary" value="Eliminar" />
                <button type="button" onclick="document.getElementById('deleteTeacherModal').close();" class="button button-secondary">Cancelar</button>

            </form>
        </dialog>

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
                <button id="mostrar-form-crear-profesor" class="button button-primary" data-action-id="create">Nuevo Profesor</button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td data-login="<?php echo esc_attr($teacher->user_login); ?>" data-user-id="<?php echo esc_attr($teacher->ID); ?>">
                            <?php echo esc_html($teacher->user_login); ?>
                        </td>
                        <td><?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?></td>
                        <td><?php echo esc_html($teacher->user_email); ?></td>
                        <td>
                            <a href="#" class="button edit-teacher" data-action-id="edit" data-user-id=<?php echo esc_html($teacher->ID); ?>>Editar</a>
                            <a href="#" class="button" data-action-id="delete" data-user-id=<?php echo esc_html($teacher->ID); ?>>Eliminar</a>
                            <a href="#" class="button" data-action-id="open-calendar" data-user-id=<?php echo esc_html($teacher->ID); ?>>Calendario</a>
                            <a href="#" class="button" data-action-id="open-config" data-user-id=<?php echo esc_html($teacher->ID); ?>>Admin
                                clases</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }


    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'fullcalendar-js',
            DSB_PLUGIN_FULLCALENDAR_URL,
            ['jquery'],
            '5.11.3',
            true
        );

        wp_enqueue_style(
            'teacher-calendar-css',
            DSB_PLUGIN_URL . '../public/css/admin/teacher-view.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script('profesor-js', DSB_PLUGIN_URL . '../public/js/admin/teacher-admin-view.js', ['jquery'], '1.0.0', true);

        $all_teacher_data = [];

        $teachers = $this->get_data();

        if (!empty($teachers)) {
            foreach ($teachers as $teacher) {
                $vehiculos = $this->get_teacher_vehicles($teacher->ID);

                $teacher_info = [
                    'id'            => $teacher->ID,
                    'username'      => $teacher->user_login,
                    'firstName'     => get_user_meta($teacher->ID, 'first_name', true),
                    'lastName'      => get_user_meta($teacher->ID, 'last_name', true),
                    'email'         => $teacher->user_email,
                    'phone'         => get_user_meta($teacher->ID, 'phone', true),
                    'vehicleId'     => $vehiculos['car']['id'] ?? '',
                    'motorcycleId'  => $vehiculos['motorcycle']['id'] ?? '',
                    'config'        => get_user_meta($teacher->ID, 'dsb_clases_config', true),
                    'events'       => $this->get_events($teacher->ID),
                ];

                $all_teacher_data[] = $teacher_info;
            }
        }

        wp_localize_script('profesor-js', 'allTeacherData', $all_teacher_data);

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
