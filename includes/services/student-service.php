<?php
if (!defined('ABSPATH')) {
    exit;
}

class DSB_Student_Service
{
    public static function get_student($student_id)
    {
        if (!$student_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'ID de estudiante inválido',
            ], 400);
        }

        $user = get_userdata($student_id);
        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Estudiante no encontrado',
            ], 404);
        }

        // Obtener la imagen personalizada desde los metadatos del usuario
        $avatar_id = get_user_meta($student_id, 'user_avatar', true);
        $avatar_url = ($avatar_id) ? wp_get_attachment_url($avatar_id) : get_avatar_url($student_id);

        // Obtener ID del profesor y del vehículo desde los metadatos del estudiante
        $teacher_id = get_user_meta($student_id, 'assigned_teacher', true);
        $vehicle_id = get_user_meta($student_id, 'assigned_vehicle', true);

        // Obtener el nombre del profesor
        $teacher_name = ($teacher_id) ? get_userdata($teacher_id)->display_name : "Sin profesor asignado";

        // Obtener el nombre del vehículo
        $vehicle_name = ($vehicle_id) ? get_post($vehicle_id)->post_title : "Sin vehículo asignado";

        // Obtener datos adicionales del perfil
        $meta_data = [
            'dni' => get_user_meta($student_id, 'dni', true),
            'phone' => get_user_meta($student_id, 'phone', true),
            'birth_date' => get_user_meta($student_id, 'birth_date', true),
            'address' => get_user_meta($student_id, 'address', true),
            'city' => get_user_meta($student_id, 'city', true),
            'postal_code' => get_user_meta($student_id, 'postal_code', true),
            'license_type' => get_user_meta($student_id, 'license_type', true),
            'assigned_teacher' => [
                'name' => $teacher_name,
                'id' =>  $teacher_id
            ],
            'assigned_vehicle' => [
                'name' =>  $vehicle_name,
                'id' => $vehicle_id 
            ],
            'class_points' => get_user_meta($student_id, 'class_points', true),
        ];

        // Obtener las reservas del estudiante
        $bookings = self::get_student_bookings($student_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Datos del estudiante obtenidos correctamente',
            'data' => array_merge([
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => $avatar_url,
                'bookings' => $bookings,
            ], $meta_data)
        ], 200);
    }

    private static function get_student_bookings($student_id)
    {
        $args = [
            'post_type' => 'reserva',
            'meta_query' => [
                [
                    'key' => 'student_id',
                    'value' => $student_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1
        ];

        $query = new WP_Query($args);
        $bookings = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bookings[] = [
                    'id' => get_the_ID(),
                    'teacher' => get_user_by('id', get_post_meta(get_the_ID(), 'teacher_id', true))->display_name,
                    'vehicle' => get_post(get_post_meta(get_the_ID(), 'vehicle_id', true))->post_title,
                    'date' => get_post_meta(get_the_ID(), 'date', true),
                    'time' => get_post_meta(get_the_ID(), 'time', true),
                    'status' => get_post_meta(get_the_ID(), 'status', true),
                ];
            }
        }

        wp_reset_postdata();

        return rest_ensure_response([
            'success' => true,
            'data' => $bookings
        ]);
    }


    public function upload_user_avatar($request)
    {
        $user_id = $request->userId;

        if (!$user_id) {
            return new WP_Error('unauthorized', 'No tienes permisos para esta acción', ['status' => 401]);
        }

        if (empty($_FILES['file'])) {
            return new WP_Error('no_file', 'No se ha enviado ningún archivo', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file = $_FILES['file'];
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error'], ['status' => 500]);
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        if (!$attach_id) {
            return new WP_Error('upload_failed', 'Error al guardar la imagen en la biblioteca de medios', ['status' => 500]);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));

        // Guardar la URL de la imagen en el perfil del usuario
        $image_url = wp_get_attachment_url($attach_id);
        update_user_meta($user_id, 'profile_picture', $image_url);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Imagen subida correctamente',
            'url' => $image_url
        ]);
    }

}

