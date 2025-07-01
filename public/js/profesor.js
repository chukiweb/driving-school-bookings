document.addEventListener('DOMContentLoaded', function () {

    class ProfesorView {
        static profesorData = {};
        static bookings = [];
        static calendar = null;
        static bookingDetailModal = null;
        static studentDetailModal = null;
        static rejectConfirmModal = null;
        static completedClassesModal = null;
        static currentCompletedPage = 1;
        static blockTimeModal = null;
        static createBookingModal = null;
        static apiUrl = DSB_CONFIG.apiBaseUrl;
        static jwtToken = DSB_CONFIG.jwtToken;
        static selectedBookingId = null;
        static blockModeActive = false;
        static createBookingModeActive = false;
        static descansosCounter = 0;
        static currentMode = null;
        static originalButtonStates = new Map();
        static teacherSlots = [];

        static init() {
            // Inicializar datos
            ProfesorView.profesorData = teacherData;
            ProfesorView.bookings = bookingsData;

            // Inicializar modales
            ProfesorView.bookingDetailModal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
            ProfesorView.studentDetailModal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
            ProfesorView.rejectConfirmModal = new bootstrap.Modal(document.getElementById('rejectConfirmModal'));
            ProfesorView.blockTimeModal = new bootstrap.Modal(document.getElementById('blockTimeModal'));
            ProfesorView.createBookingModal = new bootstrap.Modal(document.getElementById('createBookingModal'));

            // Inicializar calendario
            ProfesorView.initializeCalendar();

            // Configurar listeners de eventos
            ProfesorView.setupEventListeners();

            // Verificar token de sesión
            ProfesorView.checkSession();

            // Configurar filtros de estudiantes
            ProfesorView.setupStudentFilters();

            // Inicializar listeners para descansos
            ProfesorView.initDescansosListeners();

            // Inicializar modal de clases completadas
            ProfesorView.completedClassesModal = new bootstrap.Modal(document.getElementById('completedClassesModal'));

            // Cargar estadísticas de clases completadas
            ProfesorView.loadCompletedClassesStats();

            // **LISTENERS PARA CANCELAR MODALES**
            // Cuando se cierra el modal de bloqueo sin guardar, mantener el modo
            document.getElementById('blockTimeModal')?.addEventListener('hidden.bs.modal', function () {
                // Solo desactivar si no se guardó (se puede añadir una flag si es necesario)
                // Por ahora, mantener el modo activo para que el usuario pueda seguir seleccionando
            });

            document.getElementById('createBookingModal')?.addEventListener('hidden.bs.modal', function () {
                // Solo desactivar si no se guardó
            });

            // **AÑADIR BOTÓN DE CANCELAR EN MODALES PARA DESACTIVAR MODO**
            document.querySelector('#blockTimeModal .btn-secondary')?.addEventListener('click', function () {
                ProfesorView.clearMode(); // ✅ CAMBIO
            });

            document.querySelector('#createBookingModal .btn-secondary')?.addEventListener('click', function () {
                ProfesorView.clearMode(); // ✅ CAMBIO
            });
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
                ProfesorView.handleLogout();
            });

            // Formulario de configuración de horario
            document.getElementById('horario-config-form')?.addEventListener('submit', function (e) {
                e.preventDefault();
                ProfesorView.handleTeacherConfigSubmit(this);
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
                ProfesorView.cancelBooking(bookingId);
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
                window.mostrarNotificacion('', 'Actualizando calendario...');
                ProfesorView.loadBookings().then(() => {
                    ProfesorView.calendar.refetchEvents();
                    window.mostrarNotificacion('', 'Calendario actualizado', 'success');
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

            document.getElementById('blockTimeBtn')?.addEventListener('click', function (e) {
                e.preventDefault();
                ProfesorView.activateBlockMode();
            });

            document.getElementById('createBookingBtn')?.addEventListener('click', function (e) {
                e.preventDefault();
                ProfesorView.activateCreateMode();
            });

            // Guardar reserva para alumno
            document.getElementById('saveBookingBtn')?.addEventListener('click', function () {
                ProfesorView.createBookingForStudent();
            });

            // Rellenar licencia de conducir en el modal de crear reserva
            document.getElementById('student_select')?.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex].value;
                const inputLicense = document.getElementById('license_type');
                const studentLicense = ProfesorView.profesorData.students.find(alumno => alumno.id == selectedOption).license_type;

                if (studentLicense) {
                    inputLicense.value = studentLicense;
                }
            });

            document.getElementById('saveBlockBtn')?.addEventListener('click', function () {
                ProfesorView.saveTimeBlock();
            });

            // Botón para abrir historial de clases completadas
            document.getElementById('openCompletedHistoryBtn')?.addEventListener('click', function () {
                ProfesorView.openCompletedClassesModal();
            });

            // Filtrar historial por fechas
            document.getElementById('filterCompletedBtn')?.addEventListener('click', function () {
                ProfesorView.filterCompletedClasses();
            });

            // Limpiar filtros de historial
            document.getElementById('clearFiltersBtn')?.addEventListener('click', function () {
                ProfesorView.clearCompletedFilters();
            });

            // Event delegation para paginación de historial de clases completadas
            document.addEventListener('click', function (e) {
                // Manejar clics en paginación de historial completado
                if (e.target.closest('#completedHistoryPagination .page-link')) {
                    e.preventDefault();

                    const link = e.target.closest('.page-link');
                    const pageItem = link.closest('.page-item');

                    // Evitar clics en páginas deshabilitadas o activas
                    if (pageItem.classList.contains('disabled') || pageItem.classList.contains('active')) {
                        return;
                    }

                    const page = parseInt(link.dataset.page);
                    if (page && page > 0) {
                        ProfesorView.loadCompletedHistory(page);
                    }
                }
            });
        }

        // Nuevo método para inicializar listeners de descansos:
        static initDescansosListeners() {
            // Inicializar contador con descansos existentes
            const existingItems = document.querySelectorAll('.descanso-item[data-descanso-id]');
            if (existingItems.length > 0) {
                const maxId = Math.max(...Array.from(existingItems).map(item =>
                    parseInt(item.dataset.descansoId) || 0
                ));
                ProfesorView.descansosCounter = maxId;
            } else {
                ProfesorView.descansosCounter = 0;
            }

            // Listener para añadir descanso
            const addBtn = document.getElementById('add-descanso-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    ProfesorView.addDescansoField();
                });
            }

            // Delegación de eventos para los botones de eliminar
            const container = document.getElementById('descansos-container');
            if (container) {
                container.addEventListener('click', function (e) {
                    if (e.target.classList.contains('remove-descanso-btn') ||
                        e.target.closest('.remove-descanso-btn')) {
                        const button = e.target.classList.contains('remove-descanso-btn') ?
                            e.target : e.target.closest('.remove-descanso-btn');
                        ProfesorView.removeDescansoField(button);
                    }
                });
            }
        }

        static addDescansoField() {
            const container = document.getElementById('descansos-container');
            const descansoId = ++ProfesorView.descansosCounter;

            const descansoDiv = document.createElement('div');
            descansoDiv.className = 'descanso-item mb-2 p-3 border rounded bg-light';
            descansoDiv.dataset.descansoId = descansoId;

            descansoDiv.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small">Hora inicio</label>
                        <input type="time" class="form-control form-control-sm" 
                            name="descansos[${descansoId}][inicio]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Hora fin</label>
                        <input type="time" class="form-control form-control-sm" 
                            name="descansos[${descansoId}][fin]" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-descanso-btn">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(descansoDiv);
        }

        static removeDescansoField(button) {
            const descansoItem = button.closest('.descanso-item');
            if (descansoItem) {
                descansoItem.remove();
            }
        }

        static setMode(mode) {
            // Limpiar modo anterior si existe
            if (ProfesorView.currentMode) {
                ProfesorView.clearMode();
            }

            ProfesorView.currentMode = mode;
            ProfesorView.blockModeActive = (mode === 'block');
            ProfesorView.createBookingModeActive = (mode === 'create');

            // Aplicar estado visual del calendario
            const calendar = document.getElementById('calendar');
            calendar.classList.remove('block-mode', 'create-mode');

            if (mode) {
                calendar.classList.add(`${mode}-mode`);
            }

            // Configurar botones según el modo
            ProfesorView.updateButtonStates(mode);

            // Mostrar notificación correspondiente
            ProfesorView.showModeNotification(mode);
        }

        static clearMode() {
            ProfesorView.currentMode = null;
            ProfesorView.blockModeActive = false;
            ProfesorView.createBookingModeActive = false;

            const calendar = document.getElementById('calendar');
            if (calendar) {
                calendar.classList.remove('block-mode', 'create-mode');
            }

            ProfesorView.restoreButtonStates();
        }

        static updateButtonStates(mode) {
            const blockBtn = document.getElementById('blockTimeBtn');
            const createBtn = document.getElementById('createBookingBtn');

            if (!blockBtn || !createBtn) return;

            // Guardar estados originales si no se han guardado
            if (!ProfesorView.originalButtonStates.has('block')) {
                ProfesorView.originalButtonStates.set('block', {
                    classes: blockBtn.className,
                    innerHTML: blockBtn.innerHTML
                });
            }
            if (!ProfesorView.originalButtonStates.has('create')) {
                ProfesorView.originalButtonStates.set('create', {
                    classes: createBtn.className,
                    innerHTML: createBtn.innerHTML
                });
            }

            switch (mode) {
                case 'block':
                    // Activar modo bloqueo
                    blockBtn.className = 'btn btn-danger';
                    blockBtn.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar bloqueo';

                    // Desactivar botón crear
                    createBtn.className = 'btn btn-outline-primary';
                    createBtn.innerHTML = '<i class="bi bi-calendar-plus"></i> Crear reserva';
                    createBtn.disabled = true;
                    break;

                case 'create':
                    // Activar modo crear
                    createBtn.className = 'btn btn-primary';
                    createBtn.innerHTML = '<i class="bi bi-x-circle"></i> Cancelar reserva';

                    // Desactivar botón bloquear
                    blockBtn.className = 'btn btn-outline-danger';
                    blockBtn.innerHTML = '<i class="bi bi-lock-fill"></i> Bloquear horario';
                    blockBtn.disabled = true;
                    break;

                default:
                    // Modo neutral - restaurar estados originales
                    ProfesorView.restoreButtonStates();
                    break;
            }
        }

        static restoreButtonStates() {
            const blockBtn = document.getElementById('blockTimeBtn');
            const createBtn = document.getElementById('createBookingBtn');

            if (blockBtn && ProfesorView.originalButtonStates.has('block')) {
                const originalBlock = ProfesorView.originalButtonStates.get('block');
                blockBtn.className = originalBlock.classes;
                blockBtn.innerHTML = originalBlock.innerHTML;
                blockBtn.disabled = false;
            }

            if (createBtn && ProfesorView.originalButtonStates.has('create')) {
                const originalCreate = ProfesorView.originalButtonStates.get('create');
                createBtn.className = originalCreate.classes;
                createBtn.innerHTML = originalCreate.innerHTML;
                createBtn.disabled = false;
            }
        }

        static showModeNotification(mode) {
            switch (mode) {
                case 'block':
                    window.mostrarNotificacion(
                        'Modo Bloqueo Activado',
                        'Haga clic en un slot o arrastre para seleccionar el período a bloquear', // ✅ CAMBIO
                        'info'
                    );
                    break;
                case 'create':
                    window.mostrarNotificacion(
                        'Modo Reserva Activado',
                        'Haga clic en un slot para crear una nueva reserva', // ✅ CAMBIO
                        'info'
                    );
                    break;
                case null:
                    window.mostrarNotificacion(
                        '',
                        'Modo desactivado',
                        'success'
                    );
                    break;
            }
        }

        static activateBlockMode() {
            if (ProfesorView.currentMode === 'block') {
                // Si ya está en modo bloqueo, desactivar
                ProfesorView.setMode(null);
            } else {
                // Activar modo bloqueo
                ProfesorView.setMode('block');
            }
        }

        static activateCreateMode() {
            if (ProfesorView.currentMode === 'create') {
                // Si ya está en modo crear, desactivar
                ProfesorView.setMode(null);
            } else {
                // Activar modo crear
                ProfesorView.setMode('create');
            }
        }

        static async saveTimeBlock() {
            const startDate = document.getElementById('block_start_date').value;
            const startTime = document.getElementById('block_start_time').value;
            const endDate = document.getElementById('block_end_date').value;
            const endTime = document.getElementById('block_end_time').value;
            const reason = document.getElementById('block_reason').value || 'Horario no disponible';

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/teachers/block-time`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    },
                    body: JSON.stringify({
                        teacher_id: ProfesorView.profesorData.id,
                        date: startDate,
                        start_time: startTime,
                        end_time: endTime,
                        reason: reason
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al bloquear horario');
                }

                const data = await response.json();

                // Añadir el bloqueo al calendario
                ProfesorView.calendar.addEvent({
                    id: `block-${data.id}`,
                    title: reason,
                    start: `${startDate}T${startTime}`,
                    end: `${endDate}T${endTime}`,
                    extendedProps: {
                        status: 'blocked',
                        reason: reason
                    },
                    classNames: ['blocked-event'],
                    backgroundColor: '#dc3545',
                    borderColor: '#b02a37'
                });

                // Ocultar modal y mostrar notificación
                ProfesorView.blockTimeModal.hide();
                window.mostrarNotificacion('', 'Horario bloqueado correctamente', 'success');

                ProfesorView.clearMode();
            } catch (error) {
                console.error('Error al bloquear horario:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        static async createBookingForStudent() {
            const date = document.getElementById('new_booking_date').value;
            const startTime = document.getElementById('new_booking_start_time').value;
            const endTime = document.getElementById('new_booking_end_time').value;
            const studentId = document.getElementById('student_select').value;
            const vehicle = document.getElementById('license_type').value === 'B' ? ProfesorView.profesorData.vehicle.b.name : ProfesorView.profesorData.vehicle.a.name;

            if (!studentId) {
                window.mostrarNotificacion('Error', 'Debe seleccionar un alumno', 'error');
                return;
            }

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/bookings/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    },
                    body: JSON.stringify({
                        alumno: studentId,
                        profesor: ProfesorView.profesorData.id,
                        vehiculo: vehicle,
                        fecha: date,
                        hora: startTime,
                        end_time: endTime,
                        status: 'accepted',
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al crear la reserva');
                }

                const data = await response.json();

                // Añadir la reserva al calendario
                const studentName = document.getElementById('student_select').options[document.getElementById('student_select').selectedIndex].text;

                ProfesorView.calendar.addEvent({
                    id: data.id,
                    title: `Clase con ${studentName}`,
                    start: `${date}T${startTime}`,
                    end: `${date}T${endTime}`,
                    extendedProps: {
                        status: 'accepted',
                        studentName: studentName,
                        studentId: studentId,
                        vehicle: vehicle,
                        date: date,
                        time: startTime,
                        endTime: endTime
                    },
                    className: 'accepted-event',
                    backgroundColor: '#28a745',
                    borderColor: '#28a745'
                });

                // Ocultar modal y mostrar notificación
                ProfesorView.createBookingModal.hide();
                window.mostrarNotificacion('', 'Reserva creada correctamente', 'success');

                // Actualizar la lista de reservas
                await ProfesorView.loadBookings();

            } catch (error) {
                console.error('Error al crear la reserva:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
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
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        static setupStudentFilters() {
            const searchInput = document.getElementById('searchStudentOffcanvas');
            const filterSelect = document.getElementById('studentFilter');
            const studentItems = document.querySelectorAll('.student-item');
            const itemsPerPage = 15;
            let currentPage = 1;
            let filteredItems = [...studentItems];

            // Función para filtrar estudiantes
            const filterStudents = () => {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const filterValue = filterSelect.value;

                filteredItems = [...studentItems].filter(item => {
                    const matchesSearch = searchTerm === '' ||
                        item.dataset.name.includes(searchTerm) ||
                        item.dataset.email.includes(searchTerm);

                    let matchesFilter = true;
                    if (filterValue === 'today') matchesFilter = item.dataset.today === 'true';
                    else if (filterValue === 'pending') matchesFilter = item.dataset.pending === 'true';
                    else if (filterValue === 'inactive') matchesFilter = item.dataset.inactive === 'true';

                    return matchesSearch && matchesFilter;
                });

                // Ocultar todos los items primero
                studentItems.forEach(item => item.style.display = 'none');

                const totalPages = Math.ceil(filteredItems.length / itemsPerPage) || 1;

                const paginationExists = document.getElementById('studentPagination');
                if (paginationExists) {
                    document.getElementById('totalPages').textContent = totalPages;
                    currentPage = 1;
                    document.getElementById('currentPage').textContent = currentPage;
                    document.getElementById('prevPage').disabled = true;
                    document.getElementById('nextPage').disabled = totalPages <= 1;
                }

                // Mostrar items de la página actual
                showCurrentPage();

                // Mostrar mensaje si no hay resultados
                const noResults = document.getElementById('noResults');
                const studentListContainer = document.getElementById('studentListContainer');

                if (filteredItems.length === 0) {
                    if (!noResults && studentListContainer) {
                        const message = document.createElement('div');
                        message.id = 'noResults';
                        message.className = 'alert alert-info mt-3';
                        message.innerHTML = '<i class="bi bi-info-circle me-2"></i>No se encontraron alumnos que coincidan con tu búsqueda';
                        studentListContainer.appendChild(message);
                    }
                } else if (noResults) {
                    noResults.remove();
                }
            };

            // Función para mostrar la página actual
            const showCurrentPage = () => {
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                const pageItems = filteredItems.slice(start, end);

                pageItems.forEach(item => item.style.display = 'block');
            };

            // Event listeners
            searchInput.addEventListener('input', filterStudents);
            filterSelect.addEventListener('change', filterStudents);

            // Paginación
            document.getElementById('prevPage')?.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    document.getElementById('currentPage').textContent = currentPage;
                    document.getElementById('prevPage').disabled = currentPage === 1;
                    document.getElementById('nextPage').disabled = false;

                    studentItems.forEach(item => item.style.display = 'none');
                    showCurrentPage();
                }
            });

            document.getElementById('nextPage')?.addEventListener('click', () => {
                const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    document.getElementById('currentPage').textContent = currentPage;
                    document.getElementById('prevPage').disabled = false;
                    document.getElementById('nextPage').disabled = currentPage === totalPages;

                    studentItems.forEach(item => item.style.display = 'none');
                    showCurrentPage();
                }
            });

            // Inicialización
            if (studentItems.length > itemsPerPage) {
                // Inicializar la primera página
                studentItems.forEach((item, index) => {
                    if (index >= itemsPerPage) {
                        item.style.display = 'none';
                    }
                });
            }
        }

        static showBookingDetails(booking) {
            // Actualizar el modal con los datos de la reserva
            document.getElementById('booking_id').value = booking.id;
            document.getElementById('student_name').value = booking.student_name;
            document.getElementById('booking_status').value = ProfesorView.formatStatus(booking.status);
            document.getElementById('booking_date').value = booking.date;
            document.getElementById('booking_time').value = `${booking.start_time} - ${booking.end_time}`;
            document.getElementById('booking_vehicle').value = booking.vehicle || 'No especificado';

            // Mostrar/ocultar botones según el estado
            const acceptBtn = document.getElementById('acceptBookingBtn');
            const rejectBtn = document.getElementById('rejectBookingBtn');

            if (booking.status === 'pending') {
                acceptBtn.style.display = 'inline-block';
                rejectBtn.style.display = 'inline-block';
                rejectBtn.textContent = 'Rechazar';
            } else if (booking.status === 'accepted') {
                acceptBtn.style.display = 'none';
                rejectBtn.style.display = 'inline-block';
                rejectBtn.textContent = 'Cancelar';
            } else {
                acceptBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }

            // Mostrar el modal
            ProfesorView.bookingDetailModal.show();
        }

        static initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) {
                console.error('No se encontró el elemento del calendario');
                return;
            }

            // 1. Validar que profesorData esté definido
            if (!ProfesorView.profesorData) {
                console.error('Datos del profesor no encontrados');
                // Inicializar por defecto para evitar errores
                ProfesorView.profesorData = { config: {}, id: 0 };
            }

            // 2. Obtener configuración (con valores por defecto)
            const config = (ProfesorView.profesorData.config)
                ? ProfesorView.profesorData.config
                : { dias: [], hora_inicio: '08:00:00', hora_fin: '20:00:00', duracion: 45, descansos: [] };

            // 3. Duración de la clase (formato HH:mm:ss)
            const classDuration = config.duracion || DSB_CONFIG.classDuration || 45;
            const duracionClase = `00:${String(classDuration).padStart(2, '0')}:00`;

            // 4. Calcular días no disponibles (hiddenDays)
            const diasDisponibles = config.dias || [];
            const diasNoDisponibles = [0, 1, 2, 3, 4, 5, 6].filter(diaIdx => {
                const nombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][diaIdx];
                return !diasDisponibles.includes(nombre);
            });

            // 5. Generar businessHours (incluyendo descansos)
            const businessHours = ProfesorView.generateBusinessHours(config);

            // 6. Generar “slots” del profesor (para modo crear / bloquear)
            ProfesorView.teacherSlots = ProfesorView.generateTeacherSlots(config);

            // 7. Preparar el array de eventos (tomamos booking.start / booking.end directamente)
            const bookings = ProfesorView.bookings || [];
            const events = bookings
                .filter(booking => booking.status !== 'cancelled')
                .map(booking => {
                    let eventClass = '';
                    switch (booking.status) {
                        case 'pending':
                            eventClass = 'pending-event';
                            break;
                        case 'accepted':
                            eventClass = 'accepted-event';
                            break;
                        case 'blocked':
                            eventClass = 'blocked-event';
                            break;
                        default:
                            if (new Date(booking.date) < new Date()) {
                                eventClass = 'past-event';
                            }
                            break;
                    }

                    return {
                        id: booking.id,
                        title: booking.title || `Clase con ${booking.student_name || 'Alumno'}`,
                        start: booking.start,
                        end: booking.end,
                        className: eventClass,
                        extendedProps: {
                            status: booking.status,
                            studentName: booking.student_name,
                            studentId: booking.student_id,
                            vehicle: booking.vehicle,
                            date: booking.date,
                            time: booking.time,
                            endTime: booking.end_time
                        }
                    };
                });

            // 8. Construir opciones avanzadas del calendario
            const calendarOptions = {
                // Vista inicial: Day en móvil, Week en escritorio
                initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',

                height: 'auto', // Ajuste automático de altura

                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día'
                },

                // Cabecera superior
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                slotDuration: '00:15:00',
                slotLabelInterval: duracionClase,

                allDaySlot: false,
                scrollTime: '08:00:00',

                slotMinTime: config.hora_inicio || '08:00:00',
                slotMaxTime: config.hora_fin || '20:00:00',

                hiddenDays: diasNoDisponibles,
                expandRows: true,
                locale: 'es',
                businessHours: businessHours,
                nowIndicator: true,
                navLinks: true,     // permite click en cabeceras para navegar
                dayMaxEvents: true, // muestra “+X” si hay muchos eventos

                // Formatos de hora
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

                // Personalización específica de vista “timeGridWeek”
                views: {
                    timeGridWeek: {
                        dayHeaderFormat: { weekday: 'short', day: 'numeric', month: 'numeric' }
                    }
                },

                // Habilitar selección de rangos
                selectable: true,

                // Validar que la selección esté en el mismo día
                selectAllow: function (selectInfo) {
                    const s = selectInfo.start;
                    const e = selectInfo.end;
                    return (
                        s.getDate() === e.getDate() &&
                        s.getMonth() === e.getMonth() &&
                        s.getFullYear() === e.getFullYear()
                    );
                },

                // Cuando el usuario arrastre o seleccione con el ratón
                select: function (info) {
                    if (ProfesorView.blockModeActive) {
                        // Modo bloqueo de horario
                        ProfesorView.handleBlockModeSelect(info);
                    } else if (ProfesorView.createBookingModeActive) {
                        // Modo creación de reserva
                        ProfesorView.handleCreateModeSelect(info);
                    } else {
                        // Si no está en modo alguno
                        ProfesorView.calendar.unselect();
                        window.mostrarNotificacion(
                            'Información',
                            'Activa el modo Bloqueo o Crear antes de seleccionar',
                            'info'
                        );
                    }
                },

                // Cuando el usuario hace clic sobre un slot vacío
                dateClick: function (info) {
                    // Solo funciona fuera de vista “dayGridMonth”
                    if (info.view.type === 'dayGridMonth') return;

                    if (ProfesorView.createBookingModeActive) {
                        // Calcular hora clicada (solo HH:mm)
                        const clickTime = info.date.toTimeString().substring(0, 5);
                        const matchedSlot = ProfesorView.findSlotForClick(
                            clickTime,
                            ProfesorView.teacherSlots
                        );
                        if (!matchedSlot) {
                            window.mostrarNotificacion(
                                'No disponible',
                                'No hay slot válido en este intervalo',
                                'warning'
                            );
                            return;
                        }
                        ProfesorView.handleCreateModeClick(info, matchedSlot);
                    }
                    // En “modo bloqueo” (drag & drop), se maneja en selectAllow / select
                },

                // Mouse sobre cada evento: dibujamos tooltip, iconos, etc.
                eventDidMount: function (info) {
                    const event = info.event;
                    const element = info.el;
                    const status = event.extendedProps.status;

                    // 2) Estilo especial para eventos bloqueados
                    if (status === 'blocked') {
                        element.classList.add('blocked-event');
                        element.style.backgroundColor = '#dc3545';
                        element.style.borderColor = '#b02a37';

                        // Añadir icono de candado delante de la hora
                        const timeText = element.querySelector('.fc-event-time');
                        if (timeText) {
                            timeText.innerHTML = '<i class="bi bi-lock-fill"></i> ' + timeText.innerHTML;
                        }
                        return;
                    }

                    // 3) Atributo para accesibilidad
                    element.setAttribute(
                        'aria-label',
                        `Clase con ${event.extendedProps.studentName} - ${event.extendedProps.date} a las ${event.extendedProps.time}`
                    );

                    // 4) Color/Clase según estado
                    if (status === 'pending') {
                        element.classList.add('pending-event');
                    } else if (status === 'accepted') {
                        element.classList.add('accepted-event');
                    }

                    // 5) Tooltip personalizado
                    const tooltip = document.createElement('div');
                    tooltip.classList.add('event-tooltip');
                    tooltip.innerHTML = `
                <div class="event-tooltip-header">
                    ${event.title}
                    <span class="event-tooltip-status ${status}">
                        ${{
                            pending: 'Pendiente',
                            accepted: 'Aceptada',
                            cancelled: 'Cancelada',
                            blocked: 'Bloqueada'
                        }[status] || 'N/A'}
                    </span>
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
                    // Mostrar/ocultar tooltip
                    element.addEventListener('mouseenter', () => {
                        document.body.appendChild(tooltip);
                        const rect = element.getBoundingClientRect();
                        tooltip.style.left = rect.left + window.scrollX + 'px';
                        tooltip.style.top = rect.top + window.scrollY - tooltip.offsetHeight - 10 + 'px';

                        // Ajustes si se sale de pantalla
                        const tRect = tooltip.getBoundingClientRect();
                        if (tRect.left < 0) {
                            tooltip.style.left = '10px';
                        }
                        if (tRect.right > window.innerWidth) {
                            tooltip.style.left = (window.innerWidth - tRect.width - 10) + 'px';
                        }
                        if (tRect.top < 0) {
                            tooltip.style.top = rect.bottom + window.scrollY + 10 + 'px';
                        }
                    });
                    element.addEventListener('mouseleave', () => {
                        if (document.body.contains(tooltip)) {
                            document.body.removeChild(tooltip);
                        }
                    });
                },

                // Manejo de clics sobre eventos
                eventClick: function (info) {
                    const event = info.event;
                    const status = event.extendedProps.status;

                    // Rellenar modal con datos
                    document.getElementById('booking_id').value = event.id;
                    document.getElementById('student_name').value = event.extendedProps.studentName;
                    document.getElementById('booking_date').value = event.extendedProps.date;
                    document.getElementById('booking_time').value = `${event.extendedProps.time} - ${event.extendedProps.endTime}`;
                    document.getElementById('booking_vehicle').value = event.extendedProps.vehicle || 'No especificado';

                    const acceptBtn = document.getElementById('acceptBookingBtn');
                    const rejectBtn = document.getElementById('rejectBookingBtn');

                    // Mostrar/ocultar botones según estado
                    if (status === 'pending') {
                        acceptBtn.style.display = 'inline-block';
                        rejectBtn.style.display = 'inline-block';
                        acceptBtn.textContent = 'Aceptar';
                    } else if (status === 'accepted') {
                        acceptBtn.style.display = 'none';
                        rejectBtn.textContent = 'Cancelar';
                    } else if (status === 'blocked') {
                        acceptBtn.style.display = 'none';
                        rejectBtn.textContent = 'Eliminar bloqueo';
                    } else {
                        acceptBtn.style.display = 'none';
                        rejectBtn.style.display = 'none';
                    }

                    // Mostrar el modal (ya instanciado en init)
                    ProfesorView.bookingDetailModal.show();
                },

                // Cuando la ventana se redimensiona: vista “day” en móvil
                windowResize: function (view) {
                    if (window.innerWidth < 768) {
                        ProfesorView.calendar.changeView('timeGridDay');
                    }
                },

                // Marcar días pasados
                dayCellDidMount: function (info) {
                    const hoy = new Date();
                    if (info.date < hoy.setHours(0, 0, 0, 0)) {
                        info.el.classList.add('fc-day-past');
                    }
                },

                // Asignamos los eventos que preparamos arriba
                events: events
            };

            // 9. Instalar el calendario con todas las opciones
            ProfesorView.calendar = new FullCalendar.Calendar(calendarEl, calendarOptions);
            ProfesorView.calendar.render();
        }


        static generateBusinessHours(config) {
            const dias = config.dias || [];
            const horaInicio = config.hora_inicio || '08:00';
            const horaFin = config.hora_fin || '20:00';
            const descansos = config.descansos || [];

            // Convertir nombres de días a números (FullCalendar usa 0=domingo, 1=lunes, etc.)
            const daysOfWeek = dias.map(dia => {
                const diasMap = {
                    'Domingo': 0, 'Lunes': 1, 'Martes': 2, 'Miércoles': 3,
                    'Jueves': 4, 'Viernes': 5, 'Sábado': 6
                };
                return diasMap[dia];
            }).filter(day => day !== undefined);

            if (daysOfWeek.length === 0) {
                return false; // No hay días laborales
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

            return businessHours;
        }

        static generateTeacherSlots(config) {
            const horaInicio = config.hora_inicio || '08:00';
            const horaFin = config.hora_fin || '20:00';
            const duracionClase = parseInt(config.duracion) || 45;
            const descansos = config.descansos || [];

            const timeToMinutes = (time) => {
                const [hours, minutes] = time.split(':').map(Number);
                return hours * 60 + minutes;
            };

            const minutesToTime = (minutes) => {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
            };

            const inicioMinutos = timeToMinutes(horaInicio);
            const finMinutos = timeToMinutes(horaFin);

            // Crear períodos laborales excluyendo descansos
            const periodosLaborales = [];
            const descansosOrdenados = descansos
                .map(d => ({ inicio: timeToMinutes(d.inicio), fin: timeToMinutes(d.fin) }))
                .sort((a, b) => a.inicio - b.inicio);

            let horaActual = inicioMinutos;
            descansosOrdenados.forEach(descanso => {
                if (horaActual < descanso.inicio) {
                    periodosLaborales.push({ inicio: horaActual, fin: descanso.inicio });
                }
                horaActual = Math.max(horaActual, descanso.fin);
            });

            if (horaActual < finMinutos) {
                periodosLaborales.push({ inicio: horaActual, fin: finMinutos });
            }

            // Generar slots
            const slots = [];
            periodosLaborales.forEach(periodo => {
                const duracionPeriodo = periodo.fin - periodo.inicio;
                const numClases = Math.floor(duracionPeriodo / duracionClase);

                for (let i = 0; i < numClases; i++) {
                    const inicioSlot = periodo.inicio + (i * duracionClase);
                    const finSlot = inicioSlot + duracionClase;

                    slots.push({
                        start: minutesToTime(inicioSlot),
                        end: minutesToTime(finSlot),
                        startMinutes: inicioSlot,
                        endMinutes: finSlot
                    });
                }
            });

            return slots;
        }

        static findSlotForClick(clickTime, slots) {
            const timeToMinutes = (time) => {
                const [hours, minutes] = time.split(':').map(Number);
                return hours * 60 + minutes;
            };

            const clickMinutes = timeToMinutes(clickTime);

            return slots.find(slot =>
                clickMinutes >= slot.startMinutes && clickMinutes < slot.endMinutes
            );
        }

        static handleBlockModeClick(info, matchedSlot) {
            const dateStr = info.date.toISOString().substring(0, 10);
            document.getElementById('block_start_date').value = dateStr;
            document.getElementById('block_start_time').value = matchedSlot.start;
            document.getElementById('block_end_date').value = dateStr;
            document.getElementById('block_end_time').value = matchedSlot.end;
            ProfesorView.blockTimeModal.show();
        }

        static handleCreateModeClick(info, matchedSlot) {
            const dateStr = info.date.toISOString().substring(0, 10);
            document.getElementById('new_booking_date').value = dateStr;
            document.getElementById('new_booking_start_time').value = matchedSlot.start;
            document.getElementById('new_booking_end_time').value = matchedSlot.end;
            ProfesorView.createBookingModal.show();
        }

        static handleBlockModeSelect(info) {
            document.getElementById('block_start_date').value = info.startStr.substring(0, 10);
            document.getElementById('block_start_time').value = info.startStr.substring(11, 16);
            document.getElementById('block_end_date').value = info.endStr.substring(0, 10);
            document.getElementById('block_end_time').value = info.endStr.substring(11, 16);
            ProfesorView.calendar.unselect();
            ProfesorView.blockTimeModal.show();
        }

        static handleCreateModeSelect(info) {
            const hasConflict = ProfesorView.calendar.getEvents().some(event => {
                if (event.extendedProps.status === 'cancelled') return false;
                return (info.start < event.end && info.end > event.start);
            });

            if (hasConflict) {
                window.mostrarNotificacion('No permitido', 'Ya hay una clase programada en este horario', 'warning');
                ProfesorView.calendar.unselect();
                return;
            }

            const config = ProfesorView.profesorData.config;
            const descansos = config.descansos || [];
            const startTime = info.start.toTimeString().substring(0, 5);
            const endTime = info.end.toTimeString().substring(0, 5);

            const isInBreak = descansos.some(descanso => {
                return (startTime >= descanso.inicio && startTime < descanso.fin) ||
                    (endTime > descanso.inicio && endTime <= descanso.fin) ||
                    (startTime <= descanso.inicio && endTime >= descanso.fin);
            });

            if (isInBreak) {
                window.mostrarNotificacion('No permitido', 'No puedes crear reservas durante los descansos', 'warning');
                ProfesorView.calendar.unselect();
                return;
            }

            document.getElementById('new_booking_date').value = info.startStr.substring(0, 10);
            document.getElementById('new_booking_start_time').value = info.startStr.substring(11, 16);
            document.getElementById('new_booking_end_time').value = info.endStr.substring(11, 16);
            ProfesorView.calendar.unselect();
            ProfesorView.createBookingModal.show();
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
                case 'blocked': return 'Bloqueada';
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
                window.mostrarNotificacion('', 'Reserva aceptada correctamente', 'success');

            } catch (error) {
                console.error('Error al aceptar la reserva:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        static async cancelBooking(bookingId) {
            if (!bookingId) return;

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/bookings/cancel/${bookingId}`, {
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
                window.mostrarNotificacion('', 'Reserva rechazada correctamente', 'success');

            } catch (error) {
                console.error('Error al rechazar la reserva:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        static async loadBookings() {
            try {
                const response = await fetch(`${ProfesorView.apiUrl}/teachers/${ProfesorView.profesorData.id}/calendar`, {
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
                window.mostrarNotificacion('', 'Error al cargar reservas', 'error');
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

            // Validar tipo y tamaño en el cliente
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                window.mostrarNotificacion('No permitido', 'Tipo de archivo no permitido. Use JPG, PNG o GIF', 'error');
                return;
            }

            if (file.size > maxSize) {
                window.mostrarNotificacion('No permitido', 'El archivo es demasiado grande (máx. 2MB)', 'error');
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
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error al subir la imagen');
                }

                const data = await response.json();

                if (data.success) {
                    // Actualizar la imagen con la nueva URL
                    avatarElement.src = data.url;
                    window.mostrarNotificacion('', 'Imagen de perfil actualizada', 'success');
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

        static async handleTeacherConfigSubmit(form) {
            const formData = new FormData(form);
            const dias = formData.getAll('dias[]');
            const hora_inicio = formData.get('hora_inicio');
            const hora_fin = formData.get('hora_fin');
            const duracion = formData.get('duracion');

            // Procesar descansos
            const descansos = [];
            const descansosData = {};

            // Agrupar los datos de descansos
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('descansos[')) {
                    const matches = key.match(/descansos\[(\d+)\]\[(\w+)\]/);
                    if (matches) {
                        const id = matches[1];
                        const field = matches[2];

                        if (!descansosData[id]) {
                            descansosData[id] = {};
                        }
                        descansosData[id][field] = value;
                    }
                }
            }

            // Convertir a array y filtrar descansos válidos
            Object.values(descansosData).forEach(descanso => {
                if (descanso.inicio && descanso.fin) {
                    descansos.push({
                        inicio: descanso.inicio,
                        fin: descanso.fin
                    });
                }
            });

            // Validar campos obligatorios
            if (!dias.length || !hora_inicio || !hora_fin || !duracion) {
                window.mostrarNotificacion('Error', 'Todos los campos son obligatorios', 'error');
                return;
            }

            const payload = {
                dias,
                hora_inicio,
                hora_fin,
                duracion,
                descansos
            };

            // Mostrar indicador de carga
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';
            submitBtn.disabled = true;

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/teachers/${ProfesorView.profesorData.id}/config`, {
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

                // Mostrar notificación de éxito
                window.mostrarNotificacion('', 'Configuración guardada correctamente', 'success');

                // Actualizar calendario con nueva configuración
                if (ProfesorView.calendar) {
                    ProfesorView.calendar.setOption('slotMinTime', hora_inicio);
                    ProfesorView.calendar.setOption('slotMaxTime', hora_fin);
                    ProfesorView.calendar.setOption('snapDuration', `00:${duracion}:00`);

                    // Actualizar días no disponibles
                    const diasNoDisponibles = [0, 1, 2, 3, 4, 5, 6].filter(dia => {
                        const diaNombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][dia];
                        return !dias.includes(diaNombre);
                    });
                    ProfesorView.calendar.setOption('hiddenDays', diasNoDisponibles);

                    // REGENERAR BUSINESS HOURS CON NUEVOS DESCANSOS
                    const newBusinessHours = ProfesorView.generateBusinessHours(payload);
                    ProfesorView.calendar.setOption('businessHours', newBusinessHours);

                    ProfesorView.calendar.render();

                    ProfesorView.teacherSlots = ProfesorView.generateTeacherSlots(payload);
                }

            } catch (error) {
                console.error('Error al guardar la configuración:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            } finally {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
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
                document.getElementById('modal-student-call-btn').href = `tel:${studentData.phone || ''}`;
                document.getElementById('modal-student-whatsapp-btn').href = `https://wa.me/${studentData.phone || ''}`;
                document.getElementById('modal-student-saldo').textContent = studentData.balance ? `${studentData.balance}` : 'No disponible';

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
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        /**
         * Cargar estadísticas de clases completadas (siguiendo patrón existente)
         */
        static async loadCompletedClassesStats() {
            const teacherId = ProfesorView.profesorData.id;

            try {
                const response = await fetch(`${ProfesorView.apiUrl}/teachers/${teacherId}?include_completed_stats=true`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al cargar estadísticas');
                }

                const data = await response.json();

                if (data.success && data.data.completed_stats) {
                    ProfesorView.updateCompletedStatsUI(data.data.completed_stats);
                }
            } catch (error) {
                console.error('Error loading completed stats:', error);
            }
        }

        /**
         * Actualizar UI de estadísticas (similar a showBookingDetails)
         */
        static updateCompletedStatsUI(stats) {
            document.getElementById('completedToday').textContent = stats.completed_today || 0;
            document.getElementById('completedWeek').textContent = stats.completed_week || 0;
            document.getElementById('completedMonth').textContent = stats.completed_month || 0;
        }

        /**
         * Abrir modal de historial (siguiendo patrón de studentDetailModal)
         */
        static openCompletedClassesModal() {
            ProfesorView.completedClassesModal.show();

            // Establecer fechas por defecto (último mes)
            const today = new Date();
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());

            document.getElementById('filterDateFrom').value = lastMonth.toISOString().split('T')[0];
            document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];

            // Cargar con filtros por defecto
            ProfesorView.loadCompletedHistory(1);
        }

        /**
         * Cargar historial paginado - mejorado con indicador de carga
         */
        static async loadCompletedHistory(page = 1, dateFrom = null, dateTo = null) {
            const teacherId = ProfesorView.profesorData.id;
            ProfesorView.currentCompletedPage = page;

            // Obtener fechas de filtros si no se pasaron como parámetros
            if (dateFrom === null) {
                dateFrom = document.getElementById('filterDateFrom')?.value || null;
            }
            if (dateTo === null) {
                dateTo = document.getElementById('filterDateTo')?.value || null;
            }

            let url = `${ProfesorView.apiUrl}/teachers/${teacherId}/completed-history?page=${page}&per_page=10`;
            if (dateFrom) url += `&date_from=${dateFrom}`;
            if (dateTo) url += `&date_to=${dateTo}`;

            // Mostrar indicador de carga
            const container = document.getElementById('completedHistoryList');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando historial...</p>
                </div>
            `;

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    // Para los contadores, necesitamos también obtener TODAS las clases del periodo
                    // no solo las de la página actual
                    if (page === 1) {
                        // Solo en la primera página, obtener estadísticas completas
                        await ProfesorView.loadPeriodCompleteStats(teacherId, dateFrom, dateTo, data);
                    } else {
                        // En otras páginas, solo renderizar la lista
                        ProfesorView.renderCompletedHistory(data.data);
                    }
                } else {
                    throw new Error(data.message || 'Error en la respuesta del servidor');
                }
            } catch (error) {
                console.error('Error loading history:', error);
                ProfesorView.showErrorInHistory(`Error al cargar el historial: ${error.message}`);
            }
        }

        /**
         * Renderizar historial (siguiendo patrón de dashboard-admin-view.js) - MEJORADO
         */
        static renderCompletedHistory(data) {
            const container = document.getElementById('completedHistoryList');

            // NUEVO: Actualizar contadores del periodo
            ProfesorView.updatePeriodStats(data);

            if (!data.classes || data.classes.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>No se encontraron clases completadas en este periodo</p>
                    </div>
                `;
                ProfesorView.renderHistoryPagination(data.pagination);
                return;
            }

            let html = '<div class="list-group list-group-flush">';

            data.classes.forEach(clase => {
                const fecha = new Date(clase.date).toLocaleDateString('es-ES');

                // Calcular duración de la clase
                const startTime = clase.time || clase.start_time;
                const endTime = clase.end_time;
                const duration = ProfesorView.calculateClassDuration(startTime, endTime);

                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <i class="fas fa-user text-primary me-1"></i>
                                    ${clase.student_name}
                                </h6>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    ${fecha} • ${startTime} - ${endTime}
                                    <span class="badge bg-light text-dark ms-2">
                                        <i class="fas fa-clock me-1"></i>${duration}
                                    </span>
                                </p>
                                ${clase.vehicle ? `<small class="text-muted">
                                    <i class="fas fa-car me-1"></i>
                                    ${clase.vehicle}
                                </small>` : ''}
                            </div>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Completada
                            </span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            ProfesorView.renderHistoryPagination(data.pagination);
        }

        /**
         * Renderizar paginación (SIN onclick - usando data attributes)
         */
        static renderHistoryPagination(pagination) {
            const container = document.getElementById('completedHistoryPagination');

            if (!pagination || pagination.pages <= 1) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            let html = '<nav><ul class="pagination pagination-sm justify-content-center">';

            // Botón anterior
            const prevDisabled = pagination.current_page <= 1 ? 'disabled' : '';
            const prevPage = pagination.current_page - 1;
            html += `<li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="${prevPage}" ${prevDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    <i class="fas fa-chevron-left"></i>
                    <span class="sr-only">Anterior</span>
                </a>
            </li>`;

            // Páginas numéricas (con lógica de truncamiento para muchas páginas)
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.pages, pagination.current_page + 2);

            // Primera página si no está en el rango visible
            if (startPage > 1) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" data-page="1">1</a>
                </li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>`;
                }
            }

            // Páginas en el rango visible
            for (let i = startPage; i <= endPage; i++) {
                const active = i === pagination.current_page ? 'active' : '';
                html += `<li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}" ${active ? 'aria-current="page"' : ''}>
                        ${i}
                    </a>
                </li>`;
            }

            // Última página si no está en el rango visible
            if (endPage < pagination.pages) {
                if (endPage < pagination.pages - 1) {
                    html += `<li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>`;
                }
                html += `<li class="page-item">
                    <a class="page-link" href="#" data-page="${pagination.pages}">${pagination.pages}</a>
                </li>`;
            }

            // Botón siguiente
            const nextDisabled = pagination.current_page >= pagination.pages ? 'disabled' : '';
            const nextPage = pagination.current_page + 1;
            html += `<li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="${nextPage}" ${nextDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>
                    <i class="fas fa-chevron-right"></i>
                    <span class="sr-only">Siguiente</span>
                </a>
            </li>`;

            html += '</ul></nav>';
            container.innerHTML = html;
        }

        /**
         * Filtrar clases por fecha - mejorado
         */
        static filterCompletedClasses() {
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            // Validar fechas si se proporcionan ambas
            if (dateFrom && dateTo && dateFrom > dateTo) {
                window.mostrarNotificacion(
                    'Fechas inválidas',
                    'La fecha "Desde" no puede ser posterior a la fecha "Hasta"',
                    'warning'
                );
                return;
            }

            // Resetear a página 1 cuando se aplican filtros
            ProfesorView.loadCompletedHistory(1, dateFrom, dateTo);
        }

        /**
         * Mostrar error en historial
         */
        static showErrorInHistory(message) {
            const container = document.getElementById('completedHistoryList');
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    ${message}
                </div>
            `;
        }

        /**
         * NUEVO: Actualizar estadísticas del periodo
         */
        static updatePeriodStats(data) {
            const totalClasses = data.pagination?.total || 0;

                        // Determinar rango de fechas
            const dateFrom = document.getElementById('filterDateFrom')?.value;
            const dateTo = document.getElementById('filterDateTo')?.value;
            let dateRange = 'Todas las fechas';

            if (dateFrom && dateTo) {
                const fromDate = new Date(dateFrom).toLocaleDateString('es-ES');
                const toDate = new Date(dateTo).toLocaleDateString('es-ES');
                dateRange = `${fromDate} - ${toDate}`;
            } else if (dateFrom) {
                const fromDate = new Date(dateFrom).toLocaleDateString('es-ES');
                dateRange = `Desde ${fromDate}`;
            } else if (dateTo) {
                const toDate = new Date(dateTo).toLocaleDateString('es-ES');
                dateRange = `Hasta ${toDate}`;
            }

            // Actualizar UI
            document.getElementById('periodClassesCount').textContent = totalClasses;
            document.getElementById('periodDateRange').textContent = dateRange;
        }

        /**
         * NUEVO: Calcular duración de clase en formato legible
         */
        static calculateClassDuration(startTime, endTime) {
            const minutes = ProfesorView.getClassDurationInMinutes(startTime, endTime);

            if (minutes >= 60) {
                const hours = Math.floor(minutes / 60);
                const remainingMinutes = minutes % 60;
                return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
            } else {
                return `${minutes}m`;
            }
        }

        /**
         * NUEVO: Calcular duración en minutos
         */
        static getClassDurationInMinutes(startTime, endTime) {
            if (!startTime || !endTime) return 0;

            const start = ProfesorView.timeToMinutes(startTime);
            const end = ProfesorView.timeToMinutes(endTime);

            return Math.max(0, end - start);
        }

        /**
         * NUEVO: Convertir hora a minutos
         */
        static timeToMinutes(time) {
            const [hours, minutes] = time.split(':').map(Number);
            return (hours * 60) + (minutes || 0);
        }

        /**
         * NUEVO: Limpiar filtros de fechas
         */
        static clearCompletedFilters() {
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';

            // Recargar historial sin filtros
            ProfesorView.loadCompletedHistory(1);

            window.mostrarNotificacion('', 'Filtros eliminados', 'success');
        }

        /**
         * NUEVO: Cargar estadísticas completas del periodo (todas las páginas)
         */
        static async loadPeriodCompleteStats(teacherId, dateFrom, dateTo, currentPageData) {
            try {
                // Obtener TODAS las clases del periodo para estadísticas
                let statsUrl = `${ProfesorView.apiUrl}/teachers/${teacherId}/completed-history?page=1&per_page=999`;
                if (dateFrom) statsUrl += `&date_from=${dateFrom}`;
                if (dateTo) statsUrl += `&date_to=${dateTo}`;

                const statsResponse = await fetch(statsUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${ProfesorView.jwtToken}`
                    }
                });

                if (statsResponse.ok) {
                    const statsData = await statsResponse.json();
                    if (statsData.success) {
                        // Combinar datos: estadísticas completas + datos paginados actuales
                        const combinedData = {
                            ...currentPageData.data,
                            all_classes: statsData.data.classes // Todas las clases para estadísticas
                        };
                        ProfesorView.renderCompletedHistoryWithStats(combinedData);
                        return;
                    }
                }
            } catch (error) {
                console.warn('Error loading complete stats, using current page data:', error);
            }

            // Fallback: usar solo datos de la página actual
            ProfesorView.renderCompletedHistory(currentPageData.data);
        }

        /**
         * NUEVO: Renderizar historial con estadísticas completas
         */
        static renderCompletedHistoryWithStats(data) {
            // Calcular estadísticas usando TODAS las clases
            const allClasses = data.all_classes || data.classes || [];
            const totalClasses = allClasses.length;

            const dateFrom = document.getElementById('filterDateFrom')?.value;
            const dateTo = document.getElementById('filterDateTo')?.value;
            let dateRange = 'Todas las fechas';

            if (dateFrom && dateTo) {
                const fromDate = new Date(dateFrom).toLocaleDateString('es-ES');
                const toDate = new Date(dateTo).toLocaleDateString('es-ES');
                dateRange = `${fromDate} - ${toDate}`;
            } else if (dateFrom) {
                const fromDate = new Date(dateFrom).toLocaleDateString('es-ES');
                dateRange = `Desde ${fromDate}`;
            } else if (dateTo) {
                const toDate = new Date(dateTo).toLocaleDateString('es-ES');
                dateRange = `Hasta ${toDate}`;
            }

            // Actualizar contadores
            document.getElementById('periodClassesCount').textContent = totalClasses;
            document.getElementById('periodDateRange').textContent = dateRange;

            // Renderizar lista de la página actual
            const container = document.getElementById('completedHistoryList');

            if (!data.classes || data.classes.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>No se encontraron clases completadas en este periodo</p>
                    </div>
                `;
                ProfesorView.renderHistoryPagination(data.pagination);
                return;
            }

            let html = '<div class="list-group list-group-flush">';

            data.classes.forEach(clase => {
                const fecha = new Date(clase.date).toLocaleDateString('es-ES');
                const startTime = clase.time || clase.start_time;
                const endTime = clase.end_time;
                const duration = ProfesorView.calculateClassDuration(startTime, endTime);

                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <i class="fas fa-user text-primary me-1"></i>
                                    ${clase.student_name}
                                </h6>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    ${fecha} • ${startTime} - ${endTime}
                                    <span class="badge bg-light text-dark ms-2">
                                        <i class="fas fa-clock me-1"></i>${duration}
                                    </span>
                                </p>
                                ${clase.vehicle ? `<small class="text-muted">
                                    <i class="fas fa-car me-1"></i>
                                    ${clase.vehicle}
                                </small>` : ''}
                            </div>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Completada
                            </span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            ProfesorView.renderHistoryPagination(data.pagination);
        }
    }

    // Inicializar la vista
    ProfesorView.init();
});