<?php
// includes/class-dsb-roles.php

class DSB_Roles {
    public function __construct() {
        add_action('admin_init', [$this, 'add_roles_capabilities']);
    }

    public function add_roles_capabilities() {
        // Profesor
        add_role('teacher', 'Profesor', [
            'read' => true,
            'edit_bookings' => true,
            'edit_own_bookings' => true,
            'upload_files' => true,
            'manage_assigned_vehicle' => true,
            'view_student_progress' => true,
            'create_booking_notes' => true,
            'send_notifications' => true
        ]);

        // Estudiante
        add_role('student', 'Estudiante', [
            'read' => true,
            'edit_own_bookings' => true,
            'view_own_progress' => true,
            'make_payments' => true,
            'schedule_bookings' => true,
            'cancel_own_bookings' => true
        ]);

        // Manager
        add_role('manager', 'Manager', [
            'read' => true,
            'edit_bookings' => true,
            'edit_teachers' => true,
            'view_reports' => true,
            'manage_payments' => true,
            'assign_teachers' => true
        ]);

        // Añadir capacidades al administrador
        $admin = get_role('administrator');
        $admin->add_cap('manage_school_settings');
        $admin->add_cap('view_financial_reports');
        $admin->add_cap('manage_all_bookings');
        $admin->add_cap('manage_teacher_assignments');
    }

    public function remove_roles_capabilities() {
        remove_role('teacher');
        remove_role('student');
        remove_role('manager');
        
        $admin = get_role('administrator');
        $admin->remove_cap('manage_school_settings');
        $admin->remove_cap('view_financial_reports');
        $admin->remove_cap('manage_all_bookings');
        $admin->remove_cap('manage_teacher_assignments');
    }
}