jQuery(document).ready(function ($) {

    class ProfesorView {
        static profesorData = {};
        static bookings = [];
        static calendar = null;
        static bookingDetailModal = null;
        static studentDetailModal = null;
        static rejectConfirmModal = null;
        static apiUrl = DSB_CONFIG.apiBaseUrl;
        static jwtToken = DSB_CONFIG.jwtToken;
        static selectedBookingId = null;

        static init() {
            // Inicializar datos
            ProfesorView.profesorData = teacherData;
            ProfesorView.bookings = bookingsData;

            // Inicializar modales
            ProfesorView.bookingDetailModal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
            ProfesorView.studentDetailModal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
            ProfesorView.rejectConfirmModal = new bootstrap.Modal(document.getElementById('rejectConfirmModal'));

            // Inicializar calendario
            ProfesorView.initializeCalendar();

            // Configurar listeners de eventos
            ProfesorView.setupEventListeners();

            // Verificar token de sesión
            ProfesorView.checkSession();
        }

        static checkSession() {
            if (!ProfesorView.jwtToken) {
                console.error('No hay token JWT. Redirigiendo al login...');
                window.location.href = '/acceso';
            }
        }

        static setupEventListeners() {

            document.querySelector('.logout-btn')?.addEventListener('click', function (e) {
                e.preventDefault();
                AlumnoView.handleLogout();
            });

            // Formulario de configuración de horario
            document.getElementById('horario-config-form')?.addEventListener('submit', function (e) {
                e.preventDefault();
                ProfesorView.handleHorarioSubmit(this);
            });

            // Botones para ver detalles de alumnos
            document.querySelectorAll('.ver-alumno').forEach(btn => {
                btn.addEventListener('click', function () {
                    const studentId = this.getAttribute('data-id');
                    ProfesorView.showStudentDetails(studentId);
                });
            });

            // Botón para aceptar reserva
            document.getElementById('acceptBookingBtn')?.addEventListener('click', function () {
                ProfesorView.acceptBooking();
            });

            // Botón para rechazar reserva
            document.getElementById('rejectBookingBtn')?.addEventListener('click', function () {
                const bookingId = document.getElementById('booking_id').value;
                document.getElementById('reject-booking-id').value = bookingId;
                ProfesorView.rejectConfirmModal.show();
            });

            // Confirmar rechazo
            document.getElementById('confirmRejectBtn')?.addEventListener('click', function () {
                const bookingId = document.getElementById('reject-booking-id').value;
                ProfesorView.rejectBooking(bookingId);
            });

            // Filtros de calendario
            document.getElementById('showPendingOnly')?.addEventListener('change', function () {
                ProfesorView.filterCalendarEvents();
            });

            document.getElementById('showAcceptedOnly')?.addEventListener('change', function () {
                ProfesorView.filterCalendarEvents();
            });

            // Cambio de avatar
            const avatarWrapper = document.querySelector('.avatar-wrapper');
            const fileInput = document.getElementById('file-input');

            if (avatarWrapper && fileInput) {
                avatarWrapper.addEventListener('click', function () {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function (event) {
                    ProfesorView.handleAvatarUpload(event);
                });
            }

            // Botones de vista del calendario
            document.querySelectorAll('.calendar-view-switcher .btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    ProfesorView.changeCalendarView(this.getAttribute('data-view'));
                });
            });

            // Botón para ir a hoy
            document.getElementById('goToToday')?.addEventListener('click', function () {
                ProfesorView.calendar.today();
            });

            document.getElementById('refreshCalendar')?.addEventListener('click', function () {
                ProfesorView.mostrarNotificacion('info', 'Actualizando calendario...');
                ProfesorView.loadBookings().then(() => {
                    ProfesorView.calendar.refetchEvents();
                    ProfesorView.mostrarNotificacion('success', 'Calendario actualizado');
                });
            });

            document.querySelectorAll('.calendar-view-switcher .btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const view = this.getAttribute('data-view');
                    ProfesorView.calendar.changeView(view);

                    // Actualizar estados de botones
                    document.querySelectorAll('.calendar-view-switcher .btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
        }

        // Añadir este método para manejar el cierre de sesión
        static async handleLogout() {
            try {
                // Mostrar confirmación antes de cerrar sesión
                if (!confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                    return;
                }

                const response = await fetch(`${ProfesorView.apiUrl}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
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
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static initializeCalendar() {
            const calendarElement = document.getElementById('teacher-calendar');
            if (!calendarElement) {
                console.error("No se encontró el elemento del calendario");
                return;
            }

            // Verificar si profesorData está definido y tiene la estructura esperada
            if (!ProfesorView.profesorData) {
                console.error("Datos del profesor no encontrados");
                ProfesorView.profesorData = { config: {}, id: 0 };
            }

            // Obtener configuración del profesor con valores por defecto
            const config = (ProfesorView.profesorData && ProfesorView.profesorData.config) ?
                ProfesorView.profesorData.config :
                { dias: [], hora_inicio: '08:00:00', hora_fin: '20:00:00', duracion: 45 };

            // Duración de las clases
            const duracion = config.duracion ? `00:${config.duracion}:00` : '00:45:00';

            // Configurar días no disponibles
            const diasDisponibles = config.dias || [];
            const diasNoDisponibles = [0, 1, 2, 3, 4, 5, 6].filter(dia => {
                const diaNombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][dia];
                return !diasDisponibles.includes(diaNombre);
            });

            // Obtener eventos activos
            const bookings = ProfesorView.bookings || [];

            // Configurar eventos con información visual mejorada
            const events = bookings.map(booking => {
                const eventClass = booking.status === 'pending' ? 'pending-event' :
                    booking.status === 'accepted' ? 'accepted-event' :
                        booking.status === 'cancelled' ? 'cancelled-event' :
                            new Date(booking.date) < new Date() ? 'past-event' : '';

                return {
                    id: booking.id,
                    title: booking.title || `Clase con ${booking.student_name || 'Alumno'}`,
                    start: booking.start,
                    end: booking.end,
                    extendedProps: {
                        status: booking.status,
                        studentName: booking.student_name,
                        studentId: booking.student_id,
                        vehicle: booking.vehicle,
                        date: booking.date,
                        time: booking.time,
                        endTime: booking.end_time
                    },
                    className: eventClass
                };
            });

            // Opciones avanzadas del calendario
            const calendarOptions = {
                height: '100%',
                initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotDuration: duracion,
                slotLabelInterval: '01:00',
                allDaySlot: false,
                scrollTime: '08:00:00',
                slotMinTime: config.hora_inicio || '08:00:00',
                slotMaxTime: config.hora_fin || '20:00:00',
                hiddenDays: diasNoDisponibles,
                height: 'auto',
                locale: 'es',
                events: events,
                nowIndicator: true,
                navLinks: true,
                dayMaxEvents: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                views: {
                    timeGridWeek: {
                        dayHeaderFormat: { weekday: 'short', day: 'numeric', month: 'numeric' }
                    }
                },

                // Mejora de visualización de eventos
                eventDidMount: function (info) {
                    const event = info.event;
                    const element = info.el;
                    const status = event.extendedProps.status;

                    // Añadir atributos para accesibilidad
                    element.setAttribute('aria-label', `Clase con ${event.extendedProps.studentName} - ${event.extendedProps.date} a las ${event.extendedProps.time}`);

                    // Aplicar clases específicas para estados
                    if (status === 'pending') {
                        element.classList.add('pending-event');
                    } else if (status === 'accepted') {
                        element.classList.add('accepted-event');
                    }

                    // Agregar tooltip personalizado con información detallada
                    const tooltip = document.createElement('div');
                    tooltip.classList.add('event-tooltip');
                    tooltip.innerHTML = `
                <div class="event-tooltip-header">
                    ${event.title}
                    <span class="event-tooltip-status ${status}">${status === 'pending' ? 'Pendiente' : status === 'accepted' ? 'Aceptada' : 'N/A'}</span>
                </div>
                <div class="event-tooltip-content">
                    <div class="event-tooltip-detail">
                        <i class="bi bi-person"></i> ${event.extendedProps.studentName}
                    </div>
                    <div class="event-tooltip-detail">
                        <i class="bi bi-calendar"></i> ${event.extendedProps.date}
                    </div>
                    <div class="event-tooltip-detail">
                        <i class="bi bi-clock"></i> ${event.extendedProps.time} - ${event.extendedProps.endTime}
                    </div>
                    <div class="event-tooltip-detail">
                        <i class="bi bi-car-front"></i> ${event.extendedProps.vehicle || 'No especificado'}
                    </div>
                </div>
            `;

                    // Mostrar tooltip al hover
                    element.addEventListener('mouseenter', function () {
                        document.body.appendChild(tooltip);
                        const rect = element.getBoundingClientRect();
                        tooltip.style.left = rect.left + window.scrollX + 'px';
                        tooltip.style.top = rect.top + window.scrollY - tooltip.offsetHeight - 10 + 'px';

                        // Ajustar posición si se sale de la pantalla
                        const tooltipRect = tooltip.getBoundingClientRect();
                        if (tooltipRect.left < 0) {
                            tooltip.style.left = '10px';
                        }
                        if (tooltipRect.right > window.innerWidth) {
                            tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
                        }
                        if (tooltipRect.top < 0) {
                            tooltip.style.top = rect.bottom + window.scrollY + 10 + 'px';
                        }
                    });

                    element.addEventListener('mouseleave', function () {
                        if (document.body.contains(tooltip)) {
                            document.body.removeChild(tooltip);
                        }
                    });
                },

                // Manejo de clics en eventos
                eventClick: function (info) {
                    // Código para mostrar el modal con detalles del evento
                    const event = info.event;
                    const bookingId = event.id;
                    const status = event.extendedProps.status;

                    // Completar el modal con la información del evento
                    document.getElementById('booking_id').value = bookingId;
                    document.getElementById('student_name').value = event.extendedProps.studentName;
                    document.getElementById('booking_status').value = status === 'pending' ? 'Pendiente' :
                        status === 'accepted' ? 'Aceptada' :
                            status === 'cancelled' ? 'Cancelada' : 'Desconocido';
                    document.getElementById('booking_date').value = event.extendedProps.date;
                    document.getElementById('booking_time').value = `${event.extendedProps.time} - ${event.extendedProps.endTime}`;
                    document.getElementById('booking_vehicle').value = event.extendedProps.vehicle || 'No especificado';

                    // Controles específicos según el estado
                    const acceptBtn = document.getElementById('acceptBookingBtn');
                    const rejectBtn = document.getElementById('rejectBookingBtn');

                    if (status === 'pending') {
                        acceptBtn.style.display = 'inline-block';
                        rejectBtn.style.display = 'inline-block';
                        acceptBtn.textContent = 'Aceptar';
                    } else if (status === 'accepted') {
                        acceptBtn.style.display = 'none';
                        rejectBtn.textContent = 'Cancelar';
                    } else {
                        acceptBtn.style.display = 'none';
                        rejectBtn.style.display = 'none';
                    }

                    // Mostrar el modal
                    const bookingModal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
                    bookingModal.show();
                },

                // Ajustes responsivos
                windowResize: function (view) {
                    if (window.innerWidth < 768) {
                        ProfesorView.calendar.changeView('timeGridDay');
                    }
                }
            };

            // Inicializar el calendario
            ProfesorView.calendar = new FullCalendar.Calendar(calendarElement, calendarOptions);
            ProfesorView.calendar.render();

            // Añadir eventListeners para los filtros
            document.getElementById('showPendingOnly')?.addEventListener('change', ProfesorView.applyFilters);
            document.getElementById('showAcceptedOnly')?.addEventListener('change', ProfesorView.applyFilters);
            document.getElementById('refreshCalendar')?.addEventListener('click', () => ProfesorView.calendar.refetchEvents());
        }

        static applyFilters() {
            const showPending = document.getElementById('showPendingOnly')?.checked || false;
            const showAccepted = document.getElementById('showAcceptedOnly')?.checked || false;

            if (!ProfesorView.calendar) return;

            // Si no hay filtros activos, mostrar todos
            if (!showPending && !showAccepted) {
                ProfesorView.calendar.getEvents().forEach(event => {
                    event.setProp('display', 'auto');
                });
                return;
            }

            // Aplicar filtros según las opciones seleccionadas
            ProfesorView.calendar.getEvents().forEach(event => {
                const status = event.extendedProps.status;

                if ((showPending && status === 'pending') ||
                    (showAccepted && status === 'accepted') ||
                    (!showPending && !showAccepted)) {
                    event.setProp('display', 'auto');
                } else {
                    event.setProp('display', 'none');
                }
            });
        }

        static updateActiveViewButton(viewType) {
            document.querySelectorAll('.calendar-view-switcher .btn').forEach(btn => {
                const view = btn.getAttribute('data-view');
                btn.classList.toggle('active', view === viewType);
            });
        }

        static changeCalendarView(viewType) {
            if (ProfesorView.calendar) {
                ProfesorView.calendar.changeView(viewType);
            }
        }

        static handleEventClick(info) {
            const booking = info.event;
            const bookingId = booking.id;
            const status = booking.extendedProps.status;
            const studentName = booking.extendedProps.student_name;
            const date = booking.extendedProps.date;
            const time = booking.extendedProps.time;
            const end_time = booking.extendedProps.end_time;
            const vehicle = booking.extendedProps.vehicle;

            // Actualizar el modal con los datos de la reserva
            document.getElementById('booking_id').value = bookingId;
            document.getElementById('student_name').value = studentName;
            document.getElementById('booking_status').value = ProfesorView.formatStatus(status);
            document.getElementById('booking_date').value = date;
            document.getElementById('booking_time').value = `${time} - ${end_time}`;
            document.getElementById('booking_vehicle').value = vehicle;

            // Mostrar/ocultar botones según el estado
            const acceptBtn = document.getElementById('acceptBookingBtn');
            const rejectBtn = document.getElementById('rejectBookingBtn');

            if (status === 'pending') {
                acceptBtn.style.display = 'inline-block';
                rejectBtn.style.display = 'inline-block';
            } else {
                acceptBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }

            // Mostrar el modal
            ProfesorView.bookingDetailModal.show();
        }

        static formatStatus(status) {
            switch (status) {
                case 'pending': return 'Pendiente';
                case 'accepted': return 'Aceptada';
                case 'cancelled': return 'Cancelada';
                default: return status;
            }
        }

        static async acceptBooking() {
            const bookingId = document.getElementById('booking_id').value;
            if (!bookingId) return;

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/bookings/accept/${bookingId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al aceptar la reserva');
                }

                const data = await response.json();

                // Actualizar el estado de la reserva en la lista
                const bookingIndex = ProfesorView.bookings.findIndex(b => b.id == bookingId);
                if (bookingIndex >= 0) {
                    ProfesorView.bookings[bookingIndex].status = 'accepted';
                }

                // Actualizar el estado del evento en el calendario
                const event = ProfesorView.calendar.getEventById(bookingId);
                if (event) {
                    event.setProp('backgroundColor', '#28a745');
                    event.setProp('borderColor', '#28a745');
                    event.setExtendedProp('status', 'accepted');

                    // Actualizar clases del evento
                    event.setProp('classNames', ['accepted-event']);
                }

                // Ocultar modal y mostrar notificación
                ProfesorView.bookingDetailModal.hide();
                ProfesorView.mostrarNotificacion('success', 'Reserva aceptada correctamente');

            } catch (error) {
                console.error('Error al aceptar la reserva:', error);
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static async rejectBooking(bookingId) {
            if (!bookingId) return;

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/bookings/reject/${bookingId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al rechazar la reserva');
                }

                const data = await response.json();

                // Actualizar el estado de la reserva en la lista
                const bookingIndex = ProfesorView.bookings.findIndex(b => b.id == bookingId);
                if (bookingIndex >= 0) {
                    ProfesorView.bookings[bookingIndex].status = 'cancelled';
                }

                // Eliminar el evento del calendario
                const event = ProfesorView.calendar.getEventById(bookingId);
                if (event) {
                    event.remove();
                }

                // Ocultar modales y mostrar notificación
                ProfesorView.rejectConfirmModal.hide();
                ProfesorView.bookingDetailModal.hide();
                ProfesorView.mostrarNotificacion('success', 'Reserva rechazada correctamente');

            } catch (error) {
                console.error('Error al rechazar la reserva:', error);
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static async loadBookings() {
            try {
                const response = await fetch(`${ProfesorView.apiUrl}/bookings/teacher`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + ProfesorView.jwtToken,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al cargar las reservas');
                }

                const data = await response.json();
                ProfesorView.bookings = data;

                return data;
            } catch (error) {
                console.error('Error al cargar reservas:', error);
                ProfesorView.mostrarNotificacion('error', 'Error al cargar reservas');
                return [];
            }
        }

        static filterCalendarEvents() {
            const showPending = document.getElementById('showPendingOnly').checked;
            const showAccepted = document.getElementById('showAcceptedOnly').checked;

            ProfesorView.calendar.getEvents().forEach(event => {
                const status = event.extendedProps.status;

                // Si no hay filtros, mostrar todos
                if (!showPending && !showAccepted) {
                    event.setProp('display', 'auto');
                    return;
                }

                // Mostrar según filtros seleccionados
                if ((showPending && status === 'pending') || (showAccepted && status === 'accepted')) {
                    event.setProp('display', 'auto');
                } else {
                    event.setProp('display', 'none');
                }
            });
        }

        static async handleAvatarUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validar tipo y tamaño
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                ProfesorView.mostrarNotificacion('error', 'Tipo de archivo no permitido. Use JPG, PNG o GIF');
                return;
            }

            if (file.size > maxSize) {
                ProfesorView.mostrarNotificacion('error', 'El archivo es demasiado grande (máx. 2MB)');
                return;
            }

            const formData = new FormData();
            formData.append("file", file);

            // Mostrar indicador de carga
            const avatarElement = document.getElementById('teacher-avatar');
            const originalSrc = avatarElement.src;
            avatarElement.classList.add('uploading');

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/users/me/avatar`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + ProfesorView.jwtToken
                    },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Error al subir la imagen');
                }

                const data = await response.json();

                if (data.success) {
                    avatarElement.src = data.url;
                    ProfesorView.mostrarNotificacion('success', 'Imagen de perfil actualizada correctamente');
                } else {
                    throw new Error(data.message || 'Error desconocido');
                }

            } catch (error) {
                avatarElement.src = originalSrc;
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            } finally {
                avatarElement.classList.remove('uploading');
            }
        }

        static async handleHorarioSubmit(form) {
            // Recolectar datos del formulario
            const formData = new FormData(form);
            const dias = formData.getAll('dias[]');
            const hora_inicio = formData.get('hora_inicio');
            const hora_fin = formData.get('hora_fin');
            const duracion = formData.get('duracion');

            // Validar campos
            if (!dias.length || !hora_inicio || !hora_fin || !duracion) {
                ProfesorView.mostrarNotificacion('error', 'Todos los campos son obligatorios');
                return;
            }

            // Preparar payload
            const payload = {
                dias,
                hora_inicio,
                hora_fin,
                duracion
            };

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/teachers/${ProfesorView.profesorData.id}/classes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al guardar la configuración');
                }

                const data = await response.json();

                // Actualizar datos en memoria
                ProfesorView.profesorData.config = payload;

                // Cerrar acordeón
                const accordion = bootstrap.Collapse.getInstance(document.getElementById('configCollapse'));
                if (accordion) {
                    accordion.hide();
                }

                // Mostrar notificación
                ProfesorView.mostrarNotificacion('success', 'Configuración de horario guardada correctamente');

                // Recargar el calendario para aplicar los nuevos cambios
                ProfesorView.calendar.setOption('slotMinTime', hora_inicio);
                ProfesorView.calendar.setOption('slotMaxTime', hora_fin);
                ProfesorView.calendar.setOption('slotDuration', `00:${duracion}:00`);

                // Actualizar días no disponibles
                const diasNoDisponibles = [0, 1, 2, 3, 4, 5, 6].filter(dia => {
                    const diaNombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][dia];
                    return !dias.includes(diaNombre);
                });
                ProfesorView.calendar.setOption('hiddenDays', diasNoDisponibles);

                ProfesorView.calendar.render();

            } catch (error) {
                console.error('Error al guardar la configuración:', error);
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        static async showStudentDetails(studentId) {
            if (!studentId) return;

            try {
                // Obtener datos del estudiante
                const response = await fetch(`${ProfesorView.apiUrl}/students/${studentId}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al obtener datos del estudiante');
                }

                const result = await response.json();
                const studentData = result.data;

                // Actualizar el modal con los datos del alumno
                document.getElementById('modal-student-avatar').src = studentData.avatar;
                document.getElementById('modal-student-name').textContent = studentData.display_name;
                document.getElementById('modal-student-email').textContent = studentData.email;
                document.getElementById('modal-student-license').textContent = studentData.license_type || 'No especificado';
                document.getElementById('modal-student-phone').textContent = studentData.phone || 'No especificado';

                // Filtrar las clases de este alumno
                const studentClasses = ProfesorView.bookings.filter(booking =>
                    booking.student_id == studentId && booking.status !== 'cancelled'
                );

                document.getElementById('modal-student-classes').textContent = `${studentClasses.length} clases`;

                // Mostrar listado de clases
                const classesList = document.getElementById('student-classes');
                const noClassesMessage = document.getElementById('no-classes-message');

                classesList.innerHTML = '';

                if (studentClasses.length > 0) {
                    noClassesMessage.style.display = 'none';

                    // Ordenar clases por fecha (más reciente primero)
                    studentClasses.sort((a, b) => new Date(b.date) - new Date(a.date));

                    // Mostrar las últimas 5 clases
                    const recentClasses = studentClasses.slice(0, 5);

                    recentClasses.forEach(booking => {
                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item';

                        // Formatear fecha
                        const bookingDate = new Date(booking.date);
                        const formattedDate = bookingDate.toLocaleDateString('es-ES', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long'
                        });

                        // Determinar clase según estado
                        let statusClass, statusText;
                        switch (booking.status) {
                            case 'pending':
                                statusClass = 'text-warning';
                                statusText = 'Pendiente';
                                break;
                            case 'accepted':
                                statusClass = 'text-success';
                                statusText = 'Aceptada';
                                break;
                            default:
                                statusClass = 'text-secondary';
                                statusText = booking.status;
                        }

                        listItem.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${formattedDate}</strong>
                                    <div class="text-muted">${booking.time} - ${booking.end_time}</div>
                                </div>
                                <span class="badge bg-light ${statusClass}">${statusText}</span>
                            </div>
                        `;

                        classesList.appendChild(listItem);
                    });

                } else {
                    noClassesMessage.style.display = 'block';
                }

                // Mostrar el modal
                ProfesorView.studentDetailModal.show();

            } catch (error) {
                console.error('Error al obtener datos del alumno:', error);
                ProfesorView.mostrarNotificacion('error', `Error: ${error.message}`);
            }
        }

        // Método para mostrar notificaciones no bloqueantes
        static mostrarNotificacion(tipo, mensaje, duracion = 3000) {
            // Verificar si ya existe una notificación
            let notificacion = document.querySelector('.dsb-notificacion');

            if (!notificacion) {
                // Crear elemento de notificación
                notificacion = document.createElement('div');
                notificacion.className = `dsb-notificacion ${tipo}`;
                document.body.appendChild(notificacion);
            } else {
                // Actualizar clase de la notificación existente
                notificacion.className = `dsb-notificacion ${tipo}`;
            }

            // Actualizar mensaje
            notificacion.innerHTML = mensaje;

            // Mostrar con animación
            setTimeout(() => {
                notificacion.classList.add('visible');
            }, 10);

            // Ocultar después de la duración especificada
            setTimeout(() => {
                notificacion.classList.remove('visible');
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.parentNode.removeChild(notificacion);
                    }
                }, 300);
            }, duracion);
        }
    }

    // Inicializar la vista
    ProfesorView.init();
});