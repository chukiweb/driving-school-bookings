jQuery(document).ready(function ($) {

    class vehicleAdminView {

        static createFormContainer = document.querySelector('#createFormContainer');
        static editFormContainer = document.querySelector('#editFormContainer');
        static deleteVehicleModal = document.querySelector('#deleteVehicleModal');
        static lastAction = null;

        static init() {
            document.querySelectorAll('.button').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = e.target.dataset.actionId;
                    const btn = e.target.closest('.button');

                    if (action !== vehicleAdminView.lastAction) {
                        vehicleAdminView.toogleAllContainers(action);
                        vehicleAdminView.lastAction = action;
                    }

                    vehicleAdminView.handleAction(action, btn);
                    vehicleAdminView.changeName(btn);
                });
            });
        }

        static changeName(btn) {
            const vehicleId = btn.dataset.vehicleId;

            if (!vehicleId) {
                document.querySelector('#vehicleName').textContent = '';
                return;
            }

            allVehicleData.forEach(function (prof) {
                if (prof.id == vehicleId) {
                    const name = prof.model;
                    document.querySelector('#vehicleName').textContent = name;
                }
            });
        }

        static toogleAllContainers(target) {
            const containers = [
                vehicleAdminView.createFormContainer,
                vehicleAdminView.editFormContainer,
            ];

            containers.forEach(container => {
                if (container.dataset.actionId === target) {
                    $(container).slideDown();
                } else {
                    $(container).slideUp();
                }
            });
        }

        static handleAction(action, btn) {
            switch (action) {
                case 'create':
                    vehicleAdminView.createFormAction();
                    break;
                case 'edit-vehicle':
                    vehicleAdminView.editFormAction(btn.dataset.vehicleId);
                    break;
                case 'delete-vehicle':
                    vehicleAdminView.deleteFormAction(btn.dataset.vehicleId);
                    break;
                default:
                    console.error('Acción no reconocida:', action);
            }
        }

        static createFormAction() {
            vehicleAdminView.createFormContainer.querySelector('form').reset();
        }

        static editFormAction(vehicleId) {

            const editForm = document.querySelector('#editar-vehiculo-form');

            allVehicleData.forEach(function (prof) {

                if (prof.id == vehicleId) {
                    editForm.querySelector('input[name="vehicle_id"]').value = prof.id;
                    editForm.querySelector('input[name="model"]').value = prof.model;
                    editForm.querySelector('select[name="vehicle_type"]').value = prof.vehicle_type;
                    editForm.querySelector('input[name="license_plate"]').value = prof.license_plate;
                    editForm.querySelector('input[name="model_year"]').value = prof.model_year;
                    editForm.querySelector('select[name="transmission"]').value = prof.transmission;
                }
            });
        }

        static deleteFormAction(vehicleId) {
            const deleteForm = document.querySelector('#deleteVehicleForm');

            deleteForm.querySelector('input[name="vehicle_id"]').value = vehicleId;

            vehicleAdminView.deleteVehicleModal.showModal();
        }

    }

    vehicleAdminView.init();

});