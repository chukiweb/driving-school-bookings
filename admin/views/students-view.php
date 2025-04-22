<?php
class DSB_Students_View extends DSB_Base_View
{
    public function __construct()
    {
        $this->title = 'Gestión de Estudiantes';
        $this->nonce_action = 'create_student';
        $this->nonce_name = 'student_nonce';
    }

    protected function get_data()
    {
        return get_users(['role' => 'student']);
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

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
                    'assigned_vehicle' => $_POST['vehicle'],
                    'class_points' => $_POST['initial_points']
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

    protected function render_forms()
    {
        $teachers = get_users(['role' => 'teacher']);
        $vehicles = get_posts(['post_type' => 'vehiculo', 'posts_per_page' => -1]);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
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
                            <?php foreach ($teachers as $teacher): ?>
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
        <?php
    }

    protected function render_table()
    {
        $students = $this->get_data();
        ?>
        <h2>Listado de Estudiantes</h2>
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
                <?php foreach ($students as $student):
                    $teacher_id = get_user_meta($student->ID, 'assigned_teacher', true);
                    $vehicle_id = get_user_meta($student->ID, 'assigned_vehicle', true);
                    $teacher = get_user_by('id', $teacher_id);
                    $vehicle = get_post($vehicle_id);
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
                            <a href="#" class="button" data-id="<?php echo esc_attr($student->ID); ?>">Editar</a>
                            <a href="#" class="button" data-id="<?php echo esc_attr($student->ID); ?>">Eliminar</a>
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

    protected function enqueue_scripts()
    {
        
    }
}