<?php
class DSB_PWA
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('init', [$this, 'register_pwa_endpoint']);
        add_action('query_vars', [$this, 'add_query_vars']);
        add_action('template_include', [$this, 'load_pwa_template']);
    }

    public function register_pwa_endpoint() {
        add_rewrite_rule('^app/?', 'index.php?dsb_pwa=1', 'top');
        add_rewrite_tag('%dsb_pwa%', '([^&]+)');
        
        if (get_option('dsb_flush_required')) {
            flush_rewrite_rules();
            delete_option('dsb_flush_required');
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'dsb_pwa';
        return $vars;
    }

    public function load_pwa_template($template) {
        if ($this->is_pwa_page()) {
            return DSB_PLUGIN_DIR . 'includes/pwa/templates/app.php';
        }
        return $template;
    }
 

    public function enqueue_scripts()
    {
        if ($this->is_pwa_page()) {

            wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18.0.0', true);
            wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', [], '18.0.0', true);
            //wp_enqueue_script('dsb-pwa', DSB_PLUGIN_URL . 'includes/pwa/assets/dist/bundle.js', ['react', 'react-dom'], '1.0.0', true);

            wp_enqueue_script('dsb-pwa', DSB_PLUGIN_URL . 'includes/pwa/assets/js/app.js', ['react', 'react-dom'], '1.0.0', true);
            wp_enqueue_script('dsb-pwa-react', DSB_PLUGIN_URL . 'includes/pwa/assets/js/components/App.js', [], '1.0.0', true);
            wp_enqueue_script('dsb-pwa-teacher', DSB_PLUGIN_URL . 'includes/pwa/assets/js/components/TeacherView.js', ['dsb-pwa-react'], '1.0.0', true);
            wp_enqueue_script('dsb-pwa-student', DSB_PLUGIN_URL . 'includes/pwa/assets/js/components/StudentView.js', ['dsb-pwa-react'], '1.0.0', true);
            wp_localize_script('dsb-pwa', 'dsbApi', [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest')
            ]);

            wp_enqueue_style('dsb-pwa', DSB_PLUGIN_URL . 'includes/pwa/assets/css/style.css');
        }
    }

    private function is_pwa_page()
    {
        return get_query_var('dsb_pwa');
    }

    public function register_service_worker()
    {
        if ($this->is_pwa_page()) {
            add_action('wp_head', function () {
                echo '<link rel="manifest" href="' . DSB_PLUGIN_URL . 'includes/pwa/assets/manifest.json">';
            });

            add_action('wp_footer', function () {
                ?>
                <script>
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('<?php echo DSB_PLUGIN_URL; ?>includes/pwa/assets/service-worker.js')
                            .then(registration => console.log('SW registered:', registration))
                            .catch(error => console.log('SW registration failed:', error));
                    }
                </script>
                <?php
            });
        }
    }
}