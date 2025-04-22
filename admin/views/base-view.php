<?php
abstract class DSB_Base_View {
    protected $title;
    protected $nonce_action;
    protected $nonce_name;

    abstract protected function handle_form_submission();
    abstract protected function render_forms();
    abstract protected function render_table();
    abstract protected function enqueue_scripts();

    public function render() {
        echo '<div class="wrap">';
            echo '<h1>' . esc_html($this->title) . '</h1>';

            if (isset($_POST['submit'])) {
                $this->handle_form_submission();
            }
            echo '<div class="wp-list-table widefat fixed striped table-view-list posts">';
            $this->render_table();
            echo '</div>';

            echo '<div class="wrap">';
            $this->render_forms();
            echo '</div>';

        echo '</div>';

        $this->enqueue_scripts();
    }

    protected function render_notice($message, $type = 'success') {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    protected function verify_nonce() {
        if (!isset($_POST[$this->nonce_name]) || 
            !wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)) {
            wp_die('Acción no autorizada');
        }
    }
}