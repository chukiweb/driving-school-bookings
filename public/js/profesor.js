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
        static blockModeActive = false;
        static createBookingModeActive = false;
        static descansosCounter = 0;
        static currentMode = null;
        static originalButtonStates = new Map();

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
                ProfesorView.setMode(null);
            });

            document.querySelector('#createBookingModal .btn-secondary')?.addEventListener('click', function () {
                ProfesorView.setMode(null);
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
                console.log('Contador inicial de descansos:', ProfesorView.descansosCounter);
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
            // Limpiar estados
            ProfesorView.currentMode = null;
            ProfesorView.blockModeActive = false;
            ProfesorView.createBookingModeActive = false;

            // Limpiar clases del calendario
            const calendar = document.getElementById('calendar');
            calendar.classList.remove('block-mode', 'create-mode');

            // Restaurar botones a su estado original
            ProfesorView.restoreButtonStates();

            // Deseleccionar cualquier selección activa
            if (ProfesorView.calendar) {
                ProfesorView.calendar.unselect();
            }
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
                        'Seleccione la franja horaria que desea bloquear arrastrando con el mouse',
                        'info'
                    );
                    break;
                case 'create':
                    window.mostrarNotificacion(
                        'Modo Reserva Activado',
                        'Seleccione la franja horaria para la nueva clase arrastrando con el mouse',
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

        static handleTimeBlockSelection(info) {
            // Llenar modal con información de la selección
            document.getElementById('block_start_date').value = info.startStr.substring(0, 10);
            document.getElementById('block_start_time').value = info.startStr.substring(11, 16);
            document.getElementById('block_end_date').value = info.endStr.substring(0, 10);
            document.getElementById('block_end_time').value = info.endStr.substring(11, 16);

            // Limpiar selección visual pero mantener el modo
            ProfesorView.calendar.unselect();

            // Mostrar modal de bloqueo
            ProfesorView.blockTimeModal.show();
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

                // **DESACTIVAR MODO DESPUÉS DE COMPLETAR LA ACCIÓN**
                ProfesorView.setMode(null);

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
            const vehicle = document.getElementById('license_type').value === 'B' ?
                ProfesorView.profesorData.vehicle.b.name :
                ProfesorView.profesorData.vehicle.a.name;

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

                // **DESACTIVAR MODO DESPUÉS DE COMPLETAR LA ACCIÓN**
                ProfesorView.setMode(null);

                // Actualizar la lista de reservas
                await ProfesorView.loadBookings();

            } catch (error) {
                console.error('Error al crear la reserva:', error);
                window.mostrarNotificacion('Error', `${error.message}`, 'error');
            }
        }

        static async handleBookingSelection(info) {
            // Mantener el modo activo hasta que se complete la acción

            // Verificar si la franja está bloqueada
            const events = ProfesorView.calendar.getEvents();
            for (const event of events) {
                if (event.extendedProps.status === 'blocked' &&
                    ((info.start >= event.start && info.start < event.end) ||
                        (info.end > event.start && info.end <= event.end))) {
                    window.mostrarNotificacion('No permitido', 'La franja horaria seleccionada está bloqueada', 'error');
                    ProfesorView.calendar.unselect();
                    return;
                }
            }

            // Llenar modal con información de la selección
            document.getElementById('new_booking_date').value = info.startStr.substring(0, 10);
            document.getElementById('new_booking_start_time').value = info.startStr.substring(11, 16);
            document.getElementById('new_booking_end_time').value = info.endStr.substring(11, 16);

            // Limpiar selección visual pero mantener el modo
            ProfesorView.calendar.unselect();

            // Mostrar modal de creación de reserva
            ProfesorView.createBookingModal.show();
        }

        /**
         * Guarda una reserva para un alumno
         */
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

        static calculateOptimalSlots(config) {
            const horaInicio = config.hora_inicio || '08:00';
            const horaFin = config.hora_fin || '20:00';
            const duracionClase = config.duracion || 45;
            const descansos = config.descansos || [];

            // Convertir horas a minutos para cálculos
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

            // Crear array de períodos laborales (excluyendo descansos)
            const periodosLaborales = [];

            // Ordenar descansos por hora de inicio
            const descansosOrdenados = descansos
                .map(d => ({
                    inicio: timeToMinutes(d.inicio),
                    fin: timeToMinutes(d.fin)
                }))
                .sort((a, b) => a.inicio - b.inicio);

            let horaActual = inicioMinutos;

            // Crear períodos entre descansos
            descansosOrdenados.forEach(descanso => {
                if (horaActual < descanso.inicio) {
                    periodosLaborales.push({
                        inicio: horaActual,
                        fin: descanso.inicio
                    });
                }
                horaActual = descanso.fin;
            });

            // Añadir período final si queda tiempo
            if (horaActual < finMinutos) {
                periodosLaborales.push({
                    inicio: horaActual,
                    fin: finMinutos
                });
            }

            // Generar slots optimizados para cada período
            const slotsOptimizados = [];

            periodosLaborales.forEach(periodo => {
                const duracionPeriodo = periodo.fin - periodo.inicio;
                const numClases = Math.floor(duracionPeriodo / duracionClase);

                for (let i = 0; i < numClases; i++) {
                    const slotInicio = periodo.inicio + (i * duracionClase);
                    const slotFin = slotInicio + duracionClase;

                    slotsOptimizados.push({
                        start: minutesToTime(slotInicio),
                        end: minutesToTime(slotFin),
                        periodo: `periodo-${periodosLaborales.indexOf(periodo)}`
                    });
                }
            });

            console.log('Slots optimizados calculados:', slotsOptimizados);
            return slotsOptimizados;
        }

        static initializeCalendar() {
            const calendarElement = document.getElementById('calendar');
            if (!calendarElement) {
                console.error("No se encontró el elemento del calendario");
                return;
            }

            // Obtener configuración del profesor con valores por defecto
            const config = (ProfesorView.profesorData && ProfesorView.profesorData.config) ?
                ProfesorView.profesorData.config :
                { dias: [], hora_inicio: '08:00:00', hora_fin: '20:00:00', duracion: 45, descansos: [] };

            console.log('Configuración del profesor:', config);

            // Generar businessHours considerando descansos
            const businessHours = ProfesorView.generateBusinessHours(config);
            console.log('BusinessHours generadas:', businessHours);

            // Slots cada 15 minutos para granularidad
            const duracionSlot = `00:15:00`;

            // Configurar días no disponibles
            const diasDisponibles = config.dias || [];
            const diasNoDisponibles = [0, 1, 2, 3, 4, 5, 6].filter(dia => {
                const diaNombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][dia];
                return !diasDisponibles.includes(diaNombre);
            });

            // Obtener eventos activos
            const bookings = ProfesorView.bookings || [];
            const events = bookings.map(booking => {
                const eventClass = booking.status === 'pending' ? 'pending-event' :
                    booking.status === 'accepted' ? 'accepted-event' :
                        booking.status === 'cancelled' ? 'cancelled-event' :
                            booking.status === 'blocked' ? 'blocked-event' :
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

            // Opciones del calendario simplificadas
            const calendarOptions = {
                initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                // Slots granulares cada 15 minutos
                slotDuration: duracionSlot,

                allDaySlot: false,
                scrollTime: '08:00:00',
                slotMinTime: config.hora_inicio || '08:00:00',
                slotMaxTime: config.hora_fin || '20:00:00',
                hiddenDays: diasNoDisponibles,

                // Business Hours para mostrar horarios laborales
                businessHours: businessHours,

                expandRows: true,
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

                // **SELECCIÓN LIBRE - SIN RESTRICCIONES**
                selectable: true,
                selectAllow: function (selectInfo) {
                    // Solo validar que sea el mismo día
                    var startDay = selectInfo.start.getDate();
                    var endDay = selectInfo.end.getDate();
                    var startMonth = selectInfo.start.getMonth();
                    var endMonth = selectInfo.end.getMonth();
                    var startYear = selectInfo.start.getFullYear();
                    var endYear = selectInfo.end.getFullYear();

                    return (startDay === endDay) && (startMonth === endMonth) && (startYear === endYear);
                },

                select: function (info) {
                    // Solo procesar selecciones si hay un modo activo
                    if (!ProfesorView.blockModeActive && !ProfesorView.createBookingModeActive) {
                        // Deseleccionar automáticamente si no hay modo activo
                        ProfesorView.calendar.unselect();
                        window.mostrarNotificacion('Información', 'Primero debe activar el modo de bloqueo o creación de reservas', 'info');
                        return;
                    }

                    // Verificar que no haya conflictos con eventos existentes
                    const hasConflict = ProfesorView.calendar.getEvents().some(event => {
                        // No considerar conflicto si el evento está cancelado
                        if (event.extendedProps.status === 'cancelled') {
                            return false;
                        }
                        return (info.start < event.end && info.end > event.start);
                    });

                    if (hasConflict) {
                        window.mostrarNotificacion('No permitido', 'Ya hay una clase o descanso programado en este horario', 'warning');
                        ProfesorView.calendar.unselect();
                        return;
                    }

                    // Verificar que esté en horario laboral
                    if (!ProfesorView.isWithinBusinessHours(info, config)) {
                        window.mostrarNotificacion('No permitido', 'Solo puedes seleccionar horarios laborales', 'warning');
                        ProfesorView.calendar.unselect();
                        return;
                    }

                    // Procesar según el modo activo
                    if (ProfesorView.blockModeActive) {
                        ProfesorView.handleTimeBlockSelection(info);
                    } else if (ProfesorView.createBookingModeActive) {
                        ProfesorView.handleBookingSelection(info);
                    }
                },

                slotLabelInterval: '01:00:00',

                dayCellDidMount: function (info) {
                    if (info.isPast) {
                        info.el.classList.add('fc-day-past');
                    }
                },

                selectMirror: true,
                unselectAuto: true,

                // Resto de opciones sin cambios...
                eventDidMount: function (info) {
                    const event = info.event;
                    const element = info.el;
                    const status = event.extendedProps.status;

                    // Don't show tooltip for events without valid status
                    if (!status || !['pending', 'accepted', 'cancelled', 'blocked'].includes(status)) {
                        return;
                    }

                    if (status === 'cancelled') {
                        element.style.display = 'none';
                        return;
                    }

                    // Estilo especial para eventos de bloqueo
                    if (status === 'blocked') {
                        element.classList.add('blocked-event');
                        element.style.backgroundColor = '#dc3545';
                        element.style.borderColor = '#b02a37';

                        const timeText = element.querySelector('.fc-event-time');
                        if (timeText) {
                            timeText.innerHTML = '<i class="bi bi-lock-fill"></i> ' + timeText.innerHTML;
                        }
                        return;
                    }

                    // Añadir atributos para accesibilidad
                    element.setAttribute('aria-label', `Clase con ${event.extendedProps.studentName} - ${event.extendedProps.date} a las ${event.extendedProps.time}`);

                    // Aplicar clases específicas para estados
                    if (status === 'pending') {
                        element.classList.add('pending-event');
                    } else if (status === 'accepted') {
                        element.classList.add('accepted-event');
                    } else if (status === 'blocked') {
                        element.classList.add('blocked-event');
                    }

                    // Tooltip simplificado
                    const tooltip = document.createElement('div');
                    tooltip.classList.add('event-tooltip');
                    tooltip.innerHTML = `
                        <div class="event-tooltip-header">
                            ${event.title}
                            <span class="event-tooltip-status ${status}">
                            ${{
                            'pending': 'Pendiente',
                            'accepted': 'Aceptada',
                            'cancelled': 'Cancelada',
                            'blocked': 'Bloqueada'
                        }[status] || 'N/A'
                        }
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
                        </div>`;

                    // Mostrar tooltip al hover
                    element.addEventListener('mouseenter', function () {
                        document.body.appendChild(tooltip);
                        const rect = element.getBoundingClientRect();
                        tooltip.style.left = rect.left + window.scrollX + 'px';
                        tooltip.style.top = rect.top + window.scrollY - tooltip.offsetHeight - 10 + 'px';

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

                eventClick: function (info) {
                    const event = info.event;
                    const bookingId = event.id;
                    const status = event.extendedProps.status;

                    // Llenar modal con información del evento
                    document.getElementById('booking_id').value = bookingId;
                    document.getElementById('student_name').value = event.extendedProps.studentName;
                    document.getElementById('booking_status').value = status === 'pending' ? 'Pendiente' :
                        status === 'accepted' ? 'Aceptada' :
                            status === 'cancelled' ? 'Cancelada' :
                                status === 'blocked' ? 'Bloqueada' : 'Desconocido';
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
                    } else if (status === 'blocked') {
                        acceptBtn.style.display = 'none';
                        rejectBtn.textContent = 'Eliminar bloqueo';
                    } else {
                        acceptBtn.style.display = 'none';
                        rejectBtn.style.display = 'none';
                    }

                    // Mostrar el modal
                    const bookingModal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
                    bookingModal.show();
                },

                windowResize: function (view) {
                    if (window.innerWidth < 768) {
                        ProfesorView.calendar.changeView('timeGridDay');
                    }
                },

                dayCellDidMount: function (info) {
                    var today = new Date();
                    if (info.date < today.setHours(0, 0, 0, 0)) {
                        info.el.classList.add('fc-day-past');
                    }
                },
            };

            // Inicializar el calendario
            ProfesorView.calendar = new FullCalendar.Calendar(calendarElement, calendarOptions);
            ProfesorView.calendar.render();

            // Añadir eventListeners para los filtros
            document.getElementById('showPendingOnly')?.addEventListener('change', ProfesorView.applyFilters);
            document.getElementById('showAcceptedOnly')?.addEventListener('change', ProfesorView.applyFilters);
            document.getElementById('refreshCalendar')?.addEventListener('click', () => ProfesorView.calendar.refetchEvents());
        }

        // static highlightAvailableSlots(slots) {
        //     // Esperar a que el calendario se renderice completamente
        //     setTimeout(() => {
        //         const slotElements = document.querySelectorAll('.fc-timegrid-slot[data-time]');

        //         slotElements.forEach(slotEl => {
        //             const slotTime = slotEl.getAttribute('data-time');
        //             if (!slotTime) return;

        //             // Verificar si este slot está dentro de los períodos optimizados
        //             const isAvailableSlot = slots.some(slot => {
        //                 return slotTime >= slot.start && slotTime < slot.end;
        //             });

        //             if (isAvailableSlot) {
        //                 slotEl.classList.add('optimal-slot');
        //             }
        //         });
        //     }, 500);
        // }

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

        // Método para validar si una selección está en horario laboral
        static isWithinBusinessHours(selectInfo, config) {
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
                    // Recalcular slots optimizados
                    const slotsOptimizados = ProfesorView.calculateOptimalSlots(payload);

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
    }

    // Inicializar la vista
    ProfesorView.init();

    console.log('ProfesorData', ProfesorView.profesorData);
});