(function (window, FullCalendar) {
    /**
     * DSBCalendar: Módulo para inicializar FullCalendar con configuración y callbacks comunes
     */
    const DSBCalendar = {
        /**
         * Inicializa el calendario
         * @param {{ el: string, config: object, events: Array, callbacks: object }} opts
         * @returns {FullCalendar.Calendar|null}
         */
        init(opts) {
            const container = document.querySelector(opts.el);
            if (!container) {
                console.warn('DSBCalendar: contenedor no encontrado', opts.el);
                return null;
            }
            const cfg = opts.config || {};
            // Normalizar hiddenDays: debe ser array de números de 0 a 6
            const hiddenDays = Array.isArray(cfg.hiddenDays)
                ? cfg.hiddenDays.map(d => parseInt(d, 10)).filter(d => !isNaN(d) && d >= 0 && d <= 6)
                : [];
            const calendar = new FullCalendar.Calendar(container, {
                allDaySlot: false,
                locale: cfg.locale || 'es',
                headerToolbar: cfg.headerToolbar || { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                buttonText: cfg.buttonText || { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
                slotDuration: cfg.slotDuration || '00:45:00',
                slotMinTime: cfg.slotMinTime || '08:00:00',
                slotMaxTime: cfg.slotMaxTime || '21:00:00',
                hiddenDays: hiddenDays,
                nowIndicator: true,
                selectable: true,
                selectMirror: true,
                events: opts.events || [],
                select: opts.callbacks.onSelect,
                eventClick: opts.callbacks.onEventClick,
                eventDidMount: opts.callbacks.eventDidMount,
                viewDidMount: opts.callbacks.viewDidMount,
                datesSet: opts.callbacks.datesSet,
                eventTimeFormat: cfg.eventTimeFormat || { hour: '2-digit', minute: '2-digit', hour12: false },
                slotLabelFormat: cfg.slotLabelFormat || { hour: '2-digit', minute: '2-digit', hour12: false }
            });
            calendar.render();
            return calendar;
        }
    };
    window.DSBCalendar = DSBCalendar;
})(window, FullCalendar);