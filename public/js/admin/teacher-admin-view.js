jQuery(document).ready(function($) {
    // Mostrar formulario de creación de profesor
    $("#show-form").click(function() {
        $("#professor-form").slideDown();
    });

    // Ocultar formulario
    $("#cancel-form").click(function() {
        $("#professor-form").slideUp();
    });

    // Mostrar calendario del profesor
    $(".view-calendar").click(function() {
        var teacherId = $(this).data("teacher-id");

        if (teacherReservations.hasOwnProperty(teacherId)) {
            $("#calendar-container").slideDown();

            $("#calendar").fullCalendar('destroy'); // Limpiar calendario antes de recargar
            $("#calendar").fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                events: teacherReservations[teacherId] // Cargar eventos del profesor
            });
        } else {
            alert("No hay reservas para este profesor.");
        }
    });

    // Cerrar calendario
    $("#close-calendar").click(function() {
        $("#calendar-container").slideUp();
    });
});
