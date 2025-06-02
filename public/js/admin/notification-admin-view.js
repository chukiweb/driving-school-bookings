document.addEventListener('DOMContentLoaded', () => {
    /**
     * -----------------------------------
     * CLASE PRINCIPAL: NotificationAdminView
     * -----------------------------------
     */
    class NotificationAdminView {
        // 1) Elementos “estáticos” (se capturan una sola vez)
        static inputSearch       = document.getElementById('recipient_search');
        static inputHidden       = document.getElementById('recipient_value');
        static suggestionsBox    = document.getElementById('recipient_suggestions');
        static tableBody         = document.querySelector('#the-list, .wp-list-table tbody'); 
        // Nota: ajusta el selector si tu tabla usa otro ID/clase
        static allButtons        = null; // se rellenará en init()
        static restBaseSearchUrl = window.location.origin + '/wp-json/driving-school/v1/users-search';
        static restBaseNotifUrl  = window.location.origin + '/wp-json/driving-school/v1/notifications'; 


        // Opciones de “Grupo” fijas
        static GROUP_OPTIONS = [
            { label: 'Todos los usuarios',   value: 'all-users',   type: 'group' },
            { label: 'Todos los alumnos',    value: 'student-all', type: 'group' },
            { label: 'Todos los profesores', value: 'teacher-all', type: 'group' }
        ];

        // Mapa de “etiqueta visible” → “valor real” para ocultar en el org. del form
        static optionMap = {}; 

        /**
         * Método de inicialización: lo llamamos al cargar el DOM.
         * Se encarga de:
         *  - Configurar autocompletado (grupo + usuarios).
         *  - Capturar botones de “Eliminar” y “Marcar como leída”.
         */
        static init() {
            // 1) Si faltan los elementos clave, salimos
            if (!NotificationAdminView.inputSearch ||
                !NotificationAdminView.inputHidden ||
                !NotificationAdminView.suggestionsBox) {
                return;
            }

            // 2) Configurar autocompletado en el input
            NotificationAdminView._setupAutocomplete();

            // 3) Configurar listeners sobre los botones de acción (Eliminar / Marcar como leída)
            NotificationAdminView._attachActionButtons();
        }

        /**
         * Agrega listeners en:
         *   • “input” sobre el campo de búsqueda para autocompletar.
         *   • “click” sobre el contenedor de sugerencias para seleccionar opción.
         *   • “click” fuera para ocultar el dropdown.
         *   • “keydown” para navegación con teclado (↑ ↓ Enter Esc).
         */
        static _setupAutocomplete() {
            const inputSearch    = NotificationAdminView.inputSearch;
            const suggestionsBox = NotificationAdminView.suggestionsBox;

            // Debounce para no disparar fetch al escribir cada tecla
            function debounce(fn, delay) {
                let timer = null;
                return function (...args) {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn.apply(this, args), delay);
                };
            }

            // Llamada a la API /users-search?term=...
            async function fetchUsers(term) {
                try {
                    const params = new URLSearchParams();
                    params.append('term', term);
                    const url = NotificationAdminView.restBaseSearchUrl + '?' + params.toString();

                    const response = await fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-WP-Nonce': (window.wpApiSettings ? window.wpApiSettings.nonce : ''),
                        },
                    });
                    if (!response.ok) {
                        console.error('Error al buscar usuarios:', response.status);
                        return [];
                    }
                    const data = await response.json();
                    if (!Array.isArray(data)) {
                        console.error('Respuesta inesperada de users-search:', data);
                        return [];
                    }
                    return data;
                } catch (err) {
                    console.error('Excepción en fetchUsers:', err);
                    return [];
                }
            }

            // Función que actualiza el dropdown (con grupo + usuarios)
            async function updateSuggestions() {
                const termRaw = inputSearch.value.trim().toLowerCase();
                // Limpiamos dropdown y hidden en cada cambio
                suggestionsBox.innerHTML = '';
                NotificationAdminView.inputHidden.value = '';

                if (termRaw.length < 1) {
                    return;
                }

                // 1) Filtrar opciones fijas de grupo
                const matchedGroups = NotificationAdminView.GROUP_OPTIONS.filter(opt =>
                    opt.label.toLowerCase().includes(termRaw)
                );

                // 2) Llamar a backend para usuarios individuales
                const users = await fetchUsers(termRaw);

                // Vaciamos el mapa anterior
                Object.keys(NotificationAdminView.optionMap).forEach(k => {
                    delete NotificationAdminView.optionMap[k];
                });

                // 3) Pintar los matchedGroups
                matchedGroups.forEach(opt => {
                    NotificationAdminView.optionMap[opt.label] = opt.value;
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item group';
                    div.textContent = opt.label;
                    div.dataset.value = opt.value;
                    suggestionsBox.appendChild(div);
                });

                // 4) Pintar los usuarios del fetch
                users.forEach(u => {
                    // cada u = { label: 'Nombre (Alumno)', value: 'student-49' }
                    NotificationAdminView.optionMap[u.label] = u.value;
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.textContent = u.label;
                    div.dataset.value = u.value;
                    suggestionsBox.appendChild(div);
                });

                // 5) Mostrar / ocultar según haya opciones
                if (suggestionsBox.childElementCount > 0) {
                    suggestionsBox.style.display = 'block';
                } else {
                    suggestionsBox.style.display = 'none';
                }
            }

            // Manejador al hacer clic en una sugerencia
            function onSuggestionClick(e) {
                const item = e.target.closest('.autocomplete-item');
                if (!item) return;

                const selectedLabel = item.textContent;
                const selectedValue = item.dataset.value;

                inputSearch.value = selectedLabel;
                NotificationAdminView.inputHidden.value = selectedValue;
                suggestionsBox.style.display = 'none';
            }

            // Cerrar dropdown si clicas fuera
            function onClickOutside(e) {
                if (!suggestionsBox.contains(e.target) && e.target !== inputSearch) {
                    suggestionsBox.style.display = 'none';
                }
            }

            // Navegación de flechas + Enter + Esc
            let currentFocus = -1;
            function onKeyDown(e) {
                const items = Array.from(suggestionsBox.querySelectorAll('.autocomplete-item'));
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    currentFocus++;
                    if (currentFocus >= items.length) currentFocus = 0;
                    setActiveItem(items);
                    e.preventDefault();
                } else if (e.key === 'ArrowUp') {
                    currentFocus--;
                    if (currentFocus < 0) currentFocus = items.length - 1;
                    setActiveItem(items);
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    if (currentFocus > -1) {
                        e.preventDefault();
                        items[currentFocus].click();
                    }
                } else if (e.key === 'Escape') {
                    suggestionsBox.style.display = 'none';
                }
            }

            function setActiveItem(items) {
                items.forEach(i => i.classList.remove('active'));
                if (currentFocus >= 0 && currentFocus < items.length) {
                    items[currentFocus].classList.add('active');
                }
            }

            // Enganchamos eventos
            const debouncedUpdate = debounce(updateSuggestions, 300);
            inputSearch.addEventListener('input', debouncedUpdate);
            suggestionsBox.addEventListener('click', onSuggestionClick);
            document.addEventListener('click', onClickOutside);
            inputSearch.addEventListener('keydown', onKeyDown);
        }

        /**
         * Busca en la tabla todos los botones de acción (data-action) y les
         * añade un listener. Dependiendo de data-action (delete o otra),
         * invocamos el método correspondiente.
         */
        static _attachActionButtons() {
            // Seleccionamos todos los <a class="button" data-action="…">
            NotificationAdminView.allButtons = document.querySelectorAll('a.button[data-action]');

            NotificationAdminView.allButtons.forEach(btn => {
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    const action = btn.dataset.action;   // “delete” o “otra”
                    const notifId = btn.dataset.id;       // ID de la notificación

                    if (!action || !notifId) {
                        return;
                    }

                    switch (action) {
                        case 'delete':
                            await NotificationAdminView._deleteNotification(notifId, btn);
                            break;
                        default:
                            console.warn('Acción no reconocida:', action);
                    }
                });
            });
        }

        /**
         * Elimina la notificación vía REST (DELETE /notifications/<ID>).
         * Si tiene éxito, elimina la fila de la tabla.
         */
        static async _deleteNotification(notifId, btn) {
            if (!confirm('¿Seguro que deseas eliminar esta notificación?')) {
                return;
            }

            try {
                const url = `${NotificationAdminView.restBaseNotifUrl}/${notifId}`;
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (window.wpApiSettings ? window.wpApiSettings.nonce : ''),
                    },
                });

                if (!response.ok) {
                    throw new Error(`Error al eliminar: ${response.status}`);
                }

                // Si fue OK, borramos la fila de la tabla de forma inmediata
                const row = btn.closest('tr');
                if (row) {
                    row.parentNode.removeChild(row);
                }

            } catch (err) {
                console.error('No se pudo eliminar notificación:', err);
                alert('Error al eliminar la notificación. Revisa la consola para más detalles.');
            }
        }
    }

    // Inicializamos la vista en cuanto esté listo el DOM
    NotificationAdminView.init();
});