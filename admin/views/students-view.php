<?php
class DSB_Students_View extends DSB_Base_View
{
    private $teachers;
    private $students;

    public function __construct()
    {
        $this->title = 'Gestión de Estudiantes';
        $this->nonce_action = 'create_student';
        $this->nonce_name = 'student_nonce';
        $this->teachers = get_users(['role' => 'teacher']);
        $this->students = get_users(['role' => 'student']);
    }

    protected function get_data() {}

    private function get_students_data()
    {
        $student_data = [];

        foreach ($this->students as $student) {
            $student_data[] = [
                'id' => $student->ID,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->user_email,
                'dni' => get_user_meta($student->ID, 'dni', true),
                'phone' => get_user_meta($student->ID, 'phone', true),
                'birth_date' => get_user_meta($student->ID, 'birth_date', true),
                'address' => get_user_meta($student->ID, 'address', true),
                'city' => get_user_meta($student->ID, 'city', true),
                'postal_code' => get_user_meta($student->ID, 'postal_code', true),
                'license_type' => get_user_meta($student->ID, 'license_type', true),
                'assigned_teacher_id' => get_user_meta($student->ID, 'assigned_teacher', true),
                'class_points' => get_user_meta($student->ID, 'class_points', true)
            ];
        }

        return $student_data;
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

        switch ($_POST['form_action']) {
            case 'create_student':
                $this->handle_create_student_form();
                break;
            case 'edit_student':
                $this->handle_edit_student_form();
                break;
        }
    }

