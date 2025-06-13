<?php
class DSB_Vehicles_View extends DSB_Base_View
{
    private $vehicles;

    public function __construct()
    {
        $this->title = 'Gestión de Vehículos';
        $this->nonce_action = 'create_vehicle';
        $this->nonce_name = 'vehicle_nonce';
        $this->vehicles = get_posts([
            'post_type' => 'vehiculo',
            'posts_per_page' => -1,
        ]);
    }

    protected function get_data() {}

    private function get_vehicles_data()
    {
        $vehicles = [];
        foreach ($this->vehicles as $vehicle) {
            $vehicles[] = [
                'id' => $vehicle->ID,
                'model' => $vehicle->post_title,
                'license_plate' => get_post_meta($vehicle->ID, 'license_plate', true),
                'model_year' => get_post_meta($vehicle->ID, 'model_year', true),
                'transmission' => get_post_meta($vehicle->ID, 'transmission', true),
                'vehicle_type' => get_post_meta($vehicle->ID, 'vehicle_type', true),
            ];
        }
        return $vehicles;
    }

    protected function handle_form_submission()
    {
        $this->verify_nonce();

        switch ($_POST['form_action']) {
            case 'create_vehicle':
                $this->handle_create_form_vehicle();
                break;
            case 'edit_vehicle':
                $this->handle_update_form_vehicle();
                break;
            case 'delete_vehicle':
                $this->handle_delete_form_vehicle();
                break;
            default:
                $this->render_notice('Acción no válida', 'error');
        }
    }

    private function handle_create_form_vehicle()
    {
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

    private function handle_update_form_vehicle()
    {
        $post_data = [
            'ID' => absint($_POST['vehicle_id']),
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

        $post_id = wp_update_post($post_data);

        if ($post_id) {
            $this->render_notice('Vehículo actualizado exitosamente');
        } else {
            $this->render_notice('Error al actualizar el vehículo', 'error');
        }
    }

    private function handle_delete_form_vehicle()
    {
        $post_id = absint($_POST['vehicle_id']);

        $post = wp_delete_post($post_id, true);

        if ($post) {
            $this->render_notice('Vehículo eliminado exitosamente');
        } else {
            $this->render_notice('Error al eliminar el vehículo', 'error');
        }
    }

    protected function render_forms()
    {
?>
        <div>
            <h2>
                <span id="vehicleName"></span>
            </h2>
        </div>
    <?php
        $this->render_create_vehicle_form();
        $this->render_edit_vehicle_form();
        $this->render_delete_vehicle_form();
    }

    private function render_create_vehicle_form()
    {
    ?>
        <form method="post" id="createFormContainer" data-action-id="create-vehicle" action="" style="display:none; margin-top: 20px;">
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

            <input type="hidden" name="form_action" value="create_vehicle" />

            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Crear vehículo" />
            </p>
        </form>
    <?php
    }

    private function render_edit_vehicle_form()
    {
    ?>
        <div id="editFormContainer" data-action-id="edit-vehicle" style="display:none; margin-top: 20px;">
            <form method="post" id="editar-vehiculo-form" data-action-id="edit-vehicle" action="" >
                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="vehicle_id" value="" />

                <table class="form-table">
                    <tr>
                        <th><label for="vehicle_type">Tipo de vehículo</label></th>
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

                <input type="hidden" name="form_action" value="edit_vehicle" />

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Crear vehículo" />
                </p>
            </form>
        </div>
    <?php
    }

    public function render_delete_vehicle_form()
    {
    ?>
        <dialog id="deleteVehicleModal">
            <form method="post" id="deleteVehicleForm" action="">

                <?php wp_nonce_field($this->nonce_action, $this->nonce_name); ?>
                <input type="hidden" name="vehicle_id" value="" />
                <input type="hidden" name="form_action" value="delete_vehicle" />

                <p>¿Estás seguro de que deseas eliminar este profesor?</p>

                <input type="submit" name="submit" class="button-primary" value="Eliminar" />
                <button type="button" onclick="document.getElementById('deleteVehicleModal').close();" class="button button-secondary">Cancelar</button>

            </form>
        </dialog>
    <?php
    }

    protected function enqueue_scripts()
    {
        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'fullcalendar-js',
            DSB_PLUGIN_FULLCALENDAR_URL,
            ['jquery'],
            '5.11.3',
            true
        );

        wp_enqueue_style(
            'vehicle-admin-css',
            DSB_PLUGIN_URL . '../public/css/admin/vehicle-view.css',
            [],
            DSB_VERSION
        );

        wp_enqueue_script('vehiculo-js', DSB_PLUGIN_URL . '../public/js/admin/vehicles-admin-view.js', ['jquery'], DSB_VERSION, true);

        wp_localize_script('vehiculo-js', 'allVehicleData', $this->get_vehicles_data());

        wp_localize_script('vehiculo-js', 'vehiculoAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    protected function render_table()
    {
    ?>

        <div class="heding">
            <h2>Listado de vehículos</h2>
            <div class="boton-heding">
                <button class="button button-primary" data-action-id="create-vehicle">Nuevo vehículo</button>
            </div>
        </div>

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
                <?php foreach ($this->vehicles as $vehicle): ?>
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
                            <a href="#" class="button" data-action-id="edit-vehicle" data-vehicle-id="<?= $vehicle->ID ?>">Editar</a>
                            <a href="#" class="button" data-action-id="delete-vehicle" data-vehicle-id="<?= $vehicle->ID ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
}
