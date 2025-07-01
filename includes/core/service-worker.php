<?php
// includes/core/service-worker.php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Crea el archivo service-worker.js en la raíz del sitio
 */
function dsb_create_root_service_worker()
{
	$sw_content = <<<EOT
// Importar el Service Worker de Pusher Beams
importScripts("https://js.pusher.com/beams/service-worker.js");

// Evento de instalación
self.addEventListener('install', event => {
  self.skipWaiting();
});

// Evento de activación
self.addEventListener('activate', event => {
  return self.clients.claim();
});

// Evento de notificación para personalizar el comportamiento al hacer clic
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    if (data.notification) {
      self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window'
      }).then(clients => {
        const showNotification = clients.length === 0;
        if (!showNotification) {
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
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(urlToOpen);
      }
    })
  );
});

// Este service worker fue creado automáticamente por el plugin Driving School Bookings
EOT;

	$root_sw_path = ABSPATH . 'service-worker.js';

	file_put_contents($root_sw_path, $sw_content);
	error_log('Service Worker creado en la raíz del sitio por DSB Plugin');
}

/**
 * Elimina el archivo service-worker.js de la raíz del sitio
 */
function dsb_remove_root_service_worker()
{
	$root_sw_path = ABSPATH . 'service-worker.js';

	if (file_exists($root_sw_path)) {
		$content = file_get_contents($root_sw_path);
		if (strpos($content, 'Este service worker fue creado automáticamente por el plugin Driving School Bookings') !== false) {
			unlink($root_sw_path);
			error_log('Service Worker eliminado de la raíz del sitio por DSB Plugin');
		} else {
			error_log('No se eliminó el service-worker.js porque parece no ser el del plugin DSB');
		}
	}
}

/**
 * Agrega cabeceras para el service worker
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
