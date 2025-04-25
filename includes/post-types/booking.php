<?php
// includes/post-types/class-dsb-booking.php

class DSB_Booking {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('acf/init', [$this, 'register_fields']);
        add_filter('post_updated_messages', [$this, 'custom_updated_messages']);
    }

    public function register_post_type() {
        $labels = [
            'name' => 'Reservas',
            'singular_name' => 'Reserva',
            'menu_name' => 'Reservas',
            'add_new' => 'Añadir Nueva',
            'add_new_item' => 'Añadir Nueva Reserva',
            'edit_item' => 'Editar Reserva',
            'new_item' => 'Nueva Reserva',
            'view_item' => 'Ver Reserva',
            'search_items' => 'Buscar Reservas',
            'not_found' => 'No se encontraron reservas',
            'not_found_in_trash' => 'No se encontraron reservas en la papelera'
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
           'show_in_menu' => 'driving-bookings', // Cambio aquí
            'query_var' => true,
            'rewrite' => ['slug' => 'bookings'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => ['title', 'author'],
            'show_in_rest' => true
        ];

        register_post_type('booking', $args);
    }

    public function register_taxonomies() {
        register_taxonomy('booking_status', ['booking'], [
            'hierarchical' => true,
            'labels' => [
                'name' => 'Estados de Reserva',
                'singular_name' => 'Estado',
                'menu_name' => 'Estados'
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'booking-status'],
            'show_in_rest' => true
        ]);
    }

    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_booking_details',
            'title' => 'Detalles de la Reserva',
            'fields' => [
                [
                    'key' => 'field_student',
                    'label' => 'Alumno',
                    'name' => 'student',
                    'type' => 'user',
                    'role' => ['student'],
                    'required' => 1
                ],
                [
                    'key' => 'field_teacher',
                    'label' => 'Profesor',
                    'name' => 'teacher',
                    'type' => 'user',
                    'role' => ['teacher'],
                    'required' => 1
                ],
                [
                    'key' => 'field_vehicle',
                    'label' => 'Vehículo',
                    'name' => 'vehicle',
                    'type' => 'post_object',
                    'post_type' => ['vehicle'],
                    'required' => 1
                ],
                [
                    'key' => 'field_booking_date',
                    'label' => 'Fecha y Hora',
                    'name' => 'booking_date',
                    'type' => 'date_time_picker',
                    'required' => 1
                ],
                [
                    'key' => 'field_duration',
                    'label' => 'Duración (minutos)',
                    'name' => 'duration',
                    'type' => 'number',
                    'default_value' => 60,
                    'min' => 30,
                    'max' => 180,
                    'required' => 1
                ],
                [
                    'key' => 'field_price',
                    'label' => 'Precio',
                    'name' => 'price',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_payment_status',
                    'label' => 'Estado del Pago',
                    'name' => 'payment_status',
                    'type' => 'select',
                    'choices' => [
                        'pending' => 'Pendiente',
                        'paid' => 'Pagado',
                        'refunded' => 'Reembolsado'
                    ],
                    'required' => 1
                ],
                [
                    'key' => 'field_notes',
                    'label' => 'Notas',
                    'name' => 'notes',
                    'type' => 'textarea'
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'booking'
                    ]
                ]
            ]
        ]);
    }

    public function custom_updated_messages($messages) {
        $messages['booking'] = [
            0 => '', 
            1 => 'Reserva actualizada.',
            2 => 'Campo personalizado actualizado.',
            3 => 'Campo personalizado eliminado.',
            4 => 'Reserva actualizada.',
            5 => isset($_GET['revision']) ? sprintf('Reserva recuperada desde la revisión %s', wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => 'Reserva publicada.',
            7 => 'Reserva guardada.',
            8 => 'Reserva enviada.',
            9 => 'Reserva programada.',
            10 => 'Borrador de reserva actualizado.'
        ];

        return $messages;
    }
}