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
        $students = get_users(['role' => 'estudiante']);
        $teachers = get_users(['role' => 'profesor']);
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
                    $student = get_user_by('id', get_post_meta($booking->ID, 'student_id', true));
                    $teacher = get_user_by('id', get_post_meta($booking->ID, 'teacher_id', true));
                    $vehicle = get_post($booking->vehicle_id);
                ?>
                <tr>
                    <td><?php echo esc_html($student->display_name); ?></td>
                    <td><?php echo esc_html($teacher->display_name); ?></td>
                    <td><?php echo esc_html($vehicle->post_title); ?></td>
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