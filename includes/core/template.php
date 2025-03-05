<?php
// includes/class-dsb-template.php

class DSB_Template {
    public function __construct() {
        add_filter('theme_page_templates', [$this, 'add_custom_template']);
        add_filter('template_include', [$this, 'load_custom_template']);
        add_action('wp_enqueue_scripts', [$this, 'remove_theme_styles_scripts'], 999);
    }

    // Agregar la plantilla a la lista de WordPress
    public function add_custom_template($templates) {
        $templates['template-react-full.php'] = 'React Fullscreen';
        return $templates;
    }

    // Cargar la plantilla cuando la página la usa
    public function load_custom_template($template) {
        if (is_page_template('template-react-full.php')) {
            return plugin_dir_path(__FILE__) . './template-react-full.php';
        }
        return $template;
    }

    // Deshabilitar estilos y scripts del tema si se usa la plantilla
    public function remove_theme_styles_scripts() {
        if (is_page_template('template-react-full.php')) {
            global $wp_styles, $wp_scripts;

            // Desactivar todos los estilos y scripts del tema activo
            foreach ($wp_styles->queue as $handle) {
                wp_dequeue_style($handle);
            }
            foreach ($wp_scripts->queue as $handle) {
                wp_dequeue_script($handle);
            }
        }
    }
}
