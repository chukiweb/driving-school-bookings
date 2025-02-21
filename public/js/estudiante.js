document.addEventListener("DOMContentLoaded", async function () {
    const token = localStorage.getItem("jwt_token");
    if (!token) {
        window.location.href = "/acceso";
        return;
    }
    const userId = obtenerUsuarioDesdeToken(token);
    if (!userId) {
        console.error("No se pudo obtener el ID del usuario");
        return;
    }


    try {
        const response = await fetch(`/wp-json/driving-school/v1/students/${userId}`, {
            headers: {
                "Authorization": "Bearer " + token
            }
        });

        if (!response.ok) {
            throw new Error("Error al obtener datos del estudiante");
        }

        const data = await response.json();
        document.getElementById("estudiante-avatar").src = data.data.avatar;
        document.getElementById("estudiante-name").textContent = data.data.display_name;
        document.getElementById("estudiante-email").innerHTML = `<i class="bi bi-envelope-fill"></i> ${data.data.email}`;
        document.getElementById("estudiante-car").textContent = data.data.vehicle || "Sin vehículo";

        if (data.data.bookings.length > 0) {
            document.getElementById("estudiante-clases").textContent = `Tienes ${data.data.bookings.length} clases reservadas`;
        } else {
            document.getElementById("estudiante-clases").textContent = "No tienes clases reservadas";
        }

        // Fecha actual
        const fecha = new Date();
        document.getElementById("fecha-actual").textContent = fecha.toLocaleDateString("es-ES", {
            day: "2-digit",
            month: "long",
            year: "numeric"
        });

    } catch (error) {
        console.error(error);
        document.getElementById("estudiante-clases").textContent = "Error al cargar clases";
    }
});

function obtenerUsuarioDesdeToken(token) {
    try {
        if (!token || token.split(".").length !== 3) {
            console.error("Token inválido o mal formado");
            return null;
        }

        const base64Url = token.split(".")[1]; // Obtener la segunda parte del JWT (payload)
        const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");

        // Decodificar la cadena base64
        const jsonPayload = decodeURIComponent(
            Array.from(atob(base64))
                .map(c => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2))
                .join("")
        );

        // Convertir a objeto JSON
        const payload = JSON.parse(jsonPayload);
        
        console.log("Token decodificado:", payload); // Depuración

        return payload.user.id || null; // Retornar el ID del usuario
    } catch (error) {
        console.error("Error al decodificar el token JWT", error);
        return null;
    }
}
