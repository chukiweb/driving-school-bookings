<?php

defined('ABSPATH') || exit;

class DSB_Password_Reset
{

    /**
     * Registra los hooks una sola vez.
     */
    public static function init()
    {
        error_log('DSB_Password_Reset::init() initialized.');
        // 1) Aplicar estilos propios sólo en wp-login.php?action=rp|resetpass
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // 2) Redirigir a /acceso tras el cambio de contraseña
        add_action('after_password_reset', [__CLASS__, 'redirect_after_set'], 10, 2);
    }

    /**
     * Encola CSS de tu página /acceso para que el formulario
     * de wp-login.php tenga el mismo aspecto.
     */
    public static function enqueue_assets(): void
    {
        $action = $_GET['action'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (in_array($action, ['rp', 'resetpass'], true)) {
            wp_enqueue_style(
                'dsb-reset-style',
                DSB_PLUGIN_URL . '../public/css/acceso.css', // 1 nivel arriba
                [],
                DSB_VERSION
            );
        }
    }

    /**
     * Después de establecer la nueva contraseña redirigimos al login SPA.
     *
     * @param WP_User $user      Objeto usuario.
     * @param string  $new_pass  Contraseña recién guardada.
     */
    public static function redirect_after_set($user, $new_pass): void
    {
        wp_safe_redirect(home_url('/acceso?reset=success'));
        exit;
    }
}
