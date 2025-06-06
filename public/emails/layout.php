<?php

/**
 * Layout base para emails del plugin Driving School Bookings
 *
 * Variables disponibles:
 *  - $subject      : Asunto del email (solo para la etiqueta <title>)
 *  - $content_html : Contenido HTML del cuerpo, ya renderizado
 */

?>

<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	<title><?php echo esc_html($subject); ?></title>
	<style>
		body { margin:0; padding:0; background:#f4f4f4; font-family:Arial,sans-serif; color:#333 }
		.container { max-width:600px; margin:0 auto; background:#fff }
		.header { padding:20px; text-align:center; background:#FFED00 }
		.content { padding:30px 20px }
		.content h1 { font-size:24px; margin-bottom:10px }
		.content p { font-size:16px; line-height:1.5; margin:15px 0 }
		.btn { display:inline-block; padding:12px 20px; margin:20px 0; background:#fff; color: #0073e6 !important; text-decoration:none; border-radius:4px; font-weight:bold }
		.footer { padding:15px 20px; font-size:12px; color:#777; text-align:center; background:#f0f0f0 }
		.footer a { color:#0073e6; text-decoration:none }
	</style>
</head>

<body>
	<div class="container">
		<div class="header">
			<img
				src="<?php echo esc_url(DSB_PLUGIN_URL . 'public/images/logo.png'); ?>"
				alt="Logo Autoescuela Universitaria"
				width="200"
				style="display:block; margin:0 auto; height:auto;">
		</div>
		<div class="content">
			<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<!-- Botón para acceder a /acceso -->
			<p>
				<a href="<?php echo esc_url(home_url('/acceso')); ?>" class="btn">Acceder a mi cuenta</a>
			</p>
		</div>
		<div class="footer">
			<p>Autoescuela Universitaria · Calle Ejemplo 123, Ciudad · <a href="<?php echo esc_url(home_url()); ?>">www.autoescuelauniversitaria.es</a></p>
			<p>Si no solicitaste este correo, ignóralo.</p>
		</div>
	</div>
</body>

</html>