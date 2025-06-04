document.addEventListener('DOMContentLoaded', function () {

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
                window.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static async handleFormSubmit(form) {
            // Mapeo de formularios a sus respectivos endpoints y configuraciones
            const formEndpoints = {
                'studentCalendarForm': {
                    endpoint: `${AlumnoView.apiUrl}/bookings/create`,
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
                        AlumnoView.calendarModal.hide();

                        AlumnoView.actualizarSaldo(res.newBalance);

                        window.mostrarNotificacion('Reserva creada', `El día ${res.date} a las ${res.startTime}`, 'success');

                        const status = res.status || 'pending';

                        const backgroundColor = status === 'pending' ? '#ffc107' :
                            'accepted' ? '#28a745' : '#dc3545';
                        const className = status === 'pending' ? 'pending-event' :
                            'accepted' ? 'accepted-event' : 'unavailable-event';

                        // Añadir el nuevo evento al calendario
                        AlumnoView.calendar.addEvent({
                            id: res.id,
                            title: `Clase con ${AlumnoView.alumnoData.teacher.name}`,
                            start: `${res.date}T${res.startTime}`,
                            end: `${res.date}T${res.endTime}`,
                            backgroundColor: backgroundColor,
                            borderColor: backgroundColor,
                            classNames: [className],
                            status: status
                        });

                        // Actualizar la lista de reservas en memoria
                        AlumnoView.reservas.push({
                            id: res.id,
                            date: res.date,
                            start: res.startTime,
                            end: res.endTime,
                            teacher_name: AlumnoView.alumnoData.teacher.name,
                            vehicle: AlumnoView.alumnoData.teacher.vehicle_name,
                            status: status
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

                            window.mostrarNotificacion('Reserva cancelada', `Se han devuelto ${res.refund_amount} créditos a tu cuenta`, 'success');
                        } else {
                            window.mostrarNotificacion('Reserva cancelada', 'No se ha devuelto ningún crédito a tu cuenta', 'success');
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
                        window.mostrarNotificacion('Error', 'El horario seleccionado ya no está disponible', 'error');

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
                window.mostrarNotificacion('Error', `${error.message || 'Error de conexión'}`, 'error');
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

        static async handleAvatarUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validar tipo y tamaño en el cliente
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                window.mostrarNotificacion('Error', 'Tipo de archivo no permitido. Use JPG, PNG o GIF', 'error');
                return;
            }

            if (file.size > maxSize) {
                window.mostrarNotificacion('Error', 'El archivo es demasiado grande (máx. 2MB)', 'error');
                return;
            }

            const formData = new FormData();
            formData.append("file", file);

            // Mostrar indicador de carga
            const avatarElement = document.getElementById('estudiante-avatar');
            const originalSrc = avatarElement.src;
            avatarElement.classList.add('uploading');

            try {
                const response = await fetch(`${AlumnoView.apiUrl}/users/me/avatar`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + AlumnoView.jwtToken
                    },
                    body: formData
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al subir la imagen');
                }

                const data = await response.json();

                if (data.success) {
                    // Actualizar la imagen con la nueva URL
                    avatarElement.src = data.url;
                    window.mostrarNotificacion('Imagen de perfil actualizada', '', 'success');
                } else {
                    throw new Error(data.message || 'Error al subir la imagen');
                }
            } catch (error) {
                avatarElement.src = originalSrc; // Restaurar la imagen original
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            } finally {
                avatarElement.classList.remove('uploading');
            }
        }

        static mostrarDetalles(id) {
            const reserva = AlumnoView.reservas.find(reserva => reserva.id == id);
            if (!reserva) {
                console.error("No se encontró la reserva con ID:", id);
                return;
            }

            document.getElementById('modal-fecha').textContent = new Date(reserva.date).toLocaleDateString("es-ES");
            document.getElementById('modal-hora').textContent = reserva.start;
            document.getElementById('modal-profesor').textContent = reserva.teacher_name;
            document.getElementById('modal-vehiculo').textContent = reserva.vehicle;
            document.getElementById('modal-estado').textContent = reserva.status;
        }

        static initializeCalendar() {
            const calendarElement = document.getElementById('calendar');
            const teacherConfig = AlumnoView.alumnoData.teacher.config;

            // Usar configuración del profesor o valor global como fallback
            const globalDuration = DSB_CONFIG.classDuration || 45;
            const classDuration = teacherConfig.duracion ? parseInt(teacherConfig.duracion) : globalDuration;
            const duracion = '00:' + classDuration + ':00';

            function obtenerDiasNoDisponibles(diasDisponibles) {
                const todosDias = [0, 1, 2, 3, 4, 5, 6];

                if (!diasDisponibles || !Array.isArray(diasDisponibles) || diasDisponibles.length === 0) {
                    return [];
                }

                const mapaDias = {
                    'Domingo': 0, 'Lunes': 1, 'Martes': 2, 'Miércoles': 3,
                    'Jueves': 4, 'Viernes': 5, 'Sábado': 6
                };

                const diasNumericos = diasDisponibles.map(dia => {
                    if (typeof dia === 'number') return dia;
                    return mapaDias[dia];
                }).filter(dia => dia !== undefined);

                return todosDias.filter(dia => !diasNumericos.includes(dia));
            }

            // **NUEVO: Generar businessHours considerando descansos del profesor**
            function generateBusinessHours(config) {
                const dias = config.dias || [];
                const horaInicio = config.hora_inicio || '08:00';
                const horaFin = config.hora_fin || '20:00';
                const descansos = config.descansos || [];

                // Convertir nombres de días a números
                const daysOfWeek = dias.map(dia => {
                    const diasMap = {
                        'Domingo': 0, 'Lunes': 1, 'Martes': 2, 'Miércoles': 3,
                        'Jueves': 4, 'Viernes': 5, 'Sábado': 6
                    };
                    return diasMap[dia];
                }).filter(day => day !== undefined);

                if (daysOfWeek.length === 0) {
                    return false;
                }

                // Si no hay descansos, retornar horario simple
                if (descansos.length === 0) {
                    return [{
                        daysOfWeek: daysOfWeek,
                        startTime: horaInicio,
                        endTime: horaFin
                    }];
                }

                // Ordenar descansos por hora de inicio
                const descansosOrdenados = descansos.sort((a, b) => a.inicio.localeCompare(b.inicio));

                const businessHours = [];
                let horaActual = horaInicio;

                // Crear segmentos entre descansos
                descansosOrdenados.forEach(descanso => {
                    if (horaActual < descanso.inicio) {
                        businessHours.push({
                            daysOfWeek: daysOfWeek,
                            startTime: horaActual,
                            endTime: descanso.inicio
                        });
                    }
                    horaActual = descanso.fin;
                });

                // Añadir segmento final si queda tiempo después del último descanso
                if (horaActual < horaFin) {
                    businessHours.push({
                        daysOfWeek: daysOfWeek,
                        startTime: horaActual,
                        endTime: horaFin
                    });
                }

                console.log('BusinessHours del profesor (vista alumno):', businessHours);
                return businessHours;
            }

            // **NUEVO: Validar si una selección está en horario laboral (excluyendo descansos)**
            function isWithinBusinessHours(selectInfo, config) {
                const descansos = config.descansos || [];
                const startTime = selectInfo.start.toTimeString().substring(0, 5);
                const endTime = selectInfo.end.toTimeString().substring(0, 5);

                // Verificar si la selección coincide con algún descanso
                for (const descanso of descansos) {
                    if ((startTime >= descanso.inicio && startTime < descanso.fin) ||
                        (endTime > descanso.inicio && endTime <= descanso.fin) ||
                        (startTime <= descanso.inicio && endTime >= descanso.fin)) {
                        return false;
                    }
                }

                return true;
            }

            const diasNoDisponibles = obtenerDiasNoDisponibles(teacherConfig.dias);

            // **GENERAR BUSINESS HOURS CON DESCANSOS**
            const businessHours = generateBusinessHours(teacherConfig);

            const studentCalendar = new FullCalendar.Calendar(calendarElement, {
                allDaySlot: false,
                locale: 'es',
                nowIndicator: true,
                selectable: true,
                initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
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
                slotDuration: duracion,
                slotMinTime: teacherConfig.hora_inicio || '08:00:00',
                slotMaxTime: teacherConfig.hora_fin || '21:00:00',
                hiddenDays: diasNoDisponibles,

                // **CONFIGURAR BUSINESS HOURS CON DESCANSOS**
                businessHours: businessHours,
                selectConstraint: "businessHours", // Solo permitir seleccionar en horas laborales

                select: function (selectInfo) {
                    // Verificar las restricciones temporales
                    const now = new Date();
                    const oneHourFromNow = new Date(now.getTime() + (60 * 60 * 1000));

                    if (selectInfo.start < oneHourFromNow) {
                        AlumnoView.calendar.unselect();
                        window.mostrarNotificacion('No permitido', 'Debe reservar con al menos 1 hora de antelación', 'warning');
                        return;
                    }

                    // **VALIDAR QUE NO ESTÉ EN PERÍODO DE DESCANSO**
                    if (!isWithinBusinessHours(selectInfo, teacherConfig)) {
                        AlumnoView.calendar.unselect();
                        window.mostrarNotificacion('No disponible', 'No se puede reservar durante los períodos de descanso del profesor', 'warning');
                        return;
                    }

                    // Verificar que no haya overlap con otros eventos
                    const selectedStart = selectInfo.start.toISOString();
                    const selectedEnd = selectInfo.end.toISOString();

                    const isSlotTaken = AlumnoView.teacherEvents.some(event => {
                        const eventStart = new Date(event.start).toISOString();
                        const eventEnd = new Date(event.end).toISOString();
                        return (selectedStart < eventEnd && selectedEnd > eventStart);
                    });

                    if (isSlotTaken) {
                        AlumnoView.calendar.unselect();
                        window.mostrarNotificacion('No disponible', 'Este horario ya está ocupado', 'warning');
                        return;
                    }

                    // Verificar que la duración de la selección sea correcta
                    const selectedDuration = (selectInfo.end - selectInfo.start) / 60000;
                    const slotDuration = classDuration;

                    if (Math.abs(selectedDuration - slotDuration) > 1) {
                        AlumnoView.calendar.unselect();
                        window.mostrarNotificacion('Duración incorrecta', `Las clases deben durar exactamente ${classDuration} minutos`, 'warning');
                        return;
                    }

                    // Guardar la información de la selección
                    AlumnoView.selectedDate = {
                        date: selectInfo.startStr.substring(0, 10),
                        time: selectInfo.startStr.substring(11, 16),
                        end_time: selectInfo.endStr.substring(11, 16)
                    };

                    // Mostrar modal de reserva
                    AlumnoView.calendarModal.show();
                },

                // Resto de la configuración igual...
                eventClick: function (info) {
                    if (info.event.extendedProps.status === 'cancelled') return;

                    const event = info.event;
                    const calendarInfoForm = document.getElementById('studentCalendarInfoForm');

                    if (!calendarInfoForm) return;

                    const bookingId = event.id;
                    const eventDate = event.startStr.substring(0, 10);
                    const eventStart = event.startStr.substring(11, 16);
                    const eventEnd = event.endStr.substring(11, 16);

                    calendarInfoForm.querySelector('input[name="booking_id"]').value = bookingId;
                    calendarInfoForm.querySelector('input[name="date"]').value = eventDate;
                    calendarInfoForm.querySelector('input[name="time"]').value = eventStart;
                    calendarInfoForm.querySelector('input[name="end_time"]').value = eventEnd;

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
                    // Verificaciones de tiempo
                    const now = new Date();
                    const oneHourFromNow = new Date(now.getTime() + (60 * 60 * 1000));

                    if (selectInfo.start < oneHourFromNow) {
                        return false;
                    }

                    // Verificar solapamiento con eventos existentes
                    const selectedStart = selectInfo.start.toISOString();
                    const selectedEnd = selectInfo.end.toISOString();

                    const isSlotTaken = AlumnoView.teacherEvents.some(event => {
                        const eventStart = new Date(event.start).toISOString();
                        const eventEnd = new Date(event.end).toISOString();
                        return (selectedStart < eventEnd && selectedEnd > eventStart);
                    });

                    if (isSlotTaken) return false;

                    // Verificar duración
                    const selectedDuration = (selectInfo.end - selectInfo.start) / 60000;
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
                },

                windowResize: function (view) {
                    if (window.innerWidth < 768) {
                        AlumnoView.calendar.changeView('timeGridDay');
                    }
                }
            });

            AlumnoView.calendar = studentCalendar;
            studentCalendar.render();
        }
    }

    // Initialize the view
    AlumnoView.init();
});
