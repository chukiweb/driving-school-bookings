document.addEventListener('DOMContentLoaded', function () {
    window.mostrarNotificacion = function (title, message, type = 'info', duration = 3000) {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `dsb-notificacion ${type}`;

        // Combinar título y mensaje si se proporciona título
        const content = title ? `<strong>${title}</strong><br>${message}` : message;
        notification.innerHTML = content;

        // Añadir al DOM
        document.body.appendChild(notification);

        // Mostrar con animación
        setTimeout(() => {
            notification.classList.add('visible');
        }, 10);

        // Ocultar después de la duración especificada
        setTimeout(() => {
            notification.classList.remove('visible');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, duration);
    }

    // Verificar si el navegador soporta Service Workers
    if ('serviceWorker' in navigator) {
        // Registrar el service worker desde la RAÍZ del sitio
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                // Una vez registrado el Service Worker, inicializar Pusher Beams
                initPusherBeams();
            })
            .catch(error => {
                console.error('Error al registrar el Service Worker:', error);
            });
    }
});

// Inicializar Pusher Beams con suscripciones específicas según el rol
function initPusherBeams() {
    const beamsScript = document.createElement('script');
    beamsScript.src = 'https://js.pusher.com/beams/1.0/push-notifications-cdn.js';
    beamsScript.async = true;

    beamsScript.onload = async () => {
        try {
            // Crear un cliente Pusher Beams
            const beamsClient = new PusherPushNotifications.Client({
                instanceId: DSB_PUSHER.instanceId,
            });

            // Solicitar permisos y empezar
            await beamsClient.start();

            // Suscribirse al tema general para pruebas
            await beamsClient.addDeviceInterest('debug-general');

            // Determinar si es alumno o profesor
            if (typeof studentData !== 'undefined') {
                // Es un alumno
                await beamsClient.addDeviceInterest(`debug-student-${studentData.id}`);

                // Suscribirse a notificaciones de recordatorio de clase
                await beamsClient.addDeviceInterest('class-reminders');
            } else if (typeof teacherData !== 'undefined') {
                // Es un profesor
                await beamsClient.addDeviceInterest(`debug-teacher-${teacherData.id}`);

                // Suscribirse a notificaciones de nuevas reservas
                await beamsClient.addDeviceInterest('new-bookings');
            }

            // Configurar handler para mostrar notificaciones recibidas mientras la app está abierta
            setupNotificationHandler();

        } catch (error) {
            console.error('Error al inicializar Pusher Beams:', error);
        }
    };

    document.head.appendChild(beamsScript);
}

// Manejador de notificaciones recibidas mientras la app está abierta
function setupNotificationHandler() {
    // Si el service worker ya está activo, configurar para recibir mensajes
    if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.notification) {
                const notification = event.data.notification;
                mostrarNotificacion(notification.title, notification.body, event.data.type || 'info');
            }
        });
    }
}