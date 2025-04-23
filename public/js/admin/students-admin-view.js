jQuery(document).ready(function ($) {

    class studentAdminView {

        static createFormContainer = document.querySelector('#createFormContainer');
        static editFormContainer = document.querySelector('#editFormContainer');
        // static configFormContainer = document.querySelector('#configFormContainer');
        static deleteStudentModal = document.querySelector('#deleteStudentModal');
        // static calendarContainer = document.querySelector('#studentCalendarContainer');
        // static calendarModal = document.querySelector('#studentCalendarModal');
        // static calendarInfoModal = document.querySelector('#studentCalendarInfoModal');
        static lastAction = null;

        static init() {
            document.querySelectorAll('.button').forEach(button => {
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

            // studentAdminView.initBookingFormListener();
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
                // studentAdminView.configFormContainer,
                // studentAdminView.calendarContainer,
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
                case 'open-config':
                    studentAdminView.configFormAction(btn.dataset.userId);
                    break;
                case 'open-calendar':
                    studentAdminView.calendarFormAction(btn.dataset.userId);
                    break;
                case 'delete-student':
                    studentAdminView.deleteFormAction(btn.dataset.userId);
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

        static configFormAction(studentId) {
            const configForm = document.querySelector('#configFormContainer form');
            studentAdminView.configFormContainer.querySelector('form').reset();

            allStudentData.forEach(function (prof) {
                if (prof.id == studentId) {
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

        static deleteFormAction(studentId) {
            const deleteForm = document.querySelector('#deleteStudentForm');

            deleteForm.querySelector('input[name="user_id"]').value = studentId;

            studentAdminView.deleteStudentModal.showModal();
        }

        // static initBookingFormListener() {
        //     document.querySelector('#studentCalendarForm select[name="student"]').addEventListener('change', function (e) {
        //         const studentId = this.value;

        //         if (studentId) {
        //             const studentData = allStudentData.find(student => student.id == studentId);
        //             const license = studentData.license_type;
        //             var vehicle = '';
        //             if (license == 'A') {
        //                 vehicle = studentData.profesordata.vehicle.motorcycle;
        //             } else if (license == 'B') {
        //                 vehicle = studentData.profesordata.vehicle.car;
        //             }

        //             if (studentData) {
        //                 document.querySelector('#studentCalendarForm input[name="student"]').value = studentData.profesordata.name;
        //                 document.querySelector('#studentCalendarForm input[name="student_id"]').value = studentData.profesordata.id;
        //                 document.querySelector('#studentCalendarForm input[name="license_type"]').value = studentData.license_type;
        //                 document.querySelector('#studentCalendarForm input[name="vehicle"]').value = vehicle.title;
        //                 document.querySelector('#studentCalendarForm input[name="vehicle_id"]').value = vehicle.id;
        //             }
        //         }
        //     });
        // }

        // static calendarFormAction(studentId) {
        //     // Clear any existing calendar
        //     const calendarElement = document.getElementById('studentCalendar');
        //     studentAdminView.calendarContainer.style.display = 'block';
        //     if (calendarElement) {
        //         calendarElement.innerHTML = '';
        //     }

        //     // Get student data
        //     let studentEvents = [];
        //     let studentConfig = [];
        //     allStudentData.forEach(function (prof) {
        //         if (prof.id == studentId) {
        //             ({ events: studentEvents = [], config: studentConfig = {} } = prof);
        //         }
        //     });

        //     function obtenerDiasNoDisponibles(diasDisponibles) {
        //         const todosDias = [0, 1, 2, 3, 4, 5, 6];

        //         if (!diasDisponibles || !Array.isArray(diasDisponibles) || diasDisponibles.length === 0) {
        //             return todosDias;
        //         }

        //         const mapaDias = {
        //             'Domingo': 0,
        //             'Lunes': 1,
        //             'Martes': 2,
        //             'Miércoles': 3,
        //             'Jueves': 4,
        //             'Viernes': 5,
        //             'Sábado': 6
        //         };

        //         const diasNumericos = diasDisponibles.map(dia => {
        //             if (typeof dia === 'number') return dia;
        //             return mapaDias[dia];
        //         }).filter(dia => dia !== undefined);

        //         return todosDias.filter(dia => !diasNumericos.includes(dia));
        //     }

        //     const diasNoDisponibles = obtenerDiasNoDisponibles(studentConfig.dias);

        //     const duracion = studentConfig.duracion ? '00:' + studentConfig.duracion + ':00' : '00:45:00';

        //     const calendar = new FullCalendar.Calendar(calendarElement, {
        //         allDaySlot: false,
        //         locale: 'es',
        //         nowIndicator: true,
        //         selectable: true,
        //         hiddenDays: diasNoDisponibles,
        //         select: function (e) {
        //             const start = e.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        //             const end = e.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        //             document.querySelector('#studentCalendarForm input[name="time"]').value = start;
        //             document.querySelector('#studentCalendarForm input[name="end_time"]').value = end;
        //             document.querySelector('#studentCalendarForm input[name="date"]').value = e.start.toISOString().split('T')[0];

        //             studentAdminView.calendarModal.showModal();
        //         },
        //         eventClick: function (e) {
        //             const eventId = e.event.id;
        //             const eventTitle = e.event.title;
        //             const eventStart = e.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        //             const eventEnd = e.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        //             document.querySelector('#studentCalendarInfoForm input[name="booking_id"]').value = eventId;
        //             document.querySelector('#studentCalendarInfoForm h3').innerHTML = eventTitle;

        //             studentAdminView.calendarInfoModal.showModal();
        //         },
        //         expandRows: true,
        //         height: '100%',
        //         initialView: 'timeGridWeek',
        //         buttonText: {
        //             today: 'Hoy',
        //             month: 'Mes',
        //             week: 'Semana',
        //             day: 'Día'
        //         },
        //         headerToolbar: {
        //             left: 'prev,next today',
        //             center: 'title',
        //             right: 'dayGridMonth,timeGridWeek,timeGridDay'
        //         },
        //         slotDuration: duracion || '00:45:00',
        //         slotLabelFormat: {
        //             hour: '2-digit',
        //             minute: '2-digit',
        //             hour12: false
        //         },
        //         slotMinTime: studentConfig.hora_inicio || '08:00:00',
        //         slotMaxTime: studentConfig.hora_fin || '21:00:00',
        //         events: studentEvents,
        //         eventContent: function (e) {
        //             const title = e.event.title;
        //             const start = e.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        //             const end = e.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        //             return {
        //                 html: `
        //                     <div class="fc-event-custom">
        //                         <span class="fc-event-time">${start} - ${end}</span><br>
        //                         <strong>${title}</strong>
        //                     </div>
        //                 `
        //             };
        //         },
        //     });

        //     calendar.render();
        // }
    }

    studentAdminView.init();

    console.log("STUDENT DATA", allStudentData);
});