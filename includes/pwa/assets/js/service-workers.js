self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open('dsb-v1').then((cache) => {
            return cache.addAll([
                '/app',
                '/wp-content/plugins/driving-school-bookings/includes/pwa/assets/js/app.js',
                '/wp-content/plugins/driving-school-bookings/includes/pwa/assets/css/style.css'
            ]);
        })
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});