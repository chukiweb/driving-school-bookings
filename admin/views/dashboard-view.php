<?php

class DSB_Dashboard_View extends DSB_Base_View
{

    private $teachers;
    private $students;

    public function __construct()
    {
        $this->title = 'Dashboard Autoescuela';
        $this->nonce_action = 'dashboard_settings_nonce';
        $this->nonce_name = 'dashboard_settings_nonce_field';

        // Cargar usuarios para los buscadores
        $this->teachers = get_users(['role' => 'teacher']);
        $this->students = get_users(['role' => 'student']);
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

        switch ($_POST['form_action']) {
            case 'save_settings':
                $this->handle_save_settings_form();
                break;
        }
    }

    private function handle_save_settings_form()
    {
        if (current_user_can('administrator')) {
            DSB_Settings::update('pickup_location', sanitize_text_field($_POST['pickup_location']));
            DSB_Settings::update('cancelation_time_hours', intval($_POST['cancelation_time_hours']));
            DSB_Settings::update('daily_limit', intval($_POST['daily_limit']));
            DSB_Settings::update('class_cost', floatval($_POST['class_cost']));
            DSB_Settings::update('class_duration', intval($_POST['class_duration']));
            DSB_Settings::update('default_booking_status', sanitize_text_field($_POST['default_booking_status']));
            DSB_Settings::update('default_min_antelacion', intval($_POST['default_min_antelacion']));
            DSB_Settings::update('default_max_antelacion', intval($_POST['default_max_antelacion']));

            // Nuevos campos de Pusher Beams
            if (isset($_POST['pusher_beams_instance_id'])) {
                $instance_id = sanitize_text_field($_POST['pusher_beams_instance_id']);
                DSB_Settings::update('pusher_beams_instance_id', $instance_id);
            }

            if (isset($_POST['pusher_beams_secret_key'])) {
                $secret_key = sanitize_text_field($_POST['pusher_beams_secret_key']);
                DSB_Settings::update('pusher_beams_secret_key', $secret_key);
            }

            $this->render_response([
                'message' => 'Ajustes guardados correctamente.',
                'sent' => true
            ]);
        }
    }

    protected function render_forms()
    {
        $this->render_general_settings_form();
        $this->render_search_section();
    }

    protected function render_general_settings_form()
    {
?>
        <div class="dsb-section dsb-card">
            <h2><i class="dashicons dashicons-admin-generic"></i> Ajustes Generales del Sistema</h2>

            <form method="post" id="general-settings" action="">
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="form_action" value="save_settings" />

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="pickup_location">Ubicación de recogida</label></th>
                        <td>
                            <input type="text" id="pickup_location" name="pickup_location" value="<?= esc_attr(DSB_Settings::get('pickup_location')); ?>" class="regular-text">
                            <p class="description">Introduce la ubicación de recogida para las clases.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cancelation_time_hours">Tiempo de cancelación (horas)</label></th>
                        <td><input type="number" id="cancelation_time_hours" name="cancelation_time_hours" value="<?= esc_attr(DSB_Settings::get('cancelation_time_hours')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="daily_limit">Clases diarias por alumno</label></th>
                        <td><input type="number" id="daily_limit" name="daily_limit" value="<?= esc_attr(DSB_Settings::get('daily_limit')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="class_cost">Coste por clase (créditos)</label></th>
                        <td><input type="number" step="0.1" id="class_cost" name="class_cost" value="<?= esc_attr(DSB_Settings::get('class_cost')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="class_duration">Duración de clase (minutos)</label></th>
                        <td><input type="number" id="class_duration" name="class_duration" value="<?= esc_attr(DSB_Settings::get('class_duration')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_booking_status">Estado por defecto de las reservas</label></th>
                        <td>
                            <select id="default_booking_status" name="default_booking_status">
                                <option value="accepted" <?php selected(DSB_Settings::get('default_booking_status'), 'accepted'); ?>>Aceptada</option>
                                <option value="pending" <?php selected(DSB_Settings::get('default_booking_status'), 'pending'); ?>>Pendiente</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_min_antelacion">Antelación mínima para reservar (horas)</label></th>
                        <td>
                            <input type="number" id="default_min_antelacion" name="default_min_antelacion" value="<?= esc_attr(DSB_Settings::get('default_min_antelacion')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_max_antelacion">Antelación máxima para reservar (días)</label></th>
                        <td>
                            <input type="number" id="default_max_antelacion" name="default_max_antelacion" value="<?= esc_attr(DSB_Settings::get('default_max_antelacion')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <hr>
                            <h3>Configuración de Notificaciones Push</h3>
                            <p class="description">Configura tus credenciales de Pusher Beams para habilitar las notificaciones push. <a href="https://pusher.com/beams" target="_blank">Más información</a>.</p>

                            <tr>
                                <th scope="row">
                                    <label for="pusher_beams_instance_id">ID de instancia de Pusher Beams</label>
                                </th>
                                <td>
                                    <input type="text" name="pusher_beams_instance_id" id="pusher_beams_instance_id"
                                        value="<?= esc_attr(DSB_Settings::get('pusher_beams_instance_id')); ?>" class="regular-text">
                                    <p class="description">El ID de instancia de tu cuenta de Pusher Beams</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="pusher_beams_secret_key">Clave secreta de Pusher Beams</label>
                                </th>
                                <td>
                                    <input type="password" name="pusher_beams_secret_key" id="pusher_beams_secret_key"
                                        value="<?= esc_attr(DSB_Settings::get('pusher_beams_secret_key')) ?>" class="regular-text">
                                    <p class="description">La clave secreta de tu cuenta de Pusher Beams</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Guardar ajustes" />
                </p>
            </form>
        </div>
    <?php
    }


