<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_User_Service
{
    public static function get_avatar_url($user_id)
    {
        $avatar_url = get_user_meta($user_id, 'dsb_avatar_url', true);

        if (!$avatar_url) {
            // Fallback a Gravatar si no hay avatar personalizado
            $user_data = get_userdata($user_id);
            $avatar_url = get_avatar_url($user_data->user_email, ['size' => 150, 'default' => 'mystery']);
        }

        return $avatar_url;
    }

    public static function upload_avatar($request)
    {
        // Verificar autorización y obtener ID de usuario
        $token = str_replace('Bearer ', '', $request->get_header('Authorization'));
        $decoded = DSB()->jwt->validate_token($token);

        if (!$decoded || !isset($decoded->user->id)) {
            return new WP_Error('unauthorized', 'Usuario no autorizado', ['status' => 401]);
        }

        $user_id = $decoded->user->id;

        // Verificar que se haya enviado un archivo
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error('no_file', 'No se ha enviado ningún archivo', ['status' => 400]);
        }

        // Restricciones para el avatar
        $max_size = 2 * 1024 * 1024; // 2MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        $file = $files['file'];

        // Validar tamaño
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_big', 'El archivo es demasiado grande (máx. 2MB)', ['status' => 400]);
        }

        // Validar tipo MIME
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', 'Tipo de archivo no permitido. Use JPG, PNG o GIF', ['status' => 400]);
        }

        // Preparar la subida del archivo
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Configurar carpeta personalizada para avatares
        add_filter('upload_dir', function ($dirs) use ($user_id) {
            $dirs['subdir'] = '/avatars/' . $user_id;
            $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
            return $dirs;
        });

        // Subir el archivo usando la API de medios de WordPress
        $attachment_id = media_handle_upload('file', 0, [
            'post_title' => 'Avatar del usuario ' . $user_id,
        ]);

        // Remover el filtro para no afectar otras subidas
        remove_all_filters('upload_dir');

        if (is_wp_error($attachment_id)) {
            return new WP_Error('upload_error', $attachment_id->get_error_message(), ['status' => 500]);
        }

        // Obtener URL de la imagen subida
        $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        // Guardar referencia en los meta datos del usuario
        update_user_meta($user_id, 'dsb_avatar_id', $attachment_id);
        update_user_meta($user_id, 'dsb_avatar_url', $image_url);

        // Eliminar avatar anterior si existe
        $old_avatar_id = get_user_meta($user_id, 'dsb_avatar_id', true);
        if ($old_avatar_id && $old_avatar_id != $attachment_id) {
            wp_delete_attachment($old_avatar_id, true);
        }

        return rest_ensure_response([
            'success' => true,
            'url' => $image_url,
            'message' => 'Avatar actualizado correctamente'
        ]);
    }
}
