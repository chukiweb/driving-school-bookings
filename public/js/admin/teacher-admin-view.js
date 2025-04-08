jQuery(document).ready(function ($) {

    // Mostrar/ocultar el formulario
    $('#mostrar-form-crear-profesor').on('click', function () {
        $('#createFormContainer').slideToggle();
        document.querySelector('#createFormContainer form').reset();
    });

    // EDITAR PROFESOR
    addEventListener('click', function (e) {
        const editBtn = e.target.closest('.button.edit-teacher');
        if (editBtn) {

            e.preventDefault();
            const teacherId = editBtn.dataset.userId;
            const updateForm = document.querySelector('#editar-profesor-form');

            allTeacherData.forEach(function (prof) {

                if (prof.id == teacherId) {
                    // updateForm.querySelector('input[name="username"]').value = prof.username;
                    // document.querySelector('#editar-profesor-input[name="password"]').value = '1234';
                    // document.querySelector('#editar-profesor-form input[name="email"]').value = prof.email;
                    // document.querySelector('#editar-profesor-form input[name="first_name"]').value = prof.first_name;
                    // document.querySelector('#editar-profesor-form input[name="last_name"]').value = prof.last_name;
                    // cargarVehiculos(prof.vehicle_id);
                }

            });
        }
        $('#updateFormContainer').slideToggle();

    });
    // $('.button:contains("Editar")').on('click', function (e) {


    //     // $.ajax({
    //     //     url: profesorAjax.ajaxurl,
    //     //     method: 'POST',
    //     //     data: {
    //     //         action: 'dsb_get_teacher_data',
    //     //         teacher_id: teacherId
    //     //     },
    //     //     success: function (response) {
    //     //         const prof = response.data.data.data; // Es algo muy raro tener que acceder al 3 nivel
    //     //         document.querySelector('input[name="username"]').value = prof.username;
    //     //         // document.querySelector('input[name="password"]').setAttribute('disabled', 'disabled');
    //     //         document.querySelector('input[name="password"]').value = '1234';
    //     //         document.querySelector('input[name="email"]').value = prof.email;
    //     //         document.querySelector('input[name="first_name"]').value = prof.first_name;
    //     //         document.querySelector('input[name="last_name"]').value = prof.last_name;
    //     //         cargarVehiculos(prof.vehicle_id);
    //     //         document.querySelector('#formContainer form').setAttribute('data-edit-id', prof.ID);
    //     //         document.querySelector('#formContainer form').id = 'udpate-profesor-form';
    //     //         document.querySelector('input[type="submit"]').value = 'Actualizar profesor';
    //     //         $('#formContainer').slideDown();

    //     //     },
    //     //     error: function () {
    //     //         alert("Error al obtener datos del profesor.");
    //     //     }
    //     // });
    // });

    // GUARDAR PROFESOR o ACTUALIZAR
    $('#guardar-profesor').on('click', function () {

        if (document.querySelector('#formContainer form').id === 'udpate-profesor-form') {
            const id = $('#crear-profesor-form').attr('data-edit-id') || '';
            const data = {
                action: 'dsb_save_teacher_data',
                teacher_id: id,
                username: $('input[name="username"]').val(),
                email: $('input[name="email"]').val(),
                first_name: $('input[name="first_name"]').val(),
                last_name: $('input[name="last_name"]').val(),
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
        }


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
        $('#updateFormContainer').slideToggle();
    });

    function cargarVehiculos(vehiculoSeleccionado = null) {
        $.ajax({
            url: profesorAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'dsb_get_vehicles'
            },
            success: function (reponse) {
                const vehiculos = reponse.data;
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
