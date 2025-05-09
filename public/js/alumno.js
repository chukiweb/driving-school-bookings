jQuery(document).ready(function ($) {

    class AlumnoView {
        static reservas = [];
        static alumnoData = {};
        static calendarModal = new bootstrap.Modal(document.getElementById('studentCalendarModal'));
        static calendarInfoModal = new bootstrap.Modal(document.getElementById('studentCalendarInfoModal'));
        static alumnoId = studentData.id;
        static teacherId = studentData.teacher.id;
        static apiUrl = DSB_CONFIG.apiBaseUrl;
        static jwtToken = DSB_CONFIG.jwtToken;
        static events = [];
        static calendar = null;
        static selectedDate = null;

        static init() {
            // Initialize data
            AlumnoView.alumnoData = studentData;
            AlumnoView.reservas = AlumnoView.alumnoData.bookings;

            AlumnoView.events = bookingsData.filter(event => {
                return event.status !== 'cancelled';
            }).map(event => {
                // Asignamos colores según el estado
                if (event.status === 'pending') {
                    event.backgroundColor = '#ffc107'; // Amarillo para pendientes
                    event.borderColor = '#ffc107';
                    event.classNames = ['pending-event'];
                } else {
                    event.backgroundColor = '#28a745'; // Verde para aceptadas
                    event.borderColor = '#28a745';
                    event.classNames = ['accepted-event'];
                }
                return event;
            });

            // Añadir las reservas del profesor como no disponibles
            AlumnoView.teacherEvents = teacherBookingsData.filter(event => {
                // Filtramos las reservas que son del alumno actual para no duplicar
                const isCurrentStudentBooking = AlumnoView.events.some(
                    e => e.start === event.start && e.end === event.end
                );
                return !isCurrentStudentBooking;
            }).map(event => {
                // Formato especial para reservas de otros alumnos
                return {
                    id: `teacher-${event.id}`,
                    title: 'No disponible',
                    start: event.start,
                    end: event.end,
                    backgroundColor: '#dc3545', // Rojo para no disponible
                    borderColor: '#dc3545',
                    classNames: ['unavailable-event'],
                    display: 'background', // Esto muestra el evento como bloque de fondo
                    interactive: false // No permitir interacción
                };
            });

            // Initialize the calendar
            AlumnoView.initializeCalendar();

            // Setup event handlers
            AlumnoView.setupEventListeners();
        }

        static setupEventListeners() {

            document.querySelector('.logout-btn')?.addEventListener('click', function (e) {
                e.preventDefault();
                AlumnoView.handleLogout();
            });

            // Asignar evento click a los botones generados dinámicamente
            document.addEventListener('click', function (event) {
                if (event.target.classList.contains('ver-detalles')) {
                    const index = event.target.getAttribute('data-id');
                    AlumnoView.mostrarDetalles(index);
                }
            });

            // Evento cuando se selecciona un archivo
            document.getElementById('file-input').addEventListener('change', function (event) {
                AlumnoView.handleAvatarUpload(event);
            });

            document.querySelectorAll('.dsb-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    AlumnoView.handleFormSubmit(e.target);
                });
            });

            const avatarWrapper = document.querySelector('.avatar-wrapper');
            const fileInput = document.getElementById('file-input');

            if (avatarWrapper && fileInput) {
                avatarWrapper.addEventListener('click', function () {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function (event) {
                    AlumnoView.handleAvatarUpload(event);
                });
            }

            document.getElementById('studentCalendarModal').addEventListener('shown.bs.modal', function () {
                if (!AlumnoView.selectedDate) return;

                const calendarForm = document.getElementById('studentCalendarForm');
                if (!calendarForm) return;

                // Actualizar los campos del formulario
                calendarForm.querySelector('input[name="date"]').value = AlumnoView.selectedDate.date;
                calendarForm.querySelector('input[name="time"]').value = AlumnoView.selectedDate.time;
                calendarForm.querySelector('input[name="end_time"]').value = AlumnoView.selectedDate.end_time;

                // Actualizar información sobre el límite
                const dailyLimit = parseInt(document.getElementById('max-bookings')?.textContent || '2');

                // Eliminar solo alertas de advertencia anteriores, no la información de costos
                const existingLimitAlert = calendarForm.querySelector('.alert.alert-warning');
                if (existingLimitAlert) {
                    existingLimitAlert.remove();
                }

                // Verificar si se alcanzó el límite
                if (AlumnoView.selectedDate.classCount >= dailyLimit) {
                    // Mostrar mensaje de límite alcanzado
                    const warningEl = document.createElement('div');
                    warningEl.className = 'alert alert-warning';
                    warningEl.innerHTML = `
                        <i class="bi bi-exclamation-triangle"></i>
                        Ya has reservado ${AlumnoView.selectedDate.classCount} clases para el 
                        ${new Date(AlumnoView.selectedDate.date).toLocaleDateString('es-ES')}.
                        No puedes reservar más para esta fecha.
                    `;

                    // Insertar la alerta de advertencia justo después de la alerta de información
                    const infoAlert = calendarForm.querySelector('.alert.alert-info');
                    if (infoAlert) {
                        infoAlert.after(warningEl);
                    } else {
                        calendarForm.insertBefore(warningEl, calendarForm.firstChild);
                    }

                    // Deshabilitar el botón
                    const submitBtn = calendarForm.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                } else {
                    // Habilitar el botón
                    const submitBtn = calendarForm.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            });
        }

        static async handleLogout() {
            try {
                // Mostrar confirmación antes de cerrar sesión
                if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                    return;
                }

                const response = await fetch(`${AlumnoView.apiUrl}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${AlumnoView.jwtToken}`
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Redirigir a la página de acceso
                    window.location.href = data.redirect || '/acceso';
                } else {
                    throw new Error(data.message || 'Error al cerrar sesión');
                }
            } catch (error) {
                console.error('Error al cerrar sesión:', error);
                AlumnoView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static async handleFormSubmit(form) {
            // Mapeo de formularios a sus respectivos endpoints y configuraciones
            const formEndpoints = {
                'studentCalendarForm': {
                    endpoint: `${AlumnoView.apiUrl}/bookings`,
                    method: 'POST',
                    dataMapper: (form) => ({
                        alumno: AlumnoView.alumnoId,
                        profesor: AlumnoView.teacherId,
                        vehiculo: AlumnoView.alumnoData.teacher.vehicle_id,
                        fecha: form.querySelector('input[name="date"]').value,
                        hora: form.querySelector('input[name="time"]').value,
                        end_time: form.querySelector('input[name="end_time"]').value
                    }),
                    onSuccess: (res) => {
                        // Ocultar modal
                        AlumnoView.calendarModal.hide();

                        // Actualizar el saldo visible
                        AlumnoView.actualizarSaldo(res.newBalance);

                        // Mostrar notificación sin bloquear con alert
                        AlumnoView.mostrarNotificacion('success', `Reserva creada correctamente el ${res.date} a las ${res.startTime}`);

                        // Estado inicial de la reserva (normalmente 'pending')
                        // Verifica si el backend devuelve el estado, sino usa 'pending' como predeterminado
                        const status = res.status || 'pending';

                        // Determinar color basado en el estado
                        const backgroundColor = status === 'pending' ? '#ffc107' : '#28a745';
                        const className = status === 'pending' ? 'pending-event' : 'accepted-event';

                        // Añadir el nuevo evento al calendario con color según su estado
                        AlumnoView.calendar.addEvent({
                            id: res.id,
                            title: `Clase con ${AlumnoView.alumnoData.teacher.name}`,
                            start: `${res.date}T${res.startTime}`,
                            end: `${res.date}T${res.endTime}`,
                            backgroundColor: backgroundColor,
                            borderColor: backgroundColor,
                            classNames: [className], // Aplicamos la clase CSS según estado
                            status: status // Guardamos el estado en el evento para referencia futura
                        });

                        // Actualizar la lista de reservas en memoria
                        AlumnoView.reservas.push({
                            id: res.id,
                            date: res.date,
                            start: res.startTime,
                            end: res.endTime,
                            teacher_name: AlumnoView.alumnoData.teacher.name,
                            vehicle: AlumnoView.alumnoData.teacher.vehicle_name,
                            status: status // Usamos el estado real en lugar de 'active'
                        });
                    }
                },
                'studentCalendarInfoForm': {
                    endpoint: (form) => {
                        const bookingId = form.querySelector('input[name="booking_id"]').value;
                        return `${AlumnoView.apiUrl}/bookings/cancel/${bookingId}`;
                    },
                    method: 'POST',
                    dataMapper: (form) => ({}),
                    onSuccess: (res) => {
                        // Obtener el ID de la reserva cancelada
                        const bookingId = form.querySelector('input[name="booking_id"]').value;

                        // Ocultar modal
                        AlumnoView.calendarInfoModal.hide();

                        // Actualizar el saldo si hubo reembolso
                        if (res.refund && res.refund_amount > 0) {
                            // Si el backend ya nos devuelve el nuevo saldo, lo usamos directamente
                            if (res.newBalance !== undefined) {
                                AlumnoView.actualizarSaldo(res.newBalance);
                            } else {
                                // Si no, calculamos el nuevo saldo sumando el reembolso al saldo actual
                                const currentBalance = parseFloat(AlumnoView.alumnoData.class_points);
                                const newBalance = currentBalance + parseFloat(res.refund_amount);
                                AlumnoView.actualizarSaldo(newBalance);
                            }

                            // Mensaje específico para reembolso
                            AlumnoView.mostrarNotificacion('success',
                                `Reserva cancelada correctamente. Se han devuelto ${res.refund_amount} créditos a tu cuenta.`);
                        } else {
                            // Mensaje sin reembolso
                            AlumnoView.mostrarNotificacion('success', 'Reserva cancelada correctamente');
                        }

                        // Eliminar el evento del calendario
                        const evento = AlumnoView.calendar.getEventById(bookingId);
                        if (evento) {
                            evento.remove();
                        }

                        // Actualizar el estado en la lista de reservas
                        const reservaIndex = AlumnoView.reservas.findIndex(r => r.id == bookingId);
                        if (reservaIndex >= 0) {
                            AlumnoView.reservas[reservaIndex].status = 'cancelled';
                        }
                    }
                }
            };

            const formId = form.id;
            const formConfig = formEndpoints[formId];

            if (!formConfig) {
                console.error(`No hay configuración para el formulario ${formId}`);
                return;
            }

            // Obtener la URL del endpoint
            const endpoint = typeof formConfig.endpoint === 'function'
                ? formConfig.endpoint(form)
                : formConfig.endpoint;

            // Obtener los datos
            const payload = formConfig.dataMapper(form);

            // Preparar el botón
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn?.innerHTML;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            }

            try {
                // Realizar la petición fetch
                const response = await fetch(endpoint, {
                    method: formConfig.method,
                    headers: {
                        'Authorization': 'Bearer ' + AlumnoView.jwtToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                // Verificar si la respuesta es correcta
                if (!response.ok) {
                    const errorData = await response.json();

                    // Mostrar mensaje específico para horario no disponible
                    if (errorData.code === 'slot_not_available') {
                        AlumnoView.mostrarNotificacion('error', errorData.message || 'Este horario ya no está disponible');

                        // Actualizar el calendario para reflejar el cambio
                        AlumnoView.calendar.refetchEvents();
                    } else {
                        throw new Error(errorData.message || 'Error en la petición');
                    }
                    return; // Salir de la función si hay error
                }

                // Procesar la respuesta
                const data = await response.json();
                formConfig.onSuccess(data);
            } catch (error) {
                console.error(`${formId} error:`, error);
                AlumnoView.mostrarNotificacion('error', `Error: ${error.message || 'Error de conexión'}`);
            } finally {
                // Restaurar el botón
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        }

        static actualizarSaldo(nuevoSaldo) {
            // Actualizar el valor en la clase
            AlumnoView.alumnoData.class_points = nuevoSaldo;

            // Actualizar todos los elementos que muestran el saldo
            document.querySelectorAll('.saldo-actual').forEach(elemento => {
                elemento.textContent = nuevoSaldo;
            });
        }

        // Mejoras para la función initializeCalendar
        static initializeCalendar() {
            const calendarElement = document.getElementById('calendar');
            if (!calendarElement) return;

            // Configuraciones comunes
            const duracion = teacherConfig.duracion_clase || '00:45:00';
            const classDuration = parseInt(duracion.split(':')[0]) * 60 + parseInt(duracion.split(':')[1]);
            const diasNoDisponibles = teacherConfig.dias_no_disponibles ? teacherConfig.dias_no_disponibles.split(',').map(Number) : [];

            // Opciones del Calendario
            const studentCalendar = new FullCalendar.Calendar(calendarElement, {
                allDaySlot: false,
                locale: 'es',
                nowIndicator: true,
                selectable: true,
                initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
                expandRows: true,
                height: 'auto',
                customButtons: {
                    myCustomButton: {
                        text: 'Hoy',
                        click: function () {
                            studentCalendar.today();
                        }
                    }
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día'
                },
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                slotDuration: duracion,
                slotMinTime: teacherConfig.hora_inicio || '08:00:00',
                slotMaxTime: teacherConfig.hora_fin || '21:00:00',
                hiddenDays: diasNoDisponibles,

                // Formateo de eventos para mejor visualización
                eventDidMount: function (info) {
                    // Añadir información extra a eventos
                    if (info.event.extendedProps.status === 'pending') {
                        info.el.querySelector('.fc-event-title').innerHTML =
                            '<i class="bi bi-hourglass me-1"></i>' + info.event.title;
                    } else if (info.event.extendedProps.status === 'accepted') {
                        info.el.querySelector('.fc-event-title').innerHTML =
                            '<i class="bi bi-check-circle me-1"></i>' + info.event.title;
                    }

                    // Tooltips con más información
                    const tooltip = new bootstrap.Tooltip(info.el, {
                        title: `${info.event.title}<br>
                       ${info.event.start ? info.event.start.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) : ''} - 
                       ${info.event.end ? info.event.end.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) : ''}
                       ${info.event.extendedProps.status ? '<br>Estado: ' + info.event.extendedProps.status : ''}`,
                        placement: 'top',
                        html: true,
                        container: 'body'
                    });
                },

                // Resto de la configuración existente
                select: function (e) {
                    // Tu código existente
                },
                eventClick: function (e) {
                    // Tu código existente
                },
                events: [...AlumnoView.events, ...AlumnoView.teacherEvents],
                selectMirror: true,
                selectAllow: function (selectInfo) {
                    // Tu código existente
                },
                snapDuration: duracion,
                selectMinDistance: 0,

                // Manejar cambio de vista
                viewDidMount: function (viewInfo) {
                    // Actualizar botones de vista activa
                    document.querySelectorAll('.calendar-view-switcher .btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    const activeView = viewInfo.view.type;
                    const btn = document.querySelector(`.calendar-view-switcher .btn[data-view="${activeView}"]`);
                    if (btn) btn.classList.add('active');

                    // Aplicar funcionalidad a slots no disponibles
                    setTimeout(() => AlumnoView.deshabilitarSlotsNoDisponibles(), 100);
                },

                // Aplicar la funcionalidad en cambio de fechas
                datesSet: function () {
                    setTimeout(() => AlumnoView.deshabilitarSlotsNoDisponibles(), 100);
                }
            });

            // Guardar referencia
            AlumnoView.calendar = studentCalendar;

            // Renderizar calendario
            studentCalendar.render();

            // Event listeners para botones personalizados
            document.querySelectorAll('.calendar-view-switcher .btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const view = this.getAttribute('data-view');
                    studentCalendar.changeView(view);
                });
            });

            document.getElementById('goToToday')?.addEventListener('click', function () {
                studentCalendar.today();
            });

            // Filtros de calendario
            document.getElementById('showMyClassesOnly')?.addEventListener('change', function () {
                AlumnoView.filterCalendarEvents();
            });

            document.getElementById('showAvailableOnly')?.addEventListener('change', function () {
                AlumnoView.filterCalendarEvents();
            });

            // Responsive: Ajustar vista según tamaño de pantalla
            window.addEventListener('resize', function () {
                const width = window.innerWidth;
                if (width < 768 && studentCalendar.view.type === 'timeGridWeek') {
                    studentCalendar.changeView('timeGridDay');
                } else if (width >= 768 && studentCalendar.view.type === 'timeGridDay' &&
                    !studentCalendar._calendarOptions.initialView.includes('Day')) {
                    studentCalendar.changeView('timeGridWeek');
                }
            });

            // Aplicar funcionalidad a slots no disponibles
            setTimeout(() => AlumnoView.deshabilitarSlotsNoDisponibles(), 200);
        }

        // Nueva función para filtrar eventos del calendario
        static filterCalendarEvents() {
            const showMine = document.getElementById('showMyClassesOnly')?.checked;
            const showAvailable = document.getElementById('showAvailableOnly')?.checked;

            if (!AlumnoView.calendar) return;

            AlumnoView.calendar.getEvents().forEach(event => {
                // Evento del alumno o todos si no está marcada la opción
                const isMine = event.extendedProps.student_id === AlumnoView.alumnoId;
                const shouldShowMine = !showMine || (showMine && isMine);

                // Evento disponible o todos si no está marcada la opción
                const isAvailable = !event.extendedProps.status || event.extendedProps.status === 'available';
                const shouldShowAvailable = !showAvailable || (showAvailable && isAvailable);

                // Aplicar visibilidad
                if (shouldShowMine && shouldShowAvailable) {
                    event.setProp('display', 'auto');
                } else {
                    event.setProp('display', 'none');
                }
            });
        }

        // Método para mostrar notificaciones no bloqueantes
        static mostrarNotificacion(tipo, mensaje, duracion = 3000) {
            // Crear elemento de notificación
            const notificacion = document.createElement('div');
            notificacion.className = `dsb-notificacion ${tipo}`;
            notificacion.innerHTML = mensaje;

            // Añadir al DOM
            document.body.appendChild(notificacion);

            // Mostrar con animación
            setTimeout(() => {
                notificacion.classList.add('visible');
            }, 10);

            // Ocultar después de la duración especificada
            setTimeout(() => {
                notificacion.classList.remove('visible');
                setTimeout(() => {
                    document.body.removeChild(notificacion);
                }, 300); // Tiempo para la animación de salida
            }, duracion);
        }

        static handleAvatarUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validar tipo y tamaño en el cliente
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                AlumnoView.mostrarNotificacion('error', 'Tipo de archivo no permitido. Use JPG, PNG o GIF');
                return;
            }

            if (file.size > maxSize) {
                AlumnoView.mostrarNotificacion('error', 'El archivo es demasiado grande (máx. 2MB)');
                return;
            }

            const formData = new FormData();
            formData.append("file", file);

            // Mostrar indicador de carga
            const avatarElement = document.getElementById('estudiante-avatar');
            const originalSrc = avatarElement.src;
            avatarElement.classList.add('uploading');

            fetch(`${AlumnoView.apiUrl}/users/me/avatar`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + AlumnoView.jwtToken
                },
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.message || 'Error al subir la imagen');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Actualizar la imagen con la nueva URL
                        avatarElement.src = data.url;
                        AlumnoView.mostrarNotificacion('success', 'Imagen de perfil actualizada');
                    } else {
                        throw new Error(data.message || 'Error al subir la imagen');
                    }
                })
                .catch(error => {
                    avatarElement.src = originalSrc; // Restaurar la imagen original
                    AlumnoView.mostrarNotificacion('error', `Error: ${error.message}`);
                })
                .finally(() => {
                    avatarElement.classList.remove('uploading');
                });
        }

        static mostrarDetalles(id) {
            const reserva = AlumnoView.reservas.find(reserva => reserva.id == id);
            if (!reserva) {
                console.error("No se encontró la reserva con ID:", id);
                return;
            }

            $("#modal-fecha").text(new Date(reserva.date).toLocaleDateString("es-ES"));
            $("#modal-hora").text(reserva.start);
            $("#modal-profesor").text(reserva.teacher_name);
            $("#modal-vehiculo").text(reserva.vehicle);
            $("#modal-estado").text(reserva.status);
        }

        static initializeCalendar() {
            // Initialize the calendar with the bookings data
            const calendarElement = document.getElementById('calendar');
            const teacherConfig = AlumnoView.alumnoData.teacher.config;

            // Usar configuración del profesor o valor global como fallback
            const globalDuration = DSB_CONFIG.classDuration || 45;
            const classDuration = teacherConfig.duracion ? parseInt(teacherConfig.duracion) : globalDuration;
            const duracion = '00:' + classDuration + ':00';

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

            const studentCalendar = new FullCalendar.Calendar(calendarElement, {
                allDaySlot: false,
                locale: 'es',
                nowIndicator: true,
                selectable: true,
                initialView: 'timeGridWeek',
                expandRows: true,
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
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                slotDuration: duracion,  // Usar la duración de clase que tenga el sistema
                slotMinTime: teacherConfig.hora_inicio || '08:00:00',  // Configuración de la hora de inicio para los estudiantes
                slotMaxTime: teacherConfig.hora_fin || '21:00:00',  // Configuración de la hora de fin para los estudiantes
                hiddenDays: diasNoDisponibles,  // Asegúrate de definir los días no disponibles si es necesario
                select: function (e) {
                    // Verificar las restricciones temporales
                    const now = new Date();
                    const oneHourFromNow = new Date(now.getTime() + (60 * 60 * 1000));

                    if (e.start < oneHourFromNow) {
                        AlumnoView.mostrarNotificacion('warning', 'Debes reservar las clases con al menos 1 hora de antelación');
                        return; // No abrir el modal
                    }

                    // Al seleccionar un horario, se asignan los valores al formulario
                    const selectedDate = e.start.toISOString().split('T')[0];
                    const start = e.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const end = e.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    // Contar clases en la fecha seleccionada
                    const classesInSelectedDate = AlumnoView.reservas.filter(
                        booking => booking.date === selectedDate && booking.status !== 'cancelled'
                    ).length;

                    // Guardar datos para usar cuando se abra el modal
                    AlumnoView.selectedDate = {
                        date: selectedDate,
                        time: start,
                        end_time: end,
                        classCount: classesInSelectedDate
                    };

                    AlumnoView.calendarModal.show();
                },
                eventClick: function (e) {
                    const calendarInfoForm = document.getElementById('studentCalendarInfoForm');
                    const eventId = e.event.id;
                    const eventTitle = e.event.title;
                    const eventStart = e.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const eventEnd = e.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    // Rellenar los campos del formulario con la información del evento
                    calendarInfoForm.querySelector('input[name="booking_id"]').value = eventId;
                    calendarInfoForm.querySelector('input[name="date"]').value = e.event.start.toISOString().split('T')[0];
                    calendarInfoForm.querySelector('input[name="time"]').value = eventStart;
                    calendarInfoForm.querySelector('input[name="end_time"]').value = eventEnd;

                    // Actualizar el costo de la reserva
                    const bookingPointsElement = calendarInfoForm.querySelector('.booking-points');
                    if (bookingPointsElement) {
                        const classPrice = document.getElementById('precio-clase').textContent;
                        bookingPointsElement.textContent = classPrice;
                    }

                    AlumnoView.calendarInfoModal.show();
                },
                events: [...AlumnoView.events, ...AlumnoView.teacherEvents],
                selectMirror: false,
                selectAllow: function (selectInfo) {
                    // 1. Obtener fecha y hora actual más una hora de antelación
                    const now = new Date();
                    const oneHourFromNow = new Date(now.getTime() + (60 * 60 * 1000));

                    // 2. No permitir seleccionar fechas/horas pasadas o con menos de 1 hora de antelación
                    if (selectInfo.start < oneHourFromNow) {
                        return false;
                    }

                    // 3. Verificaciones existentes (solapamiento, duración)
                    // Verificar que no haya overlap con otros eventos
                    const selectedStart = selectInfo.start.toISOString();
                    const selectedEnd = selectInfo.end.toISOString();

                    // Comprobar solapamiento con eventos del profesor
                    const isSlotTaken = AlumnoView.teacherEvents.some(event => {
                        const eventStart = new Date(event.start).toISOString();
                        const eventEnd = new Date(event.end).toISOString();
                        return (selectedStart < eventEnd && selectedEnd > eventStart);
                    });

                    if (isSlotTaken) return false;

                    // Verificar que la duración de la selección sea correcta
                    const selectedDuration = (selectInfo.end - selectInfo.start) / 60000; // en minutos
                    const slotDuration = classDuration;

                    return Math.abs(selectedDuration - slotDuration) < 1;
                },
                snapDuration: duracion,
                selectMinDistance: 0,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                dayCellDidMount: function (info) {
                    var today = new Date();
                    if (info.date < today.setHours(0, 0, 0, 0)) {
                        info.el.classList.add('fc-day-past');
                    }
                }
            });

            AlumnoView.calendar = studentCalendar;
            studentCalendar.render();
        }
    }

    // Initialize the view
    AlumnoView.init();

    console.log('Estructura de FullCalendar:', document.querySelector('.fc'));
});
