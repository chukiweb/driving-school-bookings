jQuery(document).ready(function ($) {
    console.log("Script conectado");

    // Mostrar/ocultar el formulario
    $('#mostrar-form-crear-profesor').on('click', function () {
        $('#crear-profesor-form').slideToggle();
    });

    // Mostrar calendario al hacer clic
    $('.button:contains("Calendario")').on('click', function (e) {
        e.preventDefault();

        const row = $(this).closest('tr');
        const teacherLogin = row.find('td[data-login]').data('login');
        const teacherId = teacherMap[teacherLogin];

        if (!teacherId) {
            alert("No se pudo identificar al profesor.");
            return;
        }

        console.log("ID del profesor seleccionado:", teacherId);

        $.get(`/wp-json/driving-school/v1/teachers/${teacherId}/calendar`, function (events) {
            const calendar = new FullCalendar.Calendar(document.getElementById('teacher-calendar'), {
                initialView: 'timeGridWeek',
                events: events
            });
            calendar.render();
        });
    });
});
