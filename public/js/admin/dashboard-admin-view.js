/**
 * Script para la vista de Dashboard de administración
 * Maneja la funcionalidad de los buscadores de profesores y alumnos
 */
jQuery(document).ready(function ($) {

    /**
     * Clase para manejar buscadores
     */
    class SearchManager {
        constructor(options) {
            // Opciones de configuración
            this.options = {
                searchInput: '',
                filterSelect: '',
                itemSelector: '',
                noResultsMsg: '',
                paginationEnabled: false,
                prevPageBtn: '',
                nextPageBtn: '',
                currentPageSpan: '',
                totalPagesSpan: '',
                itemsPerPage: 10,
                countBadge: '',
                ...options
            };

            // Estado
            this.items = [];
            this.filteredItems = [];
            this.currentPage = 1;

            // Inicializar
            this.init();
        }

        init() {
            // Capturar todos los elementos
            this.items = document.querySelectorAll(this.options.itemSelector);
            this.filteredItems = [...this.items];

            // Configurar event listeners
            const searchInput = document.getElementById(this.options.searchInput);
            const filterSelect = document.getElementById(this.options.filterSelect);

            if (searchInput) {
                searchInput.addEventListener('input', () => this.filterItems());
            }

            if (filterSelect) {
                filterSelect.addEventListener('change', () => this.filterItems());
            }

            // Configurar paginación si está habilitada
            if (this.options.paginationEnabled) {
                const prevBtn = document.getElementById(this.options.prevPageBtn);
                const nextBtn = document.getElementById(this.options.nextPageBtn);

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        if (this.currentPage > 1) {
                            this.currentPage--;
                            this.showCurrentPage();
                        }
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        const totalPages = Math.ceil(this.filteredItems.length / this.options.itemsPerPage);
                        if (this.currentPage < totalPages) {
                            this.currentPage++;
                            this.showCurrentPage();
                        }
                    });
                }
            }

            // Mostrar la página inicial
            this.showCurrentPage();
        }

        filterItems() {
            const searchInput = document.getElementById(this.options.searchInput);
            const filterSelect = document.getElementById(this.options.filterSelect);
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const filterValue = filterSelect ? filterSelect.value : '';

            this.filteredItems = [...this.items].filter(item => {
                // Filtrar por término de búsqueda
                const matchesSearch = searchTerm === '' ||
                    item.dataset.name?.toLowerCase().includes(searchTerm) ||
                    item.dataset.email?.toLowerCase().includes(searchTerm) ||
                    item.dataset.phone?.toLowerCase().includes(searchTerm);

                // Aplicar filtros adicionales según el tipo
                let matchesFilter = true;
                if (filterValue === 'with_students') {
                    matchesFilter = parseInt(item.dataset.students || '0') > 0;
                } else if (filterValue === 'without_students') {
                    matchesFilter = parseInt(item.dataset.students || '0') === 0;
                } else if (filterValue === 'with_bookings') {
                    matchesFilter = parseInt(item.dataset.bookings || '0') > 0;
                } else if (filterValue === 'license_a') {
                    matchesFilter = (item.dataset.license || '').toLowerCase() === 'a';
                } else if (filterValue === 'license_b') {
                    matchesFilter = (item.dataset.license || '').toLowerCase() === 'b';
                }

                return matchesSearch && matchesFilter;
            });

            // Reset a la primera página
            this.currentPage = 1;

            // Actualizar contador
            if (this.options.countBadge) {
                const countBadge = document.getElementById(this.options.countBadge);
                if (countBadge) {
                    countBadge.textContent = this.filteredItems.length;
                }
            }

            // Mostrar mensaje si no hay resultados
            const noResultsMsg = document.getElementById(this.options.noResultsMsg);
            if (noResultsMsg) {
                noResultsMsg.style.display = this.filteredItems.length === 0 ? 'flex' : 'none';
            }

            // Actualizar visualización
            this.showCurrentPage();
        }

        showCurrentPage() {
            // Ocultar todos los items primero
            this.items.forEach(item => item.style.display = 'none');

            // Calcular el rango de items para la página actual
            const start = (this.currentPage - 1) * this.options.itemsPerPage;
            const end = Math.min(start + this.options.itemsPerPage, this.filteredItems.length);

            // Mostrar solo los items de la página actual
            for (let i = start; i < end; i++) {
                this.filteredItems[i].style.display = 'flex';
            }

            // Actualizar controles de paginación si están habilitados
            if (this.options.paginationEnabled) {
                const totalPages = Math.ceil(this.filteredItems.length / this.options.itemsPerPage) || 1;

                // Actualizar texto de página actual/total
                const currentPageSpan = document.getElementById(this.options.currentPageSpan);
                const totalPagesSpan = document.getElementById(this.options.totalPagesSpan);

                if (currentPageSpan) currentPageSpan.textContent = this.currentPage;
                if (totalPagesSpan) totalPagesSpan.textContent = totalPages;

                // Habilitar/deshabilitar botones según corresponda
                const prevBtn = document.getElementById(this.options.prevPageBtn);
                const nextBtn = document.getElementById(this.options.nextPageBtn);

                if (prevBtn) prevBtn.disabled = this.currentPage <= 1;
                if (nextBtn) nextBtn.disabled = this.currentPage >= totalPages;
            }
        }
    }

    /**
     * Clase para manejar modales de detalles
     */
    class DetailsModalManager {
        constructor() {
            this.init();
        }

        init() {
            // Event listeners para abrir modales
            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const role = e.currentTarget.dataset.role;
                    const id = e.currentTarget.dataset.id;

                    if (role === 'teacher') {
                        this.openTeacherModal(id);
                    } else if (role === 'student') {
                        this.openStudentModal(id);
                    }
                });
            });

            // Event listeners para cerrar modales
            document.querySelectorAll('.dsb-modal-close, .close-modal-btn').forEach(el => {
                el.addEventListener('click', (e) => {
                    const modal = e.target.closest('.dsb-modal');
                    if (modal) {
                        this.closeModal(modal);
                    }
                });
            });

            // Cerrar modal al hacer clic fuera
            document.querySelectorAll('.dsb-modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.closeModal(modal);
                    }
                });
            });

            // Configurar botones de edición - Verificar que existen primero
            const editTeacherBtn = document.getElementById('editTeacherBtn');
            if (editTeacherBtn) {
                editTeacherBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const teacherId = e.currentTarget.dataset.id;
                    if (teacherId) {
                        window.location.href = `admin.php?page=dsb-teachers`;
                    }
                });
            }

            const editStudentBtn = document.getElementById('editStudentBtn');
            if (editStudentBtn) {
                editStudentBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const studentId = e.currentTarget.dataset.id;
                    if (studentId) {
                        window.location.href = `admin.php?page=dsb-students`;
                    }
                });
            }

            // Configurar tabs de estadísticas
            document.querySelectorAll('.dsb-stats-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const period = e.currentTarget.dataset.period;
                    const teacherBtn = document.getElementById('editTeacherBtn');

                    if (teacherBtn) {
                        const teacherId = teacherBtn.dataset.id;

                        // Actualizar la clase active
                        document.querySelectorAll('.dsb-stats-tab').forEach(t => {
                            t.classList.remove('active');
                        });
                        e.currentTarget.classList.add('active');

                        // Cargar las estadísticas del período seleccionado
                        this.loadTeacherStats(teacherId, period);
                    }
                });
            });
        }

        openTeacherModal(teacherId) {
            const modal = document.getElementById('teacherDetailModal');

            // Mostrar estado de carga
            modal.querySelector('.dsb-loading').style.display = 'flex';
            modal.querySelector('.dsb-profile-details').style.display = 'none';
            modal.querySelector('.dsb-error-message').style.display = 'none';

            // Establecer ID para el botón de edición
            document.getElementById('editTeacherBtn').dataset.id = teacherId;

            // Mostrar modal
            modal.style.display = 'flex';

            // Cargar datos del profesor
            this.loadTeacherData(teacherId);
            this.loadTeacherStats(teacherId);
        }

        openStudentModal(studentId) {
            const modal = document.getElementById('studentDetailModal');

            // Mostrar estado de carga
            modal.querySelector('.dsb-loading').style.display = 'flex';
            modal.querySelector('.dsb-profile-details').style.display = 'none';
            modal.querySelector('.dsb-error-message').style.display = 'none';

            // Establecer ID para el botón de edición
            document.getElementById('editStudentBtn').dataset.id = studentId;

            // Mostrar modal
            modal.style.display = 'flex';

            // Cargar datos del alumno
            this.loadStudentData(studentId);
        }

        closeModal(modal) {
            modal.style.display = 'none';
        }

        async loadTeacherData(teacherId) {
            try {
                const response = await fetch(`/wp-json/driving-school/v1/teachers/${teacherId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al cargar datos del profesor');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar datos del profesor');
                }

                this.displayTeacherData(data.data);

            } catch (error) {
                console.error('Error:', error);
                const modal = document.getElementById('teacherDetailModal');
                modal.querySelector('.dsb-loading').style.display = 'none';
                modal.querySelector('.dsb-error-message').style.display = 'flex';
            }
        }

        async loadStudentData(studentId) {
            try {
                const response = await fetch(`/wp-json/driving-school/v1/students/${studentId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al cargar datos del alumno');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar datos del alumno');
                }

                this.displayStudentData(data.data, studentId);

            } catch (error) {
                console.error('Error:', error);
                const modal = document.getElementById('studentDetailModal');
                modal.querySelector('.dsb-loading').style.display = 'none';
                modal.querySelector('.dsb-error-message').style.display = 'flex';
            }
        }

        async loadStudentStats(studentId) {
            try {
                const response = await fetch(`/wp-json/driving-school/v1/students/${studentId}/stats`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Error al cargar estadísticas del alumno');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar estadísticas del alumno');
                }

                this.displayStudentStats(data.data);

            } catch (error) {
                console.error('Error:', error);
            }
        }

        displayTeacherData(teacher) {
            const modal = document.getElementById('teacherDetailModal');

            // Información básica
            document.getElementById('teacherModalAvatar').src = teacher.avatar || '/wp-content/plugins/driving-school-bookings/public/img/default-avatar.png';
            document.getElementById('teacherModalName').textContent = teacher.display_name;
            document.getElementById('teacherModalEmail').textContent = teacher.email;
            document.getElementById('teacherModalPhone').textContent = teacher.phone || 'No disponible';

            // Vehículos
            const vehiclesContainer = document.getElementById('teacherModalVehicles');
            vehiclesContainer.innerHTML = '';

            if (teacher.vehicle && (teacher.vehicle.a || teacher.vehicle.b)) {
                const vehiclesList = document.createElement('ul');
                vehiclesList.className = 'dsb-info-list';

                if (teacher.vehicle.a && teacher.vehicle.a.name) {
                    const item = document.createElement('li');
                    item.innerHTML = `<i class="dashicons dashicons-motorcycle"></i> Moto: <strong>${teacher.vehicle.a.name}</strong>`;
                    vehiclesList.appendChild(item);
                }

                if (teacher.vehicle.b && teacher.vehicle.b.name) {
                    const item = document.createElement('li');
                    item.innerHTML = `<i class="dashicons dashicons-car"></i> Coche: <strong>${teacher.vehicle.b.name}</strong>`;
                    vehiclesList.appendChild(item);
                }

                vehiclesContainer.appendChild(vehiclesList);
            } else {
                vehiclesContainer.innerHTML = '<p class="dsb-empty-message">Sin vehículos asignados</p>';
            }

            // Alumnos asignados
            const studentsContainer = document.getElementById('teacherModalStudents');
            studentsContainer.innerHTML = '';

            if (teacher.students && teacher.students.length > 0) {
                teacher.students.forEach(student => {
                    const studentElement = document.createElement('div');
                    studentElement.className = 'dsb-student-item';
                    studentElement.innerHTML = `
                        <div class="dsb-student-info">
                            <p class="dsb-student-name">${student.display_name}</p>
                            <p class="dsb-student-email">${student.email}</p>
                        </div>
                        <div>
                            <span class="dsb-tag ${student.license_type ? 'license-' + student.license_type.toLowerCase() : ''}">
                                <i class="dashicons ${student.license_type === 'A' ? 'dashicons-motorcycle' : 'dashicons-car'}"></i>
                                ${student.license_type || 'N/A'}
                            </span>
                        </div>
                    `;
                    studentsContainer.appendChild(studentElement);
                });
            } else {
                studentsContainer.className = 'dsb-students-list empty';
                studentsContainer.innerHTML = '<p>Este profesor no tiene alumnos asignados</p>';
            }

            // Mostrar detalles y ocultar cargador
            modal.querySelector('.dsb-loading').style.display = 'none';
            modal.querySelector('.dsb-profile-details').style.display = 'block';
        }

        displayStudentData(student, studentId) {
            const modal = document.getElementById('studentDetailModal');

            // Información básica
            document.getElementById('studentModalAvatar').src = student.avatar || '/wp-content/plugins/driving-school-bookings/public/img/default-avatar.png';
            document.getElementById('studentModalName').textContent = student.display_name;
            document.getElementById('studentModalEmail').textContent = student.email;
            document.getElementById('studentModalPhone').textContent = student.phone || 'No disponible';
            document.getElementById('studentModalDNI').textContent = student.dni || 'No disponible';

            // Información académica
            document.getElementById('studentModalLicense').textContent = student.license_type || 'No asignada';
            document.getElementById('studentModalTeacher').textContent = student.assigned_teacher?.name || 'Sin asignar';
            document.getElementById('studentModalCredits').textContent = student.class_points || '0';

            // Historial de clases
            const bookingsContainer = document.getElementById('studentModalBookings');
            bookingsContainer.innerHTML = '';

            if (student.bookings && student.bookings.length > 0) {
                student.bookings.forEach(booking => {
                    const bookingDate = new Date(booking.date + 'T' + booking.time);
                    const formattedDate = bookingDate.toLocaleString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const bookingElement = document.createElement('div');
                    bookingElement.className = 'dsb-booking-item';
                    bookingElement.innerHTML = `
                        <div class="dsb-booking-info">
                            <p class="dsb-booking-title">
                                <i class="dashicons dashicons-calendar-alt"></i> 
                                ${formattedDate}
                            </p>
                            <div class="dsb-booking-meta">
                                <small>Profesor: ${booking.teacher}</small>
                                <small>Vehículo: ${booking.vehicle}</small>
                            </div>
                        </div>
                        <div>
                            <span class="dsb-booking-status ${booking.status}">
                                ${this.formatStatus(booking.status)}
                            </span>
                        </div>
                    `;
                    bookingsContainer.appendChild(bookingElement);
                });
            } else {
                bookingsContainer.className = 'dsb-bookings-list empty';
                bookingsContainer.innerHTML = '<p>Este alumno no tiene reservas realizadas</p>';
            }

            // Mostrar detalles y ocultar cargador
            modal.querySelector('.dsb-loading').style.display = 'none';
            modal.querySelector('.dsb-profile-details').style.display = 'block';
            this.loadStudentStats(studentId);
        }

        formatStatus(status) {
            switch (status) {
                case 'pending': return 'Pendiente';
                case 'accepted': return 'Aceptada';
                case 'cancelled': return 'Cancelada';
                case 'completed': return 'Completada';
                case 'blocked': return 'Bloqueada';
                default: return status;
            }
        }

        loadTeacherStats(teacherId, period = 'current') {
            document.getElementById('teacherStatsLoader').style.display = 'flex';
            document.getElementById('teacherStatsContent').style.display = 'none';

            fetch(`/wp-json/driving-school/v1/teachers/${teacherId}/stats?period=${period}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error al cargar estadísticas');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        this.displayTeacherStats(data.data);
                    } else {
                        throw new Error(data.message || 'Error al cargar estadísticas');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Mostrar mensaje de error en la sección de estadísticas
                })
                .finally(() => {
                    document.getElementById('teacherStatsLoader').style.display = 'none';
                    document.getElementById('teacherStatsContent').style.display = 'block';
                });
        }

        displayTeacherStats(stats) {
            // Actualizar contadores
            document.getElementById('teacherTotalClasses').textContent = stats.total || 0;
            document.getElementById('teacherAcceptedClasses').textContent = stats.accepted || 0;
            document.getElementById('teacherCanceledClasses').textContent = stats.canceled || 0;

            // Si hay una gráfica existente, destruirla antes de crear una nueva
            if (this.teacherChart) {
                this.teacherChart.destroy();
            }

            // Crear gráfica de barras con Chart.js
            const ctx = document.getElementById('teacherStatsChart').getContext('2d');
            this.teacherChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: stats.days.map(day => day.date),
                    datasets: [{
                        label: 'Clases aceptadas',
                        data: stats.days.map(day => day.count),
                        backgroundColor: '#2980b9',
                        borderColor: '#2980b9',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        displayStudentStats(stats) {
            // Actualizar contadores
            document.getElementById('studentTotalClasses').textContent = stats.total || 0;
            document.getElementById('studentAcceptedClasses').textContent = stats.accepted || 0;
            // document.getElementById('studentCompletedClasses').textContent = stats.completed || 0;
            document.getElementById('studentPendingClasses').textContent = stats.pending || 0;
            document.getElementById('studentCancelledClasses').textContent = stats.cancelled || 0;

            // Actualizar barra de progreso de créditos
            const usedCredits = stats.used_credits || 0;
            const totalCredits = stats.total_credits || 0;
            const percentUsed = totalCredits > 0 ? (usedCredits / totalCredits * 100) : 0;

            document.getElementById('studentCreditBar').style.width = `${percentUsed}%`;
            document.getElementById('studentUsedCredits').textContent = usedCredits;
            document.getElementById('studentTotalCredits').textContent = totalCredits;
        }
    }

    // Inicializar buscador de profesores
    if (document.getElementById('teacherSearch')) {
        new SearchManager({
            searchInput: 'teacherSearch',
            filterSelect: 'teacherFilter',
            itemSelector: '.teacher-item',
            noResultsMsg: 'noTeachersMsg',
            paginationEnabled: true,
            prevPageBtn: 'prevTeacherPage',
            nextPageBtn: 'nextTeacherPage',
            currentPageSpan: 'currentTeacherPage',
            totalPagesSpan: 'totalTeacherPages',
            itemsPerPage: 10,
            countBadge: 'teacherCount'
        });
    }

    // Inicializar buscador de alumnos
    if (document.getElementById('studentSearch')) {
        new SearchManager({
            searchInput: 'studentSearch',
            filterSelect: 'studentFilter',
            itemSelector: '.student-item',
            noResultsMsg: 'noStudentsMsg',
            paginationEnabled: true,
            prevPageBtn: 'prevStudentPage',
            nextPageBtn: 'nextStudentPage',
            currentPageSpan: 'currentStudentPage',
            totalPagesSpan: 'totalStudentPages',
            itemsPerPage: 10,
            countBadge: 'studentCount'
        });
    }

    // Inicializar gestor de modales
    new DetailsModalManager();

    // Otros plugins y funcionalidades
    $('.dsb-card').hover(
        function () {
            $(this).addClass('dsb-card-hover');
        },
        function () {
            $(this).removeClass('dsb-card-hover');
        }
    );
});