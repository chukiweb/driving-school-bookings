document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        await login();
    });
});

async function login() {
    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;
    const errorMessage = document.getElementById("error-message");
    errorMessage.textContent = "";
  
    try {
      const resp = await fetch("/wp-json/driving-school/v1/auth/acceso", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password }),
      });
      const data = await resp.json();
  
      if (!resp.ok) {
        throw new Error(data.message || "Error al iniciar sesión");
      }
  
      // El token ya lo guarda PHP en $_SESSION y cookie de sesión,
      // así que aquí sólo redirigimos al URL que viene en la respuesta:
      window.location.href = data.user.url;
  
    } catch (err) {
      errorMessage.textContent = err.message;
    }
  }
