jQuery(document).ready(function ($) {

    class teacherAdminView {

        static createFormContainer = document.querySelector('#createFormContainer');
        static updateFormContainer = document.querySelector('#editFormContainer');
        static configFormContainer = document.querySelector('#configFormContainer');
        static deleteTeacherModal = document.querySelector('#deleteTeacherModal');
        static calendarContainer = document.querySelector('#teacherCalendarContainer');
        static lastAction = null;

        static init() {
            document.querySelectorAll('.button').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = e.target.dataset.actionId;
                    const btn = e.target.closest('.button');

                    if (action !== teacherAdminView.lastAction) {
                        teacherAdminView.toogleAllContainers(action);
                        teacherAdminView.lastAction = action;
                    }

                    teacherAdminView.handleAction(action, btn);
                });
            });
        }

        static getContainerByAction(action) {
            const containers = {
                'create': teacherAdminView.createFormContainer,
                'edit': teacherAdminView.updateFormContainer,
                'config': teacherAdminView.configFormContainer
            };
            return containers[action] || null;
        }

        static toogleAllContainers(target) {
            const containers = [
                teacherAdminView.createFormContainer,
                teacherAdminView.updateFormContainer,
                teacherAdminView.configFormContainer,
                teacherAdminView.calendarContainer,
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
                    teacherAdminView.createFormAction();
                    break;
                case 'edit':
                    teacherAdminView.editFormAction(btn.dataset.userId);
                    break;
                case 'open-config':
                    teacherAdminView.configFormAction(btn.dataset.userId);
                    break;
                case 'open-calendar':
                    teacherAdminView.calendarFormAction(btn.dataset.userId);
                    break;
                case 'delete':
                    teacherAdminView.deleteFormAction(btn.dataset.userId);
                    break;
                default:
                    console.error('Acción no reconocida:', action);
            }
        }

        static createFormAction() {
            teacherAdminView.createFormContainer.querySelector('form').reset();
        }

        static editFormAction(teacherId) {

            const updateForm = document.querySelector('#editar-profesor-form');

            allTeacherData.forEach(function (prof) {

                if (prof.id == teacherId) {
                    updateForm.querySelector('input[name="user_id"]').value = prof.id;
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

        static configFormAction(teacherId) {
            console.log("TEACHER ID CONFIG FORM: ", teacherId);
            const configForm = document.querySelector('#configFormContainer form');
            teacherAdminView.configFormContainer.querySelector('form').reset();

            allTeacherData.forEach(function (prof) {
                if (prof.id == teacherId) {
                    configForm.querySelector('input[name="user_id"]').value = prof.id;

                    if (prof.config.dias) {
                        prof.config.dias.forEach(function (day) {
                            const checkbox = configForm.querySelector(`input[value="${day}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }

                    configForm.querySelector('input[name="hora_inicio"]').value = prof.config.hora_inicio;
                    configForm.querySelector('input[name="hora_fin"]').value = prof.config.hora_fin;
                    configForm.querySelector('input[name="duracion"').value = prof.config.duracion;
                }
            });
        }

        static deleteFormAction(teacherId) {
            const deleteForm = document.querySelector('#deleteTeacherForm');
            console.log(deleteForm);
            console.log(teacherId);

            deleteForm.querySelector('input[name="user_id"]').value = teacherId;

            teacherAdminView.deleteTeacherModal.showModal();
        }

        static calendarFormAction(teacherId) {
            // Clear any existing calendar
            const calendarElement = document.getElementById('teacherCalendar');
            teacherAdminView.calendarContainer.style.display = 'block';
            if (calendarElement) {
                calendarElement.innerHTML = '';
            }

            // Get teacher data
            let teacherEvents = [];
            let teacherConfig = [];
            allTeacherData.forEach(function (prof) {
                if (prof.id == teacherId) {
                    // If the teacher has events property, use it
                    if (prof.events) {
                        teacherEvents = prof.events;
                    }
                    // If the teacher has config property, use it
                    if (prof.config) {
                        teacherConfig = prof.config;
                    }

                    // You might want to display teacher name or other info
                    // document.querySelector('#teacherCalendarContainer .teacher-name').textContent = 
                    // `${prof.firstName} ${prof.lastName}`;
                }
            });

            // Initialize the calendar with the teacher's events
            const calendar = new FullCalendar.Calendar(calendarElement, {
                // UTC time zone
                timeZone: 'UTC',
                allDaySlot: false,
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día'
                },
                expandRows: true,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: '100%',
                initialView: 'timeGridWeek',
                locale: 'es',
                nowIndicator: true,
                selectable: true,
                slotDuration: '00:45:00',
                slotLabelInterval: '00:45:00',
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                slotMinTime: teacherConfig.hora_inicio || '08:00:00',
                slotMaxTime: teacherConfig.hora_fin || '21:00:00',
                events: teacherEvents,
                eventContent: function (arg) {
                    const title = arg.event.title;
                    const start = arg.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const end = arg.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    return {
                        html: `
                            <div class="fc-event-custom">
                                <span class="fc-event-time">${start} - ${end}</span><br>
                                <strong>${title}</strong>
                            </div>
                        `
                    };
                },
            });

            calendar.render();
        }
    }

    teacherAdminView.init();

    console.log(allTeacherData);

});
