<?php
// includes/core/hooks.php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

add_action('dsb_booking_status_cancelled', function($booking_id, $new_status, $old_status) {
    DSB_Notification_Manager::get_instance()->handle_booking_status_cancelled($booking_id, $new_status, $old_status);
}, 10, 3);

/* --------------------------------------------------------------------- */
/*  RECORDATORIO UN DÍA ANTES ALUMNO                                     */
/* --------------------------------------------------------------------- */

// 1. Al activar el plugin, programar el evento diario si no existe
add_action( 'wp', function() {
    if ( ! wp_next_scheduled( 'dsb_daily_class_reminder_event' ) ) {
        // Programar para las 20:00 cada día (puedes ajustarlo)
        $timestamp = strtotime( 'today 20:00' );
        wp_schedule_event( $timestamp, 'daily', 'dsb_daily_class_reminder_event' );
    }
});

// 2. Limpiar al desactivar el plugin (quita el evento WP-Cron)
register_deactivation_hook( __FILE__, function() {
    $timestamp = wp_next_scheduled( 'dsb_daily_class_reminder_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'dsb_daily_class_reminder_event' );
    }
} );

// 3. Hook personalizado que se disparará cada día
add_action( 'dsb_daily_class_reminder_event', 'dsb_send_student_reminders' );

/**
 * Función que envía recordatorios a los alumnos con clases mañana.
 */
function dsb_send_student_reminders() {
    $tomorrow = date( 'Y-m-d', strtotime('+1 day') );

    $args = array(
        'post_type'      => 'dsb_booking',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'date',
                'value'   => $tomorrow,
                'compare' => '=',
            ),
            array(
                'key'     => 'status',
                'value'   => array( 'accepted', 'pending' ),
                'compare' => 'IN',
            ),
        ),
    );

    $bookings = get_posts( $args );

    if ( empty( $bookings ) ) {
        return;
    }

    foreach ( $bookings as $booking ) {
        $booking_id = $booking->ID;
        $student_id = (int) get_post_meta( $booking_id, 'student_id', true );
        $time       = get_post_meta( $booking_id, 'time', true ); // Ejemplo “15:30”

        if ( ! $student_id || ! $time ) {
            continue;
        }

        $student = get_user_by( 'id', $student_id );
        $student_name = $student ? $student->display_name : __( 'Alumno', 'dsb' );

        // Construir placeholders para la notificación
        $placeholders = array(
            'student_name' => $student_name,
            'date'         => date_i18n( 'd/m/Y', strtotime( $tomorrow ) ),
            'time'         => $time,
        );

        // Llamar al Notification Manager
        DSB_Notification_Manager::get_instance()->notify(
            'student_reminder',  // clave en $templates
            $student_id,         // destinatario
            $placeholders        // datos a inyectar
        );
    }
}
