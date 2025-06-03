<?php
// includes/post-types/class-dsb-notification.php

class DSB_Notification
{
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('acf/init', [$this, 'register_fields']);
    }

    public function register_post_type()
    {
        $labels = [
            'name'               => __('Notificaciones', 'dsb'),
            'singular_name'      => __('Notificación', 'dsb'),
            'menu_name'          => __('Notificaciones', 'dsb'),
            'name_admin_bar'     => __('Notificación', 'dsb'),
            'add_new'            => __('Añadir Notificación', 'dsb'),
            'add_new_item'       => __('Añadir Nueva Notificación', 'dsb'),
            'edit_item'          => __('Editar Notificación', 'dsb'),
            'new_item'           => __('Nueva Notificación', 'dsb'),
            'all_items'          => __('Todas las Notificaciones', 'dsb'),
            'search_items'       => __('Buscar Notificaciones', 'dsb'),
            'not_found'          => __('No se han encontrado notificaciones.', 'dsb'),
            'not_found_in_trash' => __('No se han encontrado notificaciones en la papelera.', 'dsb'),
            'view_item'          => __('Ver Notificación', 'dsb'),
        ];

        $args = [
            'labels' => $labels,
            'public'             => false,
            'show_ui'            => false, // bloqueamos UI nativa; usaremos nuestra vista custom
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array('title', 'editor'),
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
        ];

        register_post_type('dsb_notification', $args);
    }

    public function register_fields()
    {
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
