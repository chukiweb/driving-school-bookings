<?php
abstract class DSB_Base_View
{
    protected $title;
    protected $nonce_action;
    protected $nonce_name;

    abstract protected function handle_form_submission();
    abstract protected function render_forms();
    abstract protected function render_table();
    abstract protected function enqueue_scripts();

    public function render()
    {
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

    protected function render_notice($message, $type = 'success')
    {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    protected function render_response( $response, $on_success = null, $on_failure = null ) {
        // 1) WP_Error → error
        if ( is_wp_error( $response ) ) {
            $this->render_notice( $response->get_error_message(), 'error' );
            return;
        }

        // 2) Array con message+sent
        if ( is_array( $response ) && isset( $response['message'], $response['sent'] ) ) {
            $type = $response['sent'] ? 'success' : 'error';
            $this->render_notice( $response['message'], $type );
            return;
        }

        // 3) Array con message
        if ( is_array( $response ) && isset( $response['message'] ) ) {
            $this->render_notice( $response['message'], 'success' );
            return;
        }

        // 4) Booleano puro
        if ( is_bool( $response ) ) {
            if ( $response ) {
                // Success: mensaje personalizado o genérico
                $msg = $on_success ?? __( 'Operación completada con éxito.', 'driving-school-bookings' );
                $this->render_notice( $msg, 'success' );
            } else {
                // Failure: mensaje personalizado o genérico
                $msg = $on_failure ?? __( 'Ha ocurrido un error al procesar la operación.', 'driving-school-bookings' );
                $this->render_notice( $msg, 'error' );
            }
            return;
        }

        // 4) Tipo inesperado
        $this->render_notice( __( 'Respuesta no válida del servidor.', 'driving-school-bookings' ), 'error' );
    }

    protected function verify_nonce()
    {
        if (
            !isset($_POST[$this->nonce_name]) ||
            !wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)
        ) {
            wp_die('Acción no autorizada');
        }
    }
}
