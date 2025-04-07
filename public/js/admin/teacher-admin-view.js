jQuery(document).ready(function ($) {
    console.log("Script conectado");

    // Mostrar/ocultar el formulario
    $('#mostrar-form-crear-profesor').on('click', function () {
        $('#crear-profesor-form').slideToggle();
    });

    // EDITAR PROFESOR
    $('.button:contains("Editar")').on('click', function (e) {
        e.preventDefault();
        const teacherId = $(this).data('id');

        $.ajax({
            url: profesorAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'dsb_get_teacher_data',
                teacher_id: teacherId
            },
            success: function (response) {
                if (response.success) {
                    const prof = response.data;
                    $('#crear-profesor-form').slideDown();
                    $('input[name="username"]').val(prof.username);
                    $('input[name="email"]').val(prof.email);
                    $('input[name="first_name"]').val(prof.first_name);
                    $('input[name="last_name"]').val(prof.last_name);
                    $('input[name="license_number"]').val(prof.license);
                    cargarVehiculos(prof.vehicle_id);
                    $('#crear-profesor-form').attr('data-edit-id', prof.ID);
                    $('button[type="submit"]').text('Actualizar Profesor');
                } else {
                    alert("No se pudo obtener el profesor.");
                }
            },
            error: function () {
                alert("Error al obtener datos del profesor.");
            }
        });
    });

    // GUARDAR PROFESOR
    $('#guardar-profesor').on('click', function () {
        const id = $('#crear-profesor-form').attr('data-edit-id') || '';
        const data = {
            action: 'dsb_save_teacher_data',
            teacher_id: id,
            username: $('input[name="username"]').val(),
            email: $('input[name="email"]').val(),
            first_name: $('input[name="first_name"]').val(),
            last_name: $('input[name="last_name"]').val(),
            license: $('input[name="license_number"]').val(),
            vehicle_id: $('#assigned_vehicle').val()
        };

        $.post(profesorAjax.ajaxurl, data, function (res) {
            if (res.success) {
                alert("Guardado correctamente");
                location.reload();
            } else {
                alert("Error: " + res.data);
            }
        });
    });

    // CALENDARIO
    $('.button:contains("Calendario")').on('click', function (e) {
        e.preventDefault();
        const teacherId = $(this).closest('tr').find('[data-id]').data('id');

        $.ajax({
            url: profesorAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'dsb_get_teacher_calendar',
                teacher_id: teacherId
            },
            success: function (events) {
                $("#teacher-calendar-container").css("display", "block");
                const calendar = new FullCalendar.Calendar(document.getElementById('teacher-calendar'), {
                    initialView: 'timeGridWeek',
    slotMinTime: '08:00:00',
    slotMaxTime: '21:00:00',
    allDaySlot: false,
    height: 'auto',
    events: events,
    eventContent: function (arg) {
        const title = arg.event.title;
        const start = arg.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const end = arg.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        return {
            html: `<div class="fc-event-title">${start} - ${end}<br><strong>${title}</strong></div>`
        };
    }
                });
                calendar.render();
                
            },
            error: function () {
                alert("No se pudo cargar el calendario.");
            }
        });
    });

    // CLASES
    $('.open-class-settings').on('click', function (e) {
        e.preventDefault();
        const teacherId = $(this).data('id');
        $('#clases_teacher_id').val(teacherId);
        $('#modal-clases-profesor').slideToggle();
    });

    function cargarVehiculos(vehiculoSeleccionado = null) {
        $.ajax({
            url: profesorAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'dsb_get_vehicles'
            },
            success: function (vehiculos) {
                const $select = $('#assigned_vehicle');
                $select.empty().append('<option value="">-- Selecciona un vehículo --</option>');
                vehiculos.forEach(function (vehiculo) {
                    const selected = (vehiculo.id == vehiculoSeleccionado) ? 'selected' : '';
                    $select.append(`<option value="${vehiculo.id}" ${selected}>${vehiculo.name}</option>`);
                });
            },
            error: function () {
                alert("Error al cargar los vehículos");
            }
        });
    }
});
