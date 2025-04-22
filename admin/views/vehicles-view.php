<?php
class DSB_Vehicles_View extends DSB_Base_View
{
    public function __construct()
    {
        $this->title = 'Gestión de Vehículos';
        $this->nonce_action = 'create_vehicle';
        $this->nonce_name = 'vehicle_nonce';
    }

    protected function get_data()
    {
        return get_posts([
            'post_type' => 'vehiculo',
            'posts_per_page' => -1,
        ]);
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

        $post_data = [
            'post_title' => sanitize_text_field($_POST['model']),
            'post_type' => 'vehiculo',
            'post_status' => 'publish',
            'meta_input' => [
                'vehicle_type' => sanitize_text_field($_POST['vehicle_type']),
                'license_plate' => sanitize_text_field($_POST['license_plate']),
                'model_year' => sanitize_text_field($_POST['model_year']),
                'transmission' => sanitize_text_field($_POST['transmission'])
            ]
        ];

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            $this->render_notice('Vehículo creado exitosamente');
        } else {
            $this->render_notice('Error al crear el vehículo', 'error');
        }
    }

    protected function render_forms()
    {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
            <table class="form-table">
                <tr>
                    <th><label for="vehicle_type">Tipo de Vehículo</label></th>
                    <td>
                        <select name="vehicle_type" id="vehicle_type" required>
                            <option value="car">Coche</option>
                            <option value="motorcycle">Moto</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="model">Modelo</label></th>
                    <td><input type="text" name="model" required /></td>
                </tr>
                <tr>
                    <th><label for="license_plate">Matrícula</label></th>
                    <td><input type="text" name="license_plate" required /></td>
                </tr>
                <tr>
                    <th><label for="model_year">Año</label></th>
                    <td><input type="number" name="model_year" required /></td>
                </tr>
                <tr>
                    <th><label for="transmission">Transmisión</label></th>
                    <td>
                        <select name="transmission" required>
                            <option value="manual">Manual</option>
                            <option value="automatic">Automático</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear Vehículo" />
            </p>
        </form>
        <?php
    }

    protected function render_table()
    {
        $vehicles = $this->get_data();
        ?>
        <h2>Listado de Vehículos</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Modelo</th>
                    <th>Matrícula</th>
                    <th>Año</th>
                    <th>Transmisión</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td>
                            <?php
                            $type = get_post_meta($vehicle->ID, 'vehicle_type', true);
                            echo $type === 'car' ? 'Coche' : ($type === 'motorcycle' ? 'Moto' : '');
                            ?>
                        </td>
                        <td><?php echo esc_html($vehicle->post_title); ?></td>
                        <td><?php echo esc_html(get_post_meta($vehicle->ID, 'license_plate', true)); ?></td>
                        <td><?php echo esc_html(get_post_meta($vehicle->ID, 'model_year', true)); ?></td>
                        <td><?php echo esc_html(get_post_meta($vehicle->ID, 'transmission', true)); ?></td>
                        <td>
                            <a href="#" class="button">Editar</a>
                            <a href="#" class="button">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    protected function enqueue_scripts()
    {
        
    }
}