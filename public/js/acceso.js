async function login() {
    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;
    const errorMessage = document.getElementById("error-message");

    errorMessage.textContent = "";

    try {
        const response = await fetch("/wp-json/driving-school/v1/auth/acceso", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || "Error al iniciar sesión");
        }

        localStorage.setItem("jwt_token", data.token);

        if (data.user.role === "student") {
            window.location.href = "/estudiante";
        } else if (data.user.role === "teacher") {
            window.location.href = "/profesor";
        } else {
            window.location.href = "/acceso";
        }


    } catch (error) {
        errorMessage.textContent = error.message;
    }
}