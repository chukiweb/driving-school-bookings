<?php

/**
 * Orquestador de notificaciones (push + email)
 *
 * @package Driving_School_Bookings
 * @since   1.1.0
 */

if (! defined('ABSPATH')) {
	exit;
}

class DSB_Notification_Manager
{

	private static $instance = null;

	/** @var DSB_Push_Notification_Service */
	private $push;

	/** @var DSB_Email_Notification_Service */
	private $email;

	/**  Mapeo tipo-notificación → plantillas  */
	private $templates = array(
		/* ---------- Alumno ---------- */
		'student_reminder' => array(
			'email' => 'recordatorio-clase',
			'push'  => array(
				'title' => 'Recordatorio de clase',
				'body'  => 'Tienes una clase el {date} a las {time}.',
			),
		),
		'student_cancel_class' => array(
			'email' => 'cancelacion-clase-alumno',
			'push'  => array(
				'title' => 'Clase cancelada',
				'body'  => 'La clase del {date} a las {time} ha sido cancelada.',
			),
		),

		/* ---------- Profesor ---------- */
		'teacher_new_booking' => array(
			'email' => 'nueva-reserva-profesor',
			'push'  => array(
				'title' => 'Nueva clase asignada',
				'body'  => '{student_name} ha reservado para el {date} a las {time}.',
			),
		),
		'teacher_cancel_class' => array(
			'email' => 'cancelacion-clase-profesor',
			'push'  => array(
				'title' => 'Clase cancelada',
				'body'  => '{student_name} ha cancelado la clase del {date} a las {time}.',
			),
		),

		/* ---------- Generales ---------- */
		'admin_broadcast' => array(
			'email' => 'broadcast',
			'push'  => array(
				'title' => '{title}',
				'body'  => '{message}',
			),
		),
	);

	/**
	 * Singleton
	 */
	public static function get_instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$this->push  = DSB_Push_Notification_Service::get_instance();
		$this->email = DSB_Email_Notification_Service::get_instance();
	}

	/* -------------------------------------------------------------------------
	 *  Métodos públicos
	 * --------------------------------------------------------------------- */

	/**
	 * Envía por **un canal concreto**.
	 */
	public function send_email($user_id, $template_slug, $placeholders = array(), $subject_override = '')
	{
		return $this->email->send($user_id, $template_slug, $placeholders, $subject_override);
	}

	public function send_push($user_id, $title, $body, $data = array())
	{
		return $this->push->send_to_user($user_id, $title, $body, $data);
	}

	/**
	 * Envía por los canales indicados (`email`, `push`) según el tipo de notificación.
	 *
	 * @param string          $type          Clave de $this->templates.
	 * @param int|int[]       $user_ids      Destinatario(s).
	 * @param array           $placeholders  Datos reemplazables {key}.
	 * @param array|string    $channels      'email', 'push' o ambos.
	 *
	 * @return void
	 */
	public function notify($type, $user_ids, $placeholders = array(), $channels = array('email', 'push'))
	{

		if (!isset($this->templates[$type])) {
			error_log("[DSB] Tipo de notificación desconocido: {$type}");
			return;
		}

		$user_ids  = (array) $user_ids;
		$channels  = (array) $channels;
		$template  = $this->templates[$type];

		foreach ($user_ids as $user_id) {

			if (in_array('email', $channels, true) && ! empty($template['email'])) {
				$this->send_email($user_id, $template['email'], $placeholders);
			}

			if (in_array('push', $channels, true) && ! empty($template['push'])) {
				$title = $this->replace_placeholders($template['push']['title'], $placeholders);
				$body  = $this->replace_placeholders($template['push']['body'], $placeholders);
				$this->send_push($user_id, $title, $body, $placeholders);
			}
		}
	}

	/**
	 * Envía un broadcast a **todos** los usuarios de un rol.
	 *
	 * @param string $role         student | teacher | administrator | subscriber …
	 * @param string $title
	 * @param string $message
	 * @param array  $channels
	 */
	public function broadcast_role($role, $title, $message, $channels = array('email', 'push'))
	{

		$users = get_users(array('role' => $role, 'fields' => 'ID'));
		$this->notify(
			'admin_broadcast',
			$users,
			array(
				'title'   => $title,
				'message' => $message,
			),
			$channels
		);
	}

	/* --------------------------------------------------------------------- */
	/*  Helpers                                                              */
	/* --------------------------------------------------------------------- */

	private function replace_placeholders($text, $placeholders)
	{
		foreach ($placeholders as $key => $val) {
			$text = str_replace('{' . $key . '}', $val, $text);
		}
		return $text;
	}

	public function handle_booking_created($booking_id)
	{
		$teacher_id = (int) get_post_meta($booking_id, 'teacher_id', true);
		$student_id = (int) get_post_meta($booking_id, 'student_id', true);
		$date       = get_post_meta($booking_id, 'date', true);
		$time       = get_post_meta($booking_id, 'time', true);

		if (!$teacher_id || !$student_id) {
			return;
		}

		$student = get_user_by('id', $student_id);
		$teacher = get_user_by('id', $teacher_id);

		$placeholders = [
			'student_name' => $student ? $student->display_name : __('Alumno', 'dsb'),
			'teacher_name' => $teacher ? $teacher->display_name : __('Profesor', 'dsb'),
			'date'         => date_i18n('d/m/Y', strtotime($date)),
			'time'         => $time,
		];

		$this->notify('teacher_new_booking', $teacher_id, $placeholders);
	}

	/**
	 * Maneja los cambios de estado en la reserva.
	 *
	 * @param int    $booking_id
	 * @param string $new_status
	 * @param string $old_status
	 */
	public function handle_booking_status_cancelled($booking_id, $new_status, $old_status)
	{
		// Solo actuamos si el nuevo estado es “cancelled”
		if ($new_status !== 'cancelled') {
			return;
		}

		// Obtenemos datos básicos de la reserva
		$student_id = (int) get_post_meta($booking_id, 'student_id', true);
		$teacher_id = (int) get_post_meta($booking_id, 'teacher_id', true);
		$date       = get_post_meta($booking_id, 'date', true); // Y-m-d
		$time       = get_post_meta($booking_id, 'time', true); // HH:MM

		if (! $student_id || ! $teacher_id) {
			// Si faltan datos, detenemos
			return;
		}

		// Recuperar nombres de usuario
		$student = get_user_by('id', $student_id);
		$teacher = get_user_by('id', $teacher_id);

		$placeholders = array(
			'student_name' => $student ? $student->display_name : __('Alumno', 'dsb'),
			'teacher_name' => $teacher ? $teacher->display_name : __('Profesor', 'dsb'),
			'date'         => date_i18n('d/m/Y', strtotime($date)),
			'time'         => $time,
		);

		// 1. Notificar al profesor de que la clase ha sido cancelada
		$this->notify(
			'teacher_cancel_class',   // clave que definimos en $templates
			$teacher_id,              // destinatario
			$placeholders
		);

		// 2. Notificar al alumno de que su clase ha sido cancelada
		$this->notify(
			'student_cancel_class',
			$student_id,
			$placeholders
		);
	}
}
