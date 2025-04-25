jQuery(document).ready(function ($) {

    class teacherAdminView {

        static createFormContainer = document.querySelector('#createFormContainer');
        static updateFormContainer = document.querySelector('#editFormContainer');
        static configFormContainer = document.querySelector('#configFormContainer');
        static deleteTeacherModal = document.querySelector('#deleteTeacherModal');
        static calendarContainer = document.querySelector('#teacherCalendarContainer');
        static calendarModal = document.querySelector('#teacherCalendarModal');
        static calendarInfoModal = document.querySelector('#teacherCalendarInfoModal');
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
                    teacherAdminView.changeName(btn);
                });
            });

            teacherAdminView.initBookingFormListener();
        }

        static changeName(btn) {
            const teacherId = btn.dataset.userId;

            allTeacherData.forEach(function (prof) {
                if (prof.id == teacherId) {
                    const name = `${prof.firstName} ${prof.lastName}`;
                    document.querySelector('#teacherName').textContent = name;
                }
            });
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

            deleteForm.querySelector('input[name="user_id"]').value = teacherId;

            teacherAdminView.deleteTeacherModal.showModal();
        }

        static initBookingFormListener() {
            document.querySelector('#teacherCalendarForm select[name="student"]').addEventListener('change', function (e) {
                const studentId = this.value;

                if (studentId) {
                    const studentData = allStudentData.find(student => student.id == studentId);
                    const license = studentData.license_type;
                    var vehicle = '';
                    if (license == 'A') {
                        vehicle = studentData.profesordata.vehicle.motorcycle;
                    } else if (license == 'B') {
                        vehicle = studentData.profesordata.vehicle.car;
                    }

                    if (studentData) {
                        document.querySelector('#teacherCalendarForm input[name="teacher"]').value = studentData.profesordata.name;
                        document.querySelector('#teacherCalendarForm input[name="teacher_id"]').value = studentData.profesordata.id;
                        document.querySelector('#teacherCalendarForm input[name="license_type"]').value = studentData.license_type;
                        document.querySelector('#teacherCalendarForm input[name="vehicle"]').value = vehicle.title;
                        document.querySelector('#teacherCalendarForm input[name="vehicle_id"]').value = vehicle.id;
                    }
                }
            });
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
                    ({ events: teacherEvents = [], config: teacherConfig = {} } = prof);
                }
            });

            function obtenerDiasNoDisponibles(diasDisponibles) {
                const todosDias = [0, 1, 2, 3, 4, 5, 6];

                if (!diasDisponibles || !Array.isArray(diasDisponibles) || diasDisponibles.length === 0) {
                    return todosDias;
                }

                const mapaDias = {
                    'Domingo': 0,
                    'Lunes': 1,
                    'Martes': 2,
                    'Miércoles': 3,
                    'Jueves': 4,
                    'Viernes': 5,
                    'Sábado': 6
                };

                const diasNumericos = diasDisponibles.map(dia => {
                    if (typeof dia === 'number') return dia;
                    return mapaDias[dia];
                }).filter(dia => dia !== undefined);

                return todosDias.filter(dia => !diasNumericos.includes(dia));
            }

            const diasNoDisponibles = obtenerDiasNoDisponibles(teacherConfig.dias);

            const duracion = teacherConfig.duracion ? '00:' + teacherConfig.duracion + ':00' : '00:45:00';

            const calendar = new FullCalendar.Calendar(calendarElement, {
                allDaySlot: false,
                locale: 'es',
                nowIndicator: true,
                selectable: true,
                hiddenDays: diasNoDisponibles,
                select: function (e) {
                    const start = e.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const end = e.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    document.querySelector('#teacherCalendarForm input[name="time"]').value = start;
                    document.querySelector('#teacherCalendarForm input[name="end_time"]').value = end;
                    document.querySelector('#teacherCalendarForm input[name="date"]').value = e.start.toISOString().split('T')[0];

                    teacherAdminView.calendarModal.showModal();
                },
                eventClick: function (e) {
                    const eventId = e.event.id;
                    const eventTitle = e.event.title;
                    const eventStart = e.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const eventEnd = e.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    document.querySelector('#teacherCalendarInfoForm input[name="booking_id"]').value = eventId;
                    document.querySelector('#teacherCalendarInfoForm h3').innerHTML = eventTitle;

                    teacherAdminView.calendarInfoModal.showModal();
                },
                expandRows: true,
                height: '100%',
                initialView: 'timeGridWeek',
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día'
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotDuration: duracion || '00:45:00',
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                slotMinTime: teacherConfig.hora_inicio || '08:00:00',
                slotMaxTime: teacherConfig.hora_fin || '21:00:00',
                events: teacherEvents,
                eventContent: function (e) {
                    const title = e.event.title;
                    const start = e.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const end = e.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

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