    private function handle_create_student_form()
    {
        $username = strtolower(sanitize_text_field($_POST['first_name'])) . '.' . strtolower(sanitize_text_field($_POST['last_name']));
        $user_data = [
            'user_login' => $username,
            'user_pass'  => '23061981', // Contraseña por defecto, en produccion deberia ser generada con un enlace por mail al cliente.
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'role' => 'student'
        ];

        $user_id = wp_insert_user($user_data);

        if (!is_wp_error($user_id)) {
            try {
                // Metadatos del estudiante
                $meta_data = [
                    'dni' => $_POST['dni'],
                    'phone' => $_POST['phone'],
                    'birth_date' => $_POST['birth_date'],
                    'address' => $_POST['address'],
                    'city' => $_POST['city'],
                    'postal_code' => $_POST['postal_code'],
                    'license_type' => $_POST['license_type'],
                    'assigned_teacher' => $_POST['teacher'],
                    'class_points' => $_POST['opening_balance']
                ];

                foreach ($meta_data as $key => $value) {
                    update_user_meta($user_id, $key, sanitize_text_field($value));
                }

                // Generar enlace de recuperación
                $user = get_user_by('id', $user_id);
                if ($user) {
                    $key = get_password_reset_key($user);
                    if (!is_wp_error($key)) {
                        $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($username), 'login');

                        wp_mail(
                            $user_data['user_email'],
                            'Acceso a tu cuenta de estudiante',
                            sprintf(
                                'Bienvenido/a %s,\n\nTu cuenta ha sido creada. Establece tu contraseña aquí:\n\n%s\n\nUsuario: %s',
                                $user_data['first_name'],
                                $reset_link,
                                $username
                            )
                        );

                        $this->render_notice('Estudiante creado exitosamente. Se ha enviado un email con las instrucciones de acceso.');
                    }
                }
            } catch (Exception $e) {
                $this->render_notice('Error: ' . $e->getMessage(), 'error');
            }
        } else {
            $this->render_notice('Error al crear el usuario: ' . $user_id->get_error_message(), 'error');
        }
    }

    private function handle_edit_student_form()
    {
        $user_data = [
            'ID' => $_POST['user_id'],
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
        ];

        $user_id = wp_update_user($user_data);

        if (!is_wp_error($user_id)) {
            // Metadatos del estudiante
            $meta_data = [
                'dni' => $_POST['dni'],
                'phone' => $_POST['phone'],
                'birth_date' => $_POST['birth_date'],
                'address' => $_POST['address'],
                'city' => $_POST['city'],
                'postal_code' => $_POST['postal_code'],
                'license_type' => $_POST['license_type'],
                'assigned_teacher' => $_POST['teacher'],
                'class_points' => $_POST['opening_balance']
            ];

            foreach ($meta_data as $key => $value) {
                update_user_meta($user_id, $key, sanitize_text_field($value));
            }

            $this->render_notice('Estudiante editado exitosamente.');
        } else {
            $this->render_notice('Error al editar el usuario: ' . $user_id->get_error_message(), 'error');
        }
    }

    private function handle_delete_student_form()
    {
        $user_id = $_POST['user_id'];

        if (wp_delete_user($user_id)) {
            $this->render_notice('Estudiante eliminado exitosamente.');
        } else {
            $this->render_notice('Error al eliminar el usuario.', 'error');
        }
    }

    private function handle_points_form()
    {
        $user_id = $_POST['user_id'];
        $points = $_POST['points'];

        $current_points = get_user_meta($user_id, 'class_points', true);
        $new_points = $current_points + $points;

        update_user_meta($user_id, 'class_points', $new_points);

        $this->render_notice('Puntos añadidos exitosamente.');
    }

    protected function render_forms()
    {
?>
        <div>
            <h2>
                <span id="studentName"></span>
            </h2>
        </div>
    <?php
        $this->render_create_student_form();
        $this->render_edit_student_form();
        $this->render_delete_student_form();
        $this->render_points_form();
    }

    private function render_create_student_form()
    {
    ?>
        <div id="createFormContainer" data-action-id="create" style="display: none; margin-top: 20px;">
            <form method="post" id="crear-alumno-form" action="">
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="form_action" value="create_student" />

                <table class="form-table">
                    <tr>
                        <th><label for="first_name">Nombre</label></th>
                        <td><input type="text" name="first_name" required /></td>
                    </tr>
                    <tr>
                        <th><label for="last_name">Apellidos</label></th>
                        <td><input type="text" name="last_name" required /></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" name="email" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dni">DNI</label></th>
                        <td><input type="text" name="dni" required /></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Teléfono</label></th>
                        <td><input type="tel" name="phone" required /></td>
                    </tr>
                    <tr>
                        <th><label for="birth_date">Fecha de Nacimiento</label></th>
                        <td><input type="date" name="birth_date" required /></td>
                    </tr>
                    <tr>
                        <th><label for="address">Dirección</label></th>
                        <td><input type="text" name="address" required /></td>
                    </tr>
                    <tr>
                        <th><label for="city">Ciudad</label></th>
                        <td><input type="text" name="city" required /></td>
                    </tr>
                    <tr>
                        <th><label for="postal_code">Código Postal</label></th>
                        <td><input type="text" name="postal_code" required /></td>
                    </tr>
                    <tr>
                        <th><label for="license_type">Permiso al que aspira</label></th>
                        <td>
                            <select name="license_type" required>
                                <option value="B">B - Coche</option>
                                <option value="A">A - Moto</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="teacher">Profesor Asignado</label></th>
                        <td>
                            <select name="teacher" required>
                                <option value="">Seleccionar profesor</option>
                                <?php foreach ($this->teachers as $teacher): ?>
                                    <option value="<?php echo esc_attr($teacher->ID); ?>">
                                        <?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="opening_balance">Saldo de practicas</label></th>
                        <td><input type="number" name="opening_balance" min="0" required /></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Crear Alumno" />
                </p>
            </form>
        </div>
    <?php
    }

    private function render_edit_student_form()
    {
    ?>
        <div id="editFormContainer" data-action-id="edit" style="display: none; margin-top: 20px;">
            <form method="post" id="editar-alumno-form" action="">
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="form_action" value="edit_student" />
                <input type="hidden" name="user_id" value="" />

                <table class="form-table">
                    <tr>
                        <th><label for="first_name">Nombre</label></th>
                        <td><input type="text" name="first_name" required /></td>
                    </tr>
                    <tr>
                        <th><label for="last_name">Apellidos</label></th>
                        <td><input type="text" name="last_name" required /></td>
                    </tr>
                    <tr>
                        <th><label for="email">Email</label></th>
                        <td><input type="email" name="email" required /></td>
                    </tr>
                    <tr>
                        <th><label for="dni">DNI</label></th>
                        <td><input type="text" name="dni" required /></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Teléfono</label></th>
                        <td><input type="tel" name="phone" required /></td>
                    </tr>
                    <tr>
                        <th><label for="birth_date">Fecha de Nacimiento</label></th>
                        <td><input type="date" name="birth_date" required /></td>
                    </tr>
                    <tr>
                        <th><label for="address">Dirección</label></th>
                        <td><input type="text" name="address" required /></td>
                    </tr>
                    <tr>
                        <th><label for="city">Ciudad</label></th>
                        <td><input type="text" name="city" required /></td>
                    </tr>
                    <tr>
                        <th><label for="postal_code">Código Postal</label></th>
                        <td><input type="text" name="postal_code" required /></td>
                    </tr>
                    <tr>
                        <th><label for="license_type">Permiso al que aspira</label></th>
                        <td>
                            <select name="license_type" required>
                                <option value="B">B - Coche</option>
                                <option value="A">A - Moto</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="teacher">Profesor Asignado</label></th>
                        <td>
                            <select name="teacher" required>
                                <option value="">Seleccionar profesor</option>
                                <?php foreach ($this->teachers as $teacher): ?>
                                    <option value="<?php echo esc_attr($teacher->ID); ?>">
                                        <?php echo esc_html($teacher->first_name . ' ' . $teacher->last_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="opening_balance">Saldo de practicas</label></th>
                        <td><input type="number" name="opening_balance" min="0" required /></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Editar alumno" />
                </p>
            </form>
        </div>
    <?php
    }

    private function render_delete_student_form()
    {
    ?>
        <dialog id="deleteStudentModal">
            <form method="post" id="deleteStudentForm" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="user_id" value="" />
                <input type="hidden" name="form_action" value="delete_student" />

                <p>¿Estás seguro de que deseas eliminar este alumno?</p>

                <input type="submit" name="submit" class="button-primary" value="Eliminar" />
                <button type="button" onclick="document.getElementById('deleteStudentModal').close();" class="button button-secondary">Cancelar</button>

            </form>
        </dialog>
    <?php
    }

    private function render_points_form() {}

    protected function enqueue_scripts()
    {
        wp_enqueue_script('jquery');

        // wp_enqueue_script(
        //     'fullcalendar-js',
        //     DSB_PLUGIN_FULLCALENDAR_URL,
        //     ['jquery'],
        //     '5.11.3',
        //     true
        // );

        wp_enqueue_style(
            'student-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/student-view.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script('student-js', DSB_PLUGIN_URL . '../public/js/admin/students-admin-view.js', ['jquery'], '1.0.0', true);

        wp_localize_script('student-js', 'allStudentData', $this->get_students_data());

        wp_localize_script('student-js', 'studentAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    protected function render_table()
    {
    ?>
        <div class="heding">
            <h2>Listado de Profesores</h2>
            <div class="boton-heding">
                <button class="button button-primary" data-action-id="create">Nuevo alumno</button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>DNI</th>
                    <th>Teléfono</th>
                    <th>Ciudad</th>
                    <th>Permiso al que aspira</th>
                    <th>Profesor</th>
                    <th>Acciones</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->students as $student):
                    $teacher_id = get_user_meta($student->ID, 'assigned_teacher', true);
                    $teacher = get_user_by('id', $teacher_id);
                ?>
                    <tr>
                        <td><?php echo esc_html($student->first_name . ' ' . $student->last_name); ?></td>
                        <td><?php echo esc_html($student->user_email); ?></td>
                        <td><?php echo esc_html(get_user_meta($student->ID, 'dni', true)); ?></td>
                        <td><?php echo esc_html(get_user_meta($student->ID, 'phone', true)); ?></td>
                        <td><?php echo esc_html(get_user_meta($student->ID, 'city', true)); ?></td>
                        <td><?php echo esc_html(get_user_meta($student->ID, 'license_type', true)); ?></td>
                        <td><?php echo $teacher ? esc_html($teacher->first_name . ' ' . $teacher->last_name) : '—'; ?></td>
                        <td>
                            <a href="#" class="button" data-user-id="<?php echo esc_attr($student->ID); ?>" data-action-id="edit">Editar</a>
                            <a href="#" class="button" data-user-id="<?php echo esc_attr($student->ID); ?>" data-action-id="delete">Eliminar</a>
                        </td>
                        <td>
                            <?php echo esc_html(get_user_meta($student->ID, 'class_points', true)); ?>
                            <a href="#" class="button add-points" data-student="<?php echo esc_attr($student->ID); ?>">
                                Añadir Puntos
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
}
