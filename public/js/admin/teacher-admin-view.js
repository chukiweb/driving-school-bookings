jQuery(document).ready(function ($) {

    class formContainers{

        static createFormContainer = document.querySelector('#createFormContainer');
        static updateFormContainer = document.querySelector('#editFormContainer');
        static configFormContainer = document.querySelector('#configFormContainer');
        static lastAction = null;

        static init() {
            document.querySelectorAll('.button').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = e.target.dataset.actionId;
                    const btn = e.target.closest('.button');
                    
                    if (action !== formContainers.lastAction) {
                        formContainers.toogleAllContainers(action);
                        formContainers.lastAction = action;
                    }
                    
                    formContainers.handleAction(action, btn);
                });
            });
        }

        static getContainerByAction(action) {
            const containers = {
                'create': formContainers.createFormContainer,
                'edit': formContainers.updateFormContainer,
                'config': formContainers.configFormContainer
            };
            return containers[action] || null;
        }

        static toogleAllContainers(target) {
            const containers = [
                formContainers.createFormContainer, 
                formContainers.updateFormContainer, 
                formContainers.configFormContainer
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
                    formContainers.createFormAction();
                    break;
                case 'edit':
                    formContainers.editFormAction(btn.dataset.userId);
                    break;
                case 'config':
                    formContainers.configFormAction();
                    break;
                default:
                    console.error('Acción no reconocida:', action);
            }
        }

        static createFormAction() {
            formContainers.createFormContainer.querySelector('form').reset();
        }

        static editFormAction(teacherId) {
            
            const updateForm = document.querySelector('#editar-profesor-form');

            allTeacherData.forEach(function (prof) {

                if (prof.id == teacherId) {
                    updateForm.querySelector('input[name="username"]').value = prof.username;
                    updateForm.querySelector('input[name="password"]').value = '1234';
                    updateForm.querySelector('input[name="email"]').value = prof.email;
                    updateForm.querySelector('input[name="phone"]').value = prof.phone;
                    updateForm.querySelector('input[name="first_name"]').value = prof.firstName;
                    updateForm.querySelector('input[name="last_name"]').value = prof.lastName;
                    updateForm.querySelector('select[name="assigned_vehicle"]').value = prof.vehicleId;
                    updateForm.querySelector('select[name="assign_motorcycle"]').value = prof.motorcycleId;
                }
            });
        }

    }

    formContainers.init();

    console.log(allTeacherData);

    // CALENDARIO
    $('.button:contains("Calendario")').on('click', function (e) {
        e.preventDefault();
        const teacherId = $(this).closest('tr').find('[data-id]').data('id');

        $.ajax({
            url: profesorAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'dsb_get_teacher_calendar',
                teacher_id: teacherId
            },
            success: function (events) {
                $("#teacher-calendar-container").css("display", "block");
                const calendar = new FullCalendar.Calendar(document.getElementById('teacher-calendar'), {
                    initialView: 'timeGridWeek',
                    slotMinTime: '08:00:00',
                    slotMaxTime: '21:00:00',
                    allDaySlot: false,
                    height: 'auto',
                    events: events,
                    eventContent: function (arg) {
                        const title = arg.event.title;
                        const start = arg.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const end = arg.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                        return {
                            html: `<div class="fc-event-title">${start} - ${end}<br><strong>${title}</strong></div>`
                        };
                    }
                });
                calendar.render();

            },
            error: function () {
                alert("No se pudo cargar el calendario.");
            }
        });
    });

    // CLASES
    $('.open-class-settings').on('click', function (e) {
        e.preventDefault();
        const teacherId = $(this).data('id');
        $('#clases_teacher_id').val(teacherId);
        $('#updateFormContainer').slideToggle();
    });
    
});
