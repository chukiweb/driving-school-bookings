<?php
// includes/class-dsb-api.php
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
require_once DSB_PLUGIN_DIR . 'services/teacher-service.php';
require_once DSB_PLUGIN_DIR . 'services/student-service.php';
require_once DSB_PLUGIN_DIR . 'services/calendar-service.php';

class DSB_API
{
    private $namespace = 'driving-school/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        // Auth endpoints
        register_rest_route($this->namespace, '/auth/acceso', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/auth/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refresh_token'],
            'permission_callback' => '__return_true'
        ]);

        /**
         * USERS ENDPOINTS
         */

        // Users endpoints
        register_rest_route($this->namespace, '/users/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_current_user'],
            'permission_callback' => [$this, 'check_permission']
        ]);


        //Load images endpoint
        register_rest_route($this->namespace, '/users/me/avatar', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_user_avatar'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        /**
         * BOOKINGS ENDPOINTS
         */

        // Bookings endpoints
        register_rest_route($this->namespace, '/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bookings'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/bookings/cancel/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_booking'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        /**
         * VIHICLES ENDPOINTS
         */

        // Vehicles endpoints
        register_rest_route($this->namespace, '/vehicles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicles'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        /**
         * VIEWS ENDPOINTS
         */

        register_rest_route($this->namespace, '/views/student', [
            'methods' => 'GET',
            'callback' => [$this, 'get_student_view'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/views/teacher', [
            'methods' => 'GET',
            'callback' => [$this, 'get_teacher_view'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/views/(?P<view>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_public_view'],
            'permission_callback' => '__return_true'
        ]);

        /**
         * TEACHERS ENDPOINTS
         */

        // Teachers endpoints
        register_rest_route($this->namespace, '/teachers/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_teacher'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        register_rest_route('driving-school/v1', '/professor-availability', [
            'methods' => 'GET',
            'callback' => 'get_professor_availability',
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('driving-school/v1', '/save-availability', [
            'methods' => 'POST',
            'callback' => 'save_professor_availability',
            'permission_callback' => '__return_true'
        ]);


        /**
         * STUDENT ENDPOINT
         */
        // Students endpoints
        register_rest_route($this->namespace, '/students/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_student'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        /**
         * CALENDAR ENDPOINTS
         */

        register_rest_route($this->namespace, '/teachers/(?P<id>\d+)/calendar', [
            'methods' => 'GET',
            'callback' => [$this, 'get_teacher_calendar'],
            'permission_callback' => function ($request) {
                return $this->check_permission($request);
            }
        ]);

        register_rest_route($this->namespace, '/teachers/(?P<id>\d+)/calendar', [
            'methods' => 'POST',
            'callback' => [$this, 'update_teacher_calendar'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/bookings/cancel/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_booking'],
            'permission_callback' => [$this, 'check_permission']
        ]);

    }

    public function login($request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Credenciales inválidas', ['status' => 401]);
        }

        // Asegurar que obtenemos los datos completos del usuario
        $user = get_userdata($user->ID);
        if (!$user) {
            return new WP_Error('user_not_found', 'No se pudo recuperar la información del usuario', ['status' => 401]);
        }

        // Generar JWT token
        $token = $this->generate_jwt($user);

        return [
            'token' => $token,
            'user' => $this->format_user($user),
        ];
    }

    public function get_bookings($request)
    {
        $page = isset($request['page']) ? intval($request['page']) : 1;
        $per_page = isset($request['per_page']) ? intval($request['per_page']) : 10;

        $args = [
            'post_type' => 'dsb_booking',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];

        $query = new WP_Query($args);
        $bookings = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bookings[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'date' => get_post_meta(get_the_ID(), 'dsb_booking_date', true),
                    'student_id' => get_post_meta(get_the_ID(), 'dsb_student_id', true),
                    'status' => get_post_meta(get_the_ID(), 'dsb_booking_status', true),
                ];
            }
        }

        wp_reset_postdata();

        return rest_ensure_response([
            'bookings' => $bookings,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
        ]);
    }

    public function create_booking($request)
    {
        $booking_data = [
            'post_type' => 'booking',
            'post_title' => sprintf('Reserva %s', date('Y-m-d H:i:s')),
            'post_status' => 'publish'
        ];

        $booking_id = wp_insert_post($booking_data);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        // Update ACF fields
        update_field('student', $request->get_param('student_id'), $booking_id);
        update_field('teacher', $request->get_param('teacher_id'), $booking_id);
        update_field('vehicle', $request->get_param('vehicle_id'), $booking_id);
        update_field('booking_date', $request->get_param('date'), $booking_id);
        update_field('duration', $request->get_param('duration'), $booking_id);
        update_field('price', $request->get_param('price'), $booking_id);

        return $this->get_booking($booking_id);
    }

    public function cancel_booking($request)
    {
        $booking_id = intval($request['id']);

        if (!$booking_id || get_post_type($booking_id) !== 'dsb_booking') {
            return new WP_Error('invalid_booking', 'Reserva inválida', ['status' => 400]);
        }

        $current_user = get_current_user_id();
        $student_id = get_post_meta($booking_id, 'dsb_student_id', true);

        if ($current_user !== intval($student_id)) {
            return new WP_Error('unauthorized', 'No tienes permiso para cancelar esta reserva', ['status' => 403]);
        }

        wp_update_post([
            'ID' => $booking_id,
            'post_status' => 'cancelled',
        ]);

        return rest_ensure_response(['message' => 'Reserva cancelada correctamente']);
    }

    private function format_booking($post)
    {
        return [
            'id' => $post->ID,
            'student' => get_field('student', $post->ID),
            'teacher' => get_field('teacher', $post->ID),
            'vehicle' => get_field('vehicle', $post->ID),
            'date' => get_field('booking_date', $post->ID),
            'duration' => get_field('duration', $post->ID),
            'price' => get_field('price', $post->ID),
            'status' => get_field('payment_status', $post->ID)
        ];
    }

    private function format_user($user)
    {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'role' => $user->roles[0] ?? null
        ];
    }

    private function generate_jwt($user)
    {
        return DSB()->jwt->generate_token($user);
    }

    public function refresh_token($request)
    {
        $token = $request->get_param('token');

        if (!$token) {
            return new WP_Error('missing_token', 'No se ha proporcionado un token', ['status' => 400]);
        }

        try {
            $decoded = JWT::decode($token, new Key(JWT_AUTH_SECRET_KEY, 'HS256'));
            $user_id = $decoded->data->user_id;

            $new_token = [
                'iss' => get_site_url(),
                'iat' => time(),
                'exp' => time() + 3600, // 1 horaA
                'data' => [
                    'user_id' => $user_id
                ]
            ];

            return rest_ensure_response([
                'token' => JWT::encode($new_token, JWT_AUTH_SECRET_KEY, 'HS256')
            ]);
        } catch (Exception $e) {
            return new WP_Error('invalid_token', 'Token inválido o expirado', ['status' => 403]);
        }
    }


    public function check_permission($request) {
        if (is_user_logged_in()) {
            return true;
        }
    
        // Lógica con token JWT
        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        if (!$token) return false;
    
        return DSB()->jwt->validate_token($token);
    }

    public function get_student_view()
    {
        return rest_ensure_response([
            'url' => plugins_url('public/views/estudiante.php', dirname(__FILE__))
        ]);
    }

    public function get_teacher_view()
    {
        return rest_ensure_response([
            'url' => plugins_url('public/views/profesor.php', dirname(__FILE__))
        ]);
    }

    public function get_teacher($request)
    {
        $teacher_id = intval($request['id']);
        return DSB_Teacher_Service::get_teacher($teacher_id);
    }

    public function get_professor_availability($request)
    {
        $teacher_id = intval($request['id']);
        return DSB_Teacher_Service::get_professor_availability($teacher_id);
    }


    public function save_professor_availability($request)
    {
        $teacher_id = intval($request['id']);
        return DSB_Teacher_Service::save_professor_availability($request, $teacher_id);
    }

    /**
     * BLoque de estudioante 
     */

    public function get_student($request)
    {
        $user_id = intval($request['id']);
        return DSB_Student_Service::get_student($user_id);
    }

    public function upload_user_avatar($request)
    {
        $user_id = intval($request['id']);
        return DSB_Student_Service::get_student($user_id);
    }


    /**
     * CALENDAR
     */

    public function get_teacher_calendar($request)
    {
        $teacher_id = intval($request['id']);
        return DSB_Calendar_Service::get_teacher_calendar($teacher_id);
    }

    // Agregar disponibilidad
    public function update_teacher_calendar($request)
    {
        return DSB_Calendar_Service::update_teacher_calendar($request);
    }

    // Eliminar un horario
    public function delete_booking($request)
    {
        $booking = intval($request['id']);
        return DSB_Calendar_Service::delete_booking($booking);

    }


    public function get_public_view($request)
    {
        $view = sanitize_text_field($request['view']);

        $allowed_views = ['acceso', 'estudiante', 'profesor'];
        if (!in_array($view, $allowed_views)) {
            return new WP_Error('invalid_view', 'Vista no permitida', ['status' => 403]);
        }

        $file_path = plugin_dir_path(__FILE__) . "../public/views/{$view}.php";

        if (!file_exists($file_path)) {
            return new WP_Error('not_found', 'Archivo no encontrado', ['status' => 404]);
        }

        ob_start();
        include $file_path;
        return ob_get_clean();
    }





}