<?php
// admin/notifications-view.php

if (! defined('ABSPATH')) {
    exit;
}

class DSB_Notifications_View extends DSB_Base_View
{

    public function __construct()
    {
        $this->title        = 'Gestión de Notificaciones';
        $this->nonce_action = 'create_notification';
        $this->nonce_name   = 'notification_nonce';
    }

    protected function get_data()
    {
        return get_posts([
            'post_type'      => 'dsb_notification',
            'posts_per_page' => -1,
        ]);
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

        $title     = sanitize_text_field($_POST['title']);
        $message   = wp_kses_post($_POST['message']);
        $type      = sanitize_text_field($_POST['type']);
        $recipient = sanitize_text_field($_POST['recipient']);

        if (empty($recipient)) {
            $this->render_notice('Debes seleccionar un destinatario válido (o escribir "student-all" para todos los alumnos, "teacher-all" para todos los profesores).', 'error');
            return;
        }

        // Insertar post
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $message,
            'post_type'    => 'dsb_notification',
            'post_status'  => 'publish',
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'type', $type);
            update_post_meta($post_id, 'recipient', $recipient);
            update_post_meta($post_id, 'status', 'unread');

            $this->send_admin_notification($post_id, $title, $message, $type, $recipient);
            $this->render_notice('Notificación creada y enviada exitosamente');
        } else {
            $this->render_notice('Error al crear la notificación', 'error');
        }
    }


    private function send_admin_notification($post_id, $title, $message, $type, $recipient)
    {
        $user_ids = [];

        if ('all-users' === $recipient) {
            $user_ids = get_users(['fields' => 'ID']);
        } elseif ('student-all' === $recipient) {
            $user_ids = get_users(['role' => 'student', 'fields' => 'ID']);
        } elseif ('teacher-all' === $recipient) {
            $user_ids = get_users(['role' => 'teacher', 'fields' => 'ID']);
        } elseif (preg_match('/^(student|teacher)-(\d+)$/', $recipient, $m)) {
            $user_ids = [(int) $m[2]];
        }

        if (empty($user_ids)) {
            return;
        }

        $manager = DSB_Notification_Manager::get_instance();
        $placeholders = ['title' => $title, 'message' => $message, 'type' => $type];

        foreach ($user_ids as $uid) {
            $manager->notify('admin_broadcast', $uid, $placeholders);
        }

        // Marcar meta para no reenviarlo
        update_post_meta($post_id, '_dsb_notification_sent', 1);
    }

    protected function render_forms()
    {
?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>

            <table class="form-table">
                <!-- Título de la notificación -->
                <tr>
                    <th><label for="title">Título</label></th>
                    <td><input type="text" name="title" id="title" required /></td>
                </tr>

                <!-- Mensaje -->
                <tr>
                    <th><label for="message">Mensaje</label></th>
                    <td><textarea name="message" id="message" rows="5" required></textarea></td>
                </tr>

                <!-- Tipo -->
                <tr>
                    <th><label for="type">Tipo</label></th>
                    <td>
                        <select name="type" id="type" required>
                            <option value="info">Información</option>
                            <option value="warning">Advertencia</option>
                            <option value="success">Éxito</option>
                            <option value="error">Error</option>
                        </select>
                    </td>
                </tr>

                <!-- Campo de autocompletado para usuario -->
                <tr>
                    <th><label for="recipient_search">Buscar usuario</label></th>
                    <td>
                        <div class="autocomplete-wrapper" style="position: relative; display: inline-block; width: 250px;">
                            <input
                                type="text"
                                name="recipient_search"
                                id="recipient_search"
                                placeholder="Escribe nombre o “todos”…"
                                autocomplete="off"
                                style="width: 100%;"
                                required />

                            <div id="recipient_suggestions" class="autocomplete-items"></div>
                        </div>

                        <input type="hidden" name="recipient" id="recipient_value" value="" />

                        <p class="description">
                            Empieza a escribir y selecciona una opción de la lista.<br>
                            Puedes elegir “Todos los usuarios”, “Todos los alumnos” o “Todos los profesores”.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear Notificación" />
            </p>
        </form>
    <?php
    }

    protected function render_table()
    {
        $notifications = $this->get_data();
    ?>
        <h2>Listado de Notificaciones</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Destinatario</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notification) :
                    $meta_type       = get_post_meta($notification->ID, 'type', true);
                    $meta_recipient  = get_post_meta($notification->ID, 'recipient', true);
                    $meta_status     = get_post_meta($notification->ID, 'status', true);
                    $display_recipient = $this->get_recipient_label($meta_recipient);
                ?>
                    <tr>
                        <td><?php echo esc_html($notification->post_title); ?></td>
                        <td><?php echo esc_html($meta_type); ?></td>
                        <td><?php echo esc_html($display_recipient); ?></td>
                        <td><?php echo esc_html($meta_status); ?></td>
                        <td><?php echo esc_html(get_the_date('', $notification->ID)); ?></td>
                        <td>
                            <a href="#" class="button" data-action="delete" data-id="<?php echo esc_attr($notification->ID); ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }

    /**
     * Traduce el valor guardado en meta “recipient” a un texto legible.
     */
    private function get_recipient_label($recipient_value)
    {
        if (! $recipient_value) {
            return 'N/A';
        }

        if ('all-users' === $recipient_value) {
            return 'Todos los usuarios';
        }
        if ('student-all' === $recipient_value) {
            return 'Todos los alumnos';
        }
        if ('teacher-all' === $recipient_value) {
            return 'Todos los profesores';
        }

        // Si comienza con “student-” o “teacher-” y luego ID
        if (preg_match('/^(student|teacher)-(\d+)$/', $recipient_value, $matches)) {
            $role = $matches[1];   // “student” o “teacher”
            $id   = (int) $matches[2];
            $user = get_user_by('ID', $id);
            if ($user) {
                return $user->display_name . ' (' . ucfirst($role) . ')';
            }
        }

        return 'Desconocido';
    }

    protected function enqueue_scripts()
    {
        wp_enqueue_script(
            'dsb-notifications-admin',
            DSB_PLUGIN_URL . '../public/js/admin/notification-admin-view.js',
            ['wp-api'],
            DSB_VERSION,
            true
        );

        wp_localize_script(
            'dsb-notifications-admin',
            'wpApiSettings',
            ['nonce' => wp_create_nonce('wp_rest')]
        );

        wp_enqueue_style(
            'notification-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/notification-view.css',
            [],
            '1.0.0'
        );
    }
}
