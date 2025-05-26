<?php
// File: includes/class-dsb-user-manager.php
if (! defined('ABSPATH')) exit;

class DSB_User_Manager
{

    public function __construct() {}

    //
    // ——— Métodos públicos por rol y operación ———
    //

    public function create_student()
    {
        $common = $this->extract_common_post_data();
        $meta   = [
            'dni'              => sanitize_text_field($_POST['dni']),
            'phone'            => sanitize_text_field($_POST['phone']),
            'birth_date'       => sanitize_text_field($_POST['birth_date']),
            'address'          => sanitize_text_field($_POST['address']),
            'city'             => sanitize_text_field($_POST['city']),
            'postal_code'      => sanitize_text_field($_POST['postal_code']),
            'license_type'     => sanitize_text_field($_POST['license_type']),
            'assigned_teacher' => absint($_POST['teacher']),
            'class_points'     => absint($_POST['opening_balance']),
        ];
        return $this->create_user('student', $common, $meta);
    }

    public function create_teacher()
    {
        $common = $this->extract_common_post_data();
        $meta   = [
            'assigned_vehicle'    => sanitize_text_field($_POST['assigned_vehicle']),
            'assigned_motorcycle' => sanitize_text_field($_POST['assign_motorcycle']),
            'phone'               => sanitize_text_field($_POST['phone']),
        ];
        return $this->create_user('teacher', $common, $meta);
    }

    public function update_student()
    {
        $common = $this->extract_common_post_data(true);
        $meta   = [
            'dni'              => sanitize_text_field($_POST['dni']),
            'phone'            => sanitize_text_field($_POST['phone']),
            'birth_date'       => sanitize_text_field($_POST['birth_date']),
            'address'          => sanitize_text_field($_POST['address']),
            'city'             => sanitize_text_field($_POST['city']),
            'postal_code'      => sanitize_text_field($_POST['postal_code']),
            'license_type'     => sanitize_text_field($_POST['license_type']),
            'assigned_teacher' => absint($_POST['teacher']),
            'class_points'     => absint($_POST['opening_balance']),
        ];
        return $this->update_user('student', $common, $meta);
    }

    public function update_teacher()
    {
        $common = $this->extract_common_post_data(true);
        $meta   = [
            'assigned_vehicle'    => sanitize_text_field($_POST['assigned_vehicle']),
            'assigned_motorcycle' => sanitize_text_field($_POST['assign_motorcycle']),
            'phone'               => sanitize_text_field($_POST['phone']),
        ];
        return $this->update_user('teacher', $common, $meta);
    }

    public function delete_student($user_id)
    {
        return $this->delete_user(absint($user_id));
    }
    public function delete_teacher($user_id)
    {
        return $this->delete_user(absint($user_id));
    }

    //
    // ——— Lógica común protegida ———
    //

    /**
     * Extrae los campos comunes de $_POST.
     * Si $is_update==true, espera venir con user_id y opcional password.
     */
    protected function extract_common_post_data($is_update = false)
    {
        $nombre = sanitize_text_field($_POST['first_name']);
        $apellidos  = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);

        $data  = compact('nombre', 'apellidos', 'email');

        if ($is_update) {
            $data['ID'] = absint($_POST['user_id']);
            if (! empty($_POST['password'])) {
                $data['user_pass'] = sanitize_text_field($_POST['password']);
            }
        }

