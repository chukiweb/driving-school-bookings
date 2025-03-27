<?php
// includes/post-types/vehicle.php

class DSB_Vehicle {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('acf/init', [$this, 'register_fields']);
    }

    public function register_post_type() {
        $labels = [
            'name' => 'Vehículos',
            'singular_name' => 'Vehículo',
            'menu_name' => 'Vehículos',
            'add_new' => 'Añadir Nuevo',
            'add_new_item' => 'Añadir Nuevo Vehículo',
            'edit_item' => 'Editar Vehículo',
            'new_item' => 'Nuevo Vehículo',
            'view_item' => 'Ver Vehículo',
            'search_items' => 'Buscar Vehículos',
            'not_found' => 'No se encontraron vehículos',
            'not_found_in_trash' => 'No se encontraron vehículos en la papelera'
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'driving-bookings', // Cambio aquí
            'query_var' => true,
            'rewrite' => ['slug' => 'vehicles'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true
        ];

        register_post_type('vehicle', $args);
    }

    public function register_taxonomies() {
        $labels = [
            'name' => 'Estados',
            'singular_name' => 'Estado',
            'search_items' => 'Buscar Estados',
            'all_items' => 'Todos los Estados',
            'edit_item' => 'Editar Estado',
            'update_item' => 'Actualizar Estado',
            'add_new_item' => 'Añadir Nuevo Estado',
            'new_item_name' => 'Nombre del Nuevo Estado',
            'menu_name' => 'Estados'
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'vehicle-status'],
            'show_in_rest' => true
        ];

        register_taxonomy('vehicle_status', ['vehicle'], $args);
    }

    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_vehicle_details',
            'title' => 'Detalles del Vehículo',
            'fields' => [
                [
                    'key' => 'field_vehicle_type',
                    'label' => 'Tipo de Vehículo',
                    'name' => 'vehicle_type',
                    'type' => 'select',
                    'choices' => [
                        'car' => 'Coche',
                        'motorcycle' => 'Moto',
                    ],
                    'required' => 1
                ],
                [
                    'key' => 'field_brand',
                    'label' => 'Marca',
                    'name' => 'brand',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_model',
                    'label' => 'Modelo',
                    'name' => 'model',
                    'type' => 'text',
                    'required' => 1
                ],
                [
                    'key' => 'field_year',
                    'label' => 'Año',
                    'name' => 'year',
                    'type' => 'number',
                    'required' => 1
                ],
                [
                    'key' => 'field_maintenance_dates',
                    'label' => 'Mantenimiento',
                    'name' => 'maintenance',
                    'type' => 'group',
                    'fields' => [
                        [
                            'key' => 'field_last_maintenance',
                            'label' => 'Último Mantenimiento',
                            'name' => 'last_maintenance',
                            'type' => 'date_picker'
                        ],
                        [
                            'key' => 'field_next_maintenance',
                            'label' => 'Próximo Mantenimiento',
                            'name' => 'next_maintenance',
                            'type' => 'date_picker'
                        ]
                    ]
                ]
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'vehicle'
                    ]
                ]
            ]
        ]);
    }
}