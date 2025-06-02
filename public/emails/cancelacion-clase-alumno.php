<!-- Subject: Tu clase ha sido cancelada -->
<h1>¡Hola <?= esc_html($student_name); ?>!</h1>
<p>La clase que tenías agendada el <strong><?= esc_html($date); ?></strong> a las <strong><?= esc_html($time); ?></strong> ha sido cancelada.</p>
<p>Puedes volver a reservar o consultar tu calendario en la app.</p>