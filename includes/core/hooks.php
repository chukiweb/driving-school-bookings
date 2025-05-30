<?php
// includes/core/hooks.php

/**
 * Hooks para cambiar la configuración del correo electrónico
 */
add_filter('wp_mail_from', function($original_email_address) {
    $host = parse_url(home_url(), PHP_URL_HOST);
    $host = preg_replace('/^www\./', '', $host);
    return 'no-reply@' . $host;
});

add_filter('wp_mail_from_name', function($original_email_from) {
    return get_bloginfo('name');
});


/**
 * Hooks para notificaciones del plugin Driving School Bookings
 */
add_action('dsb_booking_created', function($booking_id) {
    DSB_Notification_Manager::get_instance()->handle_booking_created($booking_id);
});

add_action('dsb_booking_status_changed', function($booking_id, $new_status, $old_status) {
    DSB_Notification_Manager::get_instance()->handle_booking_status_changed($booking_id, $new_status, $old_status);
}, 10, 3);
