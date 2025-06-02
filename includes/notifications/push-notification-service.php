<?php

/**
 * Servicio exclusivo de **notificaciones push** (Pusher Beams)
 *
 * @package Driving_School_Bookings
 * @since   1.1.0
 */

if (! defined('ABSPATH')) {
	exit;
}

class DSB_Push_Notification_Service
{

	/** @var self */
	private static $instance = null;

	/** @var string */
	private $beams_instance_id;

	/** @var string */
	private $beams_secret_key;

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

	/**
	 * Constructor privado
	 */
	private function __construct()
	{
		$this->beams_instance_id = DSB_Settings::get('pusher_beams_instance_id');
		$this->beams_secret_key  = DSB_Settings::get('pusher_beams_secret_key');
	}

	/* -------------------------------------------------------------------------
	 *  API PÚBLICA
	 * --------------------------------------------------------------------- */

	/**
	 * Envía un push genérico a un usuario.
	 *
	 * @param int    $user_id  ID de usuario.
	 * @param string $title    Título de la notificación.
	 * @param string $body     Cuerpo de la notificación.
	 * @param array  $data     Datos extra (array asociativo).
	 *
	 * @return bool
	 */
	public function send_to_user($user_id, $title, $body, $data = array())
	{
		$user = get_userdata($user_id);
		if (! $user || empty($user->roles)) {
			error_log('[DSB] Usuario o rol no encontrado para notificación push');
			return false;
		}

		$role = $user->roles[0];

		$interest = "{$role}-{$user_id}";
		return $this->send_push_notification($interest, $title, $body, $data);
	}

	/**
	 * Envía un push a **todos** los usuarios con un rol determinado.
	 * Ej.: role = student | teacher
	 *
	 * @param string $role
	 * @param string $title
	 * @param string $body
	 * @param array  $data
	 */
	public function send_to_role($role, $title, $body, $data = array())
	{
		$interest = "{$role}-all";
		return $this->send_push_notification($interest, $title, $body, $data);
	}

	/* -------------------------------------------------------------------------
	 *  IMPLEMENTACIÓN PRIVADA
	 * --------------------------------------------------------------------- */

	/**
	 * Llamada directa a la API de Pusher Beams
	 */
	private function send_push_notification($interest, $title, $body, $data = array())
	{

		if (empty($this->beams_secret_key) || empty($this->beams_instance_id)) {
			error_log('[DSB] Pusher Beams no configurado.');
			return false;
		}

		$url = "https://{$this->beams_instance_id}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->beams_instance_id}/publishes";

		$payload = array(
			'interests' => array($interest),
			'web'       => array(
				'notification' => array(
					'title'      => $title,
					'body'       => $body,
					'deep_link'  => home_url(),
					'icon'       => DSB_PLUGIN_URL . 'public/images/logo.png',
				),
				'data'        => $data,
			),
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->beams_secret_key,
		));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response  = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code < 200 || $http_code >= 300) {
			error_log('[DSB] Pusher Beams error: ' . $response);
			return false;
		}

		// Si llegamos aquí, la notificación se envió correctamente
		error_log(sprintf('[DSB] Push notification sent successfully to %s: %s', $interest, $title));
		return true;
	}
}
