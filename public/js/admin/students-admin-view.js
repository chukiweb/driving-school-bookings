jQuery(document).ready(function ($) {

    class studentAdminView {

        static createFormContainer = document.querySelector('#createFormContainer');
        static editFormContainer = document.querySelector('#editFormContainer');
        static deleteStudentModal = document.querySelector('#deleteStudentModal');
        static lastAction = null;

        static init() {
            document.querySelectorAll('.button').forEach(button => {
                if (button.type === 'submit') {
                    return;
                }
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = e.target.dataset.actionId;
                    const btn = e.target.closest('.button');

                    if (action !== studentAdminView.lastAction) {
                        studentAdminView.toogleAllContainers(action);
                        studentAdminView.lastAction = action;
                    }

                    studentAdminView.handleAction(action, btn);
                    studentAdminView.changeName(btn);
                });
            });
        }

        static changeName(btn) {
            const studentId = btn.dataset.userId;

            if (!studentId) {
                document.querySelector('#studentName').textContent = '';
                return;
            }

            allStudentData.forEach(function (prof) {
                if (prof.id == studentId) {
                    const name = `${prof.first_name} ${prof.last_name}`;
                    document.querySelector('#studentName').textContent = name;
                }
            });
        }

        static toogleAllContainers(target) {
            const containers = [
                studentAdminView.createFormContainer,
                studentAdminView.editFormContainer,
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
                    studentAdminView.createFormAction();
                    break;
                case 'edit':
                    studentAdminView.editFormAction(btn.dataset.userId);
                    break;
                case 'delete':
                    studentAdminView.deleteFormAction(btn.dataset.userId);
                    break;
                case 'send-reset-password-email':
                    break;
                default:
                    console.error('Acción no reconocida:', action);
            }
        }

        static createFormAction() {
            studentAdminView.createFormContainer.querySelector('form').reset();
        }

        static editFormAction(studentId) {

            const editForm = document.querySelector('#editar-alumno-form');

            allStudentData.forEach(function (prof) {

                if (prof.id == studentId) {
                    editForm.querySelector('input[name="user_id"]').value = prof.id;
                    editForm.querySelector('input[name="first_name"]').value = prof.first_name;
                    editForm.querySelector('input[name="last_name"]').value = prof.last_name;
                    editForm.querySelector('input[name="email"]').value = prof.email;
                    editForm.querySelector('input[name="dni"]').value = prof.dni;
                    editForm.querySelector('input[name="phone"]').value = prof.phone;
                    editForm.querySelector('input[name="birth_date"]').value = prof.birth_date;
                    editForm.querySelector('input[name="address"]').value = prof.address;
                    editForm.querySelector('input[name="city"]').value = prof.city;
                    editForm.querySelector('input[name="postal_code"]').value = prof.postal_code;
                    editForm.querySelector('select[name="license_type"]').value = prof.license_type;
                    editForm.querySelector('select[name="teacher"]').value = prof.assigned_teacher_id;
                    editForm.querySelector('input[name="opening_balance"]').value = prof.class_points;
                }
            });
        }

        static deleteFormAction(studentId) {
            const deleteForm = document.querySelector('#deleteStudentForm');

            deleteForm.querySelector('input[name="user_id"]').value = studentId;

            studentAdminView.deleteStudentModal.showModal();
        }

    }

    studentAdminView.init();
});