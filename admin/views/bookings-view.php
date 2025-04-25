<?php
class DSB_Bookings_View extends DSB_Base_View
{
    private $students;
    private $bookings;

    public function __construct()
    {
        $this->title = 'Gestión de Reservas';
        $this->nonce_action = 'create_booking';
        $this->nonce_name = 'booking_nonce';
        $this->students = get_users(['role' => 'student']);
        $this->bookings = get_posts([
            'post_type' => 'reserva',
            'posts_per_page' => -1,
        ]);
    }

    private function get_teacher_vehicles($teacher_id): array
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

    protected function get_bookings_data()
    {
        $all_booking_data = [];
        $bookings = get_posts([
            'post_type' => 'reserva',
            'posts_per_page' => -1,
        ]);

        foreach ($bookings as $booking) {
            $student_id = get_post_meta($booking->ID, 'student_id', true);
            $teacher_id = get_post_meta($booking->ID, 'teacher_id', true);
            $vehicle_id = get_post_meta($booking->ID, 'vehicle_id', true);

            // Get user objects
            $student = !empty($student_id) ? get_user_by('id', $student_id) : false;
            $teacher = !empty($teacher_id) ? get_user_by('id', $teacher_id) : false;
            $vehicle = !empty($vehicle_id) ? get_post($vehicle_id) : false;

            // Get student display name properly
            $student_name = 'No asignado';
            if ($student) {
                $first_name = get_user_meta($student_id, 'first_name', true);
                $last_name = get_user_meta($student_id, 'last_name', true);
                $student_name = !empty($first_name) || !empty($last_name) ?
                    trim($first_name . ' ' . $last_name) : $student->display_name;
            }

            $teacher_name = 'No asignado';
            if ($teacher) {
                $first_name = get_user_meta($teacher_id, 'first_name', true);
                $last_name = get_user_meta($teacher_id, 'last_name', true);
                $teacher_name = !empty($first_name) || !empty($last_name) ?
                    trim($first_name . ' ' . $last_name) : $teacher->display_name;
            }

            $all_booking_data[] = [
                'id' => $booking->ID,
                'student' => [
                    'id' => $student_id,
                    'name' => $student_name
                ],
                'teacher' => [
                    'id' => $teacher_id,
                    'name' => $teacher_name
                ],
                'vehicle' => [
                    'id' => $vehicle_id,
                    'name' => ($vehicle && !is_wp_error($vehicle)) ? $vehicle->post_title : 'Vehículo no encontrado'
                ],
                'date' => get_post_meta($booking->ID, 'date', true),
                'time' => get_post_meta($booking->ID, 'time', true),
                'end_time' => get_post_meta($booking->ID, 'end_time', true),
                'status' => get_post_meta($booking->ID, 'status', true)
            ];
        }

        // Ordenar reservaras por fecha y hora
        // usort($all_booking_data, function ($a, $b) {
        //     $date_compare = strcmp($a['date'], $b['date']);
        //     if ($date_compare !== 0) {
        //         return $date_compare;
        //     }
        //     return strcmp($a['time'], $b['time']);
        // });

        return $all_booking_data;
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();
        switch ($_POST['form_action']) {
            case 'create_booking':
                $this->handle_create_booking_form();
                break;
            case 'cancel_booking':
                $this->handle_cancel_booking_form();
                break;
        }
    }

