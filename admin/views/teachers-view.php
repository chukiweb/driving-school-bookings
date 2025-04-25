<?php
class DSB_Teachers_View extends DSB_Base_View
{
    private $vehicles;
    private $teachers;
    private $students;

    function __construct()
    {
        $this->title = 'Gestión de Profesores';
        $this->nonce_action = 'process_teacher_form';
        $this->nonce_name = 'teacher_nonce';
        $this->vehicles = get_posts([
            'post_type' => 'vehiculo',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        $this->teachers = get_users([
            'role' => 'teacher'
        ]);
        $this->students = get_users(['role' => 'student']);
    }

    protected function get_data() {}

    protected function get_vehicles(string $type): array
    {
        $vehiculos = ['car' => [], 'motorcycle' => []];
        foreach ($this->vehicles as $vehicle) {
            if (get_post_meta($vehicle->ID, 'vehicle_type', true) === 'car') {
                $vehiculos['car'][] = [
                    'id' => $vehicle->ID,
                    'title' => $vehicle->post_title,
                ];
            } elseif (get_post_meta($vehicle->ID, 'vehicle_type', true) === 'motorcycle') {
                $vehiculos['motorcycle'][] = [
                    'id' => $vehicle->ID,
                    'title' => $vehicle->post_title,
                ];
            }
        }

        return $vehiculos[$type];
    }

    private function get_teacher_vehicles($teacher_id)
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

    private function get_teacher_data()
    {
        $all_teacher_data = [];

        $teachers = $this->teachers;

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
                    'events'        => DSB_Calendar_Service::get_teacher_calendar($teacher->ID),
                ];

                $all_teacher_data[] = $teacher_info;
            }
        }

