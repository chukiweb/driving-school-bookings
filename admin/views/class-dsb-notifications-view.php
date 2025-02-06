<?php
class DSB_Notifications_View extends DSB_Base_View {
    public function __construct() {
        $this->title = 'Gestión de Notificaciones';
        $this->nonce_action = 'create_notification';
        $this->nonce_name = 'notification_nonce';
    }

    protected function get_data() {
        return get_posts([
            'post_type' => 'notificacion',
            'posts_per_page' => -1,
        ]);
    }

    protected function handle_form_submission() {
        $this->verify_nonce();

        $post_data = [
            'post_title' => sanitize_text_field($_POST['title']),
            'post_content' => wp_kses_post($_POST['message']),
            'post_type' => 'notificacion',
            'post_status' => 'publish',
            'meta_input' => [
                'type' => sanitize_text_field($_POST['type']),
                'recipient_id' => sanitize_text_field($_POST['recipient']),
                'status' => 'unread'
            ]
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id) {
            $this->render_notice('Notificación creada exitosamente');
        } else {
            $this->render_notice('Error al crear la notificación', 'error');
        }
    }

    protected function render_form() {
        $users = get_users(['role__in' => ['estudiante', 'profesor']]);
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
            <table class="form-table">
                <tr>
                    <th><label for="title">Título</label></th>
                    <td><input type="text" name="title" required /></td>
                </tr>
                <tr>
                    <th><label for="message">Mensaje</label></th>
                    <td><textarea name="message" rows="5" required></textarea></td>
                </tr>
                <tr>
                    <th><label for="type">Tipo</label></th>
                    <td>
                        <select name="type" required>
                            <option value="info">Información</option>
                            <option value="warning">Advertencia</option>
                            <option value="success">Éxito</option>
                            <option value="error">Error</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="recipient">Destinatario</label></th>
                    <td>
                        <select name="recipient" required>
                            <option value="">Seleccionar destinatario</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name . ' (' . ucfirst($user->roles[0]) . ')'); ?> -->
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear Notificación" />
            </p>
        </form>
        <?php
    }

    protected function render_table() {
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
            <?php foreach ($notifications as $notification):
                   $recipient = get_user_by('id', get_post_meta($notification->ID, 'recipient_id', true));
               ?>
               <tr>
                   <td><?php echo esc_html($notification->post_title); ?></td>
                   <td><?php echo esc_html(get_post_meta($notification->ID, 'type', true)); ?></td>
                   <td><?php echo $recipient ? esc_html($recipient->display_name) : 'N/A'; ?></td>
                   <td><?php echo esc_html(get_post_meta($notification->ID, 'status', true)); ?></td>
                   <td><?php echo esc_html(get_the_date('', $notification->ID)); ?></td>
                   <td>
                       <a href="#" class="button">Marcar como leída</a>
                       <a href="#" class="button">Eliminar</a>
                   </td>
               </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
       <?php
   }
}