    protected function handle_create_booking_form()
    {
        $start_time = sanitize_text_field($_POST['time']);
        $date = sanitize_text_field($_POST['date']);

        $datetime_inicio = new DateTime("$date $start_time");
        $datetime_fin = clone $datetime_inicio;
        $datetime_fin->modify('+45 minutes');
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

    protected function handle_cancel_booking_form()
    {
        if (isset($_POST['booking_id'])) {
            $booking_id = intval($_POST['booking_id']);
            update_post_meta($booking_id, 'status', 'cancelled');

            $this->render_notice('Reserva cancelada exitosamente');
        }
    }

    protected function render_forms()
    {
        $this->render_create_booking_form();
        $this->render_accept_booking_form();
        $this->render_cancel_booking_form();
    }

    private function render_create_booking_form()
    {
?>

        <div id="createFormContainer" data-action-id="create" style="display: none; margin-top: 20px;">
            <form method="post" id="createBookingForm" action>
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="form_action" value="create_booking" />
                <input type="hidden" name="teacher_id" value="" />
                <input type="hidden" name="vehicle_id" value="" />

                <table class="form-table">
                    <tr>
                        <th><label for="student">Alumno</label></th>
                        <td>
                            <select name="student" required>
                                <option value="">Seleccionar alumno</option>
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
                        <th><label for="time">Hora</label></th>
                        <td><input type="time" name="time" required /></td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Crear Reserva" />
                </p>
            </form>
        </div>
    <?php
    }

    private function render_accept_booking_form()
    {
    ?>
        <dialog id="acceptBookingModal">
            <form method="post" id="acceptBookingForm" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="booking_id" value="" />
                <input type="hidden" name="form_action" value="accept_booking" />

                <div>
                    <h3>Detalles de la reserva</h3>
                    <p><strong>Alumno:</strong> <span class="student-name"></span></p>
                    <p><strong>Profesor:</strong> <span class="teacher-name"></span></p>
                    <p><strong>Vehículo:</strong> <span class="vehicle-name"></span></p>
                    <p><strong>Fecha:</strong> <span class="booking-date"></span></p>
                    <p><strong>Hora:</strong> <span class="booking-time"></span></p>
                    <p><strong>Estado:</strong> <span class="booking-status"></span></p>
                </div>
                <p><strong>¿Estás seguro de aceptar esta reserva?</strong></p>

                <input type="submit" name="submit" class="button-primary" value="Aceptar reserva" />
                <button type="button" onclick="document.getElementById('acceptBookingModal').close();" class="button button-secondary">Volver</button>

            </form>
        </dialog>
    <?php
    }

    private function render_cancel_booking_form()
    {
    ?>
        <dialog id="cancelBookingModal">
            <form method="post" id="cancelBookingForm" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="booking_id" value="" />
                <input type="hidden" name="form_action" value="cancel_booking" />

                <div>
                    <h3>Detalles de la reserva</h3>
                    <p><strong>Alumno:</strong> <span class="student-name"></span></p>
                    <p><strong>Profesor:</strong> <span class="teacher-name"></span></p>
                    <p><strong>Vehículo:</strong> <span class="vehicle-name"></span></p>
                    <p><strong>Fecha:</strong> <span class="booking-date"></span></p>
                    <p><strong>Hora:</strong> <span class="booking-time"></span></p>
                    <p><strong>Estado:</strong> <span class="booking-status"></span></p>
                </div>
                <p><strong>¿Estás seguro de cancelar esta reserva?</strong></p>

                <input type="submit" name="submit" class="button-primary" value="Cancelar reserva" />
                <button type="button" onclick="document.getElementById('cancelBookingModal').close();" class="button button-secondary">Volver</button>

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
            'booking-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/booking-view.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script('booking-js', DSB_PLUGIN_URL . '../public/js/admin/booking-admin-view.js', ['jquery'], '1.0.0', true);

        wp_localize_script('booking-js', 'allStudentData', $this->get_students_data());

        wp_localize_script('booking-js', 'allBookingsData', $this->get_bookings_data());

        wp_localize_script('booking-js', 'bookingAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    protected function render_table()
    {
    ?>
        <div class="heding">
            <h2>Listado de Reservas</h2>
            <div class="boton-heding">
                <button class="button button-primary" data-action-id="create">Añadir reserva</button>
            </div>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Profesor</th>
                    <th>Vehículo</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->bookings as $booking):
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
                            <a href="#" class="button" data-action-id="accept" data-booking-id="<?= $booking->ID ?>">Aceptar</a>
                            <a href="#" class="button" data-action-id="cancel" data-booking-id="<?= $booking->ID ?>">Cancelar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
}