    protected function enqueue_scripts()
    {
        wp_enqueue_style(
            'dashboard-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/dashboard-view.css',
            [],
            DSB_VERSION
        );

        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '4.3.3',
            true
        );

        wp_enqueue_script(
            'dashboard-admin-js',
            DSB_PLUGIN_URL . '../public/js/admin/dashboard-admin-view.js',
            ['jquery', 'wp-api', 'chartjs'],
            DSB_VERSION,
            true
        );

        wp_localize_script(
            'dashboard-admin-js',
            'wpApiSettings',
            ['nonce' => wp_create_nonce('wp_rest')]
        );
    }

    protected function render_table() {}

    protected function render_search_section()
    {
    ?>
        <div class="dsb-section dsb-search-container">
            <div class="dsb-card dsb-search-teachers">
                <h2><i class="dashicons dashicons-businessman"></i> Buscador de Profesores</h2>
                <div class="dsb-search-form">
                    <input type="text" id="teacherSearch" placeholder="Buscar profesor por nombre o email..." class="regular-text">
                    <select id="teacherFilter" class="regular-select">
                        <option value="">Todos los profesores</option>
                        <option value="with_students">Con alumnos asignados</option>
                        <option value="without_students">Sin alumnos</option>
                    </select>
                </div>
                <div class="dsb-results-container">
                    <div class="dsb-search-stats">
                        <span id="teacherCount" class="dsb-count-badge"><?= count($this->teachers) ?></span> profesores encontrados
                    </div>
                    <div class="dsb-results-list" id="teacherResults">
                        <?php foreach ($this->teachers as $teacher):
                            // Obtener metadatos relevantes
                            $first_name = get_user_meta($teacher->ID, 'first_name', true);
                            $last_name = get_user_meta($teacher->ID, 'last_name', true);
                            $display_name = $first_name && $last_name ? "$first_name $last_name" : $teacher->display_name;
                            $phone = get_user_meta($teacher->ID, 'phone', true);
                            $avatar_url = get_avatar_url($teacher->ID);

                            // Contar estudiantes asignados
                            $student_query = new WP_User_Query([
                                'role' => 'student',
                                'meta_query' => [[
                                    'key' => 'assigned_teacher',
                                    'value' => $teacher->ID,
                                    'compare' => '='
                                ]]
                            ]);
                            $student_count = $student_query->get_total();

                            // Atributos para filtrado
                            $data_attrs = 'data-name="' . esc_attr(strtolower($display_name)) . '" ';
                            $data_attrs .= 'data-email="' . esc_attr(strtolower($teacher->user_email)) . '" ';
                            $data_attrs .= 'data-phone="' . esc_attr(strtolower($phone)) . '" ';
                            $data_attrs .= 'data-students="' . $student_count . '" ';
                        ?>
                            <div class="dsb-result-item teacher-item" data-name="<?= esc_attr($display_name) ?>" data-email="<?= esc_attr($teacher->user_email) ?>" data-phone="<?= esc_attr($phone) ?>" data-students="<?= esc_attr($student_count) ?>">
                                <div class="dsb-avatar">
                                    <img src="<?= esc_url($avatar_url) ?>" alt="Avatar">
                                </div>
                                <div class="dsb-item-content">
                                    <h4><?= esc_html($display_name) ?></h4>
                                    <div class="dsb-meta">
                                        <div><i class="dashicons dashicons-email"></i> <?= esc_html($teacher->user_email) ?></div>
                                        <div><i class="dashicons dashicons-phone"></i> <?= esc_html($phone ?: 'No disponible') ?></div>
                                    </div>
                                    <div class="dsb-tags">
                                        <span class="dsb-tag"><i class="dashicons dashicons-groups"></i> <?= esc_html($student_count) ?> alumnos</span>
                                    </div>
                                </div>
                                <div class="dsb-actions">
                                    <?php if ($phone): ?>
                                        <a href="tel:<?= esc_attr(preg_replace('/\s+/', '', $phone)) ?>" class="dsb-contact-btn dsb-call-btn" title="Llamar">
                                            <i class="dashicons dashicons-phone"></i>
                                        </a>
                                        <a href="https://wa.me/<?= esc_attr(preg_replace('/[^0-9]/', '', $phone)) ?>" target="_blank" class="dsb-contact-btn dsb-whatsapp-btn" title="WhatsApp">
                                            <i class="dashicons dashicons-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="button view-details-btn" data-role="teacher" data-id="<?= esc_attr($teacher->ID) ?>">
                                        <i class="dashicons dashicons-visibility"></i> Detalles
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div id="noTeachersMsg" class="dsb-no-results" style="display: none;">
                            <i class="dashicons dashicons-info"></i> No se encontraron profesores con los criterios de búsqueda.
                        </div>
                    </div>
                    <?php if (count($this->teachers) > 10): ?>
                        <div class="dsb-pagination">
                            <button id="prevTeacherPage" class="button dsb-pagination-btn" disabled>
                                <i class="dashicons dashicons-arrow-left-alt2"></i> Anterior
                            </button>
                            <span class="dsb-pagination-info">Página <span id="currentTeacherPage">1</span> de <span id="totalTeacherPages"><?= ceil(count($this->teachers) / 10) ?></span></span>
                            <button id="nextTeacherPage" class="button dsb-pagination-btn">
                                Siguiente <i class="dashicons dashicons-arrow-right-alt2"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dsb-card dsb-search-students">
                <h2><i class="dashicons dashicons-groups"></i> Buscador de Alumnos</h2>
                <div class="dsb-search-form">
                    <input type="text" id="studentSearch" placeholder="Buscar alumno por nombre o email..." class="regular-text">
                    <select id="studentFilter" class="regular-select">
                        <option value="">Todos los alumnos</option>
                        <option value="with_bookings">Con reservas</option>
                        <option value="license_a">Licencia A (Moto)</option>
                        <option value="license_b">Licencia B (Coche)</option>
                    </select>
                </div>
                <div class="dsb-results-container">
                    <div class="dsb-search-stats">
                        <span id="studentCount" class="dsb-count-badge"><?= count($this->students) ?></span> alumnos encontrados
                    </div>
                    <div class="dsb-results-list" id="studentResults">
                        <?php foreach ($this->students as $student):
                            // Obtener metadatos relevantes
                            $first_name = get_user_meta($student->ID, 'first_name', true);
                            $last_name = get_user_meta($student->ID, 'last_name', true);
                            $display_name = $first_name && $last_name ? "$first_name $last_name" : $student->display_name;
                            $phone = get_user_meta($student->ID, 'phone', true);
                            $license_type = get_user_meta($student->ID, 'license_type', true) ?: 'No especificado';
                            $avatar_url = get_avatar_url($student->ID);

                            // Contar reservas del alumno
                            $args = [
                                'post_type' => 'dsb_booking',
                                'meta_query' => [[
                                    'key' => 'student_id',
                                    'value' => $student->ID,
                                    'compare' => '='
                                ]],
                                'posts_per_page' => -1
                            ];
                            $bookings_query = new WP_Query($args);
                            $bookings_count = $bookings_query->post_count;

                            // Obtener profesor asignado
                            $teacher_id = get_user_meta($student->ID, 'assigned_teacher', true);
                            $teacher_name = '';
                            if ($teacher_id) {
                                $teacher = get_userdata($teacher_id);
                                $teacher_name = $teacher ? $teacher->display_name : 'No encontrado';
                            } else {
                                $teacher_name = 'Sin asignar';
                            }

                            // Atributos para filtrado
                            $data_attrs = 'data-name="' . esc_attr(strtolower($display_name)) . '" ';
                            $data_attrs .= 'data-email="' . esc_attr(strtolower($student->user_email)) . '" ';
                            $data_attrs .= 'data-phone="' . esc_attr(strtolower($phone)) . '" ';
                            $data_attrs .= 'data-bookings="' . $bookings_count . '" ';
                            $data_attrs .= 'data-license="' . esc_attr(strtolower($license_type)) . '" ';
                        ?>
                            <div class="dsb-result-item student-item" data-name="<?= esc_attr($display_name) ?>" data-email="<?= esc_attr($student->user_email) ?>" data-phone="<?= esc_attr($phone) ?>" data-license="<?= esc_attr($license_type) ?>" data-bookings="<?= esc_attr($bookings_count) ?>">
                                <div class="dsb-avatar">
                                    <img src="<?= esc_url($avatar_url) ?>" alt="Avatar">
                                </div>
                                <div class="dsb-item-content">
                                    <h4><?= esc_html($display_name) ?></h4>
                                    <div class="dsb-meta">
                                        <div><i class="dashicons dashicons-email"></i> <?= esc_html($student->user_email) ?></div>
                                        <div><i class="dashicons dashicons-phone"></i> <?= esc_html($phone ?: 'No disponible') ?></div>
                                    </div>
                                    <div class="dsb-tags">
                                        <?php if ($license_type): ?>
                                            <span class="dsb-tag license-<?= strtolower($license_type) ?>">
                                                <i class="dashicons <?= $license_type == 'A' ? 'dashicons-motorcycle' : 'dashicons-car' ?>"></i>
                                                Licencia <?= esc_html($license_type) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="dsb-tag"><i class="dashicons dashicons-calendar-alt"></i> <?= esc_html($bookings_count) ?> reservas</span>
                                        <?php if ($teacher_name): ?>
                                            <span class="dsb-tag"><i class="dashicons dashicons-businessman"></i> Profesor: <?= esc_html($teacher_name) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dsb-actions">
                                    <?php if ($phone): ?>
                                        <a href="tel:<?= esc_attr(preg_replace('/\s+/', '', $phone)) ?>" class="dsb-contact-btn dsb-call-btn" title="Llamar">
                                            <i class="dashicons dashicons-phone"></i>
                                        </a>
                                        <a href="https://wa.me/<?= esc_attr(preg_replace('/[^0-9]/', '', $phone)) ?>" target="_blank" class="dsb-contact-btn dsb-whatsapp-btn" title="WhatsApp">
                                            <i class="dashicons dashicons-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="button view-details-btn" data-role="student" data-id="<?= esc_attr($student->ID) ?>">
                                        <i class="dashicons dashicons-visibility"></i> Detalles
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div id="noStudentsMsg" class="dsb-no-results" style="display: none;">
                            <i class="dashicons dashicons-info"></i> No se encontraron alumnos con los criterios de búsqueda.
                        </div>
                    </div>
                    <?php if (count($this->students) > 10): ?>
                        <div class="dsb-pagination">
                            <button id="prevStudentPage" class="button dsb-pagination-btn" disabled>
                                <i class="dashicons dashicons-arrow-left-alt2"></i> Anterior
                            </button>
                            <span class="dsb-pagination-info">Página <span id="currentStudentPage">1</span> de <span id="totalStudentPages"><?= ceil(count($this->students) / 10) ?></span></span>
                            <button id="nextStudentPage" class="button dsb-pagination-btn">
                                Siguiente <i class="dashicons dashicons-arrow-right-alt2"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modal para detalles de profesor -->
        <div class="dsb-modal" id="teacherDetailModal" style="display: none;">
            <div class="dsb-modal-content">
                <div class="dsb-modal-header">
                    <h3>Detalles del Profesor</h3>
                    <span class="dsb-modal-close">&times;</span>
                </div>
                <div class="dsb-modal-body">
                    <div class="dsb-loading">
                        <div class="spinner"></div>
                        <p>Cargando información...</p>
                    </div>
                    <div class="dsb-profile-details" style="display: none;">
                        <div class="dsb-profile-header">
                            <div class="dsb-profile-avatar">
                                <img src="" alt="Avatar" id="teacherModalAvatar">
                            </div>
                            <div class="dsb-profile-title">
                                <h4 id="teacherModalName"></h4>
                                <p id="teacherModalRole">Profesor</p>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Información de contacto</h5>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-email"></i>
                                <span id="teacherModalEmail"></span>
                            </div>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-phone"></i>
                                <span id="teacherModalPhone"></span>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Estadísticas de clases</h5>
                            <div class="dsb-stats-container">
                                <div class="dsb-stats-tabs">
                                    <button type="button" class="dsb-stats-tab active" data-period="current">Mes actual</button>
                                    <button type="button" class="dsb-stats-tab" data-period="previous">Mes anterior</button>
                                    <button type="button" class="dsb-stats-tab" data-period="year">Año actual</button>
                                </div>
                                <div class="dsb-stats-content">
                                    <div id="teacherStatsLoader" class="dsb-stats-loader">
                                        <div class="spinner"></div>
                                        <p>Cargando estadísticas...</p>
                                    </div>
                                    <div id="teacherStatsContent" class="dsb-stats-data" style="display: none;">
                                        <div class="dsb-stats-row">
                                            <div class="dsb-stat-item">
                                                <div class="dsb-stat-value" id="teacherTotalClasses">0</div>
                                                <div class="dsb-stat-label">Clases totales</div>
                                            </div>
                                            <div class="dsb-stat-item">
                                                <div class="dsb-stat-value" id="teacherAcceptedClasses">0</div>
                                                <div class="dsb-stat-label">Clases aceptadas</div>
                                            </div>
                                            <div class="dsb-stat-item">
                                                <div class="dsb-stat-value" id="teacherCanceledClasses">0</div>
                                                <div class="dsb-stat-label">Clases canceladas</div>
                                            </div>
                                        </div>
                                        <div class="dsb-stat-chart">
                                            <canvas id="teacherStatsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Vehículos asignados</h5>
                            <div id="teacherModalVehicles">
                                <!-- Los vehículos se insertarán aquí -->
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Alumnos asignados</h5>
                            <div class="dsb-students-list" id="teacherModalStudents">
                                <!-- Los estudiantes se insertarán aquí -->
                            </div>
                        </div>
                    </div>
                    <div class="dsb-error-message" style="display: none;">
                        <i class="dashicons dashicons-warning"></i>
                        <p>Ha ocurrido un error al cargar los datos. Por favor, inténtelo de nuevo más tarde.</p>
                    </div>
                </div>
                <div class="dsb-modal-footer">
                    <a href="#" class="button edit-profile-btn" id="editTeacherBtn">
                        <i class="dashicons dashicons-edit"></i> Editar Perfil
                    </a>
                    <button type="button" class="button close-modal-btn">
                        <i class="dashicons dashicons-no"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal para detalles de alumno -->
        <div class="dsb-modal" id="studentDetailModal" style="display: none;">
            <div class="dsb-modal-content">
                <div class="dsb-modal-header">
                    <h3>Detalles del Alumno</h3>
                    <span class="dsb-modal-close">&times;</span>
                </div>
                <div class="dsb-modal-body">
                    <div class="dsb-loading">
                        <div class="spinner"></div>
                        <p>Cargando información...</p>
                    </div>
                    <div class="dsb-profile-details" style="display: none;">
                        <div class="dsb-profile-header">
                            <div class="dsb-profile-avatar">
                                <img src="" alt="Avatar" id="studentModalAvatar">
                            </div>
                            <div class="dsb-profile-title">
                                <h4 id="studentModalName"></h4>
                                <p id="studentModalRole">Alumno</p>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Información de contacto</h5>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-email"></i>
                                <span id="studentModalEmail"></span>
                            </div>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-phone"></i>
                                <span id="studentModalPhone"></span>
                            </div>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-id"></i>
                                <span id="studentModalDNI"></span>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Información académica</h5>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-welcome-learn-more"></i>
                                <span>Licencia tipo: <strong id="studentModalLicense"></strong></span>
                            </div>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-businessman"></i>
                                <span>Profesor asignado: <strong id="studentModalTeacher"></strong></span>
                            </div>
                            <div class="dsb-info-item">
                                <i class="dashicons dashicons-money"></i>
                                <span>Créditos disponibles: <strong id="studentModalCredits"></strong></span>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Estadísticas de clases</h5>
                            <div class="dsb-stats-container">
                                <div class="dsb-stats-summary">
                                    <div class="dsb-stat-item">
                                        <div class="dsb-stat-value" id="studentTotalClasses">0</div>
                                        <div class="dsb-stat-label">Clases totales</div>
                                    </div>
                                    <div class="dsb-stat-item">
                                        <div class="dsb-stat-value dsb-status-accepted" id="studentAcceptedClasses">0</div>
                                        <div class="dsb-stat-label">Aceptadas</div>
                                    </div>
                                    <div class="dsb-stat-item">
                                        <div class="dsb-stat-value dsb-status-pending" id="studentPendingClasses">0</div>
                                        <div class="dsb-stat-label">Pendientes</div>
                                    </div>
                                    <div class="dsb-stat-item">
                                        <div class="dsb-stat-value dsb-status-cancelled" id="studentCancelledClasses">0</div>
                                        <div class="dsb-stat-label">Canceladas</div>
                                    </div>
                                </div>
                                <div class="dsb-credit-usage">
                                    <h6>Uso de créditos</h6>
                                    <div class="dsb-progress-bar">
                                        <div class="dsb-progress" id="studentCreditBar"></div>
                                    </div>
                                    <div class="dsb-credit-text">
                                        <span id="studentUsedCredits">0</span> de <span id="studentTotalCredits">0</span> créditos utilizados
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dsb-profile-info">
                            <h5>Historial de clases</h5>
                            <div class="dsb-bookings-list" id="studentModalBookings">
                                <!-- Las reservas se insertarán aquí -->
                            </div>
                        </div>
                    </div>
                    <div class="dsb-error-message" style="display: none;">
                        <i class="dashicons dashicons-warning"></i>
                        <p>Ha ocurrido un error al cargar los datos. Por favor, inténtelo de nuevo más tarde.</p>
                    </div>
                </div>
                <div class="dsb-modal-footer">
                    <a href="#" class="button edit-profile-btn" id="editStudentBtn">
                        <i class="dashicons dashicons-edit"></i> Editar Perfil
                    </a>
                    <button type="button" class="button close-modal-btn">
                        <i class="dashicons dashicons-no"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
<?php
    }
}
