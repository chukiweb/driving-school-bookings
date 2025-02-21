document.addEventListener("DOMContentLoaded", async function() {
    const token = localStorage.getItem("jwt_token");
    if (!token) {
        window.location.href = "/wp-json/driving-school/v1/views/acceso";
        return;
    }

    try {
        const response = await fetch("/wp-json/driving-school/v1/students/bookings", {
            headers: { "Authorization": "Bearer " + token }
        });

        if (!response.ok) throw new Error("Error al obtener reservas");

        const reservas = await response.json();
        const lista = document.getElementById("reservas");
        document.getElementById("message").textContent = reservas.length
            ? "Tus Reservas:"
            : "No tienes reservas.";

        reservas.forEach(reserva => {
            const item = document.createElement("li");
            item.classList.add("list-group-item");
            item.textContent = `Fecha: ${reserva.date} | Estado: ${reserva.status}`;
            lista.appendChild(item);
        });
    } catch (error) {
        document.getElementById("message").textContent = "Error cargando reservas";
    }
});