document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        allDaySlot: false,
        slotMinTime: "08:00:00",
        slotMaxTime: "21:00:00",
        slotDuration: "00:30:00",
        eventOverlap: true,
        events: reservas // ← esta variable viene de PHP
    });
    calendar.render();
});