        return $data;
    }

    /** Crea un usuario genérico */
    protected function create_user($role, array $common, array $meta)
    {
        // 1) Genera username y password temporal
        $username = $this->generate_unique_username($common['nombre'], $common['apellidos']);
        $temp_password = wp_generate_password(12, false);

        // 2) Inserta
        $insert = [
            'user_login' => $username,
            'user_pass'  => $temp_password,
            'user_email' => $common['email'],
            'first_name' => $common['nombre'],
            'last_name'  => $common['apellidos'],
            'role'       => $role,
        ];
        $uid = wp_insert_user($insert);
        if (is_wp_error($uid)) {
            return $uid;
        }

        // 3) Metadatos
        $this->update_meta($uid, $meta);

        // 4) Notificar
        $sent = $this->send_password_setup_email($uid);

        // 5) Mensaje final
        $msg = $role === 'student'
            ? ($sent ? 'Alumno creado. Email enviado.' : 'Alumno creado, email no enviado.')
            : ($sent ? 'Profesor creado. Email enviado.'   : 'Profesor creado, email no enviado.');

        return ['user_id' => $uid, 'sent' => (bool)$sent, 'message' => $msg];
    }

    /** Actualiza un usuario genérico */
    protected function update_user($role, array $common, array $meta)
    {
        if (empty($common['ID'])) {
            return new WP_Error('no_id', 'ID de usuario inválido');
        }

        $common['role'] = $role; // aunque role no cambia, lo pasamos por si WP lo ignora

        $uid = wp_update_user($common);

        if (is_wp_error($uid)) {
            return $uid;
        }

        $this->update_meta($uid, $meta);

        return ['user_id' => $uid, 'message' => 'Usuario actualizado correctamente.'];
    }

    /** Elimina un usuario genérico */
    protected function delete_user($user_id)
    {
        if (! $user_id) {
            return new WP_Error('no_id', 'ID de usuario inválido');
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $result = wp_delete_user($user_id);

        return $result
            ? true
            : new WP_Error('delete_failed', 'No se pudo borrar el usuario.');
    }

    protected function generate_unique_username($nombre, $apellidos)
    {
        $parts = array_merge(
            preg_split('/\s+/', trim($nombre)),
            preg_split('/\s+/', trim($apellidos))
        );

        $base = strtolower(implode('.', $parts));
        $user = $base;
        $i = 1;
        while (username_exists($user)) {
            $user = $base . '.' . $i++;
        }

        return $user;
    }

    protected function update_meta($user_id, array $meta)
    {
        foreach ($meta as $k => $v) {
            update_user_meta($user_id, $k, $v);
        }
    }

    protected function send_password_setup_email($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (! $user) {
            return false;
        }

        // 1) Generar reset key y enlace
        $key    = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return false;
        }
        $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');

        if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
            // Imprime directamente el <a><img> generado por WP
        $logo_correo = get_custom_logo();
   
        } else {
        // Fallback a url fija
            $logo_html = '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/images/logo-amarillo.png' ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="200" style="display:block;margin:0 auto;height:auto;">';
        }
        
         // 2) Preparar datos para la plantilla
        $placeholders = [
            '{{first_name}}' => $user->first_name,
            '{{username}}'   => $user->user_login,
            '{{reset_link}}' => $reset_link,
			'{{url_acceso}}' => esc_url( site_url( '/acceso' ) ),
			'{{url_sitio}}' => esc_url( site_url( '/' ) ),
			'{{app_name}}' => esc_html( get_bloginfo( 'name' ) ),
			'{{app_logo}}' =>  $logo_html,
		];

        // 3) Cargar plantilla desde /emails/new-user.php (puede ser .html)
        $tpl_path = DSB_PLUGIN_DIR_PATH . 'public/emails/new-user.html';
        error_log("Buscando plantilla en: $tpl_path");
        if (! file_exists($tpl_path)) {
            $headers  = ['Content-Type: text/plain; charset=UTF-8'];
            // plantilla fallback inline
            $message = sprintf(
                "Hola %s,\n\nTu cuenta ha sido creada. Usa este enlace para configurar tu contraseña:\n\n%s\n\nUsuario: %s",
                $user->first_name,
                $reset_link,
                $user->user_login
            );
        } else {
            $headers  = ['Content-Type: text/html; charset=UTF-8'];
            $message = file_get_contents($tpl_path);
            // 4) Reemplazar marcadores
            $message = strtr($message, $placeholders);
        }

        // 5) Asunto y cabeceras
        $subject = __('Acceso a tu cuenta en la Autoescuela', 'driving-school-bookings');

        // 6) Enviar
        return (bool) wp_mail($user->user_email, $subject, $message, $headers);
    }

    public function get_user_by_id($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (! $user) {
            return new WP_Error('no_user', 'Usuario no encontrado');
        }
        if (! in_array($user->roles[0], ['student', 'teacher'])) {
            return new WP_Error('invalid_role', 'El usuario no es un alumno o profesor');
        }

        if ($user->roles[0] === 'teacher') {
            $user_data = $this->get_teacher($user);
        } else {
            $user_data = $this->get_student($user);
        }

        return $user_data;
    }

    public function get_student($student)
    {
        $student_data = [];

        $avatar_id = get_user_meta($student->id, 'user_avatar', true);
        $avatar_url = ($avatar_id) ? wp_get_attachment_url($avatar_id) : get_avatar_url($student->ID);

        $teacher = get_user_by('id', get_user_meta($student->ID, 'assigned_teacher', true));

        $vehicle_id = get_user_meta($teacher->ID, 'assigned_vehicle', true);
        $vehicle_name = get_the_title($vehicle_id);

        $student_data = [
            'id' => $student->ID,
            'dni' => get_user_meta($student->ID, 'dni', true),
            'name' => $student->display_name,
            'email' => $student->user_email,
            'avatar' => $avatar_url,
            'phone' => get_user_meta($student->ID, 'phone', true),
            'birth_date' => date_i18n('d/m/Y', strtotime(get_user_meta($student->ID, 'birth_date', true))),
            'address' => get_user_meta($student->ID, 'address', true),
            'city' => get_user_meta($student->ID, 'city', true),
            'cp' => get_user_meta($student->ID, 'postal_code', true),
            'license_type' => get_user_meta($student->ID, 'license_type', true),
            'teacher' => [
                'id' => $teacher->ID,
                'name' => $teacher->display_name,
                'vehicle_id' => $vehicle_id,
                'vehicle_name' => $vehicle_name,
                'config' => get_user_meta($teacher->ID, 'dsb_clases_config', true),
            ],
            'bookings' => DSB_Calendar_Service::get_student_calendar($student->ID),
            'class_points' => get_user_meta($student->ID, 'class_points', true),
        ];

        return $student_data;
    }

    public function get_teacher($teacher)
    {}
}
