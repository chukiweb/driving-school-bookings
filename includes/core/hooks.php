<?php
// includes/core/hooks.php

add_action('dsb_booking_created', function($booking_id) {
    DSB_Notification_Manager::get_instance()->handle_booking_created($booking_id);
});

add_action('dsb_booking_status_changed', function($booking_id, $new_status, $old_status) {
    DSB_Notification_Manager::get_instance()->handle_booking_status_changed($booking_id, $new_status, $old_status);
}, 10, 3);
