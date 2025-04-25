jQuery(document).ready(function ($) {

    // const token = localStorage.getItem("jwt_token");
    const token = sessionStorage.getItem("jwt_token");

    if (!token) {
        window.location.href = "/acceso"; // Redirige al login si no hay sesión
        return;
    }

    const userId = obtenerUsuarioDesdeToken(token);
    if (!userId) {
        console.error("No se pudo obtener el ID del usuario");
        return;
    }

    // Cargar datos del profesor
    /*$.ajax({
        url: `/wp-json/driving-school/v1/teachers/${userId}`,
        method: "GET",
        headers: {
            "Authorization": `Bearer ${token}`,
            "Content-Type": "application/json"
        },
        success: function (data) {
            if (data.success) {
                $("#teacher-name").text(data.data.display_name);
                $("#teacher-email").text(data.data.email);
                $("#teacher-vehicle").text(data.data.vehicle || "Sin vehículo");
                $("#teacher-avatar").attr("src", data.data.avatar || "../img/default-avatar.png");

                if (data.data.appointments && data.data.appointments.length > 0) {
                    $("#teacher-appointments").text(`Tienes ${data.data.appointments.length} citas hoy`);
                    $("#loading-spinner").addClass("d-none");
                }
            } else {
                console.error("Error al obtener datos del profesor:", data.message);
            }
        },
        error: function (error) {
            console.error("Error al cargar datos del profesor:", error);
        }
    });*/

    


    var calendarEl = document.getElementById('teacher-calendar');
    if (!calendarEl) {
        console.error("❌ No se encontró el contenedor del calendario.");
        return;
    }

  /*  var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
        locale: 'es',
        editable: true,
        selectable: true,
        height: 'auto',
        contentHeight: 'auto',
        slotDuration: '00:30:00',  // Intervalos de 30 minutos
        slotMinTime: '08:00:00',   // Hora de inicio de clases
        slotMaxTime: '20:00:00',   // Hora de fin de clases
        hiddenDays: [0, 6],        // Ocultar sábados (6) y domingos (0)
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: window.innerWidth < 768 ? 'timeGridDay,listWeek' : 'dayGridMonth,timeGridWeek,timeGridDay'
        },

        events: function (fetchInfo, successCallback, failureCallback) {
            $.ajax({
                url: '/wp-json/driving-school/v1/teachers/' + userId + '/calendar',
                type: 'GET',
                headers: {
                    "Authorization": `Bearer ${token}`,
                    "Content-Type": "application/json"
                },
                success: function (data) {
                    successCallback(data);
                },
                error: function () {
                    failureCallback();
                }
            });
        }
    });

    calendar.render();*/

});


// Función para obtener el ID del usuario desde el token JWT
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
