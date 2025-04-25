let studentData = {};
let reservas = [];
jQuery(document).ready(function ($) {
    console.log("STUDENT DATA", studentDataData);
 
    // const token = localStorage.getItem("jwt_token");
    const token = sessionStorage.getItem("jwt_token");

    // if (!token) {
    //     window.location.href = "/acceso";
    //     return;
    // }

    const userId = obtenerUsuarioDesdeToken(token);
    if (!userId) {
        console.error("No se pudo obtener el ID del usuario");
        return;
    }

    $.ajax({
        url: `/wp-json/driving-school/v1/students/${userId}`,
        method: "GET",
        headers: {
            "Authorization": "Bearer " + token
        },
        success: function (response) {
            // Guardamos toda la información en la variable global
            studentData = response.data;
            reservas = studentData.bookings.data.data;
            console.log(reservas);
            mostrarDatosUsuario();
            mostrarDatosReservas();
        },
        error: function () {
            console.error("Error al obtener datos del estudiante");
            $("#estudiante-clases").text("Error al cargar clases");
        }
    });


   
     // Asignar evento click a los botones generados dinámicamente
     $(document).on("click", ".ver-detalles", function () {
        let index = $(this).attr("data-id");
        mostrarDetalles(index);
    });
    

    $("#estudiante-avatar").click(function() {
        $("#file-input").click();
    });


     // Evento cuando se selecciona un archivo
     $("#file-input").change(function(event) {
        let file = event.target.files[0];
        if (!file) return;

        let formData = new FormData();
        formData.append("file", file);
        formData.append("id", userId);

        $.ajax({
            url: "/wp-json/driving-school/v1/users/me/avatar",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            headers: {
                // "Authorization": "Bearer " + localStorage.getItem("jwt_token")
                "Authorization": "Bearer " + sessionStorage.getItem("jwt_token")
            },
            success: function(response) {
                if (response.success) {
                    $("#estudiante-avatar").attr("src", response.url);
                    alert("Imagen subida correctamente");
                } else {
                    alert("Error al subir la imagen: " + response.message);
                }
            },
            error: function() {
                alert("Hubo un error en la subida de la imagen.");
            }
        });
    });



    function mostrarDetalles(id) {
        const reserva = reservas.find(reserva => reserva.id == id);
    if (!reserva) {
        console.error("No se encontró la reserva con ID:", id);
        return;
    }

    $("#modal-fecha").text(new Date(reserva.date).toLocaleDateString("es-ES"));
    $("#modal-hora").text(reserva.time);
    $("#modal-profesor").text(reserva.teacher);
    $("#modal-vehiculo").text(reserva.vehicle);
    $("#modal-estado").text(reserva.status);
    }
    
    function mostrarDatosUsuario() {
        $("#estudiante-avatar").attr("src", studentData.avatar);
        $("#estudiante-name").text(studentData.display_name);
        $("#estudiante-email").html(`<i class="bi bi-envelope-fill"></i> ${studentData.email}`);
        $("#assigned-car").html(`<i class="bi bi-car-front-fill"></i> ${studentData.assigned_vehicle.name}`);
        $("#assigned-teacher").html(`<i class="bi bi-person-badge"></i> ${studentData.assigned_teacher.name}`);
    
        // Fecha actual
        const fecha = new Date();
        $("#fecha-actual").text(fecha.toLocaleDateString("es-ES", {
            day: "2-digit",
            month: "long",
            year: "numeric"
        }));
    }
    
    function mostrarDatosReservas() {

        let container = $('#reservas-container');
    
         if (!Array.isArray(reservas) || reservas.length === 0) {
        container.html("<p class='text-center'>No tienes clases reservadas.</p>");
        return;
        }
        
        reservas.forEach((reserva, index) => {
            const card = $(`
                <div class="col-12">
                    <div class="d-flex align-items-center p-3 shadow-sm bg-light rounded">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold">${new Date(reserva.date).toLocaleDateString("es-ES")}</h6>
                            <p class="mb-0"><strong>Hora:</strong> ${reserva.time}</p>
                            <p class="mb-0"><strong>Profesor:</strong> ${reserva.teacher}</p>
                        </div>
                        <button class="btn btn-warning ms-3 ver-detalles" data-bs-toggle="modal" 
                                data-bs-target="#detalleReservaModal" data-id="${reserva.id}">
                            Ver detalles
                        </button>
                    </div>
                </div>
            `);
        
            container.append(card);
        });
       
    }
    
    
    function obtenerUsuarioDesdeToken(token) {
        try {
            if (!token || token.split(".").length !== 3) {
                console.error("Token inválido o mal formado");
                return null;
            }
    
            const base64Url = token.split(".")[1];
            const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
    
            const jsonPayload = decodeURIComponent(
                Array.from(atob(base64))
                    .map(c => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2))
                    .join("")
            );
    
            const payload = JSON.parse(jsonPayload);
            return payload.user.id || null;
        } catch (error) {
            console.error("Error al decodificar el token JWT", error);
            return null;
        }
    }

});




