<?php
/**
 * Servicio de **correo electrónico** para notificaciones
 *
 * @package Driving_School_Bookings
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSB_Email_Notification_Service {

	private static $instance = null;

	/**
	 * Singleton
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Envía un email utilizando una plantilla PHP.
	 *
	 * @param int    $user_id         ID de usuario destinatario.
	 * @param string $template_slug   Nombre de la plantilla (sin extensión).
	 * @param array  $placeholders    Datos que se inyectarán en la plantilla.
	 * @param string $subject_override Opcional para forzar asunto manual.
	 *
	 * @return bool
	 */
	public function send( $user_id, $template_slug, $placeholders = array(), $subject_override = '' ) {

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		// 1. Cargar plantilla
		$template_file = DSB_PLUGIN_DIR . 'emails/' . $template_slug . '.php';
		if ( ! file_exists( $template_file ) ) {
			error_log( "[DSB] Plantilla de email no encontrada: {$template_slug}" );
			return false;
		}

		ob_start();
		/** @noinspection PhpIncludeInspection */
		include $template_file;           // la plantilla puede usar $placeholders
		$message = ob_get_clean();

		// 2. Asunto (primer comentario `<!-- Subject: ... -->` en la plantilla o override)
		$subject = $subject_override ? $subject_override : $this->extract_subject( $message );
		$message = preg_replace( '/<!--\s*Subject:.*?-->/', '', $message, 1 ); // eliminar comentario

		// 3. Reemplazo sencillo de placeholders {key}
		foreach ( $placeholders as $key => $value ) {
			$message = str_replace( '{' . $key . '}', $value, $message );
			$subject = str_replace( '{' . $key . '}', $value, $subject );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	/* --------------------------------------------------------------------- */
	/*  Helpers                                                              */
	/* --------------------------------------------------------------------- */

	private function extract_subject( $html ) {
		if ( preg_match( '/<!--\s*Subject:(.*?)-->/', $html, $m ) ) {
			return trim( $m[1] );
		}
		return get_bloginfo( 'name' ) . ' – Notificación';
	}
}
