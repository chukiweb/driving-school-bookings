<?php

/**
 * Servicio de **correo electrónico** para notificaciones
 *
 * @package Driving_School_Bookings
 * @since   1.1.0
 */

if (! defined('ABSPATH')) {
    exit;
}

class DSB_Email_Notification_Service
{

    private static $instance = null;

    /**
     * Singleton
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Envía un email utilizando una plantilla PHP.
     *
     * @param int    $user_id         ID de usuario destinatario.
     * @param string $template_slug   Nombre de la plantilla (sin extensión).
     * @param array  $placeholders    Datos que se inyectarán en la plantilla.
     * @param string $subject_override Opcional para forzar asunto manual.
     *
     * @return bool
     */
    public function send($user_id, $template_slug, $placeholders = array(), $subject_override = '')
    {

        $user = get_user_by('id', $user_id);
        if (! $user || ! is_email($user->user_email)) {
            return false;
        }

        // 1. Cargar parcial ----------------------------------------------------
        $template_file = DSB_PLUGIN_DIR_PATH . 'public/emails/' . $template_slug . '.php';
        if (! file_exists($template_file)) {
            error_log("[DSB] Plantilla de email no encontrada: {$template_slug}");
            return false;
        }

        // 2. Render parcial y asunto ------------------------------------------
        $partial_html = $this->render($template_file, $placeholders);

        $subject = $subject_override
            ? $subject_override
            : $this->extract_subject($partial_html);

        // Eliminar la línea <!-- Subject: ... -->
        $partial_html = preg_replace('/<!--\s*Subject:.*?-->/', '', $partial_html, 1);

        // 3. Render layout -----------------------------------------------------
        $layout_file = DSB_PLUGIN_DIR_PATH . 'public/emails/layout.php';
        $message     = $this->render($layout_file, array(
            'subject'      => $subject,
            'content_html' => $partial_html,
        ));

        // 4. Enviar ------------------------------------------------------------
        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /* --------------------------------------------------------------------- */
    /*  Helpers                                                              */
    /* --------------------------------------------------------------------- */

    private function extract_subject($html)
    {
        if (preg_match('/<!--\s*Subject:(.*?)-->/', $html, $m)) {
            return trim($m[1]);
        }
        return get_bloginfo('name') . ' – Notificación';
    }

    /**
     * Renderiza un archivo de plantilla y devuelve el HTML.
     */
    private function render($file, $vars = array())
    {
        if (! file_exists($file)) {
            return '';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
