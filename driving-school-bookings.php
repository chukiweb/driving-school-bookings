<?php

/**
 * Plugin Name: Driving School Bookings
 * Description: Sistema de gestión de reservas para autoescuela
 * Version: 1.0.0
 * Author: Tu Nombre
 * Text Domain: driving-school-bookings
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/vendor/autoload.php';


//require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/init.php';

function DSB()
{
    return DSB_Init::getInstance();
}

add_action('plugins_loaded', 'DSB');

// Hook para la activación del plugin
register_activation_hook(__FILE__, 'dsb_activate_plugin');

// Hook para la desactivación del plugin
register_deactivation_hook(__FILE__, 'dsb_deactivate_plugin');

/**
 * Función que se ejecuta al activar el plugin
 */
function dsb_activate_plugin()
{
    // Crear el service worker en la raíz
    dsb_create_root_service_worker();
}

/**
 * Función que se ejecuta al desactivar el plugin
 */
function dsb_deactivate_plugin()
{
    // Eliminar el service worker de la raíz
    dsb_remove_root_service_worker();
}

/**
 * Crea el archivo service-worker.js en la raíz del sitio
 */
function dsb_create_root_service_worker()
{
    // Contenido para el service worker
    $sw_content = <<<EOT
// Importar el Service Worker de Pusher Beams
importScripts("https://js.pusher.com/beams/service-worker.js");

// Evento de instalación
self.addEventListener('install', event => {
  console.log('Service Worker instalado en raíz');
  self.skipWaiting();
});

// Evento de activación
self.addEventListener('activate', event => {
  console.log('Service Worker activado en raíz');
  return self.clients.claim();
});

// Evento de notificación para personalizar el comportamiento al hacer clic
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    if (data.notification) {
      // Intentar enviar la notificación a todas las ventanas abiertas
      self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window'
      }).then(clients => {
        // Solo mostrar notificación push si no hay ventanas abiertas
        const showNotification = clients.length === 0;
        
        if (!showNotification) {
          // Enviar datos a la ventana abierta
          clients.forEach(client => {
            client.postMessage({
              notification: data.notification,
              data: data.data,
              type: data.data ? data.data.type : 'info'
            });
          });
        }
      });
    }
  }
});

// Manejar clic en la notificación
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  const urlToOpen = new URL('/', self.location.origin).href;
  
  event.waitUntil(
    self.clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    }).then(clientList => {
      // Si ya hay una ventana abierta, enfocarla
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          return client.focus();
        }
      }
      // Si no hay ventanas abiertas, abrir una nueva
      if (self.clients.openWindow) {
        return self.clients.openWindow(urlToOpen);
      }
    })
  );
});

// Este service worker fue creado automáticamente por el plugin Driving School Bookings
EOT;

    // Ruta al service worker en la raíz
    $root_sw_path = ABSPATH . 'service-worker.js';

    // Escribir el archivo
    file_put_contents($root_sw_path, $sw_content);

    // Registrar en el log
    error_log('Service Worker creado en la raíz del sitio por DSB Plugin');
}

/**
 * Elimina el archivo service-worker.js de la raíz del sitio
 */
function dsb_remove_root_service_worker()
{
    // Ruta al service worker en la raíz
    $root_sw_path = ABSPATH . 'service-worker.js';

    // Verificar si existe antes de eliminarlo
    if (file_exists($root_sw_path)) {
        // Leer el contenido para verificar que es nuestro archivo
        $content = file_get_contents($root_sw_path);

        // Solo eliminar si contiene nuestra marca de identificación
        if (strpos($content, 'Este service worker fue creado automáticamente por el plugin Driving School Bookings') !== false) {
            unlink($root_sw_path);
            error_log('Service Worker eliminado de la raíz del sitio por DSB Plugin');
        } else {
            error_log('No se eliminó el service-worker.js porque parece no ser el del plugin DSB');
        }
    }
}

/**
 * Verifica y recrea el service worker si es necesario
 * Se ejecuta en cada carga de página para asegurar que existe
 */
function dsb_check_root_service_worker()
{
    // Ruta al service worker en la raíz
    $root_sw_path = ABSPATH . 'service-worker.js';

    // Si no existe o está vacío, lo creamos
    if (!file_exists($root_sw_path) || filesize($root_sw_path) < 10) {
        dsb_create_root_service_worker();
    }
}
add_action('init', 'dsb_check_root_service_worker');

/**
 * Configura encabezados para el service worker
 */
function dsb_add_service_worker_headers()
{
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/service-worker.js') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
    }
}
add_action('send_headers', 'dsb_add_service_worker_headers');

// Notificar al crear una nueva reserva
add_action('dsb_booking_created', function ($booking_id) {
    DSB()->notifications->notify_new_booking($booking_id);
});

// Notificar al cambiar el estado de una reserva
add_action('dsb_booking_status_changed', function ($booking_id, $new_status, $old_status) {
    DSB()->notifications->notify_booking_status_change($booking_id, $new_status);
}, 10, 3);

// Agregar configuración para Pusher Beams en el panel de administración
add_filter('dsb_settings_fields', function ($fields) {
    $fields['pusher_beams_secret'] = [
        'label' => 'Pusher Beams Secret Key',
        'type' => 'text',
        'description' => 'Clave secreta para enviar notificaciones push con Pusher Beams'
    ];
    return $fields;
});
