<?php
// includes/post-types/class-dsb-notification.php

class DSB_Notification {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('acf/init', [$this, 'register_fields']);
    }

    public function register_post_type() {
        $args = [
            'labels' => [
                'name' => 'Notificaciones',
                'singular_name' => 'Notificación'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_position' => 6,
            'supports' => ['title'],
            'show_in_rest' => true
        ];

        register_post_type('notification', $args);
    }

    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_notification_details',
            'title' => 'Detalles de la Notificación',
            'fields' => [
                [
                    'key' => 'field_notification_type',
                    'label' => 'Tipo',
                    'name' => 'type',
                    'type' => 'select',
                    'choices' => [
                        'booking_reminder' => 'Recordatorio de Reserva',
                        'booking_confirmation' => 'Confirmación de Reserva',
                        'booking_cancellation' => 'Cancelación de Reserva',
                        'maintenance_alert' => 'Alerta de Mantenimiento'
                    ],
                    'required' => 1
                ],
                [
                    'key' => 'field_recipient',
                    'label' => 'Destinatario',
                    'name' => 'recipient',
                    'type' => 'user',
                    'required' => 1
                ],
                [
                    'key' => 'field_message',
                    'label' => 'Mensaje',
                    'name' => 'message',
                    'type' => 'textarea',
                    'required' => 1
                ],
                [
                    'key' => 'field_read_status',
                    'label' => 'Leído',
                    'name' => 'read_status',
                    'type' => 'true_false',
                    'default_value' => 0
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'notification'
                    ]
                ]
            ]
        ]);
    }
}