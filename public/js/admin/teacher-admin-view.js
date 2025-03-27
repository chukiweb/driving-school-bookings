jQuery(document).ready(function ($) {
    console.log("Script conectado");

    // Mostrar/ocultar el formulario
    $('#mostrar-form-crear-profesor').on('click', function () {
        $('#crear-profesor-form').slideToggle();
    });

    /**
     * 
     */
    $('.button:contains("Editar")').on('click', function (e) {
        e.preventDefault();
    
        const teacherId = $(this).data('id');
        const token = localStorage.getItem("jwt_token");
    
        if (!token) {
            alert("No tienes sesión activa");
            return;
        }
    
        $.ajax({
            url: `/wp-json/driving-school/v1/teachers/${teacherId}`,
            method: 'GET',
            headers: {
                "Authorization": `Bearer ${token}`
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
    
                    cargarVehiculos(prof.vehicle_id); // rellena el <select>
                    $('#crear-profesor-form').attr('data-edit-id', prof.ID);
                    $('button[type="submit"]').text('Actualizar Profesor');
                } else {
                    alert("No se pudieron obtener los datos del profesor.");
                }
            },
            error: function (xhr) {
                alert("Error: " + xhr.status + " - No autorizado.");
            }
        });
    });
    

    /**
     * Funcion para guardar un profesor editado
     */
    $('#guardar-profesor').on('click', function () {
        const id = $('#profesor_id').val();
        const nombre = $('#nombre_profesor').val();
        const email = $('#email_profesor').val();
        const licencia = $('#licencia_profesor').val();
        const vehiculo = $('#vehiculo_profesor').val();

        const metodo = id ? 'PUT' : 'POST';
        const url = id ? `/wp-json/driving-school/v1/teachers/${id}` : '/wp-json/driving-school/v1/teachers';

        $.ajax({
            url: url,
            method: metodo,
            contentType: 'application/json',
            data: JSON.stringify({
                display_name: nombre,
                email: email,
                license: licencia,
                vehicle_id: vehiculo
            }),
            success: function (res) {
                alert("Profesor guardado correctamente");
                location.reload();
            },
            error: function () {
                alert("Error al guardar el profesor");
            }
        });
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

    function cargarVehiculos(vehiculoSeleccionado = null) {
        const token = localStorage.getItem("jwt_token");
    
        $.ajax({
            url: '/wp-json/driving-school/v1/vehicles',
            method: 'GET',
            headers: {
                "Authorization": `Bearer ${token}`
            },
            success: function (vehiculos) {
                const $select = $('#assigned_vehicle');
                $select.empty();
                $select.append('<option value="">-- Selecciona un vehículo --</option>');
    
                vehiculos.forEach(function (vehiculo) {
                    const selected = (vehiculo.id == vehiculoSeleccionado) ? 'selected' : '';
                    $select.append(`<option value="${vehiculo.id}" ${selected}>${vehiculo.name}</option>`);
                });
            },
            error: function () {
                alert("No se pudieron cargar los vehículos.");
            }
        });
    }
    

    

});


jQuery(document).ready(function($) {
    $('.open-class-settings').on('click', function(e) {
        e.preventDefault();
        const teacherId = $(this).data('id');
        $('#clases_teacher_id').val(teacherId);

        // Mostrar modal
        $('#modal-clases-profesor').slideToggle();
    
    });

    // Enviar formulario al endpoint REST
    $('#form-clases-profesor').on('submit', function(e) {
        e.preventDefault();
        const token = localStorage.getItem("jwt_token");
        const teacherId = $('#clases_teacher_id').val();
        const dias = [];
        $('input[name="dias[]"]:checked').each(function() {
            dias.push($(this).val());
        });

        const data = {
            dias: dias,
            hora_inicio: $('input[name="hora_inicio"]').val(),
            hora_fin: $('input[name="hora_fin"]').val(),
            duracion: parseInt($('input[name="duracion"]').val())
        };

        fetch(`/wp-json/v1/professor/${teacherId}/classes`, {
            method: 'POST',
            headers: {
                "Authorization": `Bearer ${token}`
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                alert('Datos de clases guardados correctamente.');
                tb_remove(); // Cerrar modal
            } else {
                alert('Error: ' + (response.message || 'No se pudo guardar'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al guardar los datos');
        });
    });
});

