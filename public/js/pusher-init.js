document.addEventListener('DOMContentLoaded', function () {
    // Verificar si el navegador soporta Service Workers
    if ('serviceWorker' in navigator) {
        // Registrar el service worker desde la RAÍZ del sitio
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registrado correctamente:', registration.scope);

                // Una vez registrado el Service Worker, inicializar Pusher Beams
                initPusherBeams();
            })
            .catch(error => {
                console.error('Error al registrar el Service Worker:', error);
            });
    }
});

// Inicializar Pusher Beams de forma básica
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
            console.log('Pusher Beams inicializado correctamente');

            // Suscribirse al tema general para pruebas
            await beamsClient.addDeviceInterest('debug-general');
            console.log('Suscrito al tema "debug-general"');
        } catch (error) {
            console.error('Error al inicializar Pusher Beams:', error);
        }
    };

    document.head.appendChild(beamsScript);
}