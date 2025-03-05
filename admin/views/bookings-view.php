<?php
class DSB_Bookings_View extends DSB_Base_View {
    public function __construct() {
        $this->title = 'Gestión de Reservas';
        $this->nonce_action = 'create_booking';
        $this->nonce_name = 'booking_nonce';
    }

    protected function get_data() {
        return get_posts([
            'post_type' => 'reserva',
            'posts_per_page' => -1,
        ]);
    }

    protected function handle_form_submission() {
        $this->verify_nonce();

        $post_data = [
            'post_title' => sprintf(
                'Reserva - %s - %s',
                sanitize_text_field($_POST['student']),
                sanitize_text_field($_POST['date'])
            ),
            'post_type' => 'reserva',
            'post_status' => 'publish',
            'meta_input' => [
                'student_id' => sanitize_text_field($_POST['student']),
                'teacher_id' => sanitize_text_field($_POST['teacher']),
                'vehicle_id' => sanitize_text_field($_POST['vehicle']),
                'date' => sanitize_text_field($_POST['date']),
                'time' => sanitize_text_field($_POST['time']),
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

    protected function render_form() {
        $students = get_users(['role' => 'student']);
        $teachers = get_users(['role' => 'teacher']);
        $vehicles = get_posts(['post_type' => 'vehiculo', 'posts_per_page' => -1]);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
            <table class="form-table">
                <tr>
                    <th><label for="student">Estudiante</label></th>
                    <td>
                        <select name="student" required>
                            <option value="">Seleccionar estudiante</option>
                            <?php foreach ($students as $student): ?>
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
                        <select name="teacher" required>
                            <option value="">Seleccionar profesor</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo esc_attr($teacher->ID); ?>">
                                    <?php echo esc_html($teacher->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="vehicle">Vehículo</label></th>
                    <td>
                        <select name="vehicle" required>
                            <option value="">Seleccionar vehículo</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo esc_attr($vehicle->ID); ?>">
                                    <?php echo esc_html($vehicle->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="date">Fecha</label></th>
                    <td><input type="date" name="date" required /></td>
                </tr>
                <tr>
                    <th><label for="time">Hora</label></th>
                    <td><input type="time" name="time" required /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear Reserva" />
            </p>
        </form>
        <?php
    }

    protected function render_table() {
        $bookings = $this->get_data();
        ?>
        <h2>Listado de Reservas</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Profesor</th>
                    <th>Vehículo</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): 
                    // Obtener el ID de estudiante y profesor
                    $student_id = get_post_meta($booking->ID, 'student_id', true);
                    $teacher_id = get_post_meta($booking->ID, 'teacher_id', true);
                    
                    // Obtener los objetos de usuario
                    $student = !empty($student_id) ? get_user_by('id', $student_id) : false;
                    $teacher = !empty($teacher_id) ? get_user_by('id', $teacher_id) : false;
                    
                    // Obtener vehículo
                    $vehicle = get_post(get_post_meta($booking->ID, 'vehicle_id', true));
                ?>
                <tr>
                    <td><?php echo $student ? esc_html($student->display_name) : 'No asignado'; ?></td>
                    <td><?php echo $teacher ? esc_html($teacher->display_name) : 'No asignado'; ?></td>
                    <td><?php echo ($vehicle && !is_wp_error($vehicle)) ? esc_html($vehicle->post_title) : 'Vehículo no encontrado'; ?></td>
                    <td><?php echo esc_html(get_post_meta($booking->ID, 'date', true)); ?></td>
                    <td><?php echo esc_html(get_post_meta($booking->ID, 'time', true)); ?></td>
                    <td><?php echo esc_html(get_post_meta($booking->ID, 'status', true)); ?></td>
                    <td>
                        <a href="#" class="button">Editar</a>
                        <a href="#" class="button">Cancelar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}