<?php
// includes/class-dsb-api.php

class DSB_API {
    private $namespace = 'driving-school/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Auth endpoints
        register_rest_route($this->namespace, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true'
        ]);

        // Bookings endpoints
        register_rest_route($this->namespace, '/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bookings'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/bookings', [
            'methods' => 'POST',
            'callback' => [$this, 'create_booking'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Vehicles endpoints
        register_rest_route($this->namespace, '/vehicles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vehicles'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Users endpoints
        register_rest_route($this->namespace, '/users/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_current_user'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    public function login($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Credenciales inválidas',
                ['status' => 401]
            );
        }

        // Generate JWT token
        $token = $this->generate_jwt($user);

        return [
            'token' => $token,
            'user' => $this->format_user($user)
        ];
    }

    public function get_bookings($request) {
        $args = [
            'post_type' => 'booking',
            'posts_per_page' => 10,
            'paged' => $request->get_param('page') ?? 1
        ];

        $query = new WP_Query($args);
        return array_map([$this, 'format_booking'], $query->posts);
    }

    public function create_booking($request) {
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

    private function format_booking($post) {
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

    private function format_user($user) {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'role' => $user->roles[0] ?? null
        ];
    }

    private function generate_jwt($user) {
        // Implementar generación de JWT
        return 'token_placeholder';
    }

    public function check_permission() {
        return is_user_logged_in();
    }
}