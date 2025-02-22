document.addEventListener("DOMContentLoaded", async () => {
    const token = localStorage.getItem("jwt_token");


    if (!token) {
        window.location.href = "/public/views/acceso.php"; // Redirige al login si no hay sesión
        return;
    }
    const userId = obtenerUsuarioDesdeToken(token);
    if (!userId) {
        console.error("No se pudo obtener el ID del usuario");
        return;
    }

    try {
        const response = await fetch(`/wp-json/driving-school/v1/teachers/${userId}`, {
            method: "GET",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Content-Type": "application/json"
            }
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById("teacher-name").textContent = data.data.display_name;
            document.getElementById("teacher-email").textContent = data.data.email;
            document.getElementById("teacher-vehicle").textContent = data.data.vehicle || "Sin vehículo";
            document.getElementById("teacher-avatar").src = data.data.avatar || "../img/default-avatar.png";

            // Verificar si tiene citas
            if (data.data.appointments && data.data.appointments.length > 0) {
                document.getElementById("teacher-appointments").textContent = `Tienes ${data.data.appointments.length} citas hoy`;
                document.getElementById("loading-spinner").classList.add("d-none");
            }
        } else {
            console.error("Error al obtener datos del profesor:", data.message);
        }
    } catch (error) {
        console.error("Error al cargar datos del profesor:", error);
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

