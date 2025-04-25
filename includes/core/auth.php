<?php
// core/auth.php

class DSB_Auth {
    public function __construct() {
        // Inicia la sesión si no existe
        add_action('init', [$this, 'maybe_start_session']);
        // Protege las rutas antes de cargar el template (prioridad 5 para ejecutarse antes del redirect de plantillas)
        add_action('template_redirect', [$this, 'protect_routes'], 5);
    }

    public function maybe_start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    public function protect_routes() {
        // Identifica la vista solicitada por query var dsb_view
        $view = get_query_var('dsb_view');

        // Si no es una de nuestras rutas, no interferimos
        if (!$view) {
            return;
        }

        // Recupera y valida el token de la sesión
        $token = $_SESSION['jwt_token'] ?? ($_COOKIE['jwt_token'] ?? '');
        $decoded = DSB()->jwt->validate_token($token);

        // Si intenta acceder al login (/acceso)
        if ($view === 'acceso') {
            // Si ya está autenticado, redirige a su dashboard según rol
            if ($decoded) {
                $roles = $decoded->user->roles;
                $role = is_array($roles) ? $roles[0] : $roles;
                $redirect = ($role === 'student') ? 'alumno' : (($role === 'teacher') ? 'profesor' : 'acceso');
                wp_redirect(site_url("/{$redirect}/"));
                exit;
            }
            // Si no, deja ver el login
            return;
        }

        // Para cualquier otra vista, requerimos token válido
        if (!$decoded) {
            wp_redirect(site_url('/acceso/'));
            exit;
        }

        // Determina rol y protege vistas específicas
        $roles = $decoded->user->roles;
        $role = is_array($roles) ? $roles[0] : $roles;

        if ($role === 'student' && $view !== 'alumno') {
            wp_redirect(site_url('/acceso/'));
            exit;
        }
        if ($role === 'teacher' && $view !== 'profesor') {
            wp_redirect(site_url('/acceso/'));
            exit;
        }
        // Cualquier vista fuera de alumno/profesor redirige al login
        if (!in_array($view, ['alumno', 'profesor'], true)) {
            wp_redirect(site_url('/acceso/'));
            exit;
        }
    }
}