        return $all_teacher_data;
    }

    protected function get_students_data(): array
    {
        $all_student_data = [];
        $students = $this->students;

        if (!empty($students)) {
            foreach ($students as $student) {
                $teacher = get_user_by('id', get_user_meta($student->ID, 'assigned_teacher', true));

                $student_info = [
                    'id' => $student->ID,
                    'name' => get_user_meta($student->ID, 'first_name', true) . ' ' . get_user_meta($student->ID, 'last_name', true),
                    'license_type' => get_user_meta($student->ID, 'license_type', true),
                    'profesordata' => [
                        'id' => $teacher->ID,
                        'name' => get_user_meta($teacher->ID, 'first_name', true) . ' ' . get_user_meta($teacher->ID, 'last_name', true),
                        'vehicle' => $this->get_teacher_vehicles($teacher->ID),
                    ],
                ];

                $all_student_data[] = $student_info;
            }
        }

        return $all_student_data;
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
            case 'create_booking':
                $this->handle_create_booking_form();
                break;
            case 'delete_booking':
                $this->handle_delete_booking_form();
                break;
        }
    }

    protected function handle_create_teacher_form()
    {
        $this->render_response(DSB()->user_manager->create_teacher());
    }

    public function handle_edit_teacher_form()
    {
        $this->render_response(DSB()->user_manager->update_teacher());
    }

    public function handle_config_lesson_form()
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
        $user_id = $_POST['user_id'];

        $this->render_response(DSB()->user_manager->delete_student($user_id));
    }

    private function handle_create_booking_form()
    {
        $start_time = sanitize_text_field($_POST['time']);
        $end_time = isset($_POST['end_time']) && $_POST['end_time'] !== '' ? sanitize_text_field($_POST['end_time']) : null;
        $date = sanitize_text_field($_POST['date']);

        $datetime_inicio = new DateTime("$date $start_time");
        if ($end_time !== null) {
            $datetime_fin = new DateTime("$date $end_time");
        } else {
            $datetime_fin = clone $datetime_inicio;
            $datetime_fin->modify('+45 minutes');
        }
        $end_time = $datetime_fin->format('H:i');

        $post_data = [
            'post_title' => sprintf(
                'Reserva - %s - %s',
                sanitize_text_field($_POST['student']),
                $date
            ),
            'post_type' => 'reserva',
            'post_status' => 'publish',
            'meta_input' => [
                'student_id' => sanitize_text_field($_POST['student']),
                'teacher_id' => sanitize_text_field($_POST['teacher_id']),
                'vehicle_id' => sanitize_text_field($_POST['vehicle_id']),
                'date' => $date,
                'time' => $start_time,
                'end_time' => $end_time,
                'status' => 'pending'
            ]
        ];
        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            $this->render_notice('Reserva creada exitosamente');
        } else {
            $this->render_notice('Error al crear la reserva', 'error');
        }
    }

    private function handle_delete_booking_form()
    {
        if (isset($_POST['booking_id'])) {
            $booking_id = intval($_POST['booking_id']);
            $result = wp_delete_post($booking_id, true);

            if ($result) {
                $this->render_notice('Reserva eliminada exitosamente');
            } else {
                $this->render_notice('Error al eliminar la reserva', 'error');
            }
        }
    }

    protected function render_forms()
    {
?>
        <div>
            <h2>
                <span id="teacherName"></span>
            </h2>
        </div>
    <?php
        $this->render_create_teacher_form();
        $this->render_edit_teacher_form();
        $this->render_config_teacher_form();
        $this->render_delete_teacher_form();
        $this->render_teacher_calendar();
    }

    private function render_create_teacher_form()
    {
    ?>
        <div id="createFormContainer" data-action-id="create" style="display: none; margin-top: 20px;">
            <form method="post" id="crear-profesor-form" action="">
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <table class="form-table">
                    <!-- <tr>
                        <th><label for="password">Contraseña</label></th>
                        <td><input type="password" name="password" required /></td>
                    </tr> -->
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
                                <?php foreach ($this->get_vehicles('car') as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo['id']); ?>">
                                        <?php echo esc_html($vehiculo['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                    <tr>
                        <th><label for="assign_motorcycle">Moto asignada (opcional)</label></th>
                        <td> <select name="assign_motorcycle" class="form-control">
                                <option value="">-- Selecciona una moto --</option>
                                <?php foreach ($this->get_vehicles('motorcycle') as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo['id']); ?>">
                                        <?php echo esc_html($vehiculo['title']); ?>
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
    <?php
    }

    private function render_edit_teacher_form()
    {
    ?>
        <div id="editFormContainer" data-action-id="edit" style="display: none; margin-top: 20px;">
            <form method="post" id="editar-profesor-form" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="user_id" value="" />

                <table class="form-table">
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
                                <?php foreach ($this->get_vehicles('car') as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo['id']); ?>">
                                        <?php echo esc_html($vehiculo['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></td>
                    </tr>
                    <tr>
                        <th><label for="assign_motorcycle">Moto asignada (opcional)</label></th>
                        <td> <select name="assign_motorcycle" class="form-control">
                                <option value="">-- Selecciona una moto --</option>
                                <?php foreach ($this->get_vehicles('motorcycle') as $vehiculo): ?>
                                    <option value="<?php echo esc_attr($vehiculo['id']); ?>">
                                        <?php echo esc_html($vehiculo['title']); ?>
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
    <?php
    }

    private function render_config_teacher_form()
    {
    ?>
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
    <?php
    }

    private function render_teacher_calendar()
    {
    ?>
        <div id="teacherCalendarContainer" data-action-id="open-calendar" style="display:none; margin-top: 30px;">
            <h2>Calendario del Profesor</h2>
            <div id="teacherCalendar" style="min-height: 600px;"></div>
            <dialog id="teacherCalendarModal">
                <form method="post" id="teacherCalendarForm" action="">
                    <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                    <input type="hidden" name="form_action" value="create_booking" />
                    <input type="hidden" name="teacher_id" value="" />
                    <input type="hidden" name="vehicle_id" value="" />

                    <table class="form-table">
                        <tr>
                            <th><label for="student">Estudiante</label></th>
                            <td>
                                <select name="student" required>
                                    <option value="">Seleccionar estudiante</option>
                                    <?php foreach ($this->students as $student): ?>
                                        <option value="<?php echo esc_attr($student->ID); ?>">
                                            <?php echo esc_html($student->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><label for="teacher">Profesor</label></th>
                            <td>
                                <input type="text" name="teacher" required readonly />
                            </td>
                        </tr>

                        <tr>
                            <th><label for="license_type">Licencia</label></th>
                            <td>
                                <input type="text" name="license_type" required readonly />
                            </td>
                        </tr>

                        <tr>
                            <th><label for="vehicle">Vehículo</label></th>
                            <td>
                                <input type="text" name="vehicle" required readonly />
                            </td>
                        </tr>

                        <tr>
                            <th><label for="date">Fecha</label></th>
                            <td><input type="date" name="date" required /></td>
                        </tr>

                        <tr>
                            <th><label for="time">Hora de inicio</label></th>
                            <td><input type="time" name="time" required /></td>
                        </tr>

                        <tr>
                            <th><label for="end">Hora de finalizacion</label></th>
                            <td><input type="time" name="end_time" required /></td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button-primary" value="Crear Reserva" />
                    </p>
                </form>
                </form>
            </dialog>

            <dialog id="teacherCalendarInfoModal">
                <form method="post" id="teacherCalendarInfoForm" action="">
                    <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                    <input type="hidden" name="form_action" value="delete_booking" />
                    <input type="hidden" name="booking_id" value="" />
                    <h3></h3>

                    <p>¿Estás seguro de que deseas eliminar esta reserva?</p>

                    <input type="submit" name="submit" class="button-primary" value="Eliminar Reserva" />
                    <button type="button" onclick="document.getElementById('teacherCalendarInfoModal').close();" class="button button-secondary">Cancelar</button>
                </form>
            </dialog>
        </div>
    <?php
    }

    private function render_delete_teacher_form()
    {
    ?>
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
    }

    protected function enqueue_scripts()
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
            'teacher-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/teacher-view.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script('profesor-js', DSB_PLUGIN_URL . '../public/js/admin/teacher-admin-view.js', ['jquery'], '1.0.0', true);

        wp_localize_script('profesor-js', 'allStudentData', $this->get_students_data());

        wp_localize_script('profesor-js', 'allTeacherData', $this->get_teacher_data());

        wp_localize_script('profesor-js', 'profesorAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    protected function render_table()
    {
    ?>

        <div class="heding">
            <h2>Listado de Profesores</h2>
            <div class="boton-heding">
                <button class="button button-primary" data-action-id="create">Nuevo profesor</button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Vehículo</th>
                    <th>Moto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->teachers as $teacher): ?>
                    <tr>
                        <td><?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?></td>
                        <td><?php echo esc_html($teacher->user_email); ?></td>
                        <td><?php echo esc_html(get_user_meta($teacher->ID, 'phone', true)); ?></td>
                        <td>
                            <?php
                            $vehiculos = $this->get_teacher_vehicles($teacher->ID);
                            echo esc_html($vehiculos['car']['title'] ?? 'Sin vehículo');
                            ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html($vehiculos['motorcycle']['title'] ?? 'Sin moto');
                            ?>
                        </td>
                        <td>
                            <a href="#" class="button edit-teacher" data-action-id="edit" data-user-id=<?php echo esc_html($teacher->ID); ?>>Editar</a>
                            <a href="#" class="button" data-action-id="delete" data-user-id=<?php echo esc_html($teacher->ID); ?>>Eliminar</a>
                            <a href="#" class="button" data-action-id="open-calendar" data-user-id=<?php echo esc_html($teacher->ID); ?>>Calendario</a>
                            <a href="#" class="button" data-action-id="open-config" data-user-id=<?php echo esc_html($teacher->ID); ?>>Configurar clases</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
